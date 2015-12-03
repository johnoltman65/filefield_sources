<?php

/**
 * @file
 * Contains \Drupal\imce\Controller\ImceController.
 */

namespace Drupal\filefield_sources\Controller;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\imce\Imce;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Controller routines for imce routes.
 */
class ImceController extends ControllerBase {

  /**
   * Outputs the IMCE browser for FileField.
   */
  public function page($entity_type, $bundle_name, $field_name, Request $request) {
    // Check access.
    if (!\Drupal::moduleHandler()->moduleExists('imce') || !Imce::access() || !$instance = entity_load('field_config', $entity_type . '.' . $bundle_name . '.' . $field_name)) {
      throw new AccessDeniedHttpException();
    }

    $settings = $instance->getSettings();
    $imceFM = Imce::userFM(\Drupal::currentUser(), $settings['uri_scheme'], $request);

    // Override scanner.
    if (!empty($imceFM)) {
      $scanner = \Drupal::service('filefield_sources.imce_scanner');
      $widget = entity_get_form_display($entity_type, $bundle_name, 'default')->getComponent($field_name);
      // Full mode.
      if (!empty($widget['third_party_settings']['filefield_sources']['filefield_sources']['source_imce']['imce_mode'])) {
        $imceFM->setConf('scanner', array($scanner, 'customScanFull'));
        // Set context.
        $scanner->setContext(array(
          'scheme' => $imceFM->getConf('scheme'),
        ));
      }
      // Restricted mode.
      else {
        $imceFM->setConf('scanner', array($scanner, 'customScanRestricted'));
        // Set active folder.
        $scheme = $imceFM->getConf('scheme');
        $root = $scheme . '://';
        $field_uri = static::getUploadLocation($settings);
        $is_root = $field_uri == $root;
        $path = $is_root ? '.' : substr($field_uri, strlen($root));
        $imceFM->activeFolder = $imceFM->checkFolder($path);
        // Set context.
        $scanner->setContext(array(
          'entity_type' => $entity_type,
          'field_name' => $field_name,
          'uri' => $field_uri,
          'is_rool' => $is_root,
        ));
      }

      // Disable absolute URLs.
      \Drupal::configFactory()->getEditable('imce.settings')->set('abs_urls', FALSE);

      return $imceFM->pageResponse();
    }
  }

  /**
   * Determines the URI for a file field.
   *
   * @param array $data
   *   An array of token objects to pass to token_replace().
   *
   * @return string
   *   A file directory URI with tokens replaced.
   *
   * @see token_replace()
   */
  public static function getUploadLocation($settings, $data = array()) {
    $destination = trim($settings['file_directory'], '/');

    // Replace tokens. To ensure that render context is empty, pass a bubbleable
    // metadata object to the replace method.
    $bubbleable_metadata = new BubbleableMetadata();
    $destination = \Drupal::token()->replace($destination, $data, [], $bubbleable_metadata);

    return $settings['uri_scheme'] . '://' . $destination;
  }

}
