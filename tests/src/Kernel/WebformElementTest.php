<?php

namespace Drupal\Tests\webform_abr_lookup\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\webform_abr_lookup\Element\WebformAbrLookup;
use Drupal\Core\Form\FormState;

/**
 * Tests the webform ABR lookup element.
 *
 * @group webform_abr_lookup
 * @coversDefaultClass \Drupal\webform_abr_lookup\Element\WebformAbrLookup
 */
class WebformElementTest extends KernelTestBase {

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

    $this->installConfig(['webform_abr_lookup']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('key');
  }

  /**
   * Test element info definition.
   */
  public function testElementInfo() {
    $element = new WebformAbrLookup([], 'webform_abr_lookup', []);
    $info = $element->getInfo();

    $this->assertTrue($info['#input']);
    $this->assertTrue($info['#tree']);
    $this->assertEquals('abn', $info['#lookup_type']);
    $this->assertTrue($info['#auto_populate']);
    $this->assertTrue($info['#show_details']);
    $this->assertContains('container', $info['#theme_wrappers']);
  }

  /**
   * Test element processing.
   */
  public function testElementProcessing() {
    $element = [
      '#type' => 'webform_abr_lookup',
      '#title' => 'Test ABN Lookup',
      '#lookup_type' => 'abn',
      '#auto_populate' => TRUE,
      '#show_details' => TRUE,
    ];

    $form_state = new FormState();
    $complete_form = [];

    $processed = WebformAbrLookup::processWebformAbnLookup($element, $form_state, $complete_form);

    // Check that the main search field is created
    $this->assertArrayHasKey('search', $processed);
    $this->assertEquals('textfield', $processed['search']['#type']);
    $this->assertEquals('Australian Business Number (ABN)', $processed['search']['#title']);
    $this->assertContains('webform-abr-lookup-search', $processed['search']['#attributes']['class']);

    // Check that the selected ABN hidden field is created
    $this->assertArrayHasKey('selected_abn', $processed);
    $this->assertEquals('hidden', $processed['selected_abn']['#type']);

    // Check that details section is created when show_details is TRUE
    $this->assertArrayHasKey('details', $processed);
    $this->assertEquals('details', $processed['details']['#type']);
    $this->assertEquals('Business Details', $processed['details']['#title']);

    // Check that all detail fields are present
    $detail_fields = ['abn', 'acn', 'entity_name', 'entity_type', 'gst_status', 'entity_status', 'state', 'postcode'];
    foreach ($detail_fields as $field) {
      $this->assertArrayHasKey($field, $processed['details']);
      $this->assertEquals('textfield', $processed['details'][$field]['#type']);
      $this->assertTrue($processed['details'][$field]['#readonly']);
    }

    // Check that the library is attached
    $this->assertContains('webform_abr_lookup/webform_abr_lookup', $processed['#attached']['library']);
  }

  /**
   * Test element processing with different lookup types.
   */
  public function testElementProcessingLookupTypes() {
    // Test ACN lookup type
    $element = [
      '#type' => 'webform_abr_lookup',
      '#lookup_type' => 'acn',
    ];

    $form_state = new FormState();
    $complete_form = [];

    $processed = WebformAbrLookup::processWebformAbnLookup($element, $form_state, $complete_form);
    $this->assertEquals('Australian Company Number (ACN)', $processed['search']['#title']);
    $this->assertEquals('webform_abr_lookup.autocomplete.abn', $processed['search']['#autocomplete_route_name']);

    // Test business name lookup type
    $element['#lookup_type'] = 'name';
    $processed = WebformAbrLookup::processWebformAbnLookup($element, $form_state, $complete_form);
    $this->assertEquals('Business Name', $processed['search']['#title']);
    $this->assertEquals('webform_abr_lookup.autocomplete.business_name', $processed['search']['#autocomplete_route_name']);
  }

  /**
   * Test element processing without details.
   */
  public function testElementProcessingWithoutDetails() {
    $element = [
      '#type' => 'webform_abr_lookup',
      '#show_details' => FALSE,
    ];

    $form_state = new FormState();
    $complete_form = [];

    $processed = WebformAbrLookup::processWebformAbnLookup($element, $form_state, $complete_form);

    // Details section should not be present
    $this->assertArrayNotHasKey('details', $processed);
  }

  /**
   * Test element validation.
   */
  public function testElementValidation() {
    $element = [
      '#type' => 'webform_abr_lookup',
      '#lookup_type' => 'abn',
      '#value' => [
        'search' => '51824753556',
        'selected_abn' => '51824753556',
      ],
    ];

    $form_state = new FormState();
    $complete_form = [];

    // Valid ABN should not produce errors
    WebformAbrLookup::validateWebformAbnLookup($element, $form_state, $complete_form);
    $this->assertFalse($form_state->hasAnyErrors());

    // Invalid ABN should produce an error
    $element['#value']['search'] = '51824753555'; // Invalid checksum
    $form_state = new FormState();
    WebformAbrLookup::validateWebformAbnLookup($element, $form_state, $complete_form);
    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * Test element validation for name lookup type.
   */
  public function testElementValidationNameType() {
    $element = [
      '#type' => 'webform_abr_lookup',
      '#lookup_type' => 'name',
      '#value' => [
        'search' => 'Telstra Corporation',
        'selected_abn' => '',
      ],
    ];

    $form_state = new FormState();
    $complete_form = [];

    // Name lookup should not validate ABN format
    WebformAbrLookup::validateWebformAbnLookup($element, $form_state, $complete_form);
    $this->assertFalse($form_state->hasAnyErrors());
  }

  /**
   * Test value callback with no input.
   */
  public function testValueCallbackNoInput() {
    $element = ['#type' => 'webform_abr_lookup'];
    $form_state = new FormState();

    $value = WebformAbrLookup::valueCallback($element, FALSE, $form_state);

    $this->assertIsArray($value);
    $this->assertEquals('', $value['search']);
    $this->assertEquals('', $value['selected_abn']);
    $this->assertIsArray($value['details']);
    
    $expected_details = [
      'abn' => '',
      'acn' => '',
      'entity_name' => '',
      'entity_type' => '',
      'gst_status' => '',
      'entity_status' => '',
      'state' => '',
      'postcode' => '',
    ];
    $this->assertEquals($expected_details, $value['details']);
  }

  /**
   * Test value callback with input.
   */
  public function testValueCallbackWithInput() {
    $element = ['#type' => 'webform_abr_lookup'];
    $form_state = new FormState();
    $input = [
      'search' => 'Telstra Corporation',
      'selected_abn' => '51824753556',
      'details' => [
        'abn' => '51 824 753 556',
        'entity_name' => 'TELSTRA CORPORATION LIMITED',
      ],
    ];

    $value = WebformAbrLookup::valueCallback($element, $input, $form_state);

    $this->assertEquals($input, $value);
  }

  /**
   * Test lookup title generation.
   */
  public function testLookupTitles() {
    $reflection = new \ReflectionClass(WebformAbrLookup::class);
    $method = $reflection->getMethod('getLookupTitle');
    $method->setAccessible(TRUE);

    $this->assertStringContainsString('Australian Business Number', (string) $method->invoke(NULL, 'abn'));
    $this->assertStringContainsString('Australian Company Number', (string) $method->invoke(NULL, 'acn'));
    $this->assertStringContainsString('Business Name', (string) $method->invoke(NULL, 'name'));
    $this->assertStringContainsString('Business Lookup', (string) $method->invoke(NULL, 'unknown'));
  }

  /**
   * Test lookup description generation.
   */
  public function testLookupDescriptions() {
    $reflection = new \ReflectionClass(WebformAbrLookup::class);
    $method = $reflection->getMethod('getLookupDescription');
    $method->setAccessible(TRUE);

    $this->assertStringContainsString('11-digit ABN', (string) $method->invoke(NULL, 'abn'));
    $this->assertStringContainsString('9-digit ACN', (string) $method->invoke(NULL, 'acn'));
    $this->assertStringContainsString('typing a business name', (string) $method->invoke(NULL, 'name'));
    $this->assertStringContainsString('Australian Business Register', (string) $method->invoke(NULL, 'unknown'));
  }

}