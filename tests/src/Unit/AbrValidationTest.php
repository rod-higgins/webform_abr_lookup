<?php

namespace Drupal\Tests\webform_abr_lookup\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_abr_lookup\Service\AbrClientService;

/**
 * Tests ABN validation and formatting functions.
 *
 * @group webform_abr_lookup
 * @coversDefaultClass \Drupal\webform_abr_lookup\Service\AbrClientService
 */
class AbrValidationTest extends UnitTestCase {

  /**
   * Test ABN validation with valid ABNs.
   *
   * @dataProvider validAbnProvider
   * @covers ::validateAbn
   */
  public function testValidAbnValidation($abn, $expected) {
    $this->assertEquals($expected, AbrClientService::validateAbn($abn));
  }

  /**
   * Test ABN validation with invalid ABNs.
   *
   * @dataProvider invalidAbnProvider
   * @covers ::validateAbn
   */
  public function testInvalidAbnValidation($abn) {
    $this->assertFalse(AbrClientService::validateAbn($abn));
  }

  /**
   * Test ABN formatting.
   *
   * @dataProvider abnFormattingProvider
   * @covers ::formatAbn
   */
  public function testAbnFormatting($input, $expected) {
    $this->assertEquals($expected, AbrClientService::formatAbn($input));
  }

  /**
   * Provides valid ABN test cases.
   */
  public function validAbnProvider() {
    return [
      // Telstra Corporation Limited
      ['51824753556', TRUE],
      ['51 824 753 556', TRUE],
      // Commonwealth Bank of Australia
      ['48123123124', TRUE],
      ['48 123 123 124', TRUE],
      // Woolworths Group Limited
      ['88000014675', TRUE],
      ['88 000 014 675', TRUE],
      // BHP Group Limited
      ['49004028077', TRUE],
      ['49 004 028 077', TRUE],
    ];
  }

  /**
   * Provides invalid ABN test cases.
   */
  public function invalidAbnProvider() {
    return [
      // Wrong length
      ['1234567890'],
      ['123456789012'],
      // Invalid checksum
      ['51824753555'],
      ['48123123123'],
      // Non-numeric
      ['abcdefghijk'],
      ['51 824 753 abc'],
      // Empty/null
      [''],
      [NULL],
      // Spaces only
      ['           '],
    ];
  }

  /**
   * Provides ABN formatting test cases.
   */
  public function abnFormattingProvider() {
    return [
      // Already formatted
      ['51 824 753 556', '51 824 753 556'],
      // Unformatted
      ['51824753556', '51 824 753 556'],
      // With extra spaces
      ['51  824  753  556', '51 824 753 556'],
      // With hyphens
      ['51-824-753-556', '51 824 753 556'],
      // With dots
      ['51.824.753.556', '51 824 753 556'],
      // Mixed formatting
      ['51-824 753.556', '51 824 753 556'],
      // Wrong length (should return as-is)
      ['1234567890', '1234567890'],
      ['123456789012', '123456789012'],
      // Non-numeric characters
      ['51824753abc', '51824753abc'],
    ];
  }

}