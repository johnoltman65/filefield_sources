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
   * Tests reference source enabled.
   */
  function testReferenceSourceEnabled() {

    // Create test file.
    $test_file = $this->createPermanentFileEntity();

    $this->enableSources(array(
      'reference' => TRUE,
    ));

    // Upload a file by 'Reference' source.
    $name = $this->field_name . '[0][filefield_reference][autocomplete]';
    $edit = array($name => $test_file->getFilename() . ' [fid:' . $test_file->id() . ']');
    $this->drupalPostForm(NULL, $edit, t('Select'));

    // Ensure file is uploaded.
    $this->assertLink($test_file->getFileName());
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Select'), t('After uploading a file, "Select" button is no longer displayed.'));

    // Remove uploaded file.
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure file is removed.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Select'), 'After clicking the "Remove" button, the "Select" button is displayed.');
  }

  /**
   * Test autocompletion.
   */
  function testAutocompletion() {
    // No need to enable 'Reference' source to use autocompletion feature.

    // Create test file.
    // Test file's file name just like 'text-123.txt'.
    $test_file = $this->createPermanentFileEntity();

    // Switch to 'Starts with' match type.
    $this->updateFilefieldSourcesSettings('source_reference', 'autocomplete', 'STARTS_WITH');

    // Test empty results.
    $query = 'e';
    $autocomplete_result = $this->drupalGetJSON('file/reference/node/' . $this->type_name . '/' . $this->field_name, array('query' => array('q' => $query)));
    $this->assertEqual($autocomplete_result, array(), "No files that have name starts with '$query'");

    // Test not empty results.
    $query = 't';
    $autocomplete_result = $this->drupalGetJSON('file/reference/node/' . $this->type_name . '/' . $this->field_name, array('query' => array('q' => $query)));
    $this->assertEqual($autocomplete_result[0]['label'], $test_file->getFileName(), 'Autocompletion return correct label.');
    $this->assertEqual($autocomplete_result[0]['value'], $test_file->getFilename() . ' [fid:' . $test_file->id() . ']', 'Autocompletion return correct value.');

    // Switch to 'Contains' match type.
    $this->updateFilefieldSourcesSettings('source_reference', 'autocomplete', 'CONTAINS');

    // Test empty results.
    $query = 'a';
    $autocomplete_result = $this->drupalGetJSON('file/reference/node/' . $this->type_name . '/' . $this->field_name, array('query' => array('q' => $query)));
    $this->assertEqual($autocomplete_result, array(), "No files that have name contains '$query'");

    // Test not empty results.
    $query = 'x';
    $autocomplete_result = $this->drupalGetJSON('file/reference/node/' . $this->type_name . '/' . $this->field_name, array('query' => array('q' => $query)));
    $this->assertEqual($autocomplete_result[0]['label'], $test_file->getFileName(), 'Autocompletion return correct label.');
    $this->assertEqual($autocomplete_result[0]['value'], $test_file->getFilename() . ' [fid:' . $test_file->id() . ']', 'Autocompletion return correct value.');
  }
}
