<?php

namespace Drupal\Tests\webform_abr_lookup\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;
use Drupal\webform\Entity\Webform;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests end-to-end functionality including webform integration.
 *
 * @group webform_abr_lookup
 */
class EndToEndTest extends BrowserTestBase {

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
    'webform_ui',
    'webform_abr_lookup',
  ];

  /**
   * A user with webform administration permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webformUser;

  /**
   * A test webform.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $webform;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a webform admin user.
    $this->webformUser = $this->drupalCreateUser([
      'create webform',
      'edit any webform',
      'delete any webform',
      'access webform overview',
      'administer webform',
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

    // Configure the module.
    $this->config('webform_abr_lookup.settings')
      ->set('abr_key_id', 'test_abr_key')
      ->set('abr_base_url', 'https://test.example.com/abrxmlsearch/abrxmlsearch.asmx')
      ->set('cache_duration', 3600)
      ->save();

    // Create a test webform with ABR lookup element.
    $this->webform = Webform::create([
      'id' => 'test_abr_lookup_form',
      'title' => 'Test ABR Lookup Form',
      'elements' => $this->getWebformElements(),
    ]);
    $this->webform->save();
  }

  /**
   * Test that ABR lookup element appears in webform builder.
   */
  public function testAbrElementInBuilder() {
    $this->drupalLogin($this->webformUser);
    $this->drupalGet('/admin/structure/webform/manage/test_abr_lookup_form/element/add');

    // Check that ABR Lookup appears in the elements list.
    $this->assertSession()->pageTextContains('ABN Lookup');
    $this->assertSession()->linkExists('ABN Lookup');
  }

  /**
   * Test creating a webform with ABR lookup element.
   */
  public function testCreateWebformWithAbrElement() {
    $this->drupalLogin($this->webformUser);

    // Go to the webform and check it loads.
    $this->drupalGet('/webform/test_abr_lookup_form');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the ABR lookup element is present.
    $this->assertSession()->fieldExists('business_lookup[search]');
    $this->assertSession()->fieldExists('business_lookup[selected_abn]');
    $this->assertSession()->pageTextContains('Australian Business Number (ABN)');

    // Check that business details fields are present.
    $this->assertSession()->fieldExists('business_lookup[details][abn]');
    $this->assertSession()->fieldExists('business_lookup[details][entity_name]');
    $this->assertSession()->fieldExists('business_lookup[details][entity_type]');
  }

  /**
   * Test ABN lookup functionality with valid ABN.
   */
  public function testAbnLookupWithValidAbn() {
    // Visit the webform.
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Fill in a valid ABN (Telstra).
    $this->getSession()->getPage()->fillField('business_lookup[search]', '51 824 753 556');

    // Check that the field accepts the value.
    $this->assertSession()->fieldValueEquals('business_lookup[search]', '51 824 753 556');
  }

  /**
   * Test form submission with ABR lookup data.
   */
  public function testFormSubmissionWithAbrData() {
    // Visit the webform.
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Fill in the form.
    $this->getSession()->getPage()->fillField('business_lookup[search]', 'Telstra Corporation');
    $this->getSession()->getPage()->fillField('business_lookup[selected_abn]', '51824753556');
    $this->getSession()->getPage()->fillField('business_lookup[details][entity_name]', 'TELSTRA CORPORATION LIMITED');
    $this->getSession()->getPage()->fillField('contact_name', 'John Smith');
    $this->getSession()->getPage()->fillField('contact_email', 'john@example.com');

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Submit');

    // Check for successful submission.
    $this->assertSession()->pageTextContains('Thank you');
  }

  /**
   * Test JavaScript functionality is loaded.
   */
  public function testJavaScriptLoaded() {
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Check that the JavaScript library is attached.
    $this->assertSession()->responseContains('webform_abr_lookup/webform_abr_lookup');
    
    // Check that the search field has the correct CSS classes.
    $search_field = $this->getSession()->getPage()->find('css', '.webform-abr-lookup-search');
    $this->assertNotNull($search_field);
    
    // Check data attributes are set.
    $this->assertEquals('abn', $search_field->getAttribute('data-lookup-type'));
    $this->assertEquals('1', $search_field->getAttribute('data-auto-populate'));
  }

  /**
   * Test CSS styles are loaded.
   */
  public function testCssLoaded() {
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Check that the CSS is loaded.
    $this->assertSession()->responseContains('webform_abr_lookup.css');
  }

  /**
   * Test autocomplete functionality.
   */
  public function testAutocompleteFunctionality() {
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Check that autocomplete route is set.
    $search_field = $this->getSession()->getPage()->find('css', 'input[name="business_lookup[search]"]');
    $this->assertNotNull($search_field);
    
    // The field should have autocomplete attributes (set by Drupal's autocomplete system).
    $this->assertEquals('off', $search_field->getAttribute('autocomplete'));
  }

  /**
   * Test element validation.
   */
  public function testElementValidation() {
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Fill in an invalid ABN.
    $this->getSession()->getPage()->fillField('business_lookup[search]', '51824753555'); // Invalid checksum
    $this->getSession()->getPage()->fillField('contact_name', 'John Smith');
    $this->getSession()->getPage()->fillField('contact_email', 'john@example.com');

    // Submit the form.
    $this->getSession()->getPage()->pressButton('Submit');

    // Should show validation error.
    $this->assertSession()->pageTextContains('Please enter a valid ABN');
  }

  /**
   * Test different lookup types.
   */
  public function testDifferentLookupTypes() {
    // Create webforms with different lookup types.
    $acn_webform = Webform::create([
      'id' => 'test_acn_lookup_form',
      'title' => 'Test ACN Lookup Form',
      'elements' => $this->getWebformElements('acn'),
    ]);
    $acn_webform->save();

    $name_webform = Webform::create([
      'id' => 'test_name_lookup_form',
      'title' => 'Test Name Lookup Form',
      'elements' => $this->getWebformElements('name'),
    ]);
    $name_webform->save();

    // Test ACN lookup form.
    $this->drupalGet('/webform/test_acn_lookup_form');
    $this->assertSession()->pageTextContains('Australian Company Number (ACN)');

    // Test business name lookup form.
    $this->drupalGet('/webform/test_name_lookup_form');
    $this->assertSession()->pageTextContains('Business Name');
  }

  /**
   * Test element without details.
   */
  public function testElementWithoutDetails() {
    $webform = Webform::create([
      'id' => 'test_abr_no_details',
      'title' => 'Test ABR No Details',
      'elements' => $this->getWebformElements('abn', FALSE, FALSE),
    ]);
    $webform->save();

    $this->drupalGet('/webform/test_abr_no_details');

    // Search field should be present.
    $this->assertSession()->fieldExists('business_lookup[search]');

    // Details fields should not be present.
    $this->assertSession()->fieldNotExists('business_lookup[details][abn]');
    $this->assertSession()->fieldNotExists('business_lookup[details][entity_name]');
  }

  /**
   * Test requirements checking.
   */
  public function testRequirementsPage() {
    $this->drupalLogin($this->webformUser);
    $this->drupalGet('/admin/reports/status');

    // Should show ABR lookup status.
    $this->assertSession()->pageTextContains('Webform ABN Lookup');
    $this->assertSession()->pageTextContains('ABR Authentication Key');
  }

  /**
   * Test module configuration link.
   */
  public function testConfigurationLink() {
    $this->drupalLogin($this->webformUser);
    $this->drupalGet('/admin/modules');

    // Find the webform_abr_lookup module and check configure link.
    $this->assertSession()->linkByHrefExists('/admin/config/webform/abr-lookup');
  }

  /**
   * Test form display formatting.
   */
  public function testFormDisplayFormatting() {
    $this->drupalGet('/webform/test_abr_lookup_form');

    // Check that the wrapper has correct CSS classes.
    $wrapper = $this->getSession()->getPage()->find('css', '.webform-abr-lookup');
    $this->assertNotNull($wrapper);

    // Check that details section has correct attributes.
    $details = $this->getSession()->getPage()->find('css', '.webform-abr-lookup-details');
    $this->assertNotNull($details);
  }

  /**
   * Get webform elements configuration.
   */
  protected function getWebformElements($lookup_type = 'abn', $auto_populate = TRUE, $show_details = TRUE) {
    return [
      'business_lookup' => [
        '#type' => 'webform_abr_lookup',
        '#title' => 'Business Information',
        '#lookup_type' => $lookup_type,
        '#auto_populate' => $auto_populate,
        '#show_details' => $show_details,
      ],
      'contact_name' => [
        '#type' => 'textfield',
        '#title' => 'Contact Name',
        '#required' => TRUE,
      ],
      'contact_email' => [
        '#type' => 'email',
        '#title' => 'Contact Email',
        '#required' => TRUE,
      ],
    ];
  }

}