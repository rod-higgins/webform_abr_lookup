<?php

namespace Drupal\webform_abr_lookup\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for interacting with ABR web services.
 */
class AbrClientService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * ABR XML Search base URL.
   */
  const ABR_BASE_URL = 'https://abr.business.gov.au/abrxmlsearch/abrxmlsearch.asmx';

  /**
   * Constructs a new AbrClientService.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->cache = $cache;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Search by ABN.
   *
   * @param string $abn
   *   The ABN to search for.
   *
   * @return array|null
   *   The search results or NULL on failure.
   */
  public function searchByAbn($abn) {
    $config = $this->configFactory->get('webform_abn_lookup.settings');
    $guid = $config->get('abr_guid');

    if (empty($guid)) {
      $this->loggerFactory->get('webform_abn_lookup')->error('ABR GUID not configured.');
      return NULL;
    }

    // Clean ABN (remove spaces and non-numeric characters).
    $clean_abn = preg_replace('/[^0-9]/', '', $abn);
    
    if (strlen($clean_abn) !== 11) {
      return NULL;
    }

    $cache_key = 'webform_abn_lookup:abn:' . $clean_abn;
    $cached = $this->cache->get($cache_key);
    
    if ($cached && !empty($cached->data)) {
      return $cached->data;
    }

    try {
      $url = self::ABR_BASE_URL . '/SearchByABNv202001';
      $response = $this->httpClient->request('GET', $url, [
        'query' => [
          'searchString' => $clean_abn,
          'includeHistoricalDetails' => 'N',
          'authenticationGuid' => $guid,
        ],
        'timeout' => 10,
      ]);

      $xml_content = $response->getBody()->getContents();
      $xml = simplexml_load_string($xml_content);
      
      if ($xml === FALSE) {
        $this->loggerFactory->get('webform_abn_lookup')->error('Failed to parse XML response for ABN: @abn', ['@abn' => $abn]);
        return NULL;
      }

      $result = $this->parseSearchByAbnResponse($xml);
      
      // Cache for 1 hour.
      $this->cache->set($cache_key, $result, time() + 3600);
      
      return $result;
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('webform_abn_lookup')->error('ABR API request failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Search by business name.
   *
   * @param string $name
   *   The business name to search for.
   * @param int $max_results
   *   Maximum number of results to return.
   *
   * @return array
   *   The search results.
   */
  public function searchByName($name, $max_results = 10) {
    $config = $this->configFactory->get('webform_abn_lookup.settings');
    $guid = $config->get('abr_guid');

    if (empty($guid) || strlen($name) < 3) {
      return [];
    }

    $cache_key = 'webform_abn_lookup:name:' . md5($name . $max_results);
    $cached = $this->cache->get($cache_key);
    
    if ($cached && !empty($cached->data)) {
      return $cached->data;
    }

    try {
      $url = self::ABR_BASE_URL . '/ABRSearchByNameAdvancedSimpleProtocol2017';
      $response = $this->httpClient->request('GET', $url, [
        'query' => [
          'name' => $name,
          'maxSearchResults' => $max_results,
          'searchWidth' => 'typical',
          'minimumScore' => '85',
          'authenticationGuid' => $guid,
        ],
        'timeout' => 10,
      ]);

      $xml_content = $response->getBody()->getContents();
      $xml = simplexml_load_string($xml_content);
      
      if ($xml === FALSE) {
        $this->loggerFactory->get('webform_abn_lookup')->error('Failed to parse XML response for name: @name', ['@name' => $name]);
        return [];
      }

      $results = $this->parseSearchByNameResponse($xml);
      
      // Cache for 30 minutes.
      $this->cache->set($cache_key, $results, time() + 1800);
      
      return $results;
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('webform_abn_lookup')->error('ABR API request failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Parse SearchByABN XML response.
   *
   * @param \SimpleXMLElement $xml
   *   The XML response.
   *
   * @return array|null
   *   Parsed business information.
   */
  protected function parseSearchByAbnResponse(\SimpleXMLElement $xml) {
    $namespace = $xml->getNamespaces(TRUE);
    
    // Check for exceptions.
    if (isset($xml->response->exception)) {
      return NULL;
    }

    $business_entity = $xml->response->businessEntity ?? NULL;
    
    if (empty($business_entity)) {
      return NULL;
    }

    $result = [
      'abn' => (string) $business_entity->ABN->identifierValue ?? '',
      'acn' => (string) $business_entity->ASICNumber ?? '',
      'entity_status' => (string) $business_entity->entityStatus->entityStatusCode ?? '',
      'entity_type' => (string) $business_entity->entityType->entityTypeCode ?? '',
      'gst_status' => (string) $business_entity->goodsAndServicesTax->status ?? '',
      'main_name' => '',
      'business_names' => [],
      'main_trading_name' => '',
      'trading_names' => [],
      'main_business_address' => [],
    ];

    // Get main name.
    if (isset($business_entity->mainName)) {
      $result['main_name'] = (string) $business_entity->mainName->organisationName ?? 
                             (string) $business_entity->mainName->personNameDetails->givenName . ' ' . 
                             (string) $business_entity->mainName->personNameDetails->familyName;
    }

    // Get business names.
    if (isset($business_entity->businessName)) {
      foreach ($business_entity->businessName as $business_name) {
        $result['business_names'][] = (string) $business_name->organisationName;
      }
    }

    // Get main business address.
    if (isset($business_entity->mainBusinessPhysicalAddress)) {
      $address = $business_entity->mainBusinessPhysicalAddress;
      $result['main_business_address'] = [
        'state_code' => (string) $address->stateCode ?? '',
        'postcode' => (string) $address->postcode ?? '',
        'country_code' => (string) $address->countryCode ?? '',
      ];
    }

    return $result;
  }

  /**
   * Parse SearchByName XML response.
   *
   * @param \SimpleXMLElement $xml
   *   The XML response.
   *
   * @return array
   *   Array of search results.
   */
  protected function parseSearchByNameResponse(\SimpleXMLElement $xml) {
    $results = [];

    if (isset($xml->response->searchResultsList->searchResultsRecord)) {
      foreach ($xml->response->searchResultsList->searchResultsRecord as $record) {
        $abn = (string) $record->ABN->identifierValue ?? '';
        $name = (string) $record->mainName->organisationName ?? 
                (string) $record->mainName->personNameDetails->givenName . ' ' . 
                (string) $record->mainName->personNameDetails->familyName;
        
        if (!empty($abn) && !empty($name)) {
          $results[] = [
            'abn' => $abn,
            'name' => $name,
            'score' => (string) $record->nameScore ?? '',
            'state_code' => (string) $record->mainBusinessPhysicalAddress->stateCode ?? '',
            'postcode' => (string) $record->mainBusinessPhysicalAddress->postcode ?? '',
          ];
        }
      }
    }

    return $results;
  }

  /**
   * Format ABN with spaces.
   *
   * @param string $abn
   *   The ABN to format.
   *
   * @return string
   *   Formatted ABN.
   */
  public static function formatAbn($abn) {
    $clean_abn = preg_replace('/[^0-9]/', '', $abn);
    
    if (strlen($clean_abn) === 11) {
      return substr($clean_abn, 0, 2) . ' ' . 
             substr($clean_abn, 2, 3) . ' ' . 
             substr($clean_abn, 5, 3) . ' ' . 
             substr($clean_abn, 8, 3);
    }
    
    return $abn;
  }

  /**
   * Validate ABN using the standard algorithm.
   *
   * @param string $abn
   *   The ABN to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public static function validateAbn($abn) {
    $clean_abn = preg_replace('/[^0-9]/', '', $abn);
    
    if (strlen($clean_abn) !== 11) {
      return FALSE;
    }

    $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
    $sum = 0;

    // Subtract 1 from the first digit.
    $digits = str_split($clean_abn);
    $digits[0] = $digits[0] - 1;

    for ($i = 0; $i < 11; $i++) {
      $sum += $digits[$i] * $weights[$i];
    }

    return ($sum % 89) === 0;
  }

}