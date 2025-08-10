/* Checkout UI: tips (pill), egendefinert-felt, pickup/levering */
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

    // Wrappers we want to hide on pickup
    var wrapSel = [
      '#customer_details',               // Woo wrapper with billing+shipping columns
      '#ship-to-different-address',     // the checkbox row
      '#shipping_address_1_field', '#shipping_address_2_field',
      '#shipping_postcode_field', '#shipping_city_field', '#shipping_state_field',
      '#shipping_first_name_field', '#shipping_last_name_field', '#shipping_company_field',
      '#billing_address_1_field', '#billing_address_2_field',
      '#billing_postcode_field', '#billing_city_field', '#billing_state_field'
    ];

    if (type === 'pickup') {
      // Hide detailed address fields but keep names/phone/email visible
      $(wrapSel.join(',')).hide();
    } else {
      $(wrapSel.join(',')).show();
    }
  }

  // Whenever any of our fields change, re-evaluate & refresh totals
  function bind(){
    $(document.body).on('change',
      'input[name="ffp_delivery_type"], input[name="ffp_tip"], input[name="ffp_tip_custom"], select[name="ffp_delivery_when"]',
      function(){
        toggleTipCustom();
        toggleAddressFields();
        $(document.body).trigger('update_checkout');
      }
    );
  }

  // Init
  toggleTipCustom();
  toggleAddressFields();
  bind();
});
