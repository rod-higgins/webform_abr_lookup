<?php

namespace Drupal\Tests\webform_abr_lookup\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the autocomplete controller functionality.
 *
 * @group webform_abr_lookup
 */
class AutocompleteTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
  }

  /**
   * Test ABN autocomplete endpoint accessibility.
   */
  public function testAbnAutocompleteAccess() {
    // Test that the endpoint is accessible without authentication.
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'application/json');
  }

  /**
   * Test business name autocomplete endpoint accessibility.
   */
  public function testBusinessNameAutocompleteAccess() {
    // Test that the endpoint is accessible without authentication.
    $this->drupalGet('/webform_abr_lookup/autocomplete/business-name/telstra');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'application/json');
  }

  /**
   * Test lookup endpoint accessibility.
   */
  public function testLookupEndpointAccess() {
    // Test POST access to lookup endpoint.
    $this->drupalPost('/webform_abr_lookup/lookup', 'application/x-www-form-urlencoded', [
      'abn' => '51824753556',
      'lookup_type' => 'abn',
    ]);
    
    // Should be accessible (returns 200 or error response, not 403).
    $status_code = $this->getSession()->getStatusCode();
    $this->assertNotEquals(403, $status_code);
  }

  /**
   * Test ABN autocomplete with invalid input.
   */
  public function testAbnAutocompleteInvalidInput() {
    // Test with too short input.
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/12?q=12');
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEmpty($response);

    // Test with dangerous characters.
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/test?q=<script>alert(1)</script>');
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEmpty($response);
  }

  /**
   * Test business name autocomplete with invalid input.
   */
  public function testBusinessNameAutocompleteInvalidInput() {
    // Test with too short input.
    $this->drupalGet('/webform_abr_lookup/autocomplete/business-name/a?q=a');
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEmpty($response);

    // Test with dangerous characters.
    $this->drupalGet('/webform_abr_lookup/autocomplete/business-name/test?q=<script>alert(1)</script>');
    $this->assertSession()->statusCodeEquals(200);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEmpty($response);
  }

  /**
   * Test lookup endpoint with invalid data.
   */
  public function testLookupEndpointInvalidData() {
    // Test with missing ABN.
    $this->drupalPost('/webform_abr_lookup/lookup', 'application/x-www-form-urlencoded', [
      'lookup_type' => 'abn',
    ]);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('ABN is required', $response['error']);

    // Test with invalid lookup type.
    $this->drupalPost('/webform_abr_lookup/lookup', 'application/x-www-form-urlencoded', [
      'abn' => '51824753556',
      'lookup_type' => 'invalid',
    ]);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('Invalid lookup type', $response['error']);

    // Test with invalid ABN format.
    $this->drupalPost('/webform_abr_lookup/lookup', 'application/x-www-form-urlencoded', [
      'abn' => 'invalid',
      'lookup_type' => 'abn',
    ]);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('Invalid ABN format', $response['error']);
  }

  /**
   * Test rate limiting.
   */
  public function testRateLimiting() {
    // Make many requests to trigger rate limiting.
    for ($i = 0; $i < 70; $i++) {
      $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556?q=51824753556');
    }
    
    // The last request should be rate limited (429 status).
    $this->assertSession()->statusCodeEquals(429);
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertEquals('Rate limit exceeded', $response['error']);
  }

  /**
   * Test JSON response format.
   */
  public function testJsonResponseFormat() {
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556?q=51824753556');
    $this->assertSession()->statusCodeEquals(200);
    
    $content = $this->getSession()->getPage()->getContent();
    $response = json_decode($content, TRUE);
    
    // Should be valid JSON.
    $this->assertNotNull($response);
    $this->assertIsArray($response);
  }

  /**
   * Test that endpoints handle URL encoding properly.
   */
  public function testUrlEncoding() {
    // Test with URL-encoded input.
    $this->drupalGet('/webform_abr_lookup/autocomplete/business-name/test%20company?q=test%20company');
    $this->assertSession()->statusCodeEquals(200);
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($response);
  }

  /**
   * Test CORS headers if any are set.
   */
  public function testCorsHeaders() {
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556?q=51824753556');
    
    // Check that response is JSON.
    $this->assertSession()->responseHeaderContains('Content-Type', 'application/json');
  }

  /**
   * Test endpoint with query parameter variations.
   */
  public function testQueryParameterVariations() {
    // Test without query parameter.
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556');
    $this->assertSession()->statusCodeEquals(200);
    
    // Test with empty query parameter.
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556?q=');
    $this->assertSession()->statusCodeEquals(200);
    
    // Test with different query parameter.
    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556?q=51824753556&other=value');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test method restrictions.
   */
  public function testMethodRestrictions() {
    // ABN autocomplete should only accept GET.
    $this->drupalPost('/webform_abr_lookup/autocomplete/abr/51824753556', 'application/x-www-form-urlencoded', []);
    $this->assertSession()->statusCodeEquals(405); // Method Not Allowed

    // Lookup should only accept POST.
    $this->drupalGet('/webform_abr_lookup/lookup');
    $this->assertSession()->statusCodeEquals(405); // Method Not Allowed
  }

}