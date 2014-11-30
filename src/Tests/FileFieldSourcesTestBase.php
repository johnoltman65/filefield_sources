<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\FileFieldSourcesTestBase.
 */

namespace Drupal\filefield_sources\Tests;

use Drupal\file\Tests\FileFieldTestBase;
use Drupal\simpletest\WebTestBase;

/**
 * Provides methods specifically for testing File Field Sources module's field
 * handling.
 */
abstract class FileFieldSourcesTestBase extends FileFieldTestBase {

  /**
  * Modules to enable.
  *
  * @var array
  */
  public static $modules = array('filefield_sources');

  protected $admin_user;

  protected $type_name;
  protected $field_name;

  protected function setUp() {
    WebTestBase::setUp();
    $this->admin_user = $this->drupalCreateUser(array('access content', 'access administration pages', 'administer site configuration', 'administer users', 'administer permissions', 'administer content types', 'administer node fields', 'administer node display', 'administer node form display', 'administer nodes', 'bypass node access'));
    $this->drupalLogin($this->admin_user);

    // Create content type.
    $this->type_name = 'article';
    $this->drupalCreateContentType(array('type' => $this->type_name, 'name' => 'Article'));

    // Add file field.
    $this->field_name = strtolower($this->randomMachineName());
    $this->createFileField($this->field_name, 'node', $this->type_name);
  }

}
