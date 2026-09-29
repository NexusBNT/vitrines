(function () {
  'use strict';

  var root = document.documentElement;
  root.classList.remove('no-js');
  root.classList.add('js');

  // Menu mobile
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('menu');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      nav.classList.toggle('is-open', open);
    });
    nav.addEventListener('click', function (event) {
      if (event.target.closest('a')) {
        toggle.setAttribute('aria-expanded', 'false');
        nav.classList.remove('is-open');
      }
    });
  }

  // Carte Google Maps chargée uniquement à la demande (performance et RGPD)
  document.querySelectorAll('[data-map-src]').forEach(function (map) {
    var button = map.querySelector('button');
    if (!button) return;
    button.addEventListener('click', function () {
      var iframe = document.createElement('iframe');
      iframe.src = map.getAttribute('data-map-src');
      iframe.title = 'Carte';
      iframe.loading = 'lazy';
      iframe.referrerPolicy = 'no-referrer-when-downgrade';
      map.replaceChildren(iframe);
      map.classList.add('is-loaded');
    });
  });

  // Formulaire : horodatage anti-robot posé à la première interaction
  document.querySelectorAll('[data-contact-form]').forEach(function (form) {
    var stamp = form.querySelector('input[name="ts"]');
    var mark = function () {
      if (stamp && !stamp.value) stamp.value = String(Date.now());
    };
    form.addEventListener('focusin', mark, { once: true });
  });

  // Galerie : visionneuse plein écran
  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    var links = Array.prototype.slice.call(gallery.querySelectorAll('a'));
    if (!links.length || typeof HTMLDialogElement !== 'function') return;

    var dialog = document.createElement('dialog');
    dialog.className = 'lightbox';
    dialog.innerHTML =
      '<img alt=""><p></p>' +
      '<button type="button" class="lightbox-prev" aria-label="Photo précédente">‹</button>' +
      '<button type="button" class="lightbox-next" aria-label="Photo suivante">›</button>' +
      '<button type="button" class="lightbox-close" aria-label="Fermer">×</button>';
    document.body.appendChild(dialog);

    var image = dialog.querySelector('img');
    var caption = dialog.querySelector('p');
    var current = 0;

    var show = function (index) {
      current = (index + links.length) % links.length;
      var link = links[current];
      var thumb = link.querySelector('img');
      image.src = link.href;
      image.alt = thumb ? thumb.alt : '';
      caption.textContent = link.getAttribute('data-caption') || '';
    };

    links.forEach(function (link, index) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        show(index);
        dialog.showModal();
      });
    });

    dialog.querySelector('.lightbox-prev').addEventListener('click', function () { show(current - 1); });
    dialog.querySelector('.lightbox-next').addEventListener('click', function () { show(current + 1); });
    dialog.querySelector('.lightbox-close').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft') show(current - 1);
      if (event.key === 'ArrowRight') show(current + 1);
    });
  });
})();
