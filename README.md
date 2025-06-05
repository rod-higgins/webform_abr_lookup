# Webform ABR Lookup

A Drupal 10.3+ / 11 module that provides Australian Business Number (ABN), Australian Company Number (ACN), and business name lookup functionality for webforms using the official Australian Business Register (ABR) web services.

## Features

- **ABN/ACN Lookup**: Enter an ABN or ACN to automatically retrieve business details
- **Business Name Search**: Search by business name with autocomplete suggestions
- **Auto-population**: Automatically populate business details when a valid ABN/ACN is found
- **Validation**: Built-in ABN validation using the official checksum algorithm
- **Caching**: Configurable caching to improve performance and reduce API calls
- **Webform Integration**: Seamless integration with Drupal's webform module
- **Secure Key Storage**: Uses Drupal's Key module for secure API credential storage
- **Responsive Design**: Mobile-friendly interface

## Requirements

- Drupal 11.x
- Webform module
- Key module (for secure credential storage)
- ABR web services authentication GUID (free registration required)

## Installation

1. Download and enable the required modules:
   ```bash
   composer require drupal/webform_abn_lookup drupal/key
   drush en key webform_abn_lookup
   ```

2. Register for ABR web services access:
   - Visit https://abr.business.gov.au/Documentation/WebServiceRegistration
   - Read and accept the terms of service
   - Complete the registration form
   - You will receive an authentication GUID via email

3. Create a key for your ABR GUID:
   - Navigate to `/admin/config/system/keys`
   - Click "Add key"
   - Set the key type to "Authentication"
   - Enter your ABR GUID as the key value
   - Save the key

4. Configure the module:
   - Navigate to `/admin/config/webform/abr-lookup`
   - Select your ABR authentication key from the dropdown
   - Configure the ABR API base URL (default is provided)
   - Configure other settings as needed
   - Test the connection using the built-in test functionality

## Usage

### Adding ABR Lookup to a Webform

1. Edit or create a webform
2. Add a new element
3. Select "ABR Lookup" from the "Advanced elements" category
4. Configure the element:
   - **Lookup Type**: Choose ABN, ACN, or Business Name
   - **Auto-populate**: Enable to automatically fill business details
   - **Show Details**: Display additional business information fields
   - **Cache Results**: Enable caching for better performance

### Element Configuration Options

- **ABN**: Validates and formats 11-digit ABNs, retrieves business details
- **ACN**: Validates 9-digit ACNs, retrieves business details  
- **Business Name**: Provides autocomplete search with business suggestions

### Retrieved Business Information

When a valid ABN/ACN is found, the following information is automatically populated:

- Entity Name
- ABN (formatted with spaces)
- ACN (if applicable)
- Entity Type (e.g., "Australian Private Company")
- Entity Status (Active/Cancelled)
- GST Registration Status
- State/Territory
- Postcode

## API Usage

The module uses the official ABR web services:

- **SearchByABNv202001**: Retrieves detailed business information by ABN
- **ABRSearchByNameAdvancedSimpleProtocol2017**: Searches businesses by name
- **SearchByASICv201408**: Retrieves information by ASIC number

All API calls are cached based on your configuration settings to improve performance and reduce load on the ABR services.

## Configuration

### Module Settings

Access module settings at `/admin/config/webform/abr-lookup`:

- **ABR Authentication Key**: Select a key containing your registered GUID for API access
- **ABR API Base URL**: The base URL for ABR web services (configurable for testing or alternative endpoints)
- **Cache Duration**: How long to cache lookup results (5 minutes to 24 hours)
- **Default Lookup Type**: Default selection for new elements
- **Auto-populate**: Default auto-population setting
- **Show Details**: Default visibility of business details fields

### Key Management

The module uses Drupal's Key module for secure credential storage:

1. **Creating a Key**:
   - Go to `/admin/config/system/keys`
   - Click "Add key"
   - Choose an appropriate key type (Authentication recommended)
   - Enter your ABR GUID as the key value
   - Configure key providers based on your security requirements

2. **Key Security**:
   - The Key module supports various storage backends (database, files, environment variables)
   - Consider using encrypted storage for production environments
   - Keys are never exposed in configuration exports

### Element-Specific Settings

Each ABR lookup element can be individually configured:

- Lookup type (ABN, ACN, or business name)
- Auto-population behavior
- Business details visibility
- Result caching

## Validation

The module includes comprehensive validation:

- **ABN Format**: Ensures 11-digit format
- **ABN Checksum**: Validates using the official algorithm
- **ACN Format**: Ensures 9-digit format
- **API Response**: Handles API errors gracefully
- **Key Validation**: Ensures selected keys exist and contain valid GUIDs

## Caching

Lookup results are cached to improve performance:

- **ABR Lookups**: Cached for configured duration (default: 1 hour)
- **Name Searches**: Cached for 30 minutes
- **Cache Keys**: Include search parameters to avoid conflicts
- **Cache Clearing**: Automatically clears on module uninstall

## Security

The module prioritizes security:

- **Secure Key Storage**: API credentials stored using the Key module
- **Input Validation**: All user inputs are validated and sanitized
- **Rate Limiting**: Prevents abuse with configurable rate limits
- **XSS Protection**: All outputs are properly escaped
- **No Credential Exposure**: Keys never appear in configuration exports or logs

## Troubleshooting

### Connection Issues

1. **Key Not Configured Error**:
   - Ensure you have created a key with your ABR GUID
   - Verify the key is selected in the module settings
   - Check that the key contains a valid GUID format

2. **Key Not Found Error**:
   - Verify the selected key exists in the key management interface
   - Check that the key provider is functioning correctly
   - Ensure the key has not been deleted or renamed

3. **Invalid GUID Error**:
   - Verify your GUID format (should be in format: 12345678-1234-1234-1234-123456789012)
   - Ensure you're using the GUID from the ABR registration email
   - Test the connection using the built-in test tool

4. **No Results Found**:
   - Verify the ABN is active and exists in the ABR
   - Check that the ABN passes validation (correct format and checksum)
   - Try the lookup manually on https://abr.business.gov.au/

5. **Slow Performance**:
   - Enable caching if disabled
   - Consider increasing cache duration
   - Monitor ABR service availability

### Common Issues

- **Key Module Not Available**: Ensure the Key module is installed and enabled
- **Autocomplete Not Working**: Ensure jQuery UI Autocomplete library is loaded
- **Details Not Populating**: Check JavaScript console for errors
- **Validation Errors**: Verify ABN format and checksum validation

## Migration from Previous Versions

If upgrading from a version that stored the GUID directly in configuration:

1. **Automatic Migration**: The update hook will remove the old GUID from configuration
2. **Manual Steps Required**:
   - Create a new key containing your ABR GUID
   - Configure the new key in the module settings
   - Test the connection to ensure everything works

## Support

For issues related to:

- **Module functionality**: Create an issue in the Drupal.org project queue
- **ABR web services**: Contact the Australian Business Register
- **GUID registration**: Visit https://abr.business.gov.au/Documentation/WebServiceRegistration
- **Key module**: Refer to the Key module documentation

## License

This project is licensed under the GNU General Public License v2.0 - see the LICENSE.txt file for details.

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Changelog

### 2.0.0
- **BREAKING**: Migrated to use Key module for secure credential storage
- Added configurable ABR API base URL
- Improved security with proper key management
- Enhanced error handling and validation
- Updated installation and configuration documentation

### 1.0.0
- Initial release
- ABN/ACN/Business name lookup functionality
- Webform integration
- Auto-population of business details
- Caching and validation
- Mobile-responsive design