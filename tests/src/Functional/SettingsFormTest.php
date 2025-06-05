<?php

namespace Drupal\Tests\webform_abr_lookup\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;

/**
 * Tests the settings form functionality.
 *
 * @group webform_abr_lookup
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'key',
    'webform',
    'webform_abr_lookup',
  ];

  /**
   * A user with administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer webform',
      'administer keys',
    ]);

    // Create a test key for ABR authentication.
    $key = Key::create([
      'id' => 'test_abr_key',
      'label' => 'Test ABR Key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => [
        'key_value' => '12345678-1234-1234-1234-123456789012',
      ],
    ]);
    $key->save();
  }

  /**
   * Test access to settings form.
   */
  public function testSettingsFormAccess() {
    // Anonymous users should not have access.
    $this->drupalGet('/admin/config/webform/abr-lookup');
    $this->assertSession()->statusCodeEquals(403);

    // Admin users should have access.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('ABN Lookup Settings');
  }

  /**
   * Test settings form elements.
   */
  public function testSettingsFormElements() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    // Check that all form elements are present.
    $this->assertSession()->fieldExists('abr_key_id');
    $this->assertSession()->fieldExists('abr_base_url');
    $this->assertSession()->fieldExists('cache_duration');
    $this->assertSession()->fieldExists('default_lookup_type');
    $this->assertSession()->fieldExists('default_auto_populate');
    $this->assertSession()->fieldExists('default_show_details');
    $this->assertSession()->fieldExists('test_abn');
    $this->assertSession()->buttonExists('Test Connection');

    // Check that our test key is available in the dropdown.
    $this->assertSession()->optionExists('abr_key_id', 'test_abr_key');

    // Check default values.
    $this->assertSession()->fieldValueEquals('abr_base_url', 'https://abr.business.gov.au/abrxmlsearch/abrxmlsearch.asmx');
    $this->assertSession()->fieldValueEquals('cache_duration', '3600');
    $this->assertSession()->fieldValueEquals('default_lookup_type', 'abn');
  }

  /**
   * Test saving settings form.
   */
  public function testSaveSettingsForm() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    // Submit the form with our test key.
    $this->submitForm([
      'abr_key_id' => 'test_abr_key',
      'abr_base_url' => 'https://test.example.com/abrxmlsearch/abrxmlsearch.asmx',
      'cache_duration' => '7200',
      'default_lookup_type' => 'name',
      'default_auto_populate' => FALSE,
      'default_show_details' => FALSE,
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration has been saved.');

    // Verify the configuration was saved.
    $config = $this->config('webform_abr_lookup.settings');
    $this->assertEquals('test_abr_key', $config->get('abr_key_id'));
    $this->assertEquals('https://test.example.com/abrxmlsearch/abrxmlsearch.asmx', $config->get('abr_base_url'));
    $this->assertEquals(7200, $config->get('cache_duration'));
    $this->assertEquals('name', $config->get('default_lookup_type'));
    $this->assertFalse($config->get('default_auto_populate'));
    $this->assertFalse($config->get('default_show_details'));
  }

  /**
   * Test form validation.
   */
  public function testFormValidation() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    // Test with invalid URL.
    $this->submitForm([
      'abr_key_id' => 'test_abr_key',
      'abr_base_url' => 'not-a-valid-url',
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('Please enter a valid URL for the ABR API base URL.');

    // Test with non-existent key.
    $this->submitForm([
      'abr_key_id' => 'non_existent_key',
      'abr_base_url' => 'https://example.com',
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('Selected key does not exist.');
  }

  /**
   * Test key validation with invalid GUID format.
   */
  public function testInvalidGuidValidation() {
    // Create a key with invalid GUID format.
    $invalid_key = Key::create([
      'id' => 'invalid_guid_key',
      'label' => 'Invalid GUID Key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => [
        'key_value' => 'invalid-guid-format',
      ],
    ]);
    $invalid_key->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    $this->submitForm([
      'abr_key_id' => 'invalid_guid_key',
      'abr_base_url' => 'https://example.com',
    ], 'Save configuration');

    $this->assertSession()->pageTextContains('Selected key does not contain a valid GUID format');
  }

  /**
   * Test the configuration form help text.
   */
  public function testFormHelpText() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    // Check that help text is present.
    $this->assertSession()->pageTextContains('register for free access at');
    $this->assertSession()->pageTextContains('abr.business.gov.au');
    $this->assertSession()->pageTextContains('Create a new key at');
    $this->assertSession()->pageTextContains('How long to cache ABR lookup results');
    $this->assertSession()->pageTextContains('The default lookup type for new ABN lookup elements');
  }

  /**
   * Test empty form submission.
   */
  public function testEmptyFormSubmission() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    // Submit form without required key selection.
    $this->submitForm([
      'abr_key_id' => '',
      'abr_base_url' => 'https://example.com',
    ], 'Save configuration');

    // Should show required field error.
    $this->assertSession()->pageTextContains('ABR Authentication Key field is required.');
  }

  /**
   * Test that the form shows all available cache duration options.
   */
  public function testCacheDurationOptions() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    $expected_options = [
      '300' => '5 minutes',
      '900' => '15 minutes',
      '1800' => '30 minutes',
      '3600' => '1 hour',
      '7200' => '2 hours',
      '14400' => '4 hours',
      '28800' => '8 hours',
      '86400' => '24 hours',
    ];

    foreach ($expected_options as $value => $label) {
      $this->assertSession()->optionExists('cache_duration', $value);
    }
  }

  /**
   * Test that lookup type options are available.
   */
  public function testLookupTypeOptions() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/webform/abr-lookup');

    $this->assertSession()->optionExists('default_lookup_type', 'abn');
    $this->assertSession()->optionExists('default_lookup_type', 'acn');
    $this->assertSession()->optionExists('default_lookup_type', 'name');
  }

}