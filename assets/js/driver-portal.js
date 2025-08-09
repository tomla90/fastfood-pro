(function($){
  function row(o){
    const items = (o.items||[]).map(i=>`<li>${i}</li>`).join('');
    const claimBtn = o.driver_id ? '' : `<button class="button ffp-claim" data-id="${o.id}">Ta oppdrag</button>`;
    const deliverBtn = `<button class="button ffp-status" data-id="${o.id}" data-s="ffp-out-for-delivery">På vei</button>
                        <button class="button button-primary ffp-status" data-id="${o.id}" data-s="completed">Levert</button>`;
    return `<div class="ffp-order">
      <div class="ffp-order-head"><strong>#${o.id}</strong> – ${o.status} – ET A: ${o.ffp_eta||'-'} min</div>
      <div>${o.shipping_address||''}</div>
      <ul>${items}</ul>
      <div class="ffp-driver-buttons">${claimBtn} ${deliverBtn}</div>
    </div>`;
  }

  function load(){
    $.get({
      url: FFP_DRIVER.rest + '/orders',
      beforeSend:x=>x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce)
    }).done(list=>{
      $('#ffp-driver-list').html(list.map(row).join('') || '<p>Ingen tilgjengelige oppdrag.</p>');
      bind();
    });
  }

  function bind(){
    $('#ffp-refresh').off('click').on('click', load);
    $('.ffp-claim').off('click').on('click', function(){
      const id = $(this).data('id');
      $.post({
        url: FFP_DRIVER.rest + '/orders/'+id+'/claim',
        beforeSend:x=>x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce)
      }).done(load);
    });
    $('.ffp-status').off('click').on('click', function(){
      const id = $(this).data('id'), s=$(this).data('s');
      $.post({
        url: FFP_DRIVER.rest + '/orders/'+id+'/status',
        data:{status:s},
        beforeSend:x=>x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce)
      }).done(load);
    });
  }

  $(document).on('ready', function(){
    if ($('#ffp-driver-app').length) load();
  });
})(jQuery);
