/* global ffpDriver, jQuery */
jQuery(function ($) {
  if (!window.ffpDriver) return;

  const REST = (p) => ffpDriver.restUrl.replace(/\/$/, '') + p;
  const HEAD = (xhr) => xhr.setRequestHeader('X-WP-Nonce', ffpDriver.nonce);

  // Statuser sjåfør skal se
  const ACTIVE = 'pending,on-hold,processing,ffp-preparing,ffp-ready,ffp-out-for-delivery';

  // Enkel beløpsformatter – vi viser som "<beløp> <valuta>"
  const money = (v, cur) => `${(v ?? 0).toString()} ${cur || ''}`.trim();

  function row(o) {
    const claimed = !!o.driver_id;
    const mine    = claimed && Number(o.driver_id) === Number(ffpDriver.userId);
    const badge   = mine ? ' (min)' : (claimed ? ' (tildelt)' : '');

    const items   = Array.isArray(o.items) ? o.items.map(i => `<li>${i}</li>`).join('') : '';
    const ships   = Array.isArray(o.shipping_methods) && o.shipping_methods.length
                      ? `<div>Fraktmetode: ${o.shipping_methods.join(', ')}</div>` : '';
    const coupons = Array.isArray(o.coupon_codes) && o.coupon_codes.length
                      ? `<div>Kuponger: ${o.coupon_codes.join(', ')}</div>` : '';

    return `
      <div class="ffp-order">
        <div class="ffp-order-head">
          <strong>#${o.id}</strong> – ${o.status_label || o.status}${badge}
        </div>

        <div><em>${o.billing_name || ''}</em>${o.billing_phone ? ' • ' + o.billing_phone : ''}${o.billing_email ? ' • ' + o.billing_email : ''}</div>
        <div>${o.shipping_address || ''}</div>

        <ul style="margin-top:6px">${items}</ul>

        <div style="margin-top:6px">
          <div>Subtotal: ${money(o.items_subtotal, o.currency)} • Frakt: ${money(o.shipping_total, o.currency)} • Mva: ${money(o.tax_total, o.currency)}</div>
          <div>Rabatt: ${money(o.discount_total, o.currency)} • <strong>Total: ${money(o.total, o.currency)}</strong></div>
          ${o.payment_method ? `<div>Betaling: ${o.payment_method}</div>` : ''}
          ${ships}
          ${coupons}
        </div>

        <div style="margin-top:6px">
          <div>Notat: ${o.note || '-'}</div>
          <div>Tips: ${money(o.ffp_tip, o.currency)} • ETA: ${o.ffp_eta || '-'} • Levering når: ${o.ffp_delivery_when || '-'}</div>
        </div>

        <div class="ffp-driver-actions" style="margin-top:8px">
          ${claimed ? '' : `<button class="button ffp-claim" data-id="${o.id}">Ta ordre</button>`}
          <button class="button ffp-status" data-id="${o.id}" data-status="ffp-ready">Klar</button>
          <button class="button ffp-status" data-id="${o.id}" data-status="ffp-out-for-delivery">Ut for levering</button>
          <button class="button button-primary ffp-status" data-id="${o.id}" data-status="completed">Fullført</button>
        </div>
      </div>
    `;
  }

  function render(list) {
    const arr = Array.isArray(list) ? list : [];
    const html = arr.map(row).join('') || '<p>Ingen aktive ordre.</p>';
    $('#ffp-driver-app').html(html);
  }

  function load() {
    return $.get({
      url: REST('/ffp/v1/orders') + '?status=' + encodeURIComponent(ACTIVE) + '&limit=40',
      beforeSend: HEAD
    }).done(render).fail((xhr) => {
      console.error('Driver GET failed', xhr?.responseText || xhr);
      $('#ffp-driver-app').html('<p><em>Klarte ikke å hente ordre.</em></p>');
    });
  }

  // Claim order
  $(document).on('click', '.ffp-claim', function () {
    const id = $(this).data('id');
    $.post({
      url: REST('/ffp/v1/orders/' + id + '/claim'),
      beforeSend: HEAD
    }).done(load).fail((xhr) => {
      alert('Kunne ikke ta ordren: ' + (xhr?.responseJSON?.message || 'Ukjent feil'));
    });
  });

  // Update status
  $(document).on('click', '.ffp-status', function () {
    const id = $(this).data('id');
    const s  = $(this).data('status');
    $.post({
      url: REST('/ffp/v1/orders/' + id + '/status'),
      data: { status: s },
      beforeSend: HEAD
    }).done(load).fail((xhr) => {
      alert('Kunne ikke oppdatere status: ' + (xhr?.responseJSON?.message || 'Ukjent feil'));
    });
  });

  function poll(){ load().always(() => setTimeout(poll, 7000)); }
  load(); poll();
});
