<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\ClipboardSourceTest.
 */

namespace Drupal\filefield_sources\Tests;

/**
 * Tests the clipboard source.
 *
 * @group filefield_sources
 */
class ClipboardSourceTest extends FileFieldSourcesTestBase {

  /**
   * Tests remote source enabled.
   */
  function testClipboardSourceEnabled() {
    $this->enableSources(array(
      'clipboard' => TRUE,
    ));

    // Upload a file by 'Clipboard' source.
    $test_file = $this->getTestFile('text');
    $prefix = $this->field_name . '[0][filefield_clipboard]';
    $edit = array(
      $prefix . '[filename]' => $test_file->getFilename(),
      $prefix . '[contents]' => 'data:text/plain;base64,' . base64_encode(file_get_contents($test_file->getFileUri())),
    );
    $upload_button = $this->xpath('//input[@type="submit" and @value="' . t('Upload') . '"]');
    $this->drupalPostAjaxForm(NULL, $edit, array((string) $upload_button[0]['name'] => (string) $upload_button[0]['value']));

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
