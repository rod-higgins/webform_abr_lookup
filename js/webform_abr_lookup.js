/**
 * @file
 * Webform ABN Lookup behaviors.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * ABN Lookup behavior.
   */
  Drupal.behaviors.webformAbrLookup = {
    attach: function (context, settings) {
      $('.webform-abr-lookup-search', context).once('webform-abr-lookup').each(function () {
        var $search = $(this);
        var $wrapper = $search.closest('.webform-abn-lookup');
        var $selectedAbn = $wrapper.find('.webform-abn-lookup-selected-abn');
        var $details = $wrapper.find('.webform-abn-lookup-details');
        var lookupType = $search.data('lookup-type');
        var autoPopulate = $search.data('auto-populate');

        // Handle autocomplete selection
        $search.on('autocompleteselect', function (event, ui) {
          if (ui.item && ui.item.abn) {
            $selectedAbn.val(ui.item.abn);
            
            if (autoPopulate && ui.item.data) {
              populateBusinessDetails($wrapper, ui.item.data);
            }
          }
        });

        // Handle manual ABN entry (for ABN/ACN lookup types)
        if (lookupType === 'abn' || lookupType === 'acn') {
          $search.on('blur', function () {
            var value = $(this).val().trim();
            if (value && isValidAbn(value) && value !== $selectedAbn.val()) {
              lookupBusinessDetails($wrapper, value);
            }
          });

          // Format ABN as user types
          $search.on('input', function () {
            var value = $(this).val();
            var cleaned = value.replace(/[^0-9]/g, '');
            
            if (cleaned.length === 11 && lookupType === 'abn') {
              var formatted = formatAbn(cleaned);
              if (formatted !== value) {
                $(this).val(formatted);
              }
            }
          });
        }

        // Clear details when search is cleared
        $search.on('input', function () {
          if ($(this).val().trim() === '') {
            clearBusinessDetails($wrapper);
            $selectedAbn.val('');
          }
        });
      });
    }
  };

  /**
   * Lookup business details by ABN.
   */
  function lookupBusinessDetails($wrapper, abn) {
    var $search = $wrapper.find('.webform-abr-lookup-search');
    var $selectedAbn = $wrapper.find('.webform-abr-lookup-selected-abn');
    var lookupType = $search.data('lookup-type');
    var autoPopulate = $search.data('auto-populate');

    if (!autoPopulate) {
      return;
    }

    // Show loading indicator
    $search.addClass('webform-abr-lookup-loading');

    $.ajax({
      url: '/webform_abr_lookup/lookup',
      method: 'POST',
      data: {
        abn: abn,
        lookup_type: lookupType
      },
      success: function (data) {
        $selectedAbn.val(abn);
        populateBusinessDetails($wrapper, data);
        $search.removeClass('webform-abr-lookup-loading');
      },
      error: function (xhr) {
        console.warn('ABN lookup failed:', xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error');
        clearBusinessDetails($wrapper);
        $search.removeClass('webform-abr-lookup-loading');
      }
    });
  }

  /**
   * Populate business details fields.
   */
  function populateBusinessDetails($wrapper, data) {
    var $details = $wrapper.find('.webform-abr-lookup-details');
    
    if ($details.length === 0) {
      return;
    }

    // Populate individual fields
    var fields = ['abn', 'acn', 'entity_name', 'entity_type', 'gst_status', 'entity_status', 'state', 'postcode'];
    
    fields.forEach(function (field) {
      var $field = $wrapper.find('.webform-abr-lookup-' + field.replace('_', '-'));
      if ($field.length && data[field]) {
        $field.val(data[field]);
      }
    });

    // Open details if they were closed
    if ($details.is('details') && !$details.prop('open')) {
      $details.prop('open', true);
    }

    // Trigger change events for any dependent elements
    $wrapper.find('input').trigger('change');
  }

  /**
   * Clear business details fields.
   */
  function clearBusinessDetails($wrapper) {
    var $details = $wrapper.find('.webform-abr-lookup-details');
    
    if ($details.length === 0) {
      return;
    }

    // Clear individual fields
    var fields = ['abn', 'acn', 'entity_name', 'entity_type', 'gst_status', 'entity_status', 'state', 'postcode'];
    
    fields.forEach(function (field) {
      var $field = $wrapper.find('.webform-abr-lookup-' + field.replace('_', '-'));
      if ($field.length) {
        $field.val('');
      }
    });

    // Trigger change events for any dependent elements
    $wrapper.find('input').trigger('change');
  }

  /**
   * Format ABN with spaces.
   */
  function formatAbn(abn) {
    var cleaned = abn.replace(/[^0-9]/g, '');
    
    if (cleaned.length === 11) {
      return cleaned.substr(0, 2) + ' ' + 
             cleaned.substr(2, 3) + ' ' + 
             cleaned.substr(5, 3) + ' ' + 
             cleaned.substr(8, 3);
    }
    
    return abn;
  }

  /**
   * Validate ABN using checksum algorithm.
   */
  function isValidAbn(abn) {
    var cleaned = abn.replace(/[^0-9]/g, '');
    
    if (cleaned.length !== 11) {
      return false;
    }

    var weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];
    var sum = 0;
    
    // Subtract 1 from first digit
    var digits = cleaned.split('').map(Number);
    digits[0] = digits[0] - 1;

    for (var i = 0; i < 11; i++) {
      sum += digits[i] * weights[i];
    }

    return (sum % 89) === 0;
  }

})(jQuery, Drupal);