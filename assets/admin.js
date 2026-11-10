(function () {
  'use strict';

  var form = document.getElementById('bangla-slug-form');
  var input = document.getElementById('bangla-slug-preview-input');
  var slugOut = document.getElementById('bangla-slug-preview-slug');
  var latinOut = document.getElementById('bangla-slug-preview-latin');
  var timer = null;

  if (!form || !input || !window.banglaSlug) {
    return;
  }

  function run() {
    var data = new FormData(form);
    data.delete('option_page');
    data.delete('action');
    data.delete('_wpnonce');
    data.delete('_wp_http_referer');
    data.append('action', 'bangla_slug_preview');
    data.append('nonce', window.banglaSlug.nonce);
    data.append('text', input.value);

    fetch(window.banglaSlug.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json && json.success) {
          slugOut.textContent = json.data.slug;
          latinOut.textContent = json.data.latin;
        }
      })
      .catch(function () {
        slugOut.textContent = '';
        latinOut.textContent = '';
      });
  }

  function schedule() {
    window.clearTimeout(timer);
    timer = window.setTimeout(run, 250);
  }

  input.addEventListener('input', schedule);
  form.addEventListener('change', schedule);
  form.addEventListener('input', schedule);
  run();
})();
