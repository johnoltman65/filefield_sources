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
   * Tests move file from relative path. Default settings:
   * Move file from public://file_attach to public://
   */
  function testMoveFileFromRelativePath() {
    // Create test file.
    $path = file_default_scheme()  . '://' . FILEFIELD_SOURCE_ATTACH_DEFAULT_PATH;
    $file = $this->createTemporaryFile($path);
    $dest_uri = file_default_scheme() . '://' . $file->filename;

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
    $this->drupalPostForm(NULL, $edit, t('Attach'));

    // Ensure option no longer exists.
    $options = $this->xpath('//select[@name=:name]/option[@value=:option]', array(':name' => $this->field_name . '[0][filefield_attach][filename]', ':option' => $file->uri));
    $this->assertFalse(isset($options[0]), 'File option no longer exists.');

    // Ensure file is uploaded.
    $this->assertLink($file->filename);
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Attach'), t('After uploading a file, "Attach" button is no longer displayed.'));

    // Ensure file is moved.
    $this->assertFalse(is_file($file->uri), 'Source file has been removed.');
    $this->assertTrue(is_file($dest_uri), 'Destination file has been created.');

    // Remove uploaded file.
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure empty message exists.
    $this->assertText('There currently are no files to attach.', "'No files to attach' message exists.");

    // Ensure file is removed.
    $this->assertNoLink($file->filename);
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Attach'), 'After clicking the "Remove" button, the "Attach" button is displayed.');
  }

  /**
   * Tests copy file from absolute path.
   * Copy file from sites/default/files/custom_file_attach to public://
   */
  function testCopyFileFromAbsolutePath() {
    // Calculate custom absolute path.
    $path = drupal_realpath(file_default_scheme() . '://');
    $path = str_replace(realpath('./'), '', $path);
    $path = ltrim($path, '/');
    $path = $path . '/custom_file_attach';

    // Create test file.
    $file = $this->createTemporaryFile($path);
    $dest_uri = file_default_scheme() . '://' . $file->filename;

    // Change settings.
    $this->updateFilefieldSourcesSettings('source_attach', 'path', $path);
    $this->updateFilefieldSourcesSettings('source_attach', 'absolute', FILEFIELD_SOURCE_ATTACH_ABSOLUTE);
    $this->updateFilefieldSourcesSettings('source_attach', 'attach_mode', FILEFIELD_SOURCE_ATTACH_MODE_COPY);

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
    $this->drupalPostForm(NULL, $edit, t('Attach'));

    // Ensure file is uploaded.
    $this->assertLink($file->filename);
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Attach'), t('After uploading a file, "Attach" button is no longer displayed.'));

    // Ensure file is copied.
    $this->assertTrue(is_file($file->uri), 'Source file still exists.');
    $this->assertTrue(is_file($dest_uri), 'Destination file has been created.');

    // Remove uploaded file.
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure there are no files to attach.
    $this->assertNoText('There currently are no files to attach.', "'No files to attach' message does not exist.");

    // Ensure file is removed.
    $this->assertNoLink($file->filename);
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Attach'), 'After clicking the "Remove" button, the "Attach" button is displayed.');
  }
}
