<?php

namespace Drupal\webform_abr_lookup\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\webform_abr_lookup\Service\AbrClientService;

/**
 * Provides a webform element for ABN/ACN lookup.
 *
 * @FormElement("webform_abr_lookup")
 */
class WebformAbrLookup extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processWebformAbnLookup'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateWebformAbnLookup'],
      ],
      '#theme_wrappers' => ['container'],
      '#tree' => TRUE,
      '#lookup_type' => 'abn', // 'abn', 'acn', or 'name'
      '#auto_populate' => TRUE,
      '#show_details' => TRUE,
      '#cache_results' => TRUE,
    ];
  }

  /**
   * Processes the webform ABR lookup element.
   */
  public static function processWebformAbrLookup(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#attached']['library'][] = 'webform_abr_lookup/webform_abr_lookup';
    
    $lookup_type = $element['#lookup_type'] ?? 'abn';
    $auto_populate = $element['#auto_populate'] ?? TRUE;
    $show_details = $element['#show_details'] ?? TRUE;

    // Main search field.
    $element['search'] = [
      '#type' => 'textfield',
      '#title' => $element['#title'] ?? self::getLookupTitle($lookup_type),
      '#description' => $element['#description'] ?? self::getLookupDescription($lookup_type),
      '#required' => $element['#required'] ?? FALSE,
      '#size' => 60,
      '#maxlength' => 128,
      '#attributes' => [
        'class' => ['webform-abr-lookup-search'],
        'data-lookup-type' => $lookup_type,
        'data-auto-populate' => $auto_populate ? '1' : '0',
        'autocomplete' => 'off',
      ],
    ];

    // Add autocomplete route based on lookup type.
    if ($lookup_type === 'name') {
      $element['search']['#autocomplete_route_name'] = 'webform_abr_lookup.autocomplete.business_name';
      $element['search']['#autocomplete_route_parameters'] = ['string' => ''];
    }
    else {
      $element['search']['#autocomplete_route_name'] = 'webform_abr_lookup.autocomplete.abn';
      $element['search']['#autocomplete_route_parameters'] = ['string' => ''];
    }

    // Hidden field to store the selected ABN.
    $element['selected_abn'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'class' => ['webform-abr-lookup-selected-abn'],
      ],
    ];

    if ($show_details) {
      // Business details fields (populated automatically when ABN is selected).
      $element['details'] = [
        '#type' => 'details',
        '#title' => t('Business Details'),
        '#open' => FALSE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-details'],
        ],
      ];

      $element['details']['abn'] = [
        '#type' => 'textfield',
        '#title' => t('ABN'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-abn'],
        ],
      ];

      $element['details']['acn'] = [
        '#type' => 'textfield',
        '#title' => t('ACN'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-acn'],
        ],
      ];

      $element['details']['entity_name'] = [
        '#type' => 'textfield',
        '#title' => t('Entity Name'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-entity-name'],
        ],
      ];

      $element['details']['entity_type'] = [
        '#type' => 'textfield',
        '#title' => t('Entity Type'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-entity-type'],
        ],
      ];

      $element['details']['gst_status'] = [
        '#type' => 'textfield',
        '#title' => t('GST Status'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-gst-status'],
        ],
      ];

      $element['details']['entity_status'] = [
        '#type' => 'textfield',
        '#title' => t('Entity Status'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-entity-status'],
        ],
      ];

      $element['details']['state'] = [
        '#type' => 'textfield',
        '#title' => t('State'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-state'],
        ],
      ];

      $element['details']['postcode'] = [
        '#type' => 'textfield',
        '#title' => t('Postcode'),
        '#readonly' => TRUE,
        '#attributes' => [
          'class' => ['webform-abr-lookup-postcode'],
        ],
      ];
    }

    return $element;
  }

  /**
   * Validates the webform ABN lookup element.
   */
  public static function validateWebformAbnLookup(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = $element['#value'];
    $lookup_type = $element['#lookup_type'] ?? 'abn';

    if (!empty($value['search'])) {
      if ($lookup_type === 'abn' || $lookup_type === 'acn') {
        // Validate ABN/ACN format and checksum.
        if (!AbrClientService::validateAbn($value['search'])) {
          $form_state->setError($element['search'], t('Please enter a valid ABN.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      return [
        'search' => '',
        'selected_abn' => '',
        'details' => [
          'abn' => '',
          'acn' => '',
          'entity_name' => '',
          'entity_type' => '',
          'gst_status' => '',
          'entity_status' => '',
          'state' => '',
          'postcode' => '',
        ],
      ];
    }

    return $input;
  }

  /**
   * Get lookup title based on type.
   */
  protected static function getLookupTitle($type) {
    switch ($type) {
      case 'abn':
        return t('Australian Business Number (ABN)');
      case 'acn':
        return t('Australian Company Number (ACN)');
      case 'name':
        return t('Business Name');
      default:
        return t('Business Lookup');
    }
  }

  /**
   * Get lookup description based on type.
   */
  protected static function getLookupDescription($type) {
    switch ($type) {
      case 'abn':
        return t('Enter an 11-digit ABN (with or without spaces). Business details will be automatically populated.');
      case 'acn':
        return t('Enter a 9-digit ACN. Business details will be automatically populated.');
      case 'name':
        return t('Start typing a business name to search. Select from the suggestions to populate business details.');
      default:
        return t('Enter business information to search the Australian Business Register.');
    }
  }

}