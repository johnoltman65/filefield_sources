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
   * Tests default settings.
   */
  function testDefaultSettings() {
    // Upload source enabled by default.
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // There are no 'Upload' source list item.
    $add_node = 'node/add/' . $this->type_name;
    $this->drupalGet($add_node);
    $this->assertNoLink('Upload');

    // Upload source still work.
    $test_file_text = $this->getTestFile('text');
    $name = 'files[' . $this->field_name . '_0]';
    $edit = array($name => drupal_realpath($test_file_text->getFileUri()));
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Upload') . '"]');
    $this->drupalPostAjaxForm(NULL, $edit, array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
  }

  /**
   * Tests all sources enabled.
   */
  function testAllSourcesEnabled() {
    // Upload source enabled by default.
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->field_name . "_settings_edit");

    // Enable all sources.
    $prefix = 'fields[' . $this->field_name . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources][sources]';
    $edit = array(
      $prefix . '[upload]' => TRUE,
      $prefix . '[remote]' => TRUE,
      $prefix . '[clipboard]' => TRUE,
      $prefix . '[reference]' => TRUE,
      $prefix . '[attach]' => TRUE,
    );
    $this->drupalPostAjaxForm(NULL, $edit, $this->field_name . '_plugin_settings_update');
    $this->assertText("File field sources: upload, remote, clipboard, reference, attach", 'The expected summary is displayed.');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));

    // There are all source list items.
    $add_node = 'node/add/' . $this->type_name;
    $this->drupalGet($add_node);
    foreach (array('Upload', 'Remote URL', 'Clipboard', 'Reference existing', 'File attach') as $label) {
      $this->assertLink($label);
    }

    // Upload source still work.
    $test_file_text = $this->getTestFile('text');
    $name = 'files[' . $this->field_name . '_0]';
    $edit = array($name => drupal_realpath($test_file_text->getFileUri()));
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Upload') . '"]');
    $this->drupalPostAjaxForm(NULL, $edit, array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
  }
}
