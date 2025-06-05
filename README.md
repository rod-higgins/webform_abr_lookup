# Webform ABR Lookup

A Drupal 11 module that provides Australian Business Number (ABN), Australian Company Number (ACN), and business name lookup functionality for webforms using the official Australian Business Register (ABR) web services.

## Features

- **ABN/ACN Lookup**: Enter an ABN or ACN to automatically retrieve business details
- **Business Name Search**: Search by business name with autocomplete suggestions
- **Auto-population**: Automatically populate business details when a valid ABN/ACN is found
- **Validation**: Built-in ABN validation using the official checksum algorithm
- **Caching**: Configurable caching to improve performance and reduce API calls
- **Webform Integration**: Seamless integration with Drupal's webform module
- **Responsive Design**: Mobile-friendly interface

## Requirements

- Drupal 11.x
- Webform module
- ABR web services authentication GUID (free registration required)

## Installation

1. Download and enable the module:
   ```bash
   composer require drupal/webform_abn_lookup
   drush en webform_abn_lookup
   ```

2. Register for ABR web services access:
   - Visit https://abr.business.gov.au/Documentation/WebServiceRegistration
   - Read and accept the terms of service
   - Complete the registration form
   - You will receive an authentication GUID via email

3. Configure the module:
   - Navigate to `/admin/config/webform/abn-lookup`
   - Enter your ABR authentication GUID
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

Access module settings at `/admin/config/webform/abn-lookup`:

- **ABR Authentication GUID**: Your registered GUID for API access
- **Cache Duration**: How long to cache lookup results (5 minutes to 24 hours)
- **Default Lookup Type**: Default selection for new elements
- **Auto-populate**: Default auto-population setting
- **Show Details**: Default visibility of business details fields

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

## Caching

Lookup results are cached to improve performance:

- **ABR Lookups**: Cached for configured duration (default: 1 hour)
- **Name Searches**: Cached for 30 minutes
- **Cache Keys**: Include search parameters to avoid conflicts
- **Cache Clearing**: Automatically clears on module uninstall

## Troubleshooting

### Connection Issues

1. **Invalid GUID Error**:
   - Verify your GUID format (should be in format: 12345678-1234-1234-1234-123456789012)
   - Ensure you're using the GUID from the ABR registration email
   - Test the connection using the built-in test tool

2. **No Results Found**:
   - Verify the ABN is active and exists in the ABR
   - Check that the ABN passes validation (correct format and checksum)
   - Try the lookup manually on https://abr.business.gov.au/

3. **Slow Performance**:
   - Enable caching if disabled
   - Consider increasing cache duration
   - Monitor ABR service availability

### Common Issues

- **Autocomplete Not Working**: Ensure jQuery UI Autocomplete library is loaded
- **Details Not Populating**: Check JavaScript console for errors
- **Validation Errors**: Verify ABN format and checksum validation

## Security Considerations

- All API calls are made server-side to protect your GUID
- Input validation prevents malicious data injection
- Caching helps reduce exposure to API rate limits
- No sensitive business information is stored permanently

## Support

For issues related to:

- **Module functionality**: Create an issue in the Drupal.org project queue
- **ABR web services**: Contact the Australian Business Register
- **GUID registration**: Visit https://abr.business.gov.au/Documentation/WebServiceRegistration

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

### 1.0.0
- Initial release
- ABN/ACN/Business name lookup functionality
- Webform integration
- Auto-population of business details
- Caching and validation
- Mobile-responsive design