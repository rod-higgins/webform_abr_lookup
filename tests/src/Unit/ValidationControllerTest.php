<?php

namespace Drupal\Tests\webform_abr_lookup\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_abr_lookup\Controller\AbrAutocompleteController;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\webform_abr_lookup\Service\AbrClientService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Tests controller input validation methods.
 *
 * @group webform_abr_lookup
 * @coversDefaultClass \Drupal\webform_abr_lookup\Controller\AbrAutocompleteController
 */
class ValidationControllerTest extends UnitTestCase {

  /**
   * The controller under test.
   *
   * @var \Drupal\webform_abr_lookup\Controller\AbrAutocompleteController
   */
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock dependencies
    $abr_client = $this->createMock(AbrClientService::class);
    $cache = $this->createMock(CacheBackendInterface::class);
    
    $this->controller = new AbrAutocompleteController($abr_client, $cache);
  }

  /**
   * Test input validation for ABN/ACN inputs.
   *
   * @dataProvider abnInputProvider
   */
  public function testAbnInputValidation($input, $expected) {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('validateInput');
    $method->setAccessible(TRUE);
    
    $this->assertEquals($expected, $method->invokeArgs($this->controller, [$input, 'abn']));
  }

  /**
   * Test input validation for business name inputs.
   *
   * @dataProvider nameInputProvider
   */
  public function testNameInputValidation($input, $expected) {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('validateInput');
    $method->setAccessible(TRUE);
    
    $this->assertEquals($expected, $method->invokeArgs($this->controller, [$input, 'name']));
  }

  /**
   * Test output sanitization.
   *
   * @dataProvider sanitizationProvider
   */
  public function testOutputSanitization($input, $expected) {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('sanitizeOutput');
    $method->setAccessible(TRUE);
    
    $this->assertEquals($expected, $method->invokeArgs($this->controller, [$input]));
  }

  /**
   * Test business data sanitization.
   */
  public function testBusinessDataSanitization() {
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('sanitizeBusinessData');
    $method->setAccessible(TRUE);
    
    $input_data = [
      'name' => 'Test <script>alert("xss")</script> Company',
      'abn' => '51824753556',
      'nested' => [
        'field1' => 'Value with "quotes"',
        'field2' => 'Normal value',
      ],
      'numeric' => 12345,
    ];
    
    $result = $method->invokeArgs($this->controller, [$input_data]);
    
    $this->assertEquals('Test &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; Company', $result['name']);
    $this->assertEquals('51824753556', $result['abn']);
    $this->assertEquals('Value with &quot;quotes&quot;', $result['nested']['field1']);
    $this->assertEquals('Normal value', $result['nested']['field2']);
    $this->assertEquals(12345, $result['numeric']);
  }

  /**
   * Provides ABN input validation test cases.
   */
  public function abnInputProvider() {
    return [
      // Valid ABN inputs
      ['51824753556', TRUE],
      ['51 824 753 556', TRUE],
      ['51-824-753-556', TRUE],
      ['51.824.753.556', TRUE],
      
      // Valid ACN-length inputs
      ['123456789', TRUE],
      ['123 456 789', TRUE],
      
      // Invalid - too short
      ['12', FALSE],
      ['1', FALSE],
      
      // Invalid - too long
      [str_repeat('1', 101), FALSE],
      
      // Invalid - contains dangerous characters
      ['51824753556<script>', FALSE],
      ['51824753556"alert', FALSE],
      ['51824753556\'test', FALSE],
      
      // Invalid - contains letters (for ABN/ACN)
      ['5182475355a', FALSE],
      ['abc defg hij', FALSE],
    ];
  }

  /**
   * Provides business name input validation test cases.
   */
  public function nameInputProvider() {
    return [
      // Valid business names
      ['Telstra Corporation', TRUE],
      ['ABC Company Pty Ltd', TRUE],
      ['Smith & Jones', TRUE],
      ['Test Co. (Australia)', TRUE],
      ["O'Brien's Bakery", TRUE],
      
      // Valid with numbers
      ['Company 123', TRUE],
      ['2GB Radio', TRUE],
      
      // Invalid - too short
      ['A', FALSE],
      ['AB', TRUE], // 2 chars is minimum
      
      // Invalid - too long
      [str_repeat('A', 101), FALSE],
      
      // Invalid - dangerous characters
      ['Company<script>', FALSE],
      ['Test"Company', FALSE],
      ['Company\'s', FALSE], // Single quotes are actually allowed in business names
      
      // Invalid - unsupported characters
      ['Company@Example', FALSE],
      ['Test#Company', FALSE],
      ['Company$Ltd', FALSE],
    ];
  }

  /**
   * Provides output sanitization test cases.
   */
  public function sanitizationProvider() {
    return [
      // Basic sanitization
      ['Normal text', 'Normal text'],
      ['Text with spaces', 'Text with spaces'],
      
      // HTML entities
      ['Text with <script>', 'Text with &lt;script&gt;'],
      ['Text with "quotes"', 'Text with &quot;quotes&quot;'],
      ['Text with \'apostrophes\'', 'Text with &#039;apostrophes&#039;'],
      ['Text with & ampersands', 'Text with &amp; ampersands'],
      
      // Whitespace handling
      ['  Text with spaces  ', 'Text with spaces'],
      ["\n\tText with newlines\t\n", 'Text with newlines'],
      
      // Special characters that should be preserved
      ['Company Pty Ltd', 'Company Pty Ltd'],
      ['Smith & Jones', 'Smith &amp; Jones'],
      ['Price: $100', 'Price: $100'],
    ];
  }

}