<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\MultipleValuesTest.
 */

namespace Drupal\filefield_sources\Tests;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Tests multiple sources on multiple values field.
 *
 * @group filefield_sources
 */
class MultipleValuesTest extends FileFieldSourcesTestBase {

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

    $uploaded_files = 0;
    $permanent_file_entity = $this->createPermanentFileEntity();
    $temporary_file_entity = $this->createTemporaryFileEntity();

    $path = file_default_scheme()  . '://' . FILEFIELD_SOURCE_ATTACH_DEFAULT_PATH;
    $temporary_file = $this->createTemporaryFile($path);

    // Ensure no files has been uploaded.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'There are no file have been uploaded.');

    // Upload a file by 'Remote' source.
    $input = $this->field_name . '[' . $uploaded_files . '][filefield_remote][url]';
    $edit = array($input => 'https://www.drupal.org/README.txt');
    $this->drupalPostForm(NULL, $edit, t('Transfer'));
    $uploaded_files++;

    // Upload a file by 'Reference' source.
    $input = $this->field_name . '[' . $uploaded_files . '][filefield_reference][autocomplete]';
    $edit = array($input => $permanent_file_entity->getFilename() . ' [fid:' . $permanent_file_entity->id() . ']');
    $this->drupalPostForm(NULL, $edit, t('Select'));
    $uploaded_files++;

    // Upload a file by 'Clipboard' source.
    $prefix = $this->field_name . '[' . $uploaded_files . '][filefield_clipboard]';
    $edit = array(
      $prefix . '[filename]' => $temporary_file_entity->getFilename(),
      $prefix . '[contents]' => 'data:text/plain;base64,' . base64_encode(file_get_contents($temporary_file_entity->getFileUri())),
    );
    $this->drupalPostAjaxForm(NULL, $edit, array($this->field_name . '_' . $uploaded_files . '_clipboard_upload_button' => t('Upload')));
    $uploaded_files++;

    // Upload a file by 'Attach' source.
    $edit = array(
      $this->field_name . '[' . $uploaded_files . '][filefield_attach][filename]' => $temporary_file->uri
    );
    $this->drupalPostForm(NULL, $edit, t('Attach'));
    $uploaded_files++;

    // Upload a file by 'Upload' source.
    $input = 'files[' . $this->field_name . '_' . $uploaded_files . '][]';
    $edit = array($input => drupal_realpath($temporary_file_entity->getFileUri()));
    $this->drupalPostAjaxForm(NULL, $edit, array($this->field_name . '_' . $uploaded_files . '_upload_button' => t('Upload')));
    $uploaded_files++;

    // Ensure files have been uploaded.
    $remove_buttons = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->assertEqual(count($remove_buttons), $uploaded_files, "There are $uploaded_files files have been uploaded.");

    // Remove all uploaded files.
    for ($i = 0; $i < count($remove_buttons); $i++) {
      $this->drupalPostAjaxForm(NULL, array(), array($this->field_name . '_0_remove_button' => t('Remove')));
    }

    // Ensure all files have been removed.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'All files have been removed.');
  }
}
