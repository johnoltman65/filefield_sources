<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\ReferenceSourceTest.
 */

namespace Drupal\filefield_sources\Tests;

/**
 * Tests the reference source.
 *
 * @group filefield_sources
 */
class ReferenceSourceTest extends FileFieldSourcesTestBase {

  /**
   * Tests remote source enabled.
   */
  function testReferenceSourceEnabled() {

    // Create test files.
    $file = $this->getTestFile('text');
    // Only permanent file can be referred.
    $file->status = FILE_STATUS_PERMANENT;
    // Author has permission to access file.
    $file->uid = $this->admin_user->id();
    $file->save();

    // Test autocompletion.
    $autocomplete_result = $this->drupalGetJSON('file/reference/node/' . $this->type_name . '/' . $this->field_name, array('query' => array('q' => $file->getFileName())));
    $this->assertEqual($autocomplete_result[0]['label'], $file->getFileName(), 'Autocompletion has been returned correct result.');

    // Upload source enabled by default.
    $manage_display = 'admin/structure/types/manage/' . $this->type_name . '/form-display';
    $this->drupalGet($manage_display);
    $this->assertText("File field sources: upload", 'The expected summary is displayed.');

    // Click on the widget settings button to open the widget settings form.
    $this->drupalPostAjaxForm(NULL, array(), $this->field_name . "_settings_edit");

    // Enable remote source.
    $prefix = 'fields[' . $this->field_name . '][settings_edit_form][third_party_settings][filefield_sources][filefield_sources][sources]';
    $edit = array(
      $prefix . '[reference]' => TRUE,
    );
    $this->drupalPostAjaxForm(NULL, $edit, $this->field_name . '_plugin_settings_update');
    $this->assertText("File field sources: upload, reference", 'The expected summary is displayed.');

    // Save the form to save the third party settings.
    $this->drupalPostForm(NULL, array(), t('Save'));

    // There are upload and remote source list items.
    $add_node = 'node/add/' . $this->type_name;
    $this->drupalGet($add_node);
    foreach (array('Upload', 'Reference existing') as $label) {
      $this->assertLink($label);
    }

    $result = $this->xpath('//input[@name="' . $this->field_name . '[0][filefield_reference][autocomplete]" and contains(@data-autocomplete-path, "/file/reference/node/' . $this->type_name . '/' . $this->field_name . '")]');
    $this->assertEqual(count($result), 1, 'There is a textbox with autocompletion.');

    // Test upload file by remote source.
    $name = $this->field_name . '[0][filefield_reference][autocomplete]';
    $edit = array($name => $autocomplete_result[0]['value']);
    $this->drupalPostForm(NULL, $edit, t('Select'));
    $this->assertLink($file->getFileName());
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Select'), t('After uploading a file, "Select" button is no longer displayed.'));

    // Test remove uploaded file.
    $button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $button[0]['name'] => (string) $button[0]['value']));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Select'), 'After clicking the "Remove" button, the "Select" button is displayed.');
  }
}
