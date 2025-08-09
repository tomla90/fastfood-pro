(function ($) {
  const REST_BASE = FFP_ORDERS.rest;            // f.eks. /wp-json/ffp/v1
  const REST_ORDERS = REST_BASE + '/orders';    // liste-endepunkt
  const NONCE = FFP_ORDERS.nonce;

  // Render én ordre
  function row(o) {
    const items = (o.items || []).map(i => `<li>${i}</li>`).join('');
    return `<div class="ffp-order">
      <div class="ffp-order-head">
        <strong>#${o.id}</strong> – ${o.status} – ${o.total} ${o.currency}
        <button data-id="${o.id}" data-s="ffp-preparing" class="button">Preparing</button>
        <button data-id="${o.id}" data-s="ffp-ready" class="button">Ready</button>
        <button data-id="${o.id}" data-s="ffp-out-for-delivery" class="button">Out for delivery</button>
        <button data-id="${o.id}" data-s="completed" class="button button-primary">Complete</button>
      </div>
      <div><em>${o.billing_name || ''}</em> – ${o.shipping_address || ''}</div>
      <ul>${items}</ul>
      <div>Note: ${o.note || '-'} | Tip: ${o.ffp_tip || 0} | ETA: ${o.ffp_eta || '-'} | Driver: ${o.driver_id || '-'}</div>
    </div>`;
  }

  // Render hele lista + bind knapper + evt. lyd
  function render(list) {
    const html = (list || []).map(row).join('');
    $('#ffp-orders-app').html(html || '<p>Ingen åpne ordre.</p>');
    if (FFP_ORDERS.sound) {
      try { new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play(); } catch (e) {}
    }
    bind();
  }

  // Hent ordre via REST (brukes både av poll og som manuell reload)
  function load() {
    return $.get({
      url: REST_ORDERS,
      beforeSend: x => x.setRequestHeader('X-WP-Nonce', NONCE)
    }).done(render);
  }

  // Klikk på statusknapper
  function bind() {
    $('#ffp-orders-app .button').off('click').on('click', function () {
      const id = $(this).data('id'), s = $(this).data('s');
      $.post({
        url: REST_BASE + '/orders/' + id + '/status',
        data: { status: s },
        beforeSend: x => x.setRequestHeader('X-WP-Nonce', NONCE)
      }).done(load);
    });
  }

  // Polling fallback
  function poll() {
    load().always(() => setTimeout(poll, 20000));
  }

  // Prøv SSE først (samme domene som WP)
  (function init() {
    // Første last så UI ikke står tomt mens vi venter på første event
    load();

    try {
      const root = REST_BASE.replace('/wp-json', ''); // -> https://site.tld
      const es = new EventSource(root + '/ffp/v1/events'); // server-sent events endpoint

      es.addEventListener('orders', e => {
        try {
          const data = JSON.parse(e.data);
          render(data);
        } catch (err) {
          // hvis parsing feiler, bare poll neste gang
        }
      });

      es.onerror = () => { es.close(); poll(); };
    } catch (e) {
      // EventSource ikke støttet eller feilet å opprette
      poll();
    }
  })();
})(jQuery);
