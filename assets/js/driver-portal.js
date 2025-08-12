console.log('Driver portal boot', window.ffpDriver);
/* global ffpDriver, jQuery */
jQuery(function ($) {
  if (!window.ffpDriver) return;

  const REST = (p) => ffpDriver.restUrl.replace(/\/$/, '') + p;
  const HEAD = (xhr) => xhr.setRequestHeader('X-WP-Nonce', ffpDriver.nonce);

  // Statuser sjåfør skal se
  const ACTIVE =
    'pending,on-hold,processing,ffp-preparing,ffp-ready,ffp-out-for-delivery';

  // Pene labels for visning
  const LABELS = {
    pending: 'Avventer',
    'on-hold': 'På vent',
    processing: 'Under behandling',
    'ffp-preparing': 'Tilberedes',
    'ffp-ready': 'Klar til henting',
    'ffp-out-for-delivery': 'Ut for levering',
    completed: 'Fullført',
    cancelled: 'Kansellert',
  };

  // Hold orden på hva vi allerede har vist (for “blink” ved nye ordre)
  let seenIds = new Set();

  // Lite inline style for blink
  const blinkCssId = 'ffp-driver-blink-style';
  if (!document.getElementById(blinkCssId)) {
    const style = document.createElement('style');
    style.id = blinkCssId;
    style.textContent = `
      .ffp-order.ffp-new { animation: ffpBlink 1.4s ease-out 1; }
      @keyframes ffpBlink {
        0% { box-shadow: 0 0 0 rgba(34,197,94,0); background:#eafff2; }
        100% { box-shadow: 0 0 0 rgba(34,197,94,0); background:#fff; }
      }
    `;
    document.head.appendChild(style);
  }

  function row(o) {
    const statusLabel = LABELS[o.status] || o.status;
    const mine = o.mine ? ' (min)' : o.claimed ? ' (tildelt)' : '';
    const claimedOrMine = o.claimed || o.mine;

    return `
      <div class="ffp-order" data-id="${o.id}">
        <div class="ffp-order-head">
          <strong>#${o.id}</strong> – ${statusLabel}${mine}
        </div>
        <div class="ffp-driver-actions">
          ${
            claimedOrMine
              ? ''
              : `<button class="button ffp-claim" data-id="${o.id}">Ta ordre</button>`
          }
          <button class="button ffp-status" data-id="${
            o.id
          }" data-status="ffp-out-for-delivery">Ut for levering</button>
          <button class="button button-primary ffp-status" data-id="${
            o.id
          }" data-status="completed">Fullført</button>
        </div>
      </div>
    `;
  }

  function render(list) {
    const arr = Array.isArray(list) ? list : [];
    const html = arr.map(row).join('') || '<p>Ingen aktive ordre.</p>';
    $('#ffp-driver-app').html(html);

    // Marker nye ordre én gang
    arr.forEach((o) => {
      if (!seenIds.has(String(o.id))) {
        const $el = $(`.ffp-order[data-id="${o.id}"]`);
        $el.addClass('ffp-new');
        // Fjern klassen etter animasjonen
        setTimeout(() => $el.removeClass('ffp-new'), 1500);
      }
    });
    // Oppdater “sett”
    seenIds = new Set(arr.map((o) => String(o.id)));
  }

  function load() {
    return $.get({
      url:
        REST('/ffp/v1/orders') +
        '?status=' +
        encodeURIComponent(ACTIVE) +
        '&limit=40',
      beforeSend: HEAD,
    })
      .done(render)
      .fail((xhr) => {
        console.error('Driver GET failed', xhr?.responseText || xhr);
        $('#ffp-driver-app').html('<p><em>Klarte ikke å hente ordre.</em></p>');
      });
  }

  // Claim order
  $(document).on('click', '.ffp-claim', function () {
    const id = $(this).data('id');
    $.post({
      url: REST('/ffp/v1/orders/' + id + '/claim'),
      beforeSend: HEAD,
    })
      .done(load)
      .fail((xhr) => {
        alert(
          'Kunne ikke ta ordren: ' +
            (xhr?.responseJSON?.message || 'Ukjent feil')
        );
      });
  });

  // Update status
  $(document).on('click', '.ffp-status', function () {
    const id = $(this).data('id');
    const s = $(this).data('status');
    $.post({
      url: REST('/ffp/v1/orders/' + id + '/status'),
      data: { status: s },
      beforeSend: HEAD,
    })
      .done(load)
      .fail((xhr) => {
        alert(
          'Kunne ikke oppdatere status: ' +
            (xhr?.responseJSON?.message || 'Ukjent feil')
        );
      });
  });

  function poll() {
    load().always(() => setTimeout(poll, 7000));
  }
  load();
  poll();
});
