(function () {
  'use strict';
  const form = document.getElementById('ewt-path-ebook-form');
  if (!form) return;
  const button = form.querySelector('button[type="submit"]');
  const status = document.getElementById('ewt-path-ebook-status');
  const link = document.getElementById('ewt-path-ebook-open');
  const loaded = Date.now();
  button.hidden = false;
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (Date.now() - loaded < 3000) {
      status.textContent = 'Lütfen birkaç saniye bekleyip tekrar dene.';
      return;
    }
    const tab = window.open('', '_blank');
    if (tab) {
      tab.opener = null;
      tab.document.title = 'PDF e-kitabın hazırlanıyor';
      tab.document.body.textContent = 'İçerik bağlantıların hazırlanıyor…';
    }
    button.disabled = true;
    link.hidden = true;
    status.textContent = 'PDF e-kitabın hazırlanıyor…';
    const body = new FormData(form);
    body.set('rendered_at', form.dataset.renderedAt);
    try {
      const response = await fetch(form.getAttribute('action'), { method: 'POST', body, credentials: 'same-origin' });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.data?.message || 'E-kitap oluşturulamadı.');
      const url = new URL(result.data.url, window.location.href);
      if (url.origin !== window.location.origin || !url.searchParams.has('ewt_ebook_print')) throw new Error('PDF bağlantısı doğrulanamadı.');
      link.href = url.href;
      link.hidden = false;
      status.textContent = result.data.count + ' içerik bağlantısı hazır. Açılan görünümde “PDF olarak kaydet / Yazdır” düğmesine bas.';
      if (tab) tab.location.href = url.href;
    } catch (error) {
      if (tab) tab.close();
      status.textContent = error.message || 'Bağlantı hatası. Lütfen tekrar dene.';
    } finally {
      button.disabled = false;
    }
  });
}());
