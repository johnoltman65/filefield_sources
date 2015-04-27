<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\AttachSourceTest.
 */

namespace Drupal\filefield_sources\Tests;

/**
 * Tests the attach source.
 *
 * @group filefield_sources
 */
class AttachSourceTest extends FileFieldSourcesTestBase {

  /**
   * Check to see if a option is present.
   *
   * @param type $uri
   *   The option to check.
   *
   * @return
   *   TRUE if the option is present, FALSE otherwise.
   */
  public function isOptionPresent($uri) {
    $options = $this->xpath('//select[@name=:name]/option[@value=:option]', array(
      ':name' => $this->fieldName . '[0][filefield_attach][filename]',
      ':option' => $uri,
    ));
    return isset($options[0]);
  }

  /**
   * Upload file by 'Attach' source.
   *
   * @param object $file
   *   The file object.
   */
  public function uploadFile($file) {
    $edit = array(
      $this->fieldName . '[0][filefield_attach][filename]' => $file->uri,
    );
    $this->drupalPostAjaxForm(NULL, $edit, $this->fieldName . '_0_attach');
    //$this->drupalPostForm(NULL, $edit, t('Attach'));

    // Ensure file is uploaded.
    $this->assertFileUploaded($file->filename);
  }

  /**
   * Check to see if file is uploaded.
   *
   * @param $filename
   *   The file name to check.
   */
  public function assertFileUploaded($filename) {
    $this->assertLink($filename);
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), 'After uploading a file, "Remove" button is displayed.');
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Attach'), 'After uploading a file, "Attach" button is no longer displayed.');
  }

  /**
   * Remove uploaded file.
   *
   * @param object $file
   *   The file object.
   */
  public function removeFile($file) {
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure file is removed.
    $this->assertFileRemoved($file->filename);
  }

  /**
   * Check to see if file is removed.
   *
   * @param type $filename
   *   The file name to check.
   */
  public function assertFileRemoved($filename) {
    $this->assertNoLink($filename);
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Attach'), 'After clicking the "Remove" button, the "Attach" button is displayed.');
  }

  /**
   * Tests move file from relative path.
   *
   * Default settings: Move file from 'public://file_attach' to 'public://'.
   */
  public function testMoveFileFromRelativePath() {
    // Create test file.
    $path = file_default_scheme()  . '://' . FILEFIELD_SOURCE_ATTACH_DEFAULT_PATH;
    $file = $this->createTemporaryFile($path);
    $dest_uri = file_default_scheme() . '://' . $file->filename;

    $this->enableSources(array(
      'attach' => TRUE,
    ));

    // Ensure option is present.
    $this->assertTrue($this->isOptionPresent($file->uri), 'File option exists.');

    // Upload a file.
    $this->uploadFile($file);

    // Ensure option is no longer present.
    $this->assertFalse($this->isOptionPresent($file->uri), 'File option no longer exists.');

    // Ensure file is moved.
    $this->assertFalse(is_file($file->uri), 'Source file has been removed.');
    $this->assertTrue(is_file($dest_uri), 'Destination file has been created.');

    $this->removeFile($file->filename);

    // Ensure empty message exists.
    $this->assertText('There currently are no files to attach.', "'No files to attach' message exists.");
  }

  /**
   * Calculate custom absolute path.
   */
  public function getCustomAttachPath() {
    $path = drupal_realpath(file_default_scheme() . '://');
    $path = str_replace(realpath('./'), '', $path);
    $path = ltrim($path, '/');
    $path = $path . '/custom_file_attach';
    return $path;
  }

  /**
   * Tests copy file from absolute path.
   *
   * Copy file from 'sites/default/files/custom_file_attach' to 'public://'.
   */
  public function testCopyFileFromAbsolutePath() {
    $path = $this->getCustomAttachPath();

    // Create test file.
    $file = $this->createTemporaryFile($path);
    $dest_uri = file_default_scheme() . '://' . $file->filename;

    // Change settings.
    $this->updateFilefieldSourcesSettings('source_attach', 'path', $path);
    $this->updateFilefieldSourcesSettings('source_attach', 'absolute', FILEFIELD_SOURCE_ATTACH_ABSOLUTE);
    $this->updateFilefieldSourcesSettings('source_attach', 'attach_mode', FILEFIELD_SOURCE_ATTACH_MODE_COPY);

    $this->enableSources(array(
      'attach' => TRUE,
    ));

    // Ensure option is present.
    $this->assertTrue($this->isOptionPresent($file->uri), 'File option exists.');

    // Upload a file.
    $this->uploadFile($file);

    // Ensure option is no longer present.
    $this->assertFalse($this->isOptionPresent($file->uri), 'File option no longer exists.');

    // Ensure file is copied.
    $this->assertTrue(is_file($file->uri), 'Source file still exists.');
    $this->assertTrue(is_file($dest_uri), 'Destination file has been created.');

    $this->removeFile($file->filename);

    // Ensure there are files to attach.
    $this->assertNoText('There currently are no files to attach.', "'No files to attach' message does not exist.");
  }
}
