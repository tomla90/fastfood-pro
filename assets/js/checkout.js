jQuery(function ($) {
  var $checkout = $('form.checkout');
  if (!$checkout.length) return;

  function toggleTipCustom(){
    var val = $('input[name="ffp_tip"]:checked').val();
    var $row = $('.ffp-tip-custom-row');
    var $inp = $('input[name="ffp_tip_custom"]');
    if (val === 'custom') { $row.show(); $inp.prop('disabled', false); }
    else { $row.hide(); $inp.val('').prop('disabled', true); }
  }

  function toggleAddressFields(){
    var type = $('input[name="ffp_delivery_type"]:checked').val();

    // Kun SHIPPING-felter. IKKE skjul #customer_details eller billing.
    var shipping = [
      '#ship-to-different-address',     // toggle-raden
      '#shipping_first_name_field','#shipping_last_name_field',
      '#shipping_company_field','#shipping_country_field',
      '#shipping_address_1_field','#shipping_address_2_field',
      '#shipping_postcode_field','#shipping_city_field','#shipping_state_field'
    ];

    if (type === 'pickup') { shipping.forEach(sel => $(sel).hide()); }
    else { shipping.forEach(sel => $(sel).show()); }
  }

  $(document.body).on('change', 'input[name="ffp_delivery_type"], input[name="ffp_tip"], input[name="ffp_tip_custom"], select[name="ffp_delivery_when"]', function(){
    toggleTipCustom();
    toggleAddressFields();
    $(document.body).trigger('update_checkout');
  });

  toggleTipCustom();
  toggleAddressFields();
});
