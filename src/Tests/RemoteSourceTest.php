<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\RemoteSourceTest.
 */

namespace Drupal\filefield_sources\Tests;

/**
 * Tests the remote source.
 *
 * @group filefield_sources
 */
class RemoteSourceTest extends FileFieldSourcesTestBase {

  /**
   * Tests remote source enabled.
   */
  function testRemoteSourceEnabled() {
    // Upload source enabled by default.
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->field_name . "_settings_edit");

    // Enable remote source.
    $prefix = 'fields[' . $this->field_name . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources][sources]';
    $edit = array(
      $prefix . '[remote]' => TRUE,
    );
    $this->drupalPostAjaxForm(NULL, $edit, $this->field_name . '_plugin_settings_update');
    $this->assertText("File field sources: upload, remote", 'The expected summary is displayed.');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));

    // There are upload and remote source list items.
    $add_node = 'node/add/' . $this->type_name;
    $this->drupalGet($add_node);
    foreach (array('Upload', 'Remote URL') as $label) {
      $this->assertLink($label);
    }

    // Test upload file by remote source.
    $name = $this->field_name . '[0][filefield_remote][url]';
    $edit = array($name => 'https://www.drupal.org/README.txt');
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Transfer') . '"]');
    $this->drupalPostAjaxForm(NULL, $edit, array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Upload'), t('After uploading a file, "Transfer" button is no longer displayed.'));

    // Test remove uploaded file.
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Upload'), 'After clicking the "Remove" button, the "Transfer" button is displayed.');
  }
}
