<?php

namespace Drupal\webform_abr_lookup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\webform_abr_lookup\Service\AbrClientService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for ABN lookup autocomplete functionality.
 */
class AbrAutocompleteController extends ControllerBase {

  /**
   * The ABR client service.
   *
   * @var \Drupal\webform_abr_lookup\Service\AbrClientService
   */
  protected $abrClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Maximum requests per minute per IP.
   */
  const RATE_LIMIT_PER_MINUTE = 60;

  /**
   * Constructs a new AbrAutocompleteController.
   *
   * @param \Drupal\webform_abr_lookup\Service\AbrClientService $abr_client
   *   The ABR client service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(AbrClientService $abr_client, CacheBackendInterface $cache) {
    $this->abrClient = $abr_client;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform_abr_lookup.abr_client'),
      $container->get('cache.default')
    );
  }

  /**
   * Autocomplete callback for ABN search.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $string
   *   The search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function abnAutocomplete(Request $request, $string) {
    $results = [];
    $input = trim($request->query->get('q', ''));

    // Input validation and sanitization.
    if (!$this->validateInput($input)) {
      return new JsonResponse($results);
    }

    // Check rate limiting.
    if (!$this->checkRateLimit('abr_autocomplete')) {
      return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
    }

    // Clean input (remove spaces and non-numeric characters).
    $clean_input = preg_replace('/[^0-9]/', '', $input);
    
    if (strlen($clean_input) >= 8) {
      // If it looks like an ABN, try to find exact match.
      try {
        $business_data = $this->abrClient->searchByAbn($clean_input);
        
        if ($business_data && !empty($business_data['main_name'])) {
          $formatted_abn = AbrClientService::formatAbn($business_data['abn']);
          $results[] = [
            'value' => $formatted_abn,
            'label' => $this->sanitizeOutput($formatted_abn . ' - ' . $business_data['main_name']),
            'abn' => $business_data['abn'],
            'data' => $this->sanitizeBusinessData($business_data),
          ];
        }
      }
      catch (\Exception $e) {
        $this->getLogger('webform_abr_lookup')
          ->error('ABN autocomplete search failed for input @input: @message', [
            '@input' => $input,
            '@message' => $e->getMessage(),
          ]);
      }
    }
    else {
      // For partial ABN searches (less than 8 digits), we could implement
      // a local cache search here if needed in the future.
    }

    return new JsonResponse($results);
  }

  /**
   * Autocomplete callback for business name search.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $string
   *   The search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function businessNameAutocomplete(Request $request, $string) {
    $results = [];
    $input = trim($request->query->get('q', ''));

    // Input validation and sanitization.
    if (!$this->validateInput($input, 'name')) {
      return new JsonResponse($results);
    }

    // Check rate limiting.
    if (!$this->checkRateLimit('name_autocomplete')) {
      return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
    }

    try {
      $search_results = $this->abrClient->searchByName($input, 10);
      
      foreach ($search_results as $business) {
        $formatted_abn = AbrClientService::formatAbn($business['abn']);
        $label = $this->sanitizeOutput($business['name']) . ' (ABN: ' . $formatted_abn . ')';
        
        if (!empty($business['state_code'])) {
          $label .= ' - ' . $this->sanitizeOutput($business['state_code']);
        }
        
        $results[] = [
          'value' => $this->sanitizeOutput($business['name']),
          'label' => $label,
          'abn' => $business['abn'],
          'data' => $this->sanitizeBusinessData($business),
        ];
      }
    }
    catch (\Exception $e) {
      $this->getLogger('webform_abr_lookup')
        ->error('Business name autocomplete search failed for input @input: @message', [
          '@input' => $input,
          '@message' => $e->getMessage(),
        ]);
    }

    return new JsonResponse($results);
  }

  /**
   * AJAX callback for detailed business lookup.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function lookup(Request $request) {
    $abn = trim($request->request->get('abn', ''));
    $lookup_type = $request->request->get('lookup_type', 'abn');

    // Validate input.
    if (empty($abn)) {
      return new JsonResponse(['error' => 'ABN is required'], 400);
    }

    if (!$this->validateInput($abn, 'abn')) {
      return new JsonResponse(['error' => 'Invalid ABN format'], 400);
    }

    if (!in_array($lookup_type, ['abn', 'acn', 'name'])) {
      return new JsonResponse(['error' => 'Invalid lookup type'], 400);
    }

    // Check rate limiting.
    if (!$this->checkRateLimit('abr_lookup')) {
      return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
    }

    try {
      $business_data = $this->abrClient->searchByAbn($abn);

      if (empty($business_data)) {
        return new JsonResponse(['error' => 'Business not found'], 404);
      }

      // Format the response data with sanitization.
      $response_data = [
        'abn' => AbrClientService::formatAbn($business_data['abn']),
        'acn' => $this->sanitizeOutput($business_data['acn'] ?? ''),
        'entity_name' => $this->sanitizeOutput($business_data['main_name'] ?? ''),
        'entity_type' => $this->getEntityTypeLabel($business_data['entity_type'] ?? ''),
        'entity_status' => $this->getEntityStatusLabel($business_data['entity_status'] ?? ''),
        'gst_status' => $this->getGstStatusLabel($business_data['gst_status'] ?? ''),
        'business_names' => array_map([$this, 'sanitizeOutput'], $business_data['business_names'] ?? []),
        'trading_names' => array_map([$this, 'sanitizeOutput'], $business_data['trading_names'] ?? []),
        'state' => $this->sanitizeOutput($business_data['main_business_address']['state_code'] ?? ''),
        'postcode' => $this->sanitizeOutput($business_data['main_business_address']['postcode'] ?? ''),
      ];

      return new JsonResponse($response_data);
    }
    catch (\Exception $e) {
      $this->getLogger('webform_abr_lookup')
        ->error('Business lookup failed for ABN @abn: @message', [
          '@abn' => $abn,
          '@message' => $e->getMessage(),
        ]);
      
      return new JsonResponse(['error' => 'Lookup service temporarily unavailable'], 503);
    }
  }

  /**
   * Validate input parameters.
   *
   * @param string $input
   *   The input to validate.
   * @param string $type
   *   The input type (abn, acn, name).
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  protected function validateInput($input, $type = 'abn') {
    // Basic length and content checks.
    if (strlen($input) < 2 || strlen($input) > 100) {
      return FALSE;
    }

    // Remove dangerous characters.
    if (preg_match('/[<>"\']/', $input)) {
      return FALSE;
    }

    switch ($type) {
      case 'abn':
      case 'acn':
        // For ABN/ACN, allow only numbers, spaces, and basic punctuation.
        return preg_match('/^[0-9\s\-\.]+$/', $input);

      case 'name':
        // For business names, allow alphanumeric, spaces, and common business punctuation.
        return preg_match('/^[a-zA-Z0-9\s\-\.\,\&\(\)\']+$/u', $input);

      default:
        return FALSE;
    }
  }

  /**
   * Check rate limiting for the current IP address.
   *
   * @param string $operation
   *   The operation being performed.
   *
   * @return bool
   *   TRUE if within rate limit, FALSE otherwise.
   */
  protected function checkRateLimit($operation) {
    $request = \Drupal::request();
    $ip = $request->getClientIp();
    
    // Skip rate limiting for local development.
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
      return TRUE;
    }

    $cache_key = 'webform_abr_lookup:rate_limit:' . $operation . ':' . $ip;
    $cached = $this->cache->get($cache_key);
    
    $current_count = $cached ? $cached->data + 1 : 1;
    
    if ($current_count > self::RATE_LIMIT_PER_MINUTE) {
      $this->getLogger('webform_abr_lookup')
        ->warning('Rate limit exceeded for IP @ip on operation @operation', [
          '@ip' => $ip,
          '@operation' => $operation,
        ]);
      return FALSE;
    }

    // Set cache for 1 minute.
    $this->cache->set($cache_key, $current_count, time() + 60);
    
    return TRUE;
  }

  /**
   * Sanitize output data to prevent XSS.
   *
   * @param string $text
   *   The text to sanitize.
   *
   * @return string
   *   The sanitized text.
   */
  protected function sanitizeOutput($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /**
   * Sanitize business data array.
   *
   * @param array $data
   *   The business data to sanitize.
   *
   * @return array
   *   The sanitized business data.
   */
  protected function sanitizeBusinessData(array $data) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $sanitized[$key] = array_map([$this, 'sanitizeOutput'], $value);
      }
      elseif (is_string($value)) {
        $sanitized[$key] = $this->sanitizeOutput($value);
      }
      else {
        $sanitized[$key] = $value;
      }
    }
    
    return $sanitized;
  }

  /**
   * Get human-readable entity type label.
   *
   * @param string $code
   *   The entity type code.
   *
   * @return string
   *   The entity type label.
   */
  protected function getEntityTypeLabel($code) {
    $types = [
      'IND' => $this->t('Individual/Sole Trader'),
      'PRV' => $this->t('Australian Private Company'),
      'PUB' => $this->t('Australian Public Company'),
      'NPF' => $this->t('Not for Profit Organisation'),
      'SUP' => $this->t('Superannuation Fund'),
      'TRU' => $this->t('Trust'),
      'PTN' => $this->t('Partnership'),
      'CGE' => $this->t('Commonwealth Government Entity'),
      'SGE' => $this->t('State Government Entity'),
      'LGE' => $this->t('Local Government Entity'),
      'OTH' => $this->t('Other'),
    ];

    return isset($types[$code]) ? (string) $types[$code] : $this->sanitizeOutput($code);
  }

  /**
   * Get human-readable entity status label.
   *
   * @param string $code
   *   The entity status code.
   *
   * @return string
   *   The entity status label.
   */
  protected function getEntityStatusLabel($code) {
    $statuses = [
      'ACT' => $this->t('Active'),
      'CAN' => $this->t('Cancelled'),
    ];

    return isset($statuses[$code]) ? (string) $statuses[$code] : $this->sanitizeOutput($code);
  }

  /**
   * Get human-readable GST status label.
   *
   * @param string $code
   *   The GST status code.
   *
   * @return string
   *   The GST status label.
   */
  protected function getGstStatusLabel($code) {
    $statuses = [
      'Y' => $this->t('Registered for GST'),
      'N' => $this->t('Not registered for GST'),
    ];

    return isset($statuses[$code]) ? (string) $statuses[$code] : $this->sanitizeOutput($code);
  }

}