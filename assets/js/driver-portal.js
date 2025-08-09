(function($){
  function mapHTML(id){ 
    return `<div id="map-${id}" class="ffp-map" style="height:240px;margin-top:8px;border-radius:12px;"></div>`; 
  }

  function row(o){
    const items = (o.items||[]).map(i=>`<li>${i}</li>`).join('');
    const claimBtn = o.driver_id ? '' : `<button class="button ffp-claim" data-id="${o.id}">Ta oppdrag</button>`;
    const deliverBtn = `
      <button class="button ffp-status" data-id="${o.id}" data-s="ffp-out-for-delivery">På vei</button>
      <button class="button button-primary ffp-status" data-id="${o.id}" data-s="completed">Levert</button>
    `;
    return `
      <div class="ffp-order" data-oid="${o.id}" data-addr="${(o.shipping_address||'').replace(/"/g,'&quot;')}">
        <div class="ffp-order-head"><strong>#${o.id}</strong> – ${o.status} – ETA: ${o.ffp_eta||'-'} min</div>
        <div>${o.shipping_address||''}</div>
        <ul>${items}</ul>
        <div class="ffp-driver-buttons">${claimBtn} ${deliverBtn}</div>
        ${FFP_DRIVER.map && FFP_DRIVER.map.provider==='mapbox' && FFP_DRIVER.map.mapbox_token ? mapHTML(o.id) : ''}
      </div>
    `;
  }

  function render(list){
    $('#ffp-driver-list').html((list||[]).map(row).join('') || '<p>Ingen tilgjengelige oppdrag.</p>');
    bind(); 
    initMaps();
  }

  function load(){
    $.get({
      url: FFP_DRIVER.rest + '/orders',
      beforeSend: x => x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce)
    }).done(render);
  }

  function bind(){
    $('#ffp-refresh').off('click').on('click', load);

    $('.ffp-claim').off('click').on('click', function(){
      const id = $(this).data('id');
      $.post({ 
        url: FFP_DRIVER.rest + '/orders/' + id + '/claim', 
        beforeSend: x => x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce) 
      }).done(load);
    });

    $('.ffp-status').off('click').on('click', function(){
      const id = $(this).data('id'), s = $(this).data('s');
      $.post({ 
        url: FFP_DRIVER.rest + '/orders/' + id + '/status', 
        data: {status: s}, 
        beforeSend: x => x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce) 
      }).done(load);
    });
  }

  function initMaps(){
    if (!(FFP_DRIVER.map && FFP_DRIVER.map.provider==='mapbox' && FFP_DRIVER.map.mapbox_token && window.mapboxgl)) return;
    mapboxgl.accessToken = FFP_DRIVER.map.mapbox_token;

    $('.ffp-order[data-oid]').each(function(){
      const $box = $(this), oid = $box.data('oid'), addr = $box.data('addr');
      const el = document.getElementById('map-' + oid); 
      if (!el) return;

      // Geocode via REST
      $.get({
        url: FFP_DRIVER.rest + '/geo',
        data: {address: addr},
        beforeSend: x => x.setRequestHeader('X-WP-Nonce', FFP_DRIVER.nonce)
      }).done(function(geo){
        if (!geo || !geo.lng || !geo.lat) return;

        const store = FFP_DRIVER.map.store || {lat: 0, lng: 0};
        const map = new mapboxgl.Map({ 
          container: el, 
          style: 'mapbox://styles/mapbox/streets-v11', 
          center: [store.lng || 0, store.lat || 0], 
          zoom: 10 
        });

        new mapboxgl.Marker().setLngLat([store.lng, store.lat]).addTo(map);
        new mapboxgl.Marker().setLngLat([geo.lng, geo.lat]).addTo(map);

        // Fit bounds
        const b = new mapboxgl.LngLatBounds();
        b.extend([store.lng, store.lat]); 
        b.extend([geo.lng, geo.lat]);
        map.fitBounds(b, {padding: 30});

        // Draw straight line
        map.on('load', function(){
          map.addSource('route-' + oid, {
            'type': 'geojson',
            'data': {
              'type': 'Feature',
              'geometry': { 'type': 'LineString', 'coordinates': [[store.lng, store.lat],[geo.lng, geo.lat]] }
            }
          });
          map.addLayer({
            'id': 'route-' + oid,
            'type': 'line',
            'source': 'route-' + oid,
            'layout': {'line-join':'round','line-cap':'round'},
            'paint': {'line-width': 4}
          });
        });
      });
    });
  }

  $(function(){
    if ($('#ffp-driver-app').length) load();
  });

})(jQuery);
