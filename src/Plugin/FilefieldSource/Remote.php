<?php

/**
 * @file
 * Contains \Drupal\filefield_sources\Plugin\FilefieldSource\Remote.
 */

namespace Drupal\filefield_sources\Plugin\FilefieldSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filefield_sources\FilefieldSourceInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Field\WidgetInterface;

define('FILEFIELD_SOURCE_REMOTE_HINT_TEXT', 'http://example.com/files/file.png');

/**
 * A FileField source plugin to allow downloading a file from a remote server.
 *
 * @FilefieldSource(
 *   id = "remote",
 *   name = @Translation("Remote URL textfield"),
 *   label = @Translation("Remote URL"),
 *   description = @Translation("Download a file from a remote server.")
 * )
 */
class Remote implements FilefieldSourceInterface {

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (isset($input['filefield_remote']['url']) && strlen($input['filefield_remote']['url']) > 0 && valid_url($input['filefield_remote']['url']) && $input['filefield_remote']['url'] != FILEFIELD_SOURCE_REMOTE_HINT_TEXT) {
      $field = field_info_instance($element['#entity_type'], $element['#field_name'], $element['#bundle']);
      $url = $input['filefield_remote']['url'];

      // Check that the destination is writable.
      $temporary_directory = 'temporary://';
      if (!file_prepare_directory($temporary_directory, FILE_MODIFY_PERMISSIONS)) {
        watchdog('file', 'The directory %directory is not writable, because it does not have the correct permissions set.', array('%directory' => drupal_realpath($temporary_directory)));
        drupal_set_message(t('The file could not be transferred because the temporary directory is not writable.'), 'error');
        return;
      }

      // Check that the destination is writable.
      $directory = $element['#upload_location'];
      $mode = variable_get('file_chmod_directory', 0775);

      // This first chmod check is for other systems such as S3, which don't work
      // with file_prepare_directory().
      if (!drupal_chmod($directory, $mode) && !file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
        watchdog('file', 'File %file could not be copied, because the destination directory %destination is not configured correctly.', array('%file' => $url, '%destination' => drupal_realpath($directory)));
        drupal_set_message(t('The specified file %file could not be copied, because the destination directory is not properly configured. This may be caused by a problem with file or directory permissions. More information is available in the system log.', array('%file' => $url)), 'error');
        return;
      }

      // Check the headers to make sure it exists and is within the allowed size.
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HEADER, TRUE);
      curl_setopt($ch, CURLOPT_NOBODY, TRUE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(get_called_class(), 'parseHeader'));
      // Causes a warning if PHP safe mode is on.
      @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
      curl_exec($ch);
      $info = curl_getinfo($ch);
      if ($info['http_code'] != 200) {
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        $file_contents = curl_exec($ch);
        $info = curl_getinfo($ch);
      }
      curl_close($ch);

      if ($info['http_code'] != 200) {
        switch ($info['http_code']) {
          case 403:
            form_error($element, t('The remote file could not be transferred because access to the file was denied.'));
            break;
          case 404:
            form_error($element, t('The remote file could not be transferred because it was not found.'));
            break;
          default:
            form_error($element, t('The remote file could not be transferred due to an HTTP error (@code).', array('@code' => $info['http_code'])));
        }
        return;
      }

      // Update the $url variable to reflect any redirects.
      $url = $info['url'];
      $url_info = parse_url($url);

      // Determine the proper filename by reading the filename given in the
      // Content-Disposition header. If the server fails to send this header,
      // fall back on the basename of the URL.
      //
      // We prefer to use the Content-Disposition header, because we can then
      // use URLs like http://example.com/get_file/23 which would otherwise be
      // rejected because the URL basename lacks an extension.
      $filename = static::filename();
      if (empty($filename)) {
        $filename = rawurldecode(basename($url_info['path']));
      }

      $pathinfo = pathinfo($filename);

      // Create the file extension from the MIME header if all else has failed.
      if (empty($pathinfo['extension']) && $extension = static::mimeExtension()) {
        $filename = $filename . '.' . $extension;
        $pathinfo = pathinfo($filename);
      }

      $filename = filefield_sources_clean_filename($filename, $field['settings']['file_extensions']);
      $filepath = file_create_filename($filename, $temporary_directory);

      if (empty($pathinfo['extension'])) {
        form_error($element, t('The remote URL must be a file and have an extension.'));
        return;
      }

      // Perform basic extension check on the file before trying to transfer.
      $extensions = $field['settings']['file_extensions'];
      $regex = '/\.('. preg_replace('/[ +]/', '|', preg_quote($extensions)) .')$/i';
      if (!empty($extensions) && !preg_match($regex, $filename)) {
        form_error($element, t('Only files with the following extensions are allowed: %files-allowed.', array('%files-allowed' => $extensions)));
        return;
      }

      // Check file size based off of header information.
      if (!empty($element['#upload_validators']['file_validate_size'][0])) {
        $max_size = $element['#upload_validators']['file_validate_size'][0];
        $file_size = $info['download_content_length'];
        if ($file_size > $max_size) {
          form_error($element, t('The remote file is %filesize exceeding the maximum file size of %maxsize.', array('%filesize' => format_size($file_size), '%maxsize' => format_size($max_size))));
          return;
        }
      }

      // Set progress bar information.
      $options = array(
        'key' => $element['#entity_type'] . '_' . $element['#bundle'] . '_' . $element['#field_name'] . '_' . $element['#delta'],
        'filepath' => $filepath,
      );
      static::setTransferOptions($options);

      $transfer_success = FALSE;
      // If we've already downloaded the entire file because the header-retrieval
      // failed, just ave the contents we have.
      if (isset($file_contents)) {
        if ($fp = @fopen($filepath, 'w')) {
          fwrite($fp, $file_contents);
          fclose($fp);
          $transfer_success = TRUE;
        }
      }
      // If we don't have the file contents, download the actual file.
      else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, array(get_called_class(), 'curlWrite'));
        // Causes a warning if PHP safe mode is on.
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        $transfer_success = curl_exec($ch);
        curl_close($ch);
      }
      if ($transfer_success && $file = filefield_sources_save_file($filepath, $element['#upload_validators'], $element['#upload_location'])) {
        $input = array_merge($input, (array) $file);
      }

      // Delete the temporary file.
      @unlink($filepath);
    }
  }

  /**
   * Set a transfer key that can be retreived by the progress function.
   */
  protected static function setTransferOptions($options = NULL) {
    static $current = FALSE;
    if (isset($options)) {
      $current = $options;
    }
    return $current;
  }

  /**
   * Get a transfer key that can be retrieved by the progress function.
   */
  protected static function getTransferOptions() {
    return static::setTransferOptions();
  }

  /**
   * cURL write function to save the file to disk. Also updates progress bar.
   */
  protected static function curlWrite(&$ch, $data) {
    $progress_update = 0;
    $options = static::getTransferOptions();

    // Get the current progress and update the progress value.
    // Only update every 64KB to reduce cache_set calls. cURL usually writes
    // in 16KB chunks.
    if (curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD) / 65536 > $progress_update) {
      $progress_update++;
      $progress = array(
        'current' => curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
        'total' => curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
      );
      // Set a cache so that we can retrieve this value from the progress bar.
      $cid = 'filefield_transfer:'. session_id() . ':' . $options['key'];
      if ($progress['current'] != $progress['total']) {
        cache_set($cid, $progress, 'cache', time() + 300);
      }
      else {
        cache_clear_all($cid, 'cache');
      }
    }

    $data_length = 0;
    if ($fp = @fopen($options['filepath'], 'a')) {
      fwrite($fp, $data);
      fclose($fp);
      $data_length = strlen($data);
    }

    return $data_length;
  }

  /**
   * Parse cURL header and record the filename specified in Content-Disposition.
   */
  protected static function parseHeader(&$ch, $header) {
    if (preg_match('/Content-Disposition:.*?filename="(.+?)"/', $header, $matches)) {
      // Content-Disposition: attachment; filename="FILE NAME HERE"
      static::filename($matches[1]);
    }
    elseif (preg_match('/Content-Disposition:.*?filename=([^; ]+)/', $header, $matches)) {
      // Content-Disposition: attachment; filename=file.ext
      $uri = trim($matches[1]);
      static::filename($uri);
    }
    elseif (preg_match('/Content-Type:[ ]*([a-z0-9_\-]+\/[a-z0-9_\-]+)/i', $header, $matches)) {
      $mime_type = $matches[1];
      static::mimeExtension($mime_type);
    }

    // This is required by cURL.
    return strlen($header);
  }

  /**
   * Get/set the remote file extension in a static variable.
   */
  protected static function mimeExtension($curl_mime_type = NULL) {
    static $extension = NULL;
    if (isset($curl_mime_type)) {
      include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
      $curl_mime_type = drupal_strtolower($curl_mime_type);
      $mapping = file_mimetype_mapping();
      // See if this matches a known MIME type.
      $map_id = array_search($curl_mime_type, $mapping['mimetypes']);
      if ($map_id !== FALSE) {
        // If we have a match, get this list of likely extensions. For some reason
        // Drupal lists the "most common" extension last for most file types
        // including php, jpg, and doc.
        if ($extensions = array_keys($mapping['extensions'], $map_id)) {
          $extension = end($extensions);
        }
      }
    }
    return $extension;
  }

  /**
   * Get/set the remote file name in a static variable.
   */
  protected static function filename($curl_filename = NULL) {
    static $filename = NULL;
    if (isset($curl_filename)) {
      $filename = $curl_filename;
    }
    return $filename;
  }

  /**
   * {@inheritdoc}
   */
  public static function processCallback(&$element, FormStateInterface $form_state, &$complete_form) {

    $element['filefield_remote'] = array(
      '#weight' => 100.5,
      '#theme' => 'filefield_source_remote_element',
      '#filefield_source' => TRUE, // Required for proper theming.
      '#filefield_sources_hint_text' => FILEFIELD_SOURCE_REMOTE_HINT_TEXT,
    );

    $element['filefield_remote']['url'] = array(
      '#type' => 'textfield',
      '#description' => filefield_sources_element_validation_help($element['#upload_validators']),
      '#maxlength' => NULL,
    );

    $element['filefield_remote']['transfer'] = array(
      '#name' => implode('_', $element['#array_parents']) . '_transfer',
      '#type' => 'submit',
      '#value' => t('Transfer'),
      '#validate' => array(),
      '#submit' => array('filefield_sources_field_submit'),
      '#limit_validation_errors' => array($element['#parents']),
      '#ajax' => array(
        'path' => 'file/ajax/' . implode('/', $element['#array_parents']) . '/' . $complete_form['form_build_id']['#value'],
        'wrapper' => $element['#id'] . '-ajax-wrapper',
        'effect' => 'fade',
        'progress' => array(
          'type' => 'bar',
          'path' => 'file/remote/progress/' . $element['#entity_type'] . '/' . $element['#bundle'] . '/' . $element['#field_name'] . '/' . $element['#delta'],
          'message' => t('Starting transfer...'),
        ),
      ),
    );

    return $element;
  }

  /**
   * Implements hook_theme().
   */
  public static function theme() {
    return array(
      'filefield_source_remote_element' => array(
        'render element' => 'element',
        'function' => array(get_called_class(), 'remoteElement'),
      ),
    );
  }

  /**
   * Theme the output of the remote element.
   */
  function remoteElement($variables) {
    $element = $variables['element'];

    $element['url']['#field_suffix'] = drupal_render($element['transfer']);
    return '<div class="filefield-source filefield-source-remote clear-block">' . drupal_render($element['url']) . '</div>';
  }

  /**
   * Menu callback; progress.js callback to return upload progress.
   */
  public static function progress($entity_type, $bundle_name, $field_name, $delta) {
    $key = $entity_type . '_' . $bundle_name . '_' . $field_name . '_' . $delta;
    $progress = array(
      'message' => t('Starting transfer...'),
      'percentage' => -1,
    );

    if ($cache = cache_get('filefield_transfer:'. session_id() . ':' . $key)) {
      $current = $cache->data['current'];
      $total = $cache->data['total'];
      $progress['message'] = t('Transferring... (@current of @total)', array('@current' => format_size($current), '@total' => format_size($total)));
      $progress['percentage'] = round(100 * $current / $total);
    }

    drupal_json_output($progress);
  }

  public static function routes() {
    $routes = array();

    $routes['filefield_sources.clipboard'] = new Route(
      '/file/remote/{entity_type}/{bundle_name}/{field_name}/{delta}',
      array(
        '_controller' => get_called_class() . '::progress',
      ),
      array(
        '_access' => 'TRUE',
      )
    );

    return $routes;
  }

  /**
   * Implements hook_filefield_source_settings().
   */
  public static function settings(WidgetInterface $plugin) {
    $return = array();

    // Add settings to the FileField widget form.

    return $return;

  }

}