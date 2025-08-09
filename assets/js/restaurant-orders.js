(function($){
  const rest = FFP_ORDERS.rest + '/orders';
  const nonce = FFP_ORDERS.nonce;

  function row(o){
    const items = (o.items||[]).map(i=>`<li>${i}</li>`).join('');
    return `<div class="ffp-order">
      <div class="ffp-order-head">
        <strong>#${o.id}</strong> – ${o.status} – ${o.total} ${o.currency}
        <button data-id="${o.id}" data-s="ffp-preparing" class="button">Preparing</button>
        <button data-id="${o.id}" data-s="ffp-ready" class="button">Ready</button>
        <button data-id="${o.id}" data-s="ffp-out-for-delivery" class="button">Out for delivery</button>
        <button data-id="${o.id}" data-s="completed" class="button button-primary">Complete</button>
      </div>
      <div><em>${o.billing_name}</em> – ${o.shipping_address}</div>
      <ul>${items}</ul>
      <div>Note: ${o.note||'-'} | Tip: ${o.ffp_tip||0} | ETA: ${o.ffp_eta||'-'} | Driver: ${o.driver_id||'-'}</div>
    </div>`;
  }

  function load(){
    $.get({url:rest, beforeSend:x=>x.setRequestHeader('X-WP-Nonce', nonce)})
      .done(list=>{
        $('#ffp-orders-app').html(list.map(row).join('') || '<p>Ingen åpne ordre.</p>');
        if (FFP_ORDERS.sound) try { new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play(); } catch(e){}
        bind();
      });
  }

  function bind(){
    $('#ffp-orders-app .button').off('click').on('click', function(){
      const id = $(this).data('id'), s = $(this).data('s');
      $.post({
        url: FFP_ORDERS.rest + '/orders/'+id+'/status',
        data: {status:s},
        beforeSend:x=>x.setRequestHeader('X-WP-Nonce', nonce)
      }).done(load);
    });
  }

  // Poll hvert 20. sekund
  load();
  setInterval(load, 20000);
})(jQuery);
