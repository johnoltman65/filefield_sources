<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\UploadSourceTest.
 */

namespace Drupal\filefield_sources\Tests;

/**
 * Tests the upload source.
 *
 * @group filefield_sources
 */
class UploadSourceTest extends FileFieldSourcesTestBase {

  /**
   * Tests upload source enabled.
   */
  function testUploadSourceEnabled() {
    $this->enableSources(array(
      'upload' => TRUE,
    ));

    $this->assertUploadSourceWorkProperly();
  }

  /**
   * Tests all sources enabled.
   */
  function testAllSourcesEnabled() {
    $this->enableSources(array(
      'upload' => TRUE,
      'remote' => TRUE,
      'clipboard' => TRUE,
      'reference' => TRUE,
      'attach' => TRUE,
    ));

    $this->assertUploadSourceWorkProperly();
  }

  /**
   * Tests upload source still working properly.
   */
  function assertUploadSourceWorkProperly() {
    $test_file = $this->createTemporaryFileEntity();

    // Upload a file by 'Upload' source.
    $name = 'files[' . $this->field_name . '_0]';
    $edit = array($name => drupal_realpath($test_file->getFileUri()));
    $this->drupalPostAjaxForm(NULL, $edit, array($this->field_name . '_0_upload_button' => t('Upload')));

    // Ensure file is uploaded.
    $this->assertLink($test_file->getFilename());
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Upload'), t('After uploading a file, "Upload" button is no longer displayed.'));

    // Remove uploaded file.
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure file is removed.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Upload'), 'After clicking the "Remove" button, the "Upload" button is displayed.');
  }
}
