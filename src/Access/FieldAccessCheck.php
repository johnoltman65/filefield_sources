<?php

namespace Drupal\filefield_sources\Access;

use Drupal\Core\Routing\Access\AccessInterface as RoutingAccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access check for file field source routes.
 */
class FieldAccessCheck implements RoutingAccessInterface {
  /**
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    // $instance->connection = $container->get('');
    return $instance;
  }

  /**
   * Checks access.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle_name
   *   Bundle name.
   * @param string $field_name
   *   Field name.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access($entity_type, $bundle_name, $field_name, AccountInterface $account) {
    $field = \Drupal::entityTypeManager()->getStorage('field_config')->load($entity_type . '.' . $bundle_name . '.' . $field_name);
    return $field->access('edit', $account, TRUE);
  }

}
