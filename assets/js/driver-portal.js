/* global ffpDriver, jQuery */
jQuery(function ($) {
  if (!window.ffpDriver) return;

  const REST = (p) => ffpDriver.restUrl.replace(/\/$/, '') + p;
  const HEAD = (xhr) => xhr.setRequestHeader('X-WP-Nonce', ffpDriver.nonce);

  // Statuser sjåfør skal se
  const ACTIVE = 'pending,on-hold,processing,ffp-preparing,ffp-ready,ffp-out-for-delivery';

  function row(o) {
    const mine = o.mine ? ' (min)' : (o.claimed ? ' (tildelt)' : '');
    return `
      <div class="ffp-order">
        <div class="ffp-order-head"><strong>#${o.id}</strong> – ${o.status}${mine}</div>
        <div class="ffp-driver-actions">
          ${o.claimed ? '' : `<button class="button ffp-claim" data-id="${o.id}">Ta ordre</button>`}
          <button class="button ffp-status" data-id="${o.id}" data-status="ffp-out-for-delivery">Ut for levering</button>
          <button class="button button-primary ffp-status" data-id="${o.id}" data-status="completed">Fullført</button>
        </div>
      </div>
    `;
  }

  function render(list) {
    const html = (Array.isArray(list) ? list : []).map(row).join('') || '<p>Ingen aktive ordre.</p>';
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

  $(document).on('click', '.ffp-claim', function () {
    const id = $(this).data('id');
    $.post({
      url: REST('/ffp/v1/orders/' + id + '/claim'),
      beforeSend: HEAD
    }).done(load).fail((xhr) => {
      alert('Kunne ikke ta ordren: ' + (xhr?.responseJSON?.message || 'Ukjent feil'));
    });
  });

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
