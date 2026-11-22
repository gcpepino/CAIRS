/* ==========================================================================
   CAIRS - assets/js/script.js
   Plain JavaScript, no libraries. Each section is a small function that
   only runs if the element it needs exists on the current page.

   1  Helpers              6  Toast messages (success fades out)
   2  Sidebar drawer       7  Count-up numbers on stat cards
   3  Dropdown menu        8  Photo preview (with Change / Remove)
   4  Live clock           9  Print / Save as PDF
   5  Modals + confirm    10  Maps (Leaflet): picker, report map, admin map
                          11  Live updates (bell count, SOS toast)
   ========================================================================== */
(function () {
  'use strict';

  /* 1. HELPERS ----------------------------------------------------------- */
  function $(selector, root) { return (root || document).querySelector(selector); }
  function $all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;


  /* 2. SIDEBAR DRAWER (mobile) ------------------------------------------- */
  function initSidebar() {
    var sidebar = $('.sidebar');
    var toggle = $('.nav-toggle');
    var overlay = $('.sidebar-overlay');
    if (!sidebar || !toggle) return;

    function setOpen(open) {
      sidebar.classList.toggle('open', open);
      if (overlay) overlay.classList.toggle('is-visible', open);
      document.body.classList.toggle('nav-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
    if (overlay) overlay.addEventListener('click', function () { setOpen(false); });          // click outside
    $all('a', sidebar).forEach(function (a) { a.addEventListener('click', function () { setOpen(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 820) setOpen(false); });
  }


  /* 3. DROPDOWN MENU (user menu in the top bar) -------------------------- */
  function initDropdowns() {
    var wraps = $all('.dropdown');
    function closeAll() {
      wraps.forEach(function (w) {
        w.classList.remove('open');
        var b = $('[data-dropdown]', w);
        if (b) b.setAttribute('aria-expanded', 'false');
      });
    }
    wraps.forEach(function (wrap) {
      var button = $('[data-dropdown]', wrap);
      if (!button) return;
      button.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = !wrap.classList.contains('open');
        closeAll();
        wrap.classList.toggle('open', willOpen);
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });
    document.addEventListener('click', closeAll);                                              // click outside
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
  }


  /* 4. LIVE CLOCK -------------------------------------------------------- */
  function initClock() {
    var clocks = $all('[data-clock]');
    var dates = $all('[data-clock-date]');
    if (!clocks.length && !dates.length) return;

    function tick() {
      var now = new Date();
      var short = now.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
      var long = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
      clocks.forEach(function (el) { el.textContent = short; });
      dates.forEach(function (el) { el.textContent = long; });
    }
    tick();
    setInterval(tick, 30000);
  }


  /* 5. MODALS + CONFIRM DIALOG ------------------------------------------- */
  var lastFocus = null;

  function openModal(modal) {
    lastFocus = document.activeElement;
    modal.classList.add('is-mounted');
    void modal.offsetWidth;                         // force a reflow so the CSS transition plays
    modal.classList.add('is-open');
    var focusable = $('[data-autofocus]', modal) || $('button, a[href], input, select, textarea', modal);
    if (focusable) focusable.focus();
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
    setTimeout(function () { modal.classList.remove('is-mounted'); }, reduceMotion ? 0 : 260);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function initModals() {
    // Any element with data-modal-open="modal-id" opens that modal
    $all('[data-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var m = document.getElementById(btn.getAttribute('data-modal-open'));
        if (m) openModal(m);
      });
    });
    // Anything inside a modal with data-modal-close closes it
    document.addEventListener('click', function (e) {
      var closer = e.target.closest ? e.target.closest('[data-modal-close]') : null;
      if (closer) { var m = closer.closest('.modal'); if (m) closeModal(m); }
    });
    document.addEventListener('keydown', function (e) {
      var open = $('.modal.is-open');
      if (!open) return;
      if (e.key === 'Escape') { closeModal(open); return; }
      if (e.key === 'Tab') {                         // keep keyboard focus inside the dialog
        var items = $all('button, a[href], input, select, textarea', open);
        if (!items.length) return;
        var first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  // <form data-confirm="Are you sure?"> asks first, using a styled dialog
  function initConfirmForms() {
    var forms = $all('form[data-confirm]');
    if (!forms.length) return;

    var modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML =
      '<div class="modal-backdrop" data-modal-close></div>' +
      '<div class="modal-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-text">' +
      '<h3 id="confirm-title">Please confirm</h3>' +
      '<p id="confirm-text" class="muted"></p>' +
      '<div class="modal-actions">' +
      '<button type="button" class="btn ghost" data-modal-close>Cancel</button>' +
      '<button type="button" class="btn" id="confirm-yes" data-autofocus>Yes, continue</button>' +
      '</div></div>';
    document.body.appendChild(modal);

    var pendingForm = null;
    forms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        pendingForm = form;
        $('#confirm-text', modal).textContent = form.getAttribute('data-confirm');
        openModal(modal);
      });
    });
    $('#confirm-yes', modal).addEventListener('click', function () {
      var form = pendingForm;
      closeModal(modal);
      if (form) form.submit();                       // form.submit() skips the submit event, so no loop
    });
  }


  /* 6. TOAST MESSAGES ---------------------------------------------------- */
  function initToasts() {
    $all('.toast').forEach(function (toast) {
      function dismiss() {
        toast.classList.add('hide');
        setTimeout(function () { toast.style.display = 'none'; }, 450);
      }
      var close = $('.toast-close', toast);
      if (close) close.addEventListener('click', dismiss);
      setTimeout(dismiss, toast.classList.contains('success') ? 5000 : 9000);
    });
    // Old-style inline success message (kept so nothing breaks)
    var inline = $('.flash.success:not(.toast)');
    if (inline) setTimeout(function () { inline.style.display = 'none'; }, 5000);
  }


  /* 7. COUNT-UP NUMBERS (12, 85%, 4.5 ...) ------------------------------- */
  function initCountUp() {
    if (reduceMotion) return;
    $all('.stat b').forEach(function (el) {
      var match = el.textContent.trim().match(/^(\d+(?:\.\d+)?)(.*)$/);
      if (!match) return;                            // e.g. "-" stays as it is
      var end = parseFloat(match[1]);
      var suffix = match[2];
      var decimals = (match[1].split('.')[1] || '').length;
      var start = null;

      el.textContent = (0).toFixed(decimals) + suffix;
      function step(time) {
        if (!start) start = time;
        var progress = Math.min((time - start) / 900, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = (end * eased).toFixed(decimals) + suffix;
        if (progress < 1) requestAnimationFrame(step);
      }
      setTimeout(function () { requestAnimationFrame(step); }, 250);
    });
  }


  /* 8. PHOTO PREVIEW ----------------------------------------------------- */
  function initPhotoPreview() {
    var input = document.getElementById('photo');
    var box = document.getElementById('preview');
    if (!input || !box) return;
    var url = null;

    function clearPreview() {
      if (url) URL.revokeObjectURL(url);
      url = null;
      box.innerHTML = '';
    }

    input.addEventListener('change', function () {
      clearPreview();
      var file = input.files[0];
      if (!file) return;

      var figure = document.createElement('figure');
      figure.className = 'photo-preview';

      var img = document.createElement('img');
      url = URL.createObjectURL(file);
      img.src = url;
      img.alt = 'Selected photo preview';

      var actions = document.createElement('div');
      actions.className = 'actions';

      var change = document.createElement('button');
      change.type = 'button';
      change.className = 'btn ghost small';
      change.textContent = 'Change photo';
      change.addEventListener('click', function () { input.click(); });

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn ghost small';
      remove.textContent = 'Remove';
      remove.addEventListener('click', function () { input.value = ''; clearPreview(); });   // nothing is uploaded until Submit

      actions.appendChild(change);
      actions.appendChild(remove);
      figure.appendChild(img);
      figure.appendChild(actions);

      if (file.size > 3 * 1024 * 1024) {
        var warn = document.createElement('p');
        warn.className = 'muted';
        warn.textContent = 'This photo is over 3 MB. Please choose a smaller one.';
        figure.appendChild(warn);
      }
      box.appendChild(figure);
    });
  }


  /* 9. PRINT / SAVE AS PDF ----------------------------------------------- */
  function initPrint() {
    $all('[data-print]').forEach(function (b) {
      b.addEventListener('click', function () { window.print(); });
    });
  }


  /* 10. MAPS (Leaflet is only loaded on pages that need it) -------------- */
  function initMaps() {
    if (!window.L) return;

    function addTiles(map) {
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
    }
    function centerOf(el) { return [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)]; }

    // 10a. Report form: click the map (or use GPS) to drop a pin
    var picker = document.getElementById('map-picker');
    if (picker) {
      var pickMap = L.map(picker).setView(centerOf(picker), picker.dataset.pin === '1' ? 17 : 14);
      var pin = null;
      var latInput = document.getElementById('latitude');
      var lngInput = document.getElementById('longitude');
      addTiles(pickMap);

      var setPin = function (lat, lng) {
        if (pin) pin.setLatLng([lat, lng]); else pin = L.marker([lat, lng]).addTo(pickMap);
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
      };
      if (picker.dataset.pin === '1') setPin.apply(null, centerOf(picker));
      pickMap.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng); });

      var locate = document.getElementById('locate');
      if (locate) locate.addEventListener('click', function () {
        if (!navigator.geolocation) { alert('Your browser does not support location.'); return; }
        navigator.geolocation.getCurrentPosition(function (p) {
          setPin(p.coords.latitude, p.coords.longitude);
          pickMap.setView([p.coords.latitude, p.coords.longitude], 17);
        }, function () { alert('Could not get your location. Click the map instead.'); });
      });
    }

    // 10b. Report details: show the saved pin
    var view = document.getElementById('map-view');
    if (view) {
      var viewMap = L.map(view, { scrollWheelZoom: false }).setView(centerOf(view), 17);
      addTiles(viewMap);
      L.marker(centerOf(view)).addTo(viewMap);
    }

    // 10c. Admin: every pinned report, colored by status
    var all = document.getElementById('map-all');
    if (all) {
      var allMap = L.map(all).setView(centerOf(all), 13);
      var points = JSON.parse(all.dataset.points);
      var markers = [];
      addTiles(allMap);

      points.forEach(function (p) {
        var icon = L.divIcon({
          className: 'pin pin-' + p.key,
          html: '<span></span>',
          iconSize: [24, 24], iconAnchor: [12, 24], popupAnchor: [0, -24]
        });
        var box = document.createElement('div');            // popup built with textContent = safe from HTML injection
        var link = document.createElement('a');
        var status = document.createElement('div');
        link.href = p.url;
        link.textContent = p.ref + ' ' + p.title;
        status.textContent = p.status;
        box.appendChild(link);
        box.appendChild(status);
        markers.push(L.marker([p.lat, p.lng], { icon: icon }).addTo(allMap).bindPopup(box));
      });
      if (markers.length) allMap.fitBounds(L.featureGroup(markers).getBounds().pad(0.2), { maxZoom: 16 });
    }
  }


  /* 11. LIVE UPDATES (bell count for everyone, SOS toast for the admin) -- */
  function initLivePoll() {
    var url = document.body.getAttribute('data-poll');
    if (!url || !window.fetch) return;
    var lastSos = null;

    function setCount(host, n) {
      if (!host) return;
      var badge = $('.count', host);
      if (n > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'count'; host.appendChild(badge); }
        badge.textContent = n;
      } else if (badge && badge.parentNode) {
        badge.parentNode.removeChild(badge);
      }
    }

    function sosToast(d) {
      var main = $('#main');
      if (!main) return;
      var toast = document.createElement('div');
      toast.className = 'flash error toast';
      toast.setAttribute('role', 'alert');
      var text = document.createElement('span');
      var link = document.createElement('a');
      link.href = d.sos_url;
      link.textContent = 'New SOS alert: ' + d.sos_title;       // textContent = safe from HTML injection
      text.appendChild(link);
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'toast-close';
      close.setAttribute('aria-label', 'Dismiss');
      close.innerHTML = '&times;';
      close.addEventListener('click', function () { toast.style.display = 'none'; });
      toast.appendChild(text);
      toast.appendChild(close);
      main.insertBefore(toast, main.firstChild);
      setTimeout(function () { toast.style.display = 'none'; }, 20000);
    }

    function poll() {
      if (document.hidden) return;                                 // do nothing while the tab is in the background
      fetch(url, { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) return;
          setCount($('.topbar .icon-btn'), d.unread);
          setCount($('.sidebar a[href$="resident/notifications.php"]'), d.unread);
          if (typeof d.sos_last === 'number') {
            if (lastSos !== null && d.sos_last > lastSos) sosToast(d);
            lastSos = d.sos_last;
          }
        })
        .catch(function () { /* offline or server busy: try again next time */ });
    }
    poll();
    setInterval(poll, 15000);
  }


  /* START ---------------------------------------------------------------- */
  initSidebar();
  initDropdowns();
  initClock();
  initModals();
  initConfirmForms();
  initToasts();
  initCountUp();
  initPhotoPreview();
  initPrint();
  initMaps();
  initLivePoll();
})();
