<?php

namespace Drupal\Tests\webform_abr_lookup\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\webform_abr_lookup\Service\AbrClientService;
use Drupal\key\Entity\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the ABR client service.
 *
 * @group webform_abr_lookup
 * @coversDefaultClass \Drupal\webform_abr_lookup\Service\AbrClientService
 */
class AbrClientServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
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

    $this->installConfig(['webform_abr_lookup']);
    $this->installEntitySchema('key');

    // Create a test key for ABR authentication
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

    // Configure the module to use our test key
    $this->config('webform_abr_lookup.settings')
      ->set('abr_key_id', 'test_abr_key')
      ->set('abr_base_url', 'https://test.example.com/abrxmlsearch/abrxmlsearch.asmx')
      ->set('cache_duration', 3600)
      ->save();

    // Set up mock HTTP client
    $this->mockHandler = new MockHandler();
    $handler_stack = HandlerStack::create($this->mockHandler);
    $mock_client = new Client(['handler' => $handler_stack]);

    // Replace the HTTP client in our service
    $this->abrClient = new AbrClientService(
      $mock_client,
      $this->container->get('config.factory'),
      $this->container->get('cache.default'),
      $this->container->get('logger.factory'),
      $this->container->get('key.repository')
    );
  }

  /**
   * Test service instantiation and dependency injection.
   */
  public function testServiceInstantiation() {
    $service = $this->container->get('webform_abr_lookup.abr_client');
    $this->assertInstanceOf(AbrClientService::class, $service);
  }

  /**
   * Test configuration loading.
   */
  public function testConfigurationLoading() {
    $config = $this->config('webform_abr_lookup.settings');
    $this->assertEquals('test_abr_key', $config->get('abr_key_id'));
    $this->assertEquals('https://test.example.com/abrxmlsearch/abrxmlsearch.asmx', $config->get('abr_base_url'));
    $this->assertEquals(3600, $config->get('cache_duration'));
  }

  /**
   * Test key integration.
   */
  public function testKeyIntegration() {
    $key_repository = $this->container->get('key.repository');
    $key = $key_repository->getKey('test_abr_key');
    
    $this->assertNotNull($key);
    $this->assertEquals('test_abr_key', $key->id());
    $this->assertEquals('12345678-1234-1234-1234-123456789012', $key->getKeyValue());
  }

  /**
   * Test successful ABN lookup with mocked response.
   */
  public function testSuccessfulAbnLookup() {
    // Mock successful Telstra response
    $mock_response = $this->getTelstraXmlResponse();
    $this->mockHandler->append(new Response(200, [], $mock_response));

    $result = $this->abrClient->searchByAbn('51824753556');

    $this->assertNotNull($result);
    $this->assertEquals('51824753556', $result['abn']);
    $this->assertEquals('TELSTRA CORPORATION LIMITED', $result['main_name']);
    $this->assertEquals('ACT', $result['entity_status']);
    $this->assertEquals('PUB', $result['entity_type']);
    $this->assertEquals('Y', $result['gst_status']);
  }

  /**
   * Test ABN lookup with invalid ABN.
   */
  public function testInvalidAbnLookup() {
    $result = $this->abrClient->searchByAbn('invalid');
    $this->assertNull($result);
  }

  /**
   * Test ABN lookup with empty GUID configuration.
   */
  public function testAbnLookupWithoutGuid() {
    // Remove the key configuration
    $this->config('webform_abr_lookup.settings')
      ->set('abr_key_id', '')
      ->save();

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
  }

  /**
   * Test business name search with mocked response.
   */
  public function testBusinessNameSearch() {
    // Mock successful name search response
    $mock_response = $this->getNameSearchXmlResponse();
    $this->mockHandler->append(new Response(200, [], $mock_response));

    $results = $this->abrClient->searchByName('Telstra', 5);

    $this->assertIsArray($results);
    $this->assertCount(1, $results);
    $this->assertEquals('51824753556', $results[0]['abn']);
    $this->assertEquals('TELSTRA CORPORATION LIMITED', $results[0]['name']);
  }

  /**
   * Test business name search with short query.
   */
  public function testBusinessNameSearchShortQuery() {
    $results = $this->abrClient->searchByName('AB', 5);
    $this->assertIsArray($results);
    $this->assertEmpty($results);
  }

  /**
   * Test caching behavior.
   */
  public function testCaching() {
    $cache = $this->container->get('cache.default');
    
    // Mock successful response
    $mock_response = $this->getTelstraXmlResponse();
    $this->mockHandler->append(new Response(200, [], $mock_response));

    // First call should hit the API
    $result1 = $this->abrClient->searchByAbn('51824753556');
    $this->assertNotNull($result1);

    // Check cache was set
    $cache_key = 'webform_abr_lookup:abn:51824753556';
    $cached = $cache->get($cache_key);
    $this->assertNotFalse($cached);
    $this->assertEquals($result1, $cached->data);

    // Second call should use cache (no additional mock response needed)
    $result2 = $this->abrClient->searchByAbn('51824753556');
    $this->assertEquals($result1, $result2);
  }

  /**
   * Test API error handling.
   */
  public function testApiErrorHandling() {
    // Mock HTTP error response
    $this->mockHandler->append(new Response(500, [], 'Internal Server Error'));

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
  }

  /**
   * Test XML parsing error handling.
   */
  public function testXmlParsingErrorHandling() {
    // Mock invalid XML response
    $this->mockHandler->append(new Response(200, [], 'Invalid XML content'));

    $result = $this->abrClient->searchByAbn('51824753556');
    $this->assertNull($result);
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
    </searchResultsList>
  </response>
</ABRPayloadSearchResults>';
  }

}