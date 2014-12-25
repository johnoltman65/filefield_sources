<?php

/**
 * @file
 * Definition of Drupal\filefield_sources\Tests\AttachSourceTest.
 */

namespace Drupal\filefield_sources\Tests;

/**
 * Tests the attach source.
 *
 * @group filefield_sources
 */
class AttachSourceTest extends FileFieldSourcesTestBase {

  /**
   * Tests attach source enabled.
   * Default settings: Move file from default-schema://file_attach to default-schema://
   */
  function testAttachSourceEnabled() {
    $path = file_default_scheme()  . '://file_attach';
    $file = $this->createTemporaryFile($path);
    $dest_uri = file_default_scheme() . '://' . $file->file_name;

    $this->enableSources(array(
      'attach' => TRUE,
    ));

    // Ensure option exists.
    $options = $this->xpath('//select[@name=:name]/option[@value=:option]', array(':name' => $this->field_name . '[0][filefield_attach][filename]', ':option' => $file->uri));
    $this->assertTrue(isset($options[0]), 'File option exists.');

    // Upload a file by 'Attach' source.
    $edit = array(
      $this->field_name . '[0][filefield_attach][filename]' => $file->uri
    );
    $upload_button = $this->xpath('//input[@type="submit" and @value="' . t('Attach') . '"]');
    $this->drupalPostAjaxForm(NULL, $edit, array((string) $upload_button[0]['name'] => (string) $upload_button[0]['value']));

    // Ensure option no longer exists.
    $options = $this->xpath('//select[@name=:name]/option[@value=:option]', array(':name' => $this->field_name . '[0][filefield_attach][filename]', ':option' => $file->uri));
    $this->assertFalse(isset($options[0]), 'File option no longer exists.');

    // Ensure file is uploaded.
    $this->assertLink($file->file_name);
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Attach'), t('After uploading a file, "Attach" button is no longer displayed.'));

    // Ensure file is moved.
    $this->assertFalse(is_file($file->uri), 'Source file has been removed');
    $this->assertTrue(is_file($dest_uri), 'Destination file has been created');

    // Remove uploaded file.
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure there are no files to attach.
    $this->assertText('There currently are no files to attach.', 'There are no files to attach.');

    // Ensure file is removed.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertNoFieldByXpath('//input[@type="submit"]', t('Attach'), 'The "Attach" button is not displayed.');
  }
}
