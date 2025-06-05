<?php

namespace Drupal\webform_abr_lookup\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a 'webform_abr_lookup' element.
 *
 * @WebformElement(
 *   id = "webform_abr_lookup",
 *   label = @Translation("ABN Lookup"),
 *   description = @Translation("Provides Australian ABN/ACN and business name lookup functionality."),
 *   category = @Translation("Advanced elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformAbrLookup extends WebformElementBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
      'lookup_type' => 'abn',
      'auto_populate' => TRUE,
      'show_details' => TRUE,
      'cache_results' => TRUE,
    ] + parent::defineDefaultProperties();

    // Remove properties that don't apply to this element.
    unset($properties['format_items']);
    unset($properties['format_items_html']);
    unset($properties['format_items_text']);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    $config = \Drupal::config('webform_abr_lookup.settings');
    
    return [
      'lookup_type' => $config->get('default_lookup_type') ?: 'abn',
      'auto_populate' => $config->get('default_auto_populate') ?? TRUE,
      'show_details' => $config->get('default_show_details') ?? TRUE,
      'cache_results' => TRUE,
    ] + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    // Set the element type to our custom form element.
    $element['#type'] = 'webform_abr_lookup';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['abr_lookup'] = [
      '#type' => 'details',
      '#title' => $this->t('ABN Lookup Settings'),
      '#open' => TRUE,
    ];

    $form['abr_lookup']['lookup_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Lookup Type'),
      '#description' => $this->t('Select the type of lookup this field should perform.'),
      '#options' => [
        'abn' => $this->t('ABN (Australian Business Number)'),
        'acn' => $this->t('ACN (Australian Company Number)'),
        'name' => $this->t('Business Name'),
      ],
      '#default_value' => $this->getElementProperty($form_state, 'lookup_type'),
    ];

    $form['abr_lookup']['auto_populate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-populate business details'),
      '#description' => $this->t('When enabled, business details will be automatically populated when a valid ABN/ACN is entered or selected.'),
      '#default_value' => $this->getElementProperty($form_state, 'auto_populate'),
    ];

    $form['abr_lookup']['show_details'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show business details fields'),
      '#description' => $this->t('When enabled, additional fields for business details (entity name, type, GST status, etc.) will be displayed.'),
      '#default_value' => $this->getElementProperty($form_state, 'show_details'),
    ];

    $form['abr_lookup']['cache_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cache lookup results'),
      '#description' => $this->t('When enabled, lookup results will be cached to improve performance.'),
      '#default_value' => $this->getElementProperty($form_state, 'cache_results'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    return parent::preview() + [
      '#lookup_type' => 'abn',
      '#auto_populate' => TRUE,
      '#show_details' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);
    
    if (empty($value)) {
      return '';
    }

    $build = [];

    // Display search value.
    if (!empty($value['search'])) {
      $build['search'] = [
        '#type' => 'item',
        '#title' => $this->t('Search'),
        '#markup' => $value['search'],
      ];
    }

    // Display business details if available.
    if (!empty($value['details']) && is_array($value['details'])) {
      $details = array_filter($value['details']);
      
      if (!empty($details)) {
        $build['details'] = [
          '#type' => 'details',
          '#title' => $this->t('Business Details'),
          '#open' => TRUE,
        ];

        $field_labels = [
          'abn' => $this->t('ABN'),
          'acn' => $this->t('ACN'),
          'entity_name' => $this->t('Entity Name'),
          'entity_type' => $this->t('Entity Type'),
          'gst_status' => $this->t('GST Status'),
          'entity_status' => $this->t('Entity Status'),
          'state' => $this->t('State'),
          'postcode' => $this->t('Postcode'),
        ];

        foreach ($details as $key => $detail_value) {
          if (!empty($detail_value) && isset($field_labels[$key])) {
            $build['details'][$key] = [
              '#type' => 'item',
              '#title' => $field_labels[$key],
              '#markup' => $detail_value,
            ];
          }
        }
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);
    
    if (empty($value)) {
      return '';
    }

    $lines = [];

    // Add search value.
    if (!empty($value['search'])) {
      $lines[] = $this->t('Search: @search', ['@search' => $value['search']]);
    }

    // Add business details if available.
    if (!empty($value['details']) && is_array($value['details'])) {
      $details = array_filter($value['details']);
      
      if (!empty($details)) {
        $lines[] = '';
        $lines[] = $this->t('Business Details:');

        $field_labels = [
          'abn' => $this->t('ABN'),
          'acn' => $this->t('ACN'),
          'entity_name' => $this->t('Entity Name'),
          'entity_type' => $this->t('Entity Type'),
          'gst_status' => $this->t('GST Status'),
          'entity_status' => $this->t('Entity Status'),
          'state' => $this->t('State'),
          'postcode' => $this->t('Postcode'),
        ];

        foreach ($details as $key => $detail_value) {
          if (!empty($detail_value) && isset($field_labels[$key])) {
            $lines[] = '  ' . $field_labels[$key] . ': ' . $detail_value;
          }
        }
      }
    }

    return implode("\n", $lines);
  }

  /**
   * {@inheritdoc}
   */
  public function getElementSelectorOptions(array $element) {
    $selectors = [];
    $title = $this->getAdminLabel($element);

    $selectors[':input[name="' . $element['#webform_key'] . '[search]"]'] = $title . ' [' . $this->t('Search') . ']';
    $selectors[':input[name="' . $element['#webform_key'] . '[selected_abn]"]'] = $title . ' [' . $this->t('Selected ABN') . ']';

    if (!empty($element['#show_details'])) {
      $detail_fields = [
        'abn' => $this->t('ABN'),
        'acn' => $this->t('ACN'),
        'entity_name' => $this->t('Entity Name'),
        'entity_type' => $this->t('Entity Type'),
        'gst_status' => $this->t('GST Status'),
        'entity_status' => $this->t('Entity Status'),
        'state' => $this->t('State'),
        'postcode' => $this->t('Postcode'),
      ];

      foreach ($detail_fields as $key => $label) {
        $selectors[':input[name="' . $element['#webform_key'] . '[details][' . $key . ']"]'] = $title . ' [' . $label . ']';
      }
    }

    return $selectors;
  }

}