/* global ffpOrders */
(function ($) {
  try { console.log('FFP Orders JS loaded', window.ffpOrders); } catch(e){}

  const restUrl = (window.ffpOrders && window.ffpOrders.restUrl) || (window.location.origin + '/wp-json/');
  const nonce   = (window.ffpOrders && window.ffpOrders.nonce)   || '';
  const soundOn = !!(window.ffpOrders && window.ffpOrders.sound);
  const soundSrc= (window.ffpOrders && window.ffpOrders.soundSrc) || 'https://actions.google.com/sounds/v1/alarms/beep_short.ogg';

  const ORDERS_URL = restUrl.replace(/\/$/, '') + '/ffp/v1/orders';

  const STATUS_LABELS = {
    'pending': 'Pending',
    'on-hold': 'On hold',
    'processing': 'Processing',
    'ffp-preparing': 'Preparing',
    'ffp-ready': 'Ready',
    'ffp-delivery': 'Out for Delivery',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'refunded': 'Refunded',
    'failed': 'Failed',
  };

  let lastMaxId = 0;
  let muteOnce  = false;

  function btn(label, id, s, current) {
    const active = current === s;
    const cls = 'button' + (active ? ' button-primary' : '');
    const dis = active ? ' disabled aria-disabled="true"' : '';
    return `<button data-id="${id}" data-s="${s}" class="${cls}"${dis}>${label}</button>`;
  }

  function row(o) {
    const items = Array.isArray(o.items) ? o.items : [];
    const li = items.map(i => `<li>${i}</li>`).join('');
    const label = STATUS_LABELS[o.status] || o.status || '';
    return `<div class="ffp-order">
      <div class="ffp-order-head">
        <strong>#${o.id ?? ''}</strong> – <span class="ffp-status">${label}</span> – ${o.total ?? ''} ${o.currency ?? ''}
        ${btn('Preparing', o.id, 'ffp-preparing', o.status)}
        ${btn('Ready', o.id, 'ffp-ready', o.status)}
        ${btn('Out for delivery', o.id, 'ffp-delivery', o.status)}
        ${btn('Complete', o.id, 'completed', o.status)}
      </div>
      <div><em>${o.billing_name || 'Ukjent kunde'}</em> – ${o.shipping_address || ''}</div>
      <ul>${li}</ul>
      <div>Note: ${o.note || '-'} | Tip: ${o.ffp_tip || 0} | ETA: ${o.ffp_eta || '-'} | Driver: ${o.driver_id || '-'}</div>
    </div>`;
  }

  function render(list) {
    const arr = Array.isArray(list) ? list : (Array.isArray(list?.orders) ? list.orders : []);
    const html = arr.map(row).join('');
    $('#ffp-orders-app').html(html || '<p>Ingen åpne ordre.</p>');

    if (soundOn && arr.length) {
      const maxId = arr.reduce((m, o) => Math.max(m, Number(o.id || 0)), 0);
      if (!muteOnce && maxId > lastMaxId) {
        try { new Audio(soundSrc).play(); } catch(e){}
      }
      lastMaxId = Math.max(lastMaxId, maxId);
    }

    muteOnce = false;
    bind();
  }

  function load() {
    return $.get({
      url: ORDERS_URL + '?status=pending,on-hold,processing,ffp-preparing,ffp-ready,ffp-delivery&limit=40',
      beforeSend: x => x.setRequestHeader('X-WP-Nonce', nonce)
    }).done(render).fail((xhr) => {
      const body = xhr && xhr.responseJSON;
      if (body && body.code === 'rest_cookie_invalid_nonce') {
        $('#ffp-orders-app').html('<p><em>Sesjonen er utløpt – oppdater siden.</em></p>');
        return;
      }
      console.error('FFP orders GET failed', xhr?.responseText || xhr);
      $('#ffp-orders-app').html('<p><em>Klarte ikke å hente ordre.</em></p>');
    });
  }

  function bind() {
    $('#ffp-orders-app .button').off('click').on('click', function () {
      if (this.hasAttribute('disabled')) return;
      const id = $(this).data('id'), s = $(this).data('s');
      muteOnce = true;
      $.post({
        url: restUrl.replace(/\/$/, '') + '/ffp/v1/orders/' + id + '/status',
        data: { status: s },
        beforeSend: x => x.setRequestHeader('X-WP-Nonce', nonce)
      }).done(load).fail((xhr) => {
        console.error('FFP status POST failed', xhr?.responseText || xhr);
      });
    });
  }

  function poll() { load().always(() => setTimeout(poll, 5000)); }
  $(function(){ load(); poll(); });

})(jQuery);
