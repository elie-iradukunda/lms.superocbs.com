/*
 * LMS maps: the live fleet map and the road one trip has taken.
 * Any element with data-lms-map gets a map. data-source is a JSON URL giving
 * {vehicles: [...], places: [...]} or {trail: [...], last: {...}, places: [...]};
 * data-refresh is how often (seconds) to read it again.
 * Tiles are OpenStreetMap's.
 */
(function () {
  'use strict';
  if (typeof L === 'undefined') { return; }

  var EAST_AFRICA = [[-6.9, 28.8], [1.3, 40.0]];

  function el(tag, text) { var n = document.createElement(tag); if (text !== undefined) { n.textContent = text; } return n; }

  function popupFor(v) {
    var box = el('div');
    var title = el('strong', v.plate);
    box.appendChild(title);
    var lines = [v.status, v.route, v.driver ? 'Driver: ' + v.driver : '', (v.speed !== null && v.speed !== undefined ? Math.round(v.speed) + ' km/h · ' : '') + v.ago, v.source === 'device' ? 'GPS tracker' : 'Driver phone'];
    lines.forEach(function (line) { if (line) { box.appendChild(el('div', line)); } });
    if (v.phone) { var call = el('a', 'Call ' + v.phone); call.href = 'tel:' + v.phone.replace(/\s+/g, ''); box.appendChild(call); box.appendChild(el('br')); }
    if (v.trip_url) { var open = el('a', 'Open ' + v.trip); open.href = v.trip_url; box.appendChild(open); }
    return box;
  }

  function truckIcon(v) {
    return L.divIcon({
      className: 'lms-truck-pin' + (v.stale ? ' is-stale' : '') + (v.trip ? ' is-on-trip' : ''),
      html: '<span>' + String(v.plate).replace(/[<>&"]/g, '') + '</span>',
      iconSize: null
    });
  }

  function mount(node) {
    var map = L.map(node, { scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    map.fitBounds(EAST_AFRICA);
    node.addEventListener('click', function () { map.scrollWheelZoom.enable(); }, { once: true });

    var layer = L.layerGroup().addTo(map);
    var fitted = false;

    function draw(data) {
      layer.clearLayers();
      var bounds = [];

      (data.places || []).forEach(function (p) {
        L.circle([p.lat, p.lng], { radius: p.radius || 1500, color: p.kind === 'border' ? '#c0392b' : '#1f4e8c', weight: 1, fillOpacity: 0.08 })
          .bindTooltip(p.name).addTo(layer);
      });

      (data.vehicles || []).forEach(function (v) {
        L.marker([v.lat, v.lng], { icon: truckIcon(v) }).bindPopup(popupFor(v)).addTo(layer);
        bounds.push([v.lat, v.lng]);
      });

      if (data.trail && data.trail.length) {
        var line = data.trail.map(function (p) { return [p.lat, p.lng]; });
        L.polyline(line, { color: '#1f4e8c', weight: 4, opacity: 0.8 }).addTo(layer);
        L.circleMarker(line[0], { radius: 6, color: '#2e7d32', fillOpacity: 1 }).bindTooltip('Started here').addTo(layer);
        bounds = bounds.concat(line);
      }
      if (data.last) {
        L.marker([data.last.lat, data.last.lng], { icon: truckIcon({ plate: 'Now', trip: true }) }).bindTooltip('Last position ' + data.last.at).addTo(layer);
        bounds.push([data.last.lat, data.last.lng]);
      }

      if (!fitted && bounds.length) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
        fitted = true;
      }
      if (!bounds.length && node.hasAttribute('data-empty-text') && !node.querySelector('.lms-map-empty')) {
        var empty = el('div', node.getAttribute('data-empty-text'));
        empty.className = 'lms-map-empty';
        node.appendChild(empty);
      }
    }

    function load() {
      fetch(node.getAttribute('data-source'), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data) { draw(data); } })
        .catch(function () {});
    }

    load();
    var every = parseInt(node.getAttribute('data-refresh') || '0', 10);
    if (every > 0) { setInterval(function () { if (!document.hidden) { load(); } }, every * 1000); }
  }

  document.querySelectorAll('[data-lms-map]').forEach(mount);
})();
