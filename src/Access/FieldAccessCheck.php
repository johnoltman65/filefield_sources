<?php

/**
 * @file
 * Contains \Drupal\filefield_sources\Access\FieldAccessCheck.
 */

namespace Drupal\filefield_sources\Access;

use Drupal\Core\Routing\Access\AccessInterface as RoutingAccessInterface;

/**
 * Allows access to routes to be controlled by a '_filefield_sources_field_access' parameter.
 */
class FieldAccessCheck implements RoutingAccessInterface {

  /**
   * Checks access.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle_name
   *   Bundle name.
   * @param string $field_name
   *   Field name.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access($entity_type, $bundle_name, $field_name) {
    $field = field_info_field($field_name);
    return field_access('edit', $field, $entity_type) ? static::ALLOW : static::DENY;
  }

}
