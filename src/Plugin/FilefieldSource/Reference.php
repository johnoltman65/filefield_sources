<?php

/**
 * @file
 * Contains \Drupal\filefield_sources\Plugin\FilefieldSource\Reference.
 */

namespace Drupal\filefield_sources\Plugin\FilefieldSource;

use Drupal\Core\Form\FormStateInterface;
use Drupal\filefield_sources\FilefieldSourceInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Field\WidgetInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

define('FILEFIELD_SOURCE_REFERENCE_HINT_TEXT', 'example.png [fid:123]');

/**
 * A FileField source plugin to allow referencing of existing files.
 *
 * @FilefieldSource(
 *   id = "reference",
 *   name = @Translation("Autocomplete reference textfield"),
 *   label = @Translation("Reference existing"),
 *   description = @Translation("Reuse an existing file by entering its file name."),
 *   weight = 1
 * )
 */
class Reference implements FilefieldSourceInterface {

  /**
   * {@inheritdoc}
   */
  public static function value(&$element, &$input, FormStateInterface $form_state) {
    if (isset($input['filefield_reference']['autocomplete']) && strlen($input['filefield_reference']['autocomplete']) > 0 && $input['filefield_reference']['autocomplete'] != FILEFIELD_SOURCE_REFERENCE_HINT_TEXT) {
      $matches = array();
      if (preg_match('/\[fid:(\d+)\]/', $input['filefield_reference']['autocomplete'], $matches)) {
        $fid = $matches[1];
        if ($file = file_load($fid)) {

          // Remove file size restrictions, since the file already exists on disk.
          if (isset($element['#upload_validators']['file_validate_size'])) {
            unset($element['#upload_validators']['file_validate_size']);
          }

          // Check that the user has access to this file through hook_download().
          if (!filefield_sources_file_access($file->uri)) {
            form_error($element, t('You do not have permission to use the selected file.'));
          }
          elseif (filefield_sources_element_validate($element, (object) $file)) {
            $input = array_merge($input, (array) $file);
          }
        }
        else {
          form_error($element, t('The referenced file could not be used because the file does not exist in the database.'));
        }
      }
      // No matter what happens, clear the value from the autocomplete.
      $input['filefield_reference']['autocomplete'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {

    $element['filefield_reference'] = array(
      '#weight' => 100.5,
      '#theme' => 'filefield_sources_element',
      '#source_id' => 'reference',
      '#filefield_source' => TRUE, // Required for proper theming.
      '#filefield_sources_hint_text' => FILEFIELD_SOURCE_REFERENCE_HINT_TEXT,
    );

    $autocomplete_route_parameters = array(
      'entity_type' => $element['#entity_type'],
      'bundle_name' => $element['#bundle'],
      'field_name' => $element['#field_name'],
    );

    $element['filefield_reference']['autocomplete'] = array(
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'filefield_sources.autocomplete',
      '#autocomplete_route_parameters' => $autocomplete_route_parameters,
      '#description' => filefield_sources_element_validation_help($element['#upload_validators']),
    );

    $element['filefield_reference']['select'] = array(
      '#name' => implode('_', $element['#array_parents']) . '_autocomplete_select',
      '#type' => 'submit',
      '#value' => t('Select'),
      '#validate' => array(),
      '#submit' => array('filefield_sources_field_submit'),
      '#name' => $element['#name'] . '[filefield_reference][button]',
      '#limit_validation_errors' => array($element['#parents']),
      '#ajax' => array(
        'path' => 'file/ajax/' . implode('/', $element['#array_parents']) . '/' . $complete_form['form_build_id']['#value'],
        'wrapper' => $element['#id'] . '-ajax-wrapper',
        'effect' => 'fade',
      ),
    );

    return $element;
  }

  /**
   * Theme the output of the autocomplete field.
   */
  public static function element($variables) {
    $element = $variables['element'];

    $element['autocomplete']['#field_suffix'] = drupal_render($element['select']);
    return '<div class="filefield-source filefield-source-reference clear-block">' . drupal_render($element['autocomplete']) . '</div>';
  }

  /**
   * Theme the output of a single item in the autocomplete list.
   */
  public static function autocompleteElement($variables) {
    $file = $variables['file'];

    $output = '';
    $output .= '<div class="filefield-source-reference-item">';
    $output .= '<span class="filename">' . check_plain($file->filename) . '</span> <span class="filesize">(' . format_size($file->filesize) . ')</span>';
    $output .= '</div>';
    return $output;
  }

  /**
   * Menu callback; autocomplete.js callback to return a list of files.
   */
  public static function autocomplete(Request $request, $entity_type, $bundle_name, $field_name) {
    $items = array();

    $file_name = drupal_strtolower($request->query->get('q'));
    if (!empty($file_name)) {
      $field = entity_load('field_config', $entity_type . '.' . $bundle_name . '.' . $field_name);
      if (!empty($field)) {
        $files = static::getFiles($file_name, $field);
        foreach ($files as $fid => $file) {
          $autocomplete = array(
            '#theme' => 'filefield_sources_element',
            '#source_id' => 'reference',
            '#method' => 'autocompleteElement',
            '#variables' => array(
              'file' => $file
            ),
          );
          $items[$file->filename ." [fid:$fid]"] = drupal_render($autocomplete);
        }
      }
    }

    return new JsonResponse($items);
  }

  public static function routes() {
    $routes = array();

    $routes['filefield_sources.autocomplete'] = new Route(
      '/file/reference/{entity_type}/{bundle_name}/{field_name}',
      array(
        '_controller' => get_called_class() . '::autocomplete',
      ),
      array(
        '_access_filefield_sources_field' => 'TRUE',
      )
    );

    return $routes;
  }

  /**
   * Implements hook_filefield_source_settings().
   */
  public static function settings(WidgetInterface $plugin) {
    $settings = $plugin->getThirdPartySetting('filefield_sources', 'filefield_sources', array(
      'source_reference' => array(
        'autocomplete' => '0'
      )
    ));

    $return['source_reference'] = array(
      '#title' => t('Autocomplete reference options'),
      '#type' => 'details',
    );

    $return['source_reference']['autocomplete'] = array(
      '#title' => t('Match file name'),
      '#options' => array(
        '0' => t('Starts with string'),
        '1' => t('Contains string'),
      ),
      '#type' => 'radios',
      '#default_value' => isset($settings['source_reference']['autocomplete']) ? $settings['source_reference']['autocomplete'] : '0',
    );

    return $return;
  }

  /**
   * Get all the files used within a particular field (or all fields).
   *
   * @param $file_name
   *   The partial name of the file to retrieve.
   * @param $instance
   *   Optional. A CCK field array for which to filter returned files.
   */
  protected static function getFiles($filename, $instance = NULL) {
    $instances = array();
    if (!isset($instance)) {
      foreach (field_info_fields() as $instance) {
        if ($instance['type'] == 'file' || $instance['type'] == 'image') {
          $instances[] = $instance;
        }
      }
    }
    else {
      $instances = array($instance);
    }

    $files = array();
    foreach ($instances as $instance) {
      // Load the field data, which contains the schema information.
      $field = field_info_field($instance['field_name']);

      // We don't support fields that are not stored with SQL.
      if (!isset($field['storage']['details']['sql']['FIELD_LOAD_CURRENT'])) {
        continue;
      }

      // 1 == contains, 0 == starts with.
      $like = empty($instance['widget']['settings']['filefield_sources']['source_reference']['autocomplete']) ? (db_like($filename) . '%') : ('%' . db_like($filename) . '%');

      $table_info = reset($field['storage']['details']['sql']['FIELD_LOAD_CURRENT']);
      $table = key($field['storage']['details']['sql']['FIELD_LOAD_CURRENT']);
      $query = db_select($table, 'cf');
      $query->innerJoin('file_managed', 'f', 'f.fid = cf.' . $table_info['fid']);
      $query->fields('f');
      $query->condition('f.status', 1);
      $query->condition('f.filename', $like, 'LIKE');
      $query->orderBy('f.timestamp', 'DESC');
      $query->groupBy('f.fid');
      $query->range(0, 30);
      $query->addTag('filefield_source_reference_list');
      $result = $query->execute();

      foreach ($result as $file) {
        $files[$file->fid] = $file;
      }
    }

    return $files;
  }

}
