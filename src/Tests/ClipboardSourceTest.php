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
    // Upload source enabled by default.
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->field_name . "_settings_edit");

    // Enable upload source.
    $prefix = 'fields[' . $this->field_name . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources][sources]';
    $edit = array(
      $prefix . '[clipboard]' => TRUE,
    );
    $this->drupalPostAjaxForm(NULL, $edit, $this->field_name . '_plugin_settings_update');
    $this->assertText("File field sources: upload, clipboard", 'The expected summary is displayed.');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));

    // There are upload and clipboard source list items.
    $add_node = 'node/add/' . $this->type_name;
    $this->drupalGet($add_node);
    foreach (array('Upload', 'Clipboard') as $label) {
      $this->assertLink($label);
    }

    // Test upload file by clipboard source.
    $test_file_text = $this->getTestFile('text');
    $prefix = $this->field_name . '[0][filefield_clipboard]';
    $edit = array(
      $prefix . '[filename]' => $test_file_text->getFilename(),
      $prefix . '[contents]' => 'data:text/plain;base64,' . base64_encode(file_get_contents($test_file_text->getFileUri())),
    );
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Upload') . '"]');
    $this->drupalPostAjaxForm(NULL, $edit, array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertLink($test_file_text->getFilename());
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Upload'), t('After uploading a file, "Upload" button is no longer displayed.'));

    // Test remove uploaded file.
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Upload'), 'After clicking the "Remove" button, the "Upload" button is displayed.');
  }
}
