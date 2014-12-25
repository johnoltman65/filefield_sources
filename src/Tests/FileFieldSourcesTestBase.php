<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\FileFieldSourcesTestBase.
 */

namespace Drupal\filefield_sources\Tests;

use Drupal\file\Tests\FileFieldTestBase;
use Drupal\simpletest\WebTestBase;
use Drupal\Core\Site\Settings;

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
  protected $node;

  protected function setUp() {
    WebTestBase::setUp();
    $this->admin_user = $this->drupalCreateUser(array('access content', 'access administration pages', 'administer site configuration', 'administer users', 'administer permissions', 'administer content types', 'administer node fields', 'administer node display', 'administer node form display', 'administer nodes', 'bypass node access'));
    $this->drupalLogin($this->admin_user);

    // Create content type.
    $this->type_name = 'article';
    $this->drupalCreateContentType(array('type' => $this->type_name, 'name' => 'Article'));

    // Add node.
    $this->node = $this->drupalCreateNode();

    // Add file field.
    $this->field_name = strtolower($this->randomMachineName());
    $this->createFileField($this->field_name, 'node', $this->type_name);
  }

  /**
   * Enable file field sources.
   *
   * @param type $sources
   */
  public function enableSources($sources = array()) {
    $sources += array('upload' => TRUE);
    $map = array(
      'upload' => 'Upload',
      'remote' => 'Remote URL',
      'clipboard' => 'Clipboard',
      'reference' => 'Reference existing',
      'attach' => 'File attach',
    );
    $sources = array_intersect_key($sources, $map);
    ksort($sources);

    // Upload source enabled by default.
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->field_name . "_settings_edit");

    // Enable sources.
    $prefix = 'fields[' . $this->field_name . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources][sources]';
    $edit = array();
    foreach ($sources as $source => $enabled) {
      $edit[$prefix . '[' . $source . ']'] = $enabled ? TRUE : FALSE;
    }
    $this->drupalPostAjaxForm(NULL, $edit, $this->field_name . '_plugin_settings_update');
    $this->assertText("File field sources: " . implode(', ', array_keys($sources)), 'The expected summary is displayed.');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));

    $add_node = 'node/add/' . $this->type_name;
    $this->drupalGet($add_node);
    if (count($sources) > 1) {
      // We can swith between sources.
      foreach ($sources as $source => $enabled) {
        $label = $map[$source];
        $this->assertLink($label);
      }
    }
    else {
      foreach ($map as $source => $label) {
        $this->assertNoLink($label);
      }
    }
  }

  /**
   * Create permanent file entity.
   *
   * @return object
   */
  public function createPermanentFileEntity() {
    $file = $this->getTestFile('text');
    // Only permanent file can be referred.
    $file->status = FILE_STATUS_PERMANENT;
    // Author has permission to access file.
    $file->uid = $this->admin_user->id();
    $file->save();

    // Permanent file must be used by an entity.
    \Drupal::service('file.usage')->add($file, 'file', 'node', $this->node->id());

    return $file;
  }

  /**
   * Create temporary file entity.
   *
   * @return object
   */
  public function createTemporaryFileEntity() {
    return $this->getTestFile('text');
  }

  /**
   * Create temporary file.
   *
   * @return object
   */
  public function createTemporaryFile($path = '') {
    $file_name = $this->randomMachineName() . '.txt';
    if (empty($path)) {
      $path = file_default_scheme()  . '://';
    }
    $filepath = $path . '/' . $file_name;
    $contents = $this->randomString();

    // Change mode so that we can create files.
    file_prepare_directory($path, FILE_CREATE_DIRECTORY);
    drupal_chmod($path, FILE_CHMOD_DIRECTORY);

    file_put_contents($filepath, $contents);
    $this->assertTrue(is_file($filepath), 'The temporary file has been created.');

    // Change mode so that we can delete created file.
    drupal_chmod($filepath, FILE_CHMOD_FILE);

    $file = new \stdClass();
    $file->uri = $filepath;
    $file->file_name = $file_name;
    return $file;
  }

  /**
   * Update file field sources settings.
   *
   * @param string $source_key
   * @param string $key
   * @param mixed $value
   */
  public function updateFilefieldSourcesSettings($source_key, $key, $value) {
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->field_name . "_settings_edit");

    // Update settings.
    $name = 'fields[' . $this->field_name . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources]' . "[$source_key][$key]";
    $edit = array($name => $value);
    $this->drupalPostAjaxForm(NULL, $edit, $this->field_name . '_plugin_settings_update');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));
  }

}
