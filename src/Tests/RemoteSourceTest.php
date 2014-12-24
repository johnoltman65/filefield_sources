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
    $this->enableSources(array(
      'remote' => TRUE,
    ));

    // Upload a file by 'Remote' source.
    $name = $this->field_name . '[0][filefield_remote][url]';
    $edit = array($name => 'https://www.drupal.org/README.txt');
    $this->drupalPostForm(NULL, $edit, t('Transfer'));

    // Ensure file is uploaded.
    $this->assertLink('README.txt');
    $this->assertFieldByXPath('//input[@type="submit"]', t('Remove'), t('After uploading a file, "Remove" button displayed.'));
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Transfer'), t('After uploading a file, "Transfer" button is no longer displayed.'));

    // Remove uploaded file.
    $remove_button = $this->xpath('//input[@type="submit" and @value="' . t('Remove') . '"]');
    $this->drupalPostAjaxForm(NULL, array(), array((string) $remove_button[0]['name'] => (string) $remove_button[0]['value']));

    // Ensure file is removed.
    $this->assertNoFieldByXPath('//input[@type="submit"]', t('Remove'), 'After clicking the "Remove" button, it is no longer displayed.');
    $this->assertFieldByXpath('//input[@type="submit"]', t('Transfer'), 'After clicking the "Remove" button, the "Transfer" button is displayed.');
  }
}
