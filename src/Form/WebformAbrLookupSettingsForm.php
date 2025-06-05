<?php

namespace Drupal\webform_abr_lookup\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_abr_lookup\Service\AbrClientService;
use Drupal\key\Entity\Key;
use Drupal\key\KeyRepositoryInterface;
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
   * The key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Constructs a new WebformAbrLookupSettingsForm.
   *
   * @param \Drupal\webform_abr_lookup\Service\AbrClientService $abr_client
   *   The ABR client service.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository service.
   */
  public function __construct(AbrClientService $abr_client, KeyRepositoryInterface $key_repository) {
    $this->abrClient = $abr_client;
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform_abr_lookup.abr_client'),
      $container->get('key.repository')
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

    // Get all available keys for selection.
    $key_options = [];
    $keys = $this->keyRepository->getKeys();
    foreach ($keys as $key_id => $key) {
      $key_options[$key_id] = $key->label() . ' (' . $key_id . ')';
    }

    $form['abr_api']['abr_key_id'] = [
      '#type' => 'select',
      '#title' => $this->t('ABR Authentication Key'),
      '#description' => $this->t('Select the key that contains your ABR web services authentication GUID. You can register for free access at <a href="@url" target="_blank">@url</a>. Create a new key at <a href="@key_url">the key management page</a> if needed.', [
        '@url' => 'https://abr.business.gov.au/Documentation/WebServiceRegistration',
        '@key_url' => '/admin/config/system/keys',
      ]),
      '#options' => ['' => $this->t('- Select a key -')] + $key_options,
      '#default_value' => $config->get('abr_key_id'),
      '#required' => TRUE,
    ];

    $form['abr_api']['abr_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('ABR API Base URL'),
      '#description' => $this->t('The base URL for the ABR XML Search web service.'),
      '#default_value' => $config->get('abr_base_url') ?: 'https://abr.business.gov.au/abrxmlsearch/abrxmlsearch.asmx',
      '#required' => TRUE,
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
    $key_id = $form_state->getValue('abr_key_id');
    $test_abn = $form_state->getValue('test_abn');

    if (empty($key_id)) {
      $form['testing']['test_results']['#markup'] = '<div class="messages messages--error">' . 
        $this->t('Please select an ABR authentication key first.') . '</div>';
      return $form['testing']['test_results'];
    }

    // Load the key and get its value.
    $key = $this->keyRepository->getKey($key_id);
    if (!$key) {
      $form['testing']['test_results']['#markup'] = '<div class="messages messages--error">' . 
        $this->t('Selected key not found.') . '</div>';
      return $form['testing']['test_results'];
    }

    $guid = $key->getKeyValue();
    if (empty($guid)) {
      $form['testing']['test_results']['#markup'] = '<div class="messages messages--error">' . 
        $this->t('Selected key is empty or cannot be accessed.') . '</div>';
      return $form['testing']['test_results'];
    }

    if (empty($test_abn)) {
      $test_abn = '51824753556'; // Telstra Corporation Limited
    }

    // Temporarily update config for testing.
    $original_key_id = $this->config('webform_abr_lookup.settings')->get('abr_key_id');
    $original_base_url = $this->config('webform_abr_lookup.settings')->get('abr_base_url');
    
    $this->configFactory()->getEditable('webform_abr_lookup.settings')
      ->set('abr_key_id', $key_id)
      ->set('abr_base_url', $form_state->getValue('abr_base_url'))
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

    // Restore original configuration.
    $this->configFactory()->getEditable('webform_abr_lookup.settings')
      ->set('abr_key_id', $original_key_id)
      ->set('abr_base_url', $original_base_url)
      ->save();

    return $form['testing']['test_results'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $key_id = $form_state->getValue('abr_key_id');
    
    // Validate that the selected key exists and contains a valid GUID.
    if (!empty($key_id)) {
      $key = $this->keyRepository->getKey($key_id);
      if (!$key) {
        $form_state->setErrorByName('abr_key_id', $this->t('Selected key does not exist.'));
      }
      else {
        $guid = $key->getKeyValue();
        if (empty($guid)) {
          $form_state->setErrorByName('abr_key_id', $this->t('Selected key is empty or cannot be accessed.'));
        }
        elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
          $form_state->setErrorByName('abr_key_id', $this->t('Selected key does not contain a valid GUID format (e.g., 12345678-1234-1234-1234-123456789012).'));
        }
      }
    }

    // Validate base URL.
    $base_url = $form_state->getValue('abr_base_url');
    if (!empty($base_url) && !filter_var($base_url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('abr_base_url', $this->t('Please enter a valid URL for the ABR API base URL.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('webform_abr_lookup.settings')
      ->set('abr_key_id', $form_state->getValue('abr_key_id'))
      ->set('abr_base_url', $form_state->getValue('abr_base_url'))
      ->set('cache_duration', $form_state->getValue('cache_duration'))
      ->set('default_lookup_type', $form_state->getValue('default_lookup_type'))
      ->set('default_auto_populate', $form_state->getValue('default_auto_populate'))
      ->set('default_show_details', $form_state->getValue('default_show_details'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}