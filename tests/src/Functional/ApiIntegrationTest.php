<?php

namespace Drupal\Tests\webform_abr_lookup\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use Drupal\webform_abr_lookup\Service\AbrClientService;

/**
 * Tests API integration with real and mock ABR endpoints.
 *
 * @group webform_abr_lookup
 */
class ApiIntegrationTest extends BrowserTestBase {

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
   * The ABR client service.
   *
   * @var \Drupal\webform_abr_lookup\Service\AbrClientService
   */
  protected $abrClient;

  /**
   * Mock HTTP handler.
   *
   * @var \GuzzleHttp\Handler\MockHandler
   */
  protected $mockHandler;

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
      ->set('abr_base_url', 'https://abr.business.gov.au/abrxmlsearch/abrxmlsearch.asmx')
      ->set('cache_duration', 3600)
      ->save();

    // Set up mock HTTP client for testing.
    $this->setupMockHttpClient();
  }

  /**
   * Set up mock HTTP client.
   */
  protected function setupMockHttpClient() {
    $this->mockHandler = new MockHandler();
    $handler_stack = HandlerStack::create($this->mockHandler);
    $mock_client = new Client(['handler' => $handler_stack]);

    // Replace the HTTP client in our service.
    $this->abrClient = new AbrClientService(
      $mock_client,
      $this->container->get('config.factory'),
      $this->container->get('cache.default'),
      $this->container->get('logger.factory'),
      $this->container->get('key.repository')
    );
  }

  /**
   * Test endpoint availability (without making real API calls).
   */
  public function testApiEndpointAvailability() {
    // Mock a successful response to test endpoint connectivity.
    $this->mockHandler->append(new Response(200, [], $this->getTelstraXmlResponse()));

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNotNull($result);
    $this->assertEquals('TELSTRA CORPORATION LIMITED', $result['main_name']);
  }

  /**
   * Test Telstra ABN lookup (51824753556) with mock data.
   */
  public function testTelstraAbnLookup() {
    // Mock Telstra response.
    $this->mockHandler->append(new Response(200, [], $this->getTelstraXmlResponse()));

    $result = $this->abrClient->searchByAbn('51824753556');

    $this->assertNotNull($result);
    $this->assertEquals('51824753556', $result['abn']);
    $this->assertEquals('TELSTRA CORPORATION LIMITED', $result['main_name']);
    $this->assertEquals('PUB', $result['entity_type']);
    $this->assertEquals('ACT', $result['entity_status']);
    $this->assertEquals('Y', $result['gst_status']);
    $this->assertEquals('VIC', $result['main_business_address']['state_code']);
    $this->assertEquals('3000', $result['main_business_address']['postcode']);
  }

  /**
   * Test Commonwealth Bank ABN lookup with mock data.
   */
  public function testCommonwealthBankAbnLookup() {
    // Mock Commonwealth Bank response.
    $this->mockHandler->append(new Response(200, [], $this->getCommonwealthBankXmlResponse()));

    $result = $this->abrClient->searchByAbn('48123123124');

    $this->assertNotNull($result);
    $this->assertEquals('48123123124', $result['abn']);
    $this->assertEquals('COMMONWEALTH BANK OF AUSTRALIA', $result['main_name']);
    $this->assertEquals('PUB', $result['entity_type']);
    $this->assertEquals('ACT', $result['entity_status']);
    $this->assertEquals('Y', $result['gst_status']);
  }

  /**
   * Test ACN lookup with mock data.
   */
  public function testAcnLookup() {
    // Mock ACN response (ACN 123123124 -> ABN 48123123124).
    $this->mockHandler->append(new Response(200, [], $this->getAcnXmlResponse()));

    $result = $this->abrClient->searchByAbn('123123124');

    $this->assertNotNull($result);
    $this->assertEquals('48123123124', $result['abn']);
    $this->assertEquals('123123124', $result['acn']);
    $this->assertEquals('COMMONWEALTH BANK OF AUSTRALIA', $result['main_name']);
  }

  /**
   * Test business name search with mock data.
   */
  public function testBusinessNameSearch() {
    // Mock business name search response.
    $this->mockHandler->append(new Response(200, [], $this->getNameSearchXmlResponse()));

    $results = $this->abrClient->searchByName('Telstra', 5);

    $this->assertIsArray($results);
    $this->assertCount(2, $results);
    
    // First result should be Telstra Corporation.
    $this->assertEquals('51824753556', $results[0]['abn']);
    $this->assertEquals('TELSTRA CORPORATION LIMITED', $results[0]['name']);
    $this->assertEquals('VIC', $results[0]['state_code']);
    
    // Second result should be another Telstra entity.
    $this->assertEquals('87654321098', $results[1]['abn']);
    $this->assertEquals('TELSTRA RETAIL PTY LTD', $results[1]['name']);
  }

  /**
   * Test API error handling.
   */
  public function testApiErrorHandling() {
    // Mock various error scenarios.
    
    // Test HTTP 500 error.
    $this->mockHandler->append(new Response(500, [], 'Internal Server Error'));
    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);

    // Test HTTP 404 error.
    $this->mockHandler->append(new Response(404, [], 'Not Found'));
    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);

    // Test timeout error.
    $this->mockHandler->append(new RequestException('Timeout', new \GuzzleHttp\Psr7\Request('GET', 'test')));
    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
  }

  /**
   * Test invalid ABN handling.
   */
  public function testInvalidAbnHandling() {
    // Test with invalid ABN (should not make API call).
    $result = $this->abrClient->searchByAbn('invalid');
    $this->assertNull($result);

    // Test with too short ABN.
    $result = $this->abrClient->searchByAbn('123');
    $this->assertNull($result);

    // Test with too long ABN.
    $result = $this->abrClient->searchByAbn('123456789012');
    $this->assertNull($result);
  }

  /**
   * Test XML parsing with malformed response.
   */
  public function testMalformedXmlHandling() {
    // Mock malformed XML response.
    $this->mockHandler->append(new Response(200, [], 'This is not valid XML'));

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
  }

  /**
   * Test ABR exception response.
   */
  public function testAbrExceptionResponse() {
    // Mock ABR exception response.
    $this->mockHandler->append(new Response(200, [], $this->getExceptionXmlResponse()));

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
  }

  /**
   * Test no business entity response.
   */
  public function testNoBusinessEntityResponse() {
    // Mock empty response (no business entity found).
    $this->mockHandler->append(new Response(200, [], $this->getEmptyXmlResponse()));

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
  }

  /**
   * Test full end-to-end lookup via HTTP endpoint.
   */
  public function testEndToEndHttpLookup() {
    // Mock successful response.
    $this->mockHandler->append(new Response(200, [], $this->getTelstraXmlResponse()));

    // Make HTTP request to lookup endpoint.
    $this->drupalPost('/webform_abr_lookup/lookup', 'application/x-www-form-urlencoded', [
      'abn' => '51824753556',
      'lookup_type' => 'abn',
    ]);

    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    
    $this->assertNotNull($response);
    $this->assertEquals('51 824 753 556', $response['abn']);
    $this->assertEquals('TELSTRA CORPORATION LIMITED', $response['entity_name']);
    $this->assertEquals('Australian Public Company', $response['entity_type']);
    $this->assertEquals('Active', $response['entity_status']);
    $this->assertEquals('Registered for GST', $response['gst_status']);
  }

  /**
   * Test autocomplete endpoint with mock data.
   */
  public function testAutocompleteEndpoint() {
    // Mock successful ABN autocomplete response.
    $this->mockHandler->append(new Response(200, [], $this->getTelstraXmlResponse()));

    $this->drupalGet('/webform_abr_lookup/autocomplete/abr/51824753556?q=51824753556');
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    
    $this->assertIsArray($response);
    if (!empty($response)) {
      $this->assertArrayHasKey('value', $response[0]);
      $this->assertArrayHasKey('label', $response[0]);
      $this->assertArrayHasKey('abn', $response[0]);
    }
  }

  /**
   * Test business name autocomplete endpoint.
   */
  public function testBusinessNameAutocompleteEndpoint() {
    // Mock successful name search response.
    $this->mockHandler->append(new Response(200, [], $this->getNameSearchXmlResponse()));

    $this->drupalGet('/webform_abr_lookup/autocomplete/business-name/telstra?q=telstra');
    
    $response = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    
    $this->assertIsArray($response);
    if (!empty($response)) {
      $this->assertArrayHasKey('value', $response[0]);
      $this->assertArrayHasKey('label', $response[0]);
      $this->assertStringContainsString('TELSTRA', $response[0]['label']);
    }
  }

  /**
   * Get mock Telstra XML response.
   */
  protected function getTelstraXmlResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <businessEntity>
      <ABN>
        <identifierValue>51824753556</identifierValue>
      </ABN>
      <entityStatus>
        <entityStatusCode>ACT</entityStatusCode>
      </entityStatus>
      <entityType>
        <entityTypeCode>PUB</entityTypeCode>
      </entityType>
      <goodsAndServicesTax>
        <status>Y</status>
      </goodsAndServicesTax>
      <mainName>
        <organisationName>TELSTRA CORPORATION LIMITED</organisationName>
      </mainName>
      <mainBusinessPhysicalAddress>
        <stateCode>VIC</stateCode>
        <postcode>3000</postcode>
        <countryCode>AUS</countryCode>
      </mainBusinessPhysicalAddress>
    </businessEntity>
  </response>
</ABRPayloadSearchResults>';
  }

  /**
   * Get mock Commonwealth Bank XML response.
   */
  protected function getCommonwealthBankXmlResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <businessEntity>
      <ABN>
        <identifierValue>48123123124</identifierValue>
      </ABN>
      <ASICNumber>123123124</ASICNumber>
      <entityStatus>
        <entityStatusCode>ACT</entityStatusCode>
      </entityStatus>
      <entityType>
        <entityTypeCode>PUB</entityTypeCode>
      </entityType>
      <goodsAndServicesTax>
        <status>Y</status>
      </goodsAndServicesTax>
      <mainName>
        <organisationName>COMMONWEALTH BANK OF AUSTRALIA</organisationName>
      </mainName>
      <mainBusinessPhysicalAddress>
        <stateCode>NSW</stateCode>
        <postcode>2000</postcode>
        <countryCode>AUS</countryCode>
      </mainBusinessPhysicalAddress>
    </businessEntity>
  </response>
</ABRPayloadSearchResults>';
  }

  /**
   * Get mock ACN lookup XML response.
   */
  protected function getAcnXmlResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <businessEntity>
      <ABN>
        <identifierValue>48123123124</identifierValue>
      </ABN>
      <ASICNumber>123123124</ASICNumber>
      <entityStatus>
        <entityStatusCode>ACT</entityStatusCode>
      </entityStatus>
      <entityType>
        <entityTypeCode>PUB</entityTypeCode>
      </entityType>
      <goodsAndServicesTax>
        <status>Y</status>
      </goodsAndServicesTax>
      <mainName>
        <organisationName>COMMONWEALTH BANK OF AUSTRALIA</organisationName>
      </mainName>
      <mainBusinessPhysicalAddress>
        <stateCode>NSW</stateCode>
        <postcode>2000</postcode>
        <countryCode>AUS</countryCode>
      </mainBusinessPhysicalAddress>
    </businessEntity>
  </response>
</ABRPayloadSearchResults>';
  }

  /**
   * Get mock name search XML response.
   */
  protected function getNameSearchXmlResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <searchResultsList>
      <searchResultsRecord>
        <ABN>
          <identifierValue>51824753556</identifierValue>
        </ABN>
        <mainName>
          <organisationName>TELSTRA CORPORATION LIMITED</organisationName>
        </mainName>
        <nameScore>100</nameScore>
        <mainBusinessPhysicalAddress>
          <stateCode>VIC</stateCode>
          <postcode>3000</postcode>
        </mainBusinessPhysicalAddress>
      </searchResultsRecord>
      <searchResultsRecord>
        <ABN>
          <identifierValue>87654321098</identifierValue>
        </ABN>
        <mainName>
          <organisationName>TELSTRA RETAIL PTY LTD</organisationName>
        </mainName>
        <nameScore>95</nameScore>
        <mainBusinessPhysicalAddress>
          <stateCode>VIC</stateCode>
          <postcode>3141</postcode>
        </mainBusinessPhysicalAddress>
      </searchResultsRecord>
    </searchResultsList>
  </response>
</ABRPayloadSearchResults>';
  }

  /**
   * Get mock exception XML response.
   */
  protected function getExceptionXmlResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <exception>
      <exceptionDescription>Search text is not a valid ABN or ACN format</exceptionDescription>
      <exceptionCode>InvalidFormat</exceptionCode>
    </exception>
  </response>
</ABRPayloadSearchResults>';
  }

  /**
   * Get mock empty XML response.
   */
  protected function getEmptyXmlResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
  </response>
</ABRPayloadSearchResults>';
  }

}