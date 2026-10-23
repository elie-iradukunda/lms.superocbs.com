/*
 * The driver's app (specification, section 16).
 *
 * - Each tap carries the phone's position when the phone gives it.
 * - Works on weak networks: with no signal, a form is kept on the phone
 *   (photos made smaller first) and sent by itself when the signal returns.
 * - The phone says it is alive every few minutes, so the office notices a
 *   driver who cannot be reached.
 */
(function () {
  'use strict';

  var OUTBOX = 'lms-driver-outbox';
  var base = (document.querySelector('meta[name="lms-base"]') || {}).content || '';

  function readOutbox() {
    try { return JSON.parse(localStorage.getItem(OUTBOX) || '[]'); } catch (e) { return []; }
  }
  function writeOutbox(items) {
    try { localStorage.setItem(OUTBOX, JSON.stringify(items)); return true; } catch (e) { return false; }
  }

  function showState() {
    document.querySelectorAll('[data-offline-note]').forEach(function (note) {
      note.classList.toggle('d-none', navigator.onLine);
    });
    var waiting = readOutbox().length;
    document.querySelectorAll('[data-outbox-note]').forEach(function (note) {
      note.classList.toggle('d-none', waiting === 0);
      note.textContent = waiting === 1 ? '1 thing is waiting to be sent.' : waiting + ' things are waiting to be sent.';
    });
  }

  // ------------------------------------------------------------- position
  function fillPosition(form) {
    if (!('geolocation' in navigator) || !form.hasAttribute('data-geo')) { return; }
    var status = form.querySelector('[data-geo-status]');
    navigator.geolocation.getCurrentPosition(function (position) {
      var lat = form.querySelector('input[name=lat]'), lng = form.querySelector('input[name=lng]');
      if (lat) { lat.value = position.coords.latitude.toFixed(7); }
      if (lng) { lng.value = position.coords.longitude.toFixed(7); }
      if (status) { status.textContent = 'Your position is attached.'; }
    }, function () {
      if (status) { status.textContent = 'Position not available: write where you are.'; }
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 120000 });
  }

  // --------------------------------------------------------------- photos
  function shrink(file) {
    return new Promise(function (resolve) {
      if (!file || !/^image\//.test(file.type)) { resolve(null); return; }
      var reader = new FileReader();
      reader.onload = function () {
        var image = new Image();
        image.onload = function () {
          var scale = Math.min(1, 1280 / Math.max(image.width, image.height));
          var canvas = document.createElement('canvas');
          canvas.width = Math.round(image.width * scale);
          canvas.height = Math.round(image.height * scale);
          canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
          resolve({ name: (file.name || 'photo').replace(/\.\w+$/, '') + '.jpg', data: canvas.toDataURL('image/jpeg', 0.7) });
        };
        image.onerror = function () { resolve(null); };
        image.src = reader.result;
      };
      reader.readAsDataURL(file);
    });
  }

  function dataToBlob(dataUrl) {
    var parts = dataUrl.split(','), bytes = atob(parts[1]), buffer = new Uint8Array(bytes.length);
    for (var i = 0; i < bytes.length; i++) { buffer[i] = bytes.charCodeAt(i); }
    return new Blob([buffer], { type: 'image/jpeg' });
  }

  // ---------------------------------------------------------------- queue
  function keep(form) {
    var entry = { action: form.action, fields: [], files: [], at: new Date().toISOString() };
    var jobs = [];
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name || field.disabled) { return; }
      if (field.type === 'file') {
        Array.prototype.forEach.call(field.files || [], function (file) {
          jobs.push(shrink(file).then(function (photo) { if (photo) { entry.files.push({ field: field.name, name: photo.name, data: photo.data }); } }));
        });
        return;
      }
      if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) { return; }
      entry.fields.push([field.name, field.value]);
    });
    return Promise.all(jobs).then(function () {
      var items = readOutbox();
      items.push(entry);
      if (!writeOutbox(items)) { alert('The phone is full: the photos are too big to keep. Try again with signal.'); return false; }
      showState();
      return true;
    });
  }

  var sending = false;
  function flush() {
    if (sending || !navigator.onLine) { return; }
    var items = readOutbox();
    if (!items.length) { return; }
    sending = true;
    var entry = items[0];
    var body = new FormData();
    entry.fields.forEach(function (pair) { body.append(pair[0], pair[1]); });
    entry.files.forEach(function (file) { body.append(file.field, dataToBlob(file.data), file.name); });
    fetch(entry.action, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (response) {
        sending = false;
        if (!response.ok && response.status !== 302) { throw new Error('refused'); }
        var rest = readOutbox();
        rest.shift();
        writeOutbox(rest);
        showState();
        if (rest.length) { flush(); } else if (document.querySelector('[data-outbox-note]')) { window.location.reload(); }
      })
      .catch(function () { sending = false; });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.hasAttribute('data-offline') || navigator.onLine) { return; }
    event.preventDefault();
    keep(form).then(function (kept) {
      if (kept) {
        form.reset();
        alert('No signal. It is kept on the phone and will be sent when the signal returns.');
      }
    });
  });

  window.addEventListener('online', function () { showState(); flush(); });
  window.addEventListener('offline', showState);

  document.addEventListener('DOMContentLoaded', function () {
    showState();
    flush();
    document.querySelectorAll('form[data-geo]').forEach(fillPosition);

    // The support form shows the money fields only for "Money needed".
    var money = document.querySelector('.lms-money-fields');
    if (money) {
      var toggle = function () {
        var chosen = document.querySelector('input[name=type]:checked');
        money.style.display = chosen && chosen.value === 'money' ? '' : 'none';
      };
      document.querySelectorAll('input[name=type]').forEach(function (radio) { radio.addEventListener('change', toggle); });
      toggle();
    }

    // Still here: the office can tell this phone is reachable.
    var ping = function () {
      if (!navigator.onLine) { return; }
      fetch(base + '/my-trips/ping', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
    };
    setInterval(ping, 5 * 60 * 1000);
  });
})();
