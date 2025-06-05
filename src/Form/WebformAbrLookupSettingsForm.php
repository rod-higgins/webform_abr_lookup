<?php

namespace Drupal\webform_abr_lookup\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_abr_lookup\Service\AbrClientService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Webform ABN Lookup module.
 */
class WebformAbrLookupSettingsForm extends ConfigFormBase {

  /**
   * The ABR client service.
   *
   * @var \Drupal\webform_abr_lookup\Service\AbrClientService
   */
  protected $abrClient;

  /**
   * Constructs a new WebformAbrLookupSettingsForm.
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_abr_lookup_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['webform_abr_lookup.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('webform_abr_lookup.settings');

    $form['abr_api'] = [
      '#type' => 'details',
      '#title' => $this->t('ABR Web Services Configuration'),
      '#open' => TRUE,
    ];

    $form['abr_api']['abr_guid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ABR Authentication GUID'),
      '#description' => $this->t('Enter your ABR web services authentication GUID. You can register for free access at <a href="@url" target="_blank">@url</a>.', [
        '@url' => 'https://abr.business.gov.au/Documentation/WebServiceRegistration',
      ]),
      '#default_value' => $config->get('abr_guid'),
      '#required' => TRUE,
      '#size' => 60,
    ];

    $form['abr_api']['cache_duration'] = [
      '#type' => 'select',
      '#title' => $this->t('Cache Duration'),
      '#description' => $this->t('How long to cache ABR lookup results to improve performance and reduce API calls.'),
      '#default_value' => $config->get('cache_duration') ?: 3600,
      '#options' => [
        300 => $this->t('5 minutes'),
        900 => $this->t('15 minutes'),
        1800 => $this->t('30 minutes'),
        3600 => $this->t('1 hour'),
        7200 => $this->t('2 hours'),
        14400 => $this->t('4 hours'),
        28800 => $this->t('8 hours'),
        86400 => $this->t('24 hours'),
      ],
    ];

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Settings'),
      '#open' => TRUE,
    ];

    $form['defaults']['default_lookup_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Lookup Type'),
      '#description' => $this->t('The default lookup type for new ABN lookup elements.'),
      '#default_value' => $config->get('default_lookup_type') ?: 'abn',
      '#options' => [
        'abn' => $this->t('ABN (Australian Business Number)'),
        'acn' => $this->t('ACN (Australian Company Number)'),
        'name' => $this->t('Business Name'),
      ],
    ];

    $form['defaults']['default_auto_populate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto-populate business details by default'),
      '#description' => $this->t('When enabled, business details will be automatically populated when a valid ABN/ACN is entered or selected.'),
      '#default_value' => $config->get('default_auto_populate') ?? TRUE,
    ];

    $form['defaults']['default_show_details'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show business details fields by default'),
      '#description' => $this->t('When enabled, additional fields for business details (entity name, type, GST status, etc.) will be displayed.'),
      '#default_value' => $config->get('default_show_details') ?? TRUE,
    ];

    $form['testing'] = [
      '#type' => 'details',
      '#title' => $this->t('Testing'),
      '#open' => FALSE,
    ];

    $form['testing']['test_abn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test ABN'),
      '#description' => $this->t('Enter an ABN to test the connection. Try "51824753556" (Telstra Corporation Limited).'),
      '#size' => 20,
    ];

    $form['testing']['test_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Test Connection'),
      '#ajax' => [
        'callback' => '::testConnection',
        'wrapper' => 'test-results',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Testing...'),
        ],
      ],
    ];

    $form['testing']['test_results'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="test-results">',
      '#suffix' => '</div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback for testing the ABR connection.
   */
  public function testConnection(array &$form, FormStateInterface $form_state) {
    $guid = $form_state->getValue('abr_guid');
    $test_abn = $form_state->getValue('test_abn');

    if (empty($guid)) {
      $form['testing']['test_results']['#markup'] = '<div class="messages messages--error">' . 
        $this->t('Please enter an ABR GUID first.') . '</div>';
      return $form['testing']['test_results'];
    }

    if (empty($test_abn)) {
      $test_abn = '51824753556'; // Telstra Corporation Limited
    }

    // Temporarily update config for testing.
    $original_guid = $this->config('webform_abr_lookup.settings')->get('abr_guid');
    $this->configFactory()->getEditable('webform_abr_lookup.settings')
      ->set('abr_guid', $guid)
      ->save();

    try {
      $result = $this->abrClient->searchByAbn($test_abn);
      
      if ($result && !empty($result['main_name'])) {
        $form['testing']['test_results']['#markup'] = '<div class="messages messages--status">' . 
          $this->t('✓ Connection successful! Found: @name (ABN: @abn)', [
            '@name' => $result['main_name'],
            '@abn' => AbrClientService::formatAbn($result['abn']),
          ]) . '</div>';
      }
      else {
        $form['testing']['test_results']['#markup'] = '<div class="messages messages--warning">' . 
          $this->t('Connection established but no business found for ABN: @abn', [
            '@abn' => $test_abn,
          ]) . '</div>';
      }
    }
    catch (\Exception $e) {
      $form['testing']['test_results']['#markup'] = '<div class="messages messages--error">' . 
        $this->t('✗ Connection failed: @error', [
          '@error' => $e->getMessage(),
        ]) . '</div>';
    }

    // Restore original GUID.
    $this->configFactory()->getEditable('webform_abr_lookup.settings')
      ->set('abr_guid', $original_guid)
      ->save();

    return $form['testing']['test_results'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $guid = $form_state->getValue('abr_guid');
    
    // Validate GUID format (should be a valid GUID).
    if (!empty($guid) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
      $form_state->setErrorByName('abr_guid', $this->t('Please enter a valid GUID format (e.g., 12345678-1234-1234-1234-123456789012).'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('webform_abr_lookup.settings')
      ->set('abr_guid', $form_state->getValue('abr_guid'))
      ->set('cache_duration', $form_state->getValue('cache_duration'))
      ->set('default_lookup_type', $form_state->getValue('default_lookup_type'))
      ->set('default_auto_populate', $form_state->getValue('default_auto_populate'))
      ->set('default_show_details', $form_state->getValue('default_show_details'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}