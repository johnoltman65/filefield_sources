<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\EmptyValuesTest.
 */

namespace Drupal\filefield_sources\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Tests empty values.
 *
 * @group filefield_sources
 */
class EmptyValuesTest extends FileFieldSourcesTestBase {

  /**
   * Tests all sources enabled.
   */
  function testAllSourcesEnabled() {
    // Change allowed number of values.
    $this->drupalPostForm('admin/structure/types/manage/' . $this->type_name . '/fields/node.' . $this->type_name . '.' . $this->field_name . '/storage', array('field_storage[cardinality]' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED), t('Save field settings'));

    $this->enableSources(array(
      'upload' => TRUE,
      'remote' => TRUE,
      'clipboard' => TRUE,
      'reference' => TRUE,
      'attach' => TRUE,
    ));

    // Upload a file by 'Remote' source.
    $input = $this->field_name . '[0][filefield_remote][url]';
    $button_name = $this->field_name . '_0_transfer';
    $button_label = t('Transfer');
    $this->drupalPostForm(NULL, array($input => ''), $button_label);

    $this->assertNoFilesUploaded($button_name, $button_label);

    // Upload a file by 'Reference' source.
    $input = $this->field_name . '[0][filefield_reference][autocomplete]';
    $button_name = $this->field_name . '_0_autocomplete_select';
    $button_label = t('Select');
    $this->drupalPostForm(NULL, array($input => ''), $button_label);

    $this->assertNoFilesUploaded($button_name, $button_label);

    // Upload a file by 'Clipboard' source.
    $prefix = $this->field_name . '[0][filefield_clipboard]';
    $edit = array(
      $prefix . '[filename]' => '',
      $prefix . '[contents]' => '',
    );
    $button_name = $this->field_name . '_0_clipboard_upload_button';
    $button_label = t('Upload');
    $this->drupalPostAjaxForm(NULL, $edit, array($button_name => $button_label));

    $this->assertNoFilesUploaded($button_name, $button_label);

    // Upload a file by 'Attach' source.
    $button_name = $this->field_name . '_0_attach';
    $button_label = t('Attach');
    $this->drupalPostForm(NULL, array(), $button_label);

    $this->assertNoFilesUploaded($button_name, $button_label);

    // Upload a file by 'Upload' source.
    $input = 'files[' . $this->field_name . '_0][]';
    $button_name = $this->field_name . '_0_upload_button';
    $button_label = t('Upload');
    $this->drupalPostAjaxForm(NULL, array($input => ''), array($button_name => $button_label));

    $this->assertNoFilesUploaded($button_name, $button_label);
  }

  function assertNoFilesUploaded($button_name, $button_label) {
    // Ensure that there are no remove buttons.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'There are no remove buttons.');

    // Ensure that there is only one button with name.
    $buttons = $this->xpath('//input[@name="' . $button_name . '" and @value="' . $button_label . '"]');
    $this->assertEqual(count($buttons), 1, format_string('There is only one button with name %name and label %label', array('%name' => $button_name, '%label' => $button_label)));
  }
}
