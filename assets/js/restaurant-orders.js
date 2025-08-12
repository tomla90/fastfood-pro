(function ($) {
  const restUrl = (window.ffpOrders && window.ffpOrders.restUrl) || (window.location.origin + '/wp-json');
  const nonce   = (window.ffpOrders && window.ffpOrders.nonce)   || '';
  const soundOn = !!(window.ffpOrders && window.ffpOrders.sound);

  const ORDERS_URL = restUrl.replace(/\/$/, '') + '/ffp/v1/orders';

  let lastMaxId = 0;       // For å sjekke nye ordre
  let muteOnce = false;    // For å unngå pip rett etter status-klikking

  function row(o) {
    const items = Array.isArray(o.items) ? o.items : [];
    const li = items.map(i => `<li>${i}</li>`).join('');
    return `<div class="ffp-order">
      <div class="ffp-order-head">
        <strong>#${o.id ?? ''}</strong> – ${o.status ?? ''} – ${o.total ?? ''} ${o.currency ?? ''}
        <button data-id="${o.id}" data-s="ffp-preparing" class="button">Preparing</button>
        <button data-id="${o.id}" data-s="ffp-ready" class="button">Ready</button>
        <button data-id="${o.id}" data-s="ffp-out-for-delivery" class="button">Out for delivery</button>
        <button data-id="${o.id}" data-s="completed" class="button button-primary">Complete</button>
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

    // Sjekk for nye ordre og spill lyd om aktivert
    if (soundOn && arr.length) {
      const maxId = arr.reduce((m, o) => Math.max(m, Number(o.id || 0)), 0);
      if (!muteOnce && maxId > lastMaxId) {
        try { new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play(); } catch(e) {}
      }
      lastMaxId = Math.max(lastMaxId, maxId);
    }

    muteOnce = false; // Tilbakestill mute etter én oppdatering
    bind();
  }

  function load() {
    return $.get({
      url: ORDERS_URL + '?status=pending,on-hold,processing,ffp-preparing,ffp-ready,ffp-out-for-delivery&limit=30',
      beforeSend: x => x.setRequestHeader('X-WP-Nonce', nonce)
    }).done(render).fail((xhr) => {
      console.error('FFP orders GET failed', xhr?.responseText || xhr);
      $('#ffp-orders-app').html('<p><em>Klarte ikke å hente ordre.</em></p>');
    });
  }

  function bind() {
    $('#ffp-orders-app .button').off('click').on('click', function () {
      const id = $(this).data('id'), s = $(this).data('s');
      muteOnce = true; // Ikke pip etter statusendring
      $.post({
        url: ORDERS_URL + '/' + id + '/status',
        data: { status: s },
        beforeSend: x => x.setRequestHeader('X-WP-Nonce', nonce)
      }).done(load).fail((xhr) => {
        console.error('FFP status POST failed', xhr?.responseText || xhr);
      });
    });
  }

  function poll() {
    load().always(() => setTimeout(poll, 5000));
  }

  $(function(){
    load();
    poll();
  });

})(jQuery);
