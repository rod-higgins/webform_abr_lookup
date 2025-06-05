<?php

namespace Drupal\webform_abr_lookup\Controller;

use Drupal\Core\Controller\ControllerBase;
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
   * Constructs a new AbrAutocompleteController.
   *
   * @param \Drupal\webform_abr_lookup\Service\AbrClientService $abr_client
   *   The ABR client service.
   */
  public function __construct(AbrClientService $abr_client) {
    $this->abrClient = $abr_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform_abr_lookup.abr_client')
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
  public function abrAutocomplete(Request $request, $string) {
    $results = [];
    $input = $request->query->get('q', '');

    if (strlen($input) >= 3) {
      // Clean input (remove spaces and non-numeric characters).
      $clean_input = preg_replace('/[^0-9]/', '', $input);
      
      if (strlen($clean_input) >= 8) {
        // If it looks like an ABN, try to find exact match.
        $business_data = $this->abrClient->searchByAbn($clean_input);
        
        if ($business_data && !empty($business_data['main_name'])) {
          $formatted_abn = AbrClientService::formatAbn($business_data['abn']);
          $results[] = [
            'value' => $formatted_abn,
            'label' => $formatted_abn . ' - ' . $business_data['main_name'],
            'abn' => $business_data['abn'],
            'data' => $business_data,
          ];
        }
      }
      else {
        // Search by partial ABN (not implemented in ABR API, so we skip this).
        // You could implement a local cache/database search here if needed.
      }
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
    $input = $request->query->get('q', '');

    if (strlen($input) >= 3) {
      $search_results = $this->abrClient->searchByName($input, 10);
      
      foreach ($search_results as $business) {
        $formatted_abn = AbrClientService::formatAbn($business['abn']);
        $label = $business['name'] . ' (ABN: ' . $formatted_abn . ')';
        
        if (!empty($business['state_code'])) {
          $label .= ' - ' . $business['state_code'];
        }
        
        $results[] = [
          'value' => $business['name'],
          'label' => $label,
          'abn' => $business['abn'],
          'data' => $business,
        ];
      }
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
    $abn = $request->request->get('abn');
    $lookup_type = $request->request->get('lookup_type', 'abn');

    if (empty($abn)) {
      return new JsonResponse(['error' => 'ABN is required'], 400);
    }

    $business_data = $this->abrClient->searchByAbn($abn);

    if (empty($business_data)) {
      return new JsonResponse(['error' => 'Business not found'], 404);
    }

    // Format the response data.
    $response_data = [
      'abn' => AbrClientService::formatAbn($business_data['abn']),
      'acn' => $business_data['acn'],
      'entity_name' => $business_data['main_name'],
      'entity_type' => $this->getEntityTypeLabel($business_data['entity_type']),
      'entity_status' => $this->getEntityStatusLabel($business_data['entity_status']),
      'gst_status' => $this->getGstStatusLabel($business_data['gst_status']),
      'business_names' => $business_data['business_names'],
      'trading_names' => $business_data['trading_names'],
      'state' => $business_data['main_business_address']['state_code'] ?? '',
      'postcode' => $business_data['main_business_address']['postcode'] ?? '',
    ];

    return new JsonResponse($response_data);
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
      'IND' => 'Individual/Sole Trader',
      'PRV' => 'Australian Private Company',
      'PUB' => 'Australian Public Company',
      'NPF' => 'Not for Profit Organisation',
      'SUP' => 'Superannuation Fund',
      'TRU' => 'Trust',
      'PTN' => 'Partnership',
      'CGE' => 'Commonwealth Government Entity',
      'SGE' => 'State Government Entity',
      'LGE' => 'Local Government Entity',
      'OTH' => 'Other',
    ];

    return $types[$code] ?? $code;
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
      'ACT' => 'Active',
      'CAN' => 'Cancelled',
    ];

    return $statuses[$code] ?? $code;
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
      'Y' => 'Registered for GST',
      'N' => 'Not registered for GST',
    ];

    return $statuses[$code] ?? $code;
  }

}