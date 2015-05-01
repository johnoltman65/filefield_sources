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
   * Sets up for multiple values test case.
   */
  protected function setUp() {
    parent::setUp();

    // Create test files.
    $this->permanent_file_entity = $this->createPermanentFileEntity();
    $this->temporary_file_entity_1 = $this->createTemporaryFileEntity();
    $this->temporary_file_entity_2 = $this->createTemporaryFileEntity();

    $path = file_default_scheme()  . '://' . FILEFIELD_SOURCE_ATTACH_DEFAULT_PATH;
    $this->temporary_file = $this->createTemporaryFile($path);

    // Change allowed number of values.
    $this->drupalPostForm('admin/structure/types/manage/' . $this->typeName . '/fields/node.' . $this->typeName . '.' . $this->fieldName . '/storage', array('field_storage[cardinality]' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED), t('Save field settings'));

    $this->enableSources(array(
      'upload' => TRUE,
      'remote' => TRUE,
      'clipboard' => TRUE,
      'reference' => TRUE,
      'attach' => TRUE,
    ));
  }

  /**
   * Tests uploading then removing files.
   */
  public function testUploadThenRemoveFiles() {
    $uploaded_files = $this->uploadFiles();

    // Ensure files have been uploaded.
    $remove_buttons = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->assertEqual(count($remove_buttons), $uploaded_files, "There are $uploaded_files files have been uploaded.");

    // Remove all uploaded files.
    $this->removeFile('README.txt');
    $this->removeFile($this->permanent_file_entity->getFilename());
    $this->removeFile($this->temporary_file_entity_1->getFilename());
    $this->removeFile($this->temporary_file_entity_2->getFilename());
    $this->removeFile($this->temporary_file->filename);

    // Ensure all files have been removed.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'All files have been removed.');
  }

  /**
   * Tests uploading files and saving node.
   */
  public function testUploadFilesThenSaveNode() {
    $this->uploadFiles();

    $this->drupalPostForm(NULL, array('title[0][value]' => $this->randomMachineName()), t('Save and publish'));

    // Ensure all files are saved to node.
    $this->assertLink('README.txt');
    $this->assertLink($this->permanent_file_entity->getFilename());
    $this->assertLink($this->temporary_file_entity_1->getFilename());
    $this->assertLink($this->temporary_file_entity_2->getFilename());
    $this->assertLink($this->temporary_file->filename);
  }

  /**
   * Upload files.
   *
   * @return int
   *   Number of files uploaded.
   */
  protected function uploadFiles() {
    $uploaded_files = 0;

    // Ensure no files has been uploaded.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'There are no file have been uploaded.');

    // Upload a file by 'Remote' source.
    $input = $this->fieldName . '[' . $uploaded_files . '][filefield_remote][url]';
    $edit = array($input => $GLOBALS['base_url'] . '/README.txt');
    $this->drupalPostForm(NULL, $edit, t('Transfer'));
    $this->assertLink('README.txt');
    $uploaded_files++;

    // Upload a file by 'Reference' source.
    $this->uploadFileByReferenceSource($this->permanent_file_entity->id(), $this->permanent_file_entity->getFilename(), $uploaded_files);
    $uploaded_files++;

    // Upload a file by 'Clipboard' source.
    $prefix = $this->fieldName . '[' . $uploaded_files . '][filefield_clipboard]';
    $edit = array(
      $prefix . '[filename]' => $this->temporary_file_entity_1->getFilename(),
      $prefix . '[contents]' => 'data:text/plain;base64,' . base64_encode(file_get_contents($this->temporary_file_entity_1->getFileUri())),
    );
    $this->drupalPostAjaxForm(NULL, $edit, array($this->fieldName . '_' . $uploaded_files . '_clipboard_upload_button' => t('Upload')));
    $this->assertLink($this->temporary_file_entity_1->getFilename());
    $uploaded_files++;

    // Upload a file by 'Attach' source.
    $this->uploadFileByAttachSource($this->temporary_file->uri, $this->temporary_file->filename, $uploaded_files);
    $uploaded_files++;

    // Upload a file by 'Upload' source.
    $this->uploadFileByClipboardSource($this->temporary_file_entity_2->getFileUri(), $this->temporary_file_entity_2->getFileName(), $uploaded_files);
    $uploaded_files++;

    return $uploaded_files;
  }
}
