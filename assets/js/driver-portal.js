/* global ffpDriver, jQuery */
jQuery(function ($) {
  if (!window.ffpDriver) return;

  const REST = (p) => ffpDriver.restUrl.replace(/\/$/, '') + p;
  const HEAD = (xhr) => xhr.setRequestHeader('X-WP-Nonce', ffpDriver.nonce);

  // Viktig: bruk ffp-delivery (ny slug uten mellomrom)
  const ACTIVE = 'pending,on-hold,processing,ffp-preparing,ffp-ready,ffp-delivery';

  const STATUS_LABEL = {
    'pending': 'Venter',
    'on-hold': 'På hold',
    'processing': 'Behandles',
    'ffp-preparing': 'Tilberedes',
    'ffp-ready': 'Klar',
    'ffp-delivery': 'Ut for levering',
    'completed': 'Fullført',
    'cancelled': 'Kansellert'
  };

  const money = (v, cur) => `${(v ?? 0).toString()} ${cur || ''}`.trim();

  function row(o) {
    const claimed = !!o.driver_id;
    const mine    = claimed && Number(o.driver_id) === Number(ffpDriver.userId);
    const badge   = mine ? '<span class="ffp-badge ffp-badge--mine">min</span>'
                         : (claimed ? '<span class="ffp-badge">tildelt</span>' : '');

    const items   = Array.isArray(o.items) ? o.items.map(i => `<li>${i}</li>`).join('') : '';
    const ships   = Array.isArray(o.shipping_methods) && o.shipping_methods.length
                      ? `<div class="ffp-meta-row"><span>Frakt:</span><span>${o.shipping_methods.join(', ')}</span></div>` : '';
    const coupons = Array.isArray(o.coupon_codes) && o.coupon_codes.length
                      ? `<div class="ffp-meta-row"><span>Kuponger:</span><span>${o.coupon_codes.join(', ')}</span></div>` : '';

    const statusLabel = o.status_label || STATUS_LABEL[o.status] || o.status;

    return `
      <div class="ffp-order">
        <div class="ffp-order-head">
          <div class="ffp-order-title">
            <strong>#${o.id}</strong>
            <span class="ffp-status">${statusLabel}</span>
            ${badge}
          </div>
        </div>

        <div class="ffp-order-block">
          <div class="ffp-row">
            <div class="ffp-col">
              <div class="ffp-meta-row"><span>Kunde:</span><span><strong>${o.billing_name || ''}</strong></span></div>
              <div class="ffp-meta-row"><span>Kontakt:</span><span>${o.billing_phone || ''}${o.billing_email ? (o.billing_phone ? ' • ' : '') + o.billing_email : ''}</span></div>
              <div class="ffp-meta-row"><span>Adresse:</span><span>${o.shipping_address || ''}</span></div>
              ${o.payment_method ? `<div class="ffp-meta-row"><span>Betaling:</span><span>${o.payment_method}</span></div>` : ''}
              ${ships}
              ${coupons}
            </div>
            <div class="ffp-col">
              <ul class="ffp-items">${items}</ul>
              <div class="ffp-totals">
                <div><span>Subtotal:</span><span>${money(o.items_subtotal, o.currency)}</span></div>
                <div><span>Frakt:</span><span>${money(o.shipping_total, o.currency)}</span></div>
                <div><span>Mva:</span><span>${money(o.tax_total, o.currency)}</span></div>
                <div><span>Rabatt:</span><span>${money(o.discount_total, o.currency)}</span></div>
                <div class="ffp-total"><span>Total:</span><span>${money(o.total, o.currency)}</span></div>
              </div>
            </div>
          </div>

          <div class="ffp-meta-row"><span>Notat:</span><span>${o.note || '-'}</span></div>
          <div class="ffp-meta-row"><span>Tips / ETA / Når:</span><span>${money(o.ffp_tip, o.currency)} • ${o.ffp_eta || '-'} • ${o.ffp_delivery_when || '-'}</span></div>
        </div>

        <div class="ffp-driver-actions">
          ${claimed ? '' : `<button class="button ffp-claim" data-id="${o.id}">Ta ordre</button>`}
          <button class="button ffp-status" data-id="${o.id}" data-status="ffp-ready">Klar</button>
          <button class="button ffp-status" data-id="${o.id}" data-status="ffp-delivery">Ut for levering</button>
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

  // Claim
  $(document).on('click', '.ffp-claim', function () {
    const id = $(this).data('id');
    $.post({
      url: REST('/ffp/v1/orders/' + id + '/claim'),
      beforeSend: HEAD
    }).done(load).fail((xhr) => {
      alert('Kunne ikke ta ordren: ' + (xhr?.responseJSON?.message || 'Ukjent feil'));
    });
  });

  // Status
  $(document).on('click', '.ffp-status', function () {
    const id = $(this).data('id');
    const s  = $(this).data('status'); // ffp-ready | ffp-delivery | completed
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
