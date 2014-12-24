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

    // Create test file.
    $test_file = $this->createPermanentFile();

    // Test autocompletion.
    $autocomplete_result = $this->drupalGetJSON('file/reference/node/' . $this->type_name . '/' . $this->field_name, array('query' => array('q' => $test_file->getFileName())));
    $this->assertEqual($autocomplete_result[0]['label'], $test_file->getFileName(), 'Autocompletion has been returned correct result.');

    $this->enableSources(array(
      'reference' => TRUE,
    ));

    // Ensure autocomplete textbox exists.
    $result = $this->xpath('//input[@name="' . $this->field_name . '[0][filefield_reference][autocomplete]" and contains(@data-autocomplete-path, "/file/reference/node/' . $this->type_name . '/' . $this->field_name . '")]');
    $this->assertEqual(count($result), 1, 'There is a textbox with autocompletion.');

    // Upload a file by 'Reference' source.
    $name = $this->field_name . '[0][filefield_reference][autocomplete]';
    $edit = array($name => $autocomplete_result[0]['value']);
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
}
