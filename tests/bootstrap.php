<?php

/**
 * @file
 * Bootstrap file for webform_abr_lookup module tests.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Find the Drupal root directory.
$root = FALSE;
$start_path = __DIR__;
foreach ([dirname($start_path), dirname(dirname($start_path)), dirname(dirname(dirname($start_path)))] as $possible_root) {
  if (file_exists($possible_root . '/core/includes/bootstrap.inc')) {
    $root = $possible_root;
    break;
  }
}

if (!$root) {
  throw new \Exception('Unable to locate Drupal root directory.');
}

// Bootstrap Drupal.
require_once $root . '/core/includes/bootstrap.inc';

// Set the site path (if not already set).
if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', $root);
}

// Set up the minimal request.
$request = Request::createFromGlobals();

// Create kernel and boot it.
$kernel = DrupalKernel::createFromRequest($request, \Drupal\Core\DrupalKernel::class, 'testing');

try {
  $kernel->boot();
  $kernel->preHandle($request);
}
catch (\Exception $e) {
  // If kernel boot fails, we may be running in a CI environment
  // where the database hasn't been set up yet. This is okay for unit tests.
  if (strpos($e->getMessage(), 'database') === FALSE) {
    throw $e;
  }
}

// Set up environment variables for testing.
if (!getenv('SIMPLETEST_DB')) {
  // Default to SQLite for testing if no database is configured.
  putenv('SIMPLETEST_DB=sqlite://localhost/sites/default/files/.ht.sqlite');
}

if (!getenv('SIMPLETEST_BASE_URL')) {
  // Default base URL for testing.
  putenv('SIMPLETEST_BASE_URL=http://localhost');
}

// Set up test output directory.
if (!getenv('BROWSERTEST_OUTPUT_DIRECTORY')) {
  $output_dir = $root . '/sites/simpletest/browser_output';
  if (!file_exists($output_dir)) {
    @mkdir($output_dir, 0755, TRUE);
  }
  putenv('BROWSERTEST_OUTPUT_DIRECTORY=' . $output_dir);
}

// Configure error reporting for tests.
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set memory limit to unlimited for tests.
ini_set('memory_limit', '-1');

// Set timezone to avoid warnings.
if (!ini_get('date.timezone')) {
  date_default_timezone_set('UTC');
}

// Ensure we have a clean slate for each test run.
if (function_exists('opcache_reset')) {
  opcache_reset();
}

// Set up autoloading for test classes.
$class_loader = require $root . '/vendor/autoload.php';

// Register our test namespace.
$class_loader->addPsr4('Drupal\\Tests\\webform_abr_lookup\\', __DIR__ . '/src');

// Helper function to create test keys for functional tests.
function create_test_abr_key($key_id = 'test_abr_key', $guid = NULL) {
  if (!$guid) {
    $guid = '12345678-1234-1234-1234-123456789012';
  }
  
  // Only create if we have database access.
  try {
    if (\Drupal::hasService('entity_type.manager')) {
      $key_storage = \Drupal::entityTypeManager()->getStorage('key');
      
      // Delete existing test key if it exists.
      $existing_key = $key_storage->load($key_id);
      if ($existing_key) {
        $existing_key->delete();
      }
      
      // Create new test key.
      $key = $key_storage->create([
        'id' => $key_id,
        'label' => 'Test ABR Key',
        'key_type' => 'authentication',
        'key_provider' => 'config',
        'key_provider_settings' => [
          'key_value' => $guid,
        ],
      ]);
      $key->save();
      
      return $key;
    }
  }
  catch (\Exception $e) {
    // Ignore database errors during bootstrap.
  }
  
  return NULL;
}

// Helper function to configure module for testing.
function configure_test_module() {
  try {
    if (\Drupal::hasService('config.factory')) {
      \Drupal::configFactory()->getEditable('webform_abr_lookup.settings')
        ->set('abr_key_id', 'test_abr_key')
        ->set('abr_base_url', 'https://test.example.com/abrxmlsearch/abrxmlsearch.asmx')
        ->set('cache_duration', 3600)
        ->set('default_lookup_type', 'abn')
        ->set('default_auto_populate', TRUE)
        ->set('default_show_details', TRUE)
        ->save();
    }
  }
  catch (\Exception $e) {
    // Ignore configuration errors during bootstrap.
  }
}

// Helper function to clean up test data.
function cleanup_test_data() {
  try {
    if (\Drupal::hasService('cache.default')) {
      // Clear module-specific cache.
      \Drupal::cache()->deleteAll('webform_abr_lookup:');
    }
    
    if (\Drupal::hasService('entity_type.manager')) {
      // Clean up test keys.
      $key_storage = \Drupal::entityTypeManager()->getStorage('key');
      $test_keys = $key_storage->loadByProperties(['id' => 'test_abr_key']);
      foreach ($test_keys as $key) {
        $key->delete();
      }
    }
  }
  catch (\Exception $e) {
    // Ignore cleanup errors.
  }
}

// Register shutdown function to clean up.
register_shutdown_function('cleanup_test_data');

// Define constants for test data.
define('TEST_TELSTRA_ABN', '51824753556');
define('TEST_COMMONWEALTH_ABN', '48123123124');
define('TEST_COMMONWEALTH_ACN', '123123124');
define('TEST_INVALID_ABN', '51824753555');
define('TEST_ABR_GUID', '12345678-1234-1234-1234-123456789012');

// Mock XML responses for testing.
class TestXmlResponses {
  
  public static function getTelstraResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <businessEntity>
      <ABN><identifierValue>51824753556</identifierValue></ABN>
      <entityStatus><entityStatusCode>ACT</entityStatusCode></entityStatus>
      <entityType><entityTypeCode>PUB</entityTypeCode></entityType>
      <goodsAndServicesTax><status>Y</status></goodsAndServicesTax>
      <mainName><organisationName>TELSTRA CORPORATION LIMITED</organisationName></mainName>
      <mainBusinessPhysicalAddress>
        <stateCode>VIC</stateCode>
        <postcode>3000</postcode>
        <countryCode>AUS</countryCode>
      </mainBusinessPhysicalAddress>
    </businessEntity>
  </response>
</ABRPayloadSearchResults>';
  }
  
  public static function getCommonwealthBankResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <businessEntity>
      <ABN><identifierValue>48123123124</identifierValue></ABN>
      <ASICNumber>123123124</ASICNumber>
      <entityStatus><entityStatusCode>ACT</entityStatusCode></entityStatus>
      <entityType><entityTypeCode>PUB</entityTypeCode></entityType>
      <goodsAndServicesTax><status>Y</status></goodsAndServicesTax>
      <mainName><organisationName>COMMONWEALTH BANK OF AUSTRALIA</organisationName></mainName>
      <mainBusinessPhysicalAddress>
        <stateCode>NSW</stateCode>
        <postcode>2000</postcode>
        <countryCode>AUS</countryCode>
      </mainBusinessPhysicalAddress>
    </businessEntity>
  </response>
</ABRPayloadSearchResults>';
  }
  
  public static function getNameSearchResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <searchResultsList>
      <searchResultsRecord>
        <ABN><identifierValue>51824753556</identifierValue></ABN>
        <mainName><organisationName>TELSTRA CORPORATION LIMITED</organisationName></mainName>
        <nameScore>100</nameScore>
        <mainBusinessPhysicalAddress>
          <stateCode>VIC</stateCode>
          <postcode>3000</postcode>
        </mainBusinessPhysicalAddress>
      </searchResultsRecord>
    </searchResultsList>
  </response>
</ABRPayloadSearchResults>';
  }
  
  public static function getErrorResponse() {
    return '<?xml version="1.0" encoding="utf-8"?>
<ABRPayloadSearchResults xmlns="http://abr.business.gov.au/ABRXMLSearch/">
  <response>
    <exception>
      <exceptionDescription>Search text is not a valid ABN or ACN format</exceptionDescription>
      <exceptionCode>InvalidFormat</exceptionCode>
    </exception>
  </response>
</ABRPayloadSearchResults>';
  }
  
}

// Print bootstrap completion message.
if (php_sapi_name() === 'cli') {
  echo "Webform ABR Lookup test bootstrap completed.\n";
  echo "Drupal root: " . DRUPAL_ROOT . "\n";
  echo "Test database: " . getenv('SIMPLETEST_DB') . "\n";
  echo "Test base URL: " . getenv('SIMPLETEST_BASE_URL') . "\n";
}