<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\FileFieldSourcesTestBase.
 */

namespace Drupal\filefield_sources\Tests;

use Drupal\file\Tests\FileFieldTestBase;
use Drupal\simpletest\WebTestBase;

/**
 * Base class for File Field Sources test cases.
 */
abstract class FileFieldSourcesTestBase extends FileFieldTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('filefield_sources');

  protected $adminUser;

  protected $typeName;
  protected $fieldName;
  protected $node;

  /**
   * Sets up for file field sources test cases.
   */
  protected function setUp() {
    WebTestBase::setUp();

    // Create admin user, then login.
    $this->adminUser = $this->drupalCreateUser(array(
      'access content',
      'access administration pages',
      'administer site configuration',
      'administer users',
      'administer permissions',
      'administer content types',
      'administer node fields',
      'administer node display',
      'administer node form display',
      'administer nodes',
      'bypass node access',
    ));
    $this->drupalLogin($this->adminUser);

    // Create content type.
    $this->typeName = 'article';
    $this->drupalCreateContentType(array('type' => $this->typeName, 'name' => 'Article'));

    // Add node.
    $this->node = $this->drupalCreateNode();

    // Add file field.
    $this->fieldName = strtolower($this->randomMachineName());
    $this->createFileField($this->fieldName, 'node', $this->typeName);
  }

  /**
   * Enable file field sources.
   *
   * @param array $sources
   *   List of sources to enable or disable. e.g
   *   array(
   *     'upload' => FALSE,
   *     'remote' => TRUE,
   *   ).
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
    $manage_display = 'admin/structure/types/manage/' . $this->typeName . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->fieldName . "_settings_edit");

    // Enable sources.
    $prefix = 'fields[' . $this->fieldName . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources][sources]';
    $edit = array();
    foreach ($sources as $source => $enabled) {
      $edit[$prefix . '[' . $source . ']'] = $enabled ? TRUE : FALSE;
    }
    $this->drupalPostAjaxForm(NULL, $edit, $this->fieldName . '_plugin_settings_update');
    $this->assertText("File field sources: " . implode(', ', array_keys($sources)), 'The expected summary is displayed.');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));

    $add_node = 'node/add/' . $this->typeName;
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
   *   Permanent file entity.
   */
  public function createPermanentFileEntity() {
    $file = $this->createTemporaryFileEntity();
    // Only permanent file can be referred.
    $file->status = FILE_STATUS_PERMANENT;
    // Author has permission to access file.
    $file->uid = $this->adminUser->id();
    $file->save();

    // Permanent file must be used by an entity.
    \Drupal::service('file.usage')->add($file, 'file', 'node', $this->node->id());

    return $file;
  }

  /**
   * Create temporary file entity.
   *
   * @return object
   *   Temporary file entity.
   */
  public function createTemporaryFileEntity() {
    $file = $this->createTemporaryFile();

    // Add a filesize property to files as would be read by file_load().
    $file->filesize = filesize($file->uri);

    return entity_create('file', (array) $file);
  }

  /**
   * Create temporary file.
   *
   * @return object
   *   Permanent file object.
   */
  public function createTemporaryFile($path = '') {
    $filename = $this->randomMachineName() . '.txt';
    if (empty($path)) {
      $path = file_default_scheme()  . '://';
    }
    $uri = $path . '/' . $filename;
    $contents = $this->randomString();

    // Change mode so that we can create files.
    file_prepare_directory($path, FILE_CREATE_DIRECTORY);
    drupal_chmod($path, FILE_CHMOD_DIRECTORY);

    file_put_contents($uri, $contents);
    $this->assertTrue(is_file($uri), 'The temporary file has been created.');

    // Change mode so that we can delete created file.
    drupal_chmod($uri, FILE_CHMOD_FILE);

    // Return object similar to file_scan_directory().
    $file = new \stdClass();
    $file->uri = $uri;
    $file->filename = $filename;
    $file->name = pathinfo($filename, PATHINFO_FILENAME);
    return $file;
  }

  /**
   * Update file field sources settings.
   *
   * @param string $source_key
   *   Wrapper, defined by each source.
   * @param string $key
   *   Key, defined by each source.
   * @param mixed $value
   *   Value to set.
   */
  public function updateFilefieldSourcesSettings($source_key, $key, $value) {
    $manage_display = 'admin/structure/types/manage/' . $this->typeName . '/form-display';
    $this->drupalGet($manage_display);

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->fieldName . "_settings_edit");

    // Update settings.
    $name = 'fields[' . $this->fieldName . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources]' . "[$source_key][$key]";
    $edit = array($name => $value);
    $this->drupalPostAjaxForm(NULL, $edit, $this->fieldName . '_plugin_settings_update');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));
  }

  /**
   * Upload file by 'Attach' source.
   *
   * @param string $uri
   * @param string $filename
   * @param int $index
   */
  public function uploadFileByAttachSource($uri, $filename, $index = 0) {
    $edit = array(
      $this->fieldName . '[' . $index . '][filefield_attach][filename]' => $uri,
    );
    $this->drupalPostAjaxForm(NULL, $edit, $this->fieldName . '_' . $index . '_attach');

    // Ensure file is uploaded.
    $this->assertFileUploaded($filename, $index);
  }

  /**
   * Upload file by 'Reference' source.
   *
   * @param int $fid
   * @param string $filename
   * @param int $index
   */
  public function uploadFileByReferenceSource($fid, $filename, $index = 0) {
    $name = $this->fieldName . '[' . $index . '][filefield_reference][autocomplete]';
    $edit = array($name => $filename . ' [fid:' . $fid . ']');
    $this->drupalPostAjaxForm(NULL, $edit, $this->fieldName . '_' . $index . '_autocomplete_select');

    // Ensure file is uploaded.
    $this->assertFileUploaded($filename, $index);
  }

  /**
   * Upload file by 'Clipboard' source.
   *
   * @param string $uri
   * @param string $filename
   * @param int $index
   */
  public function uploadFileByClipboardSource($uri, $filename, $index = 0) {
    $prefix = $this->fieldName . '[' . $index . '][filefield_clipboard]';
    $edit = array(
      $prefix . '[filename]' => $filename,
      $prefix . '[contents]' => 'data:text/plain;base64,' . base64_encode(file_get_contents($uri)),
    );
    $this->drupalPostAjaxForm(NULL, $edit, array($this->fieldName . '_' . $index . '_clipboard_upload_button' => t('Upload')));

    // Ensure file is uploaded.
    $this->assertFileUploaded($filename, $index);
  }

  /**
   * Upload file by 'Remote' source.
   *
   * @param string $url
   * @param string $filename
   * @param int $index
   */
  public function uploadFileByRemoteSource($url, $filename, $index = 0) {
    $name = $this->fieldName . '[' . $index . '][filefield_remote][url]';
    $edit = array($name => $url);
    $this->drupalPostAjaxForm(NULL, $edit, $this->fieldName . '_' . $index . '_transfer');

    // Ensure file is uploaded.
    $this->assertFileUploaded($filename, $index);
  }

  /**
   * Upload file by 'Upload' source.
   *
   * @param string $uri
   * @param string $filename
   * @param int $index
   */
  public function uploadFileByUploadSource($uri, $filename, $index = 0) {
    $name = 'files[' . $this->fieldName . '_' . $index . ']';
    $edit = array($name => drupal_realpath($uri));
    $this->drupalPostAjaxForm(NULL, $edit, array($this->fieldName . '_' . $index . '_upload_button' => t('Upload')));

    // Ensure file is uploaded.
    $this->assertFileUploaded($filename, $index);
  }

  /**
   * Check to see if file is uploaded.
   *
   * @param string $filename
   * @param int $index
   */
  public function assertFileUploaded($filename, $index = 0) {
    $this->assertLink($filename);
    $this->assertFieldByXPath('//input[@name="' . $this->fieldName . '_' . $index . '_remove_button"]', t('Remove'), 'After uploading a file, "Remove" button is displayed.');
  }

  /**
   * Remove uploaded file.
   *
   * @param string $filename
   * @param int $index
   */
  public function removeFile($filename, $index = 0) {
    $this->drupalPostAjaxForm(NULL, array(), $this->fieldName . '_' . $index . '_remove_button');

    // Ensure file is removed.
    $this->assertFileRemoved($filename, $index);
  }

  /**
   * Check to see if file is removed.
   *
   * @param string $filename
   * @param int $index
   */
  public function assertFileRemoved($filename, $index = 0) {
    $this->assertNoLink($filename);
    $this->assertNoFieldByXPath('//input[@name="' . $this->fieldName . '_' . $index . '_remove_button"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
  }

}
