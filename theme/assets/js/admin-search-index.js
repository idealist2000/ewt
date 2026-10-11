/**
 * Site Arama > İndeks Yönetimi
 * "İndeksi Yeniden Oluştur" butonu: tabloyu temizler, sonra tüm indekslenebilir
 * içeriği (100'erlik gruplar halinde) tek tek AJAX istekleriyle yeniden indeksler.
 * Toplu Üretim aracındaki aynı "arka arkaya küçük istekler" mantığını kullanır,
 * böylece 10.000 içerikte bile zaman aşımına takılmadan tamamlanır.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var btn      = document.getElementById('ewt_search_rebuild_btn');
		var statusEl = document.getElementById('ewt_search_rebuild_status');
		var wrap     = document.getElementById('ewt_search_progress_wrap');
		var bar      = document.getElementById('ewt_search_progress_bar');

		if (!btn || typeof ewtSearchAdmin === 'undefined') return;

		btn.addEventListener('click', function () {
			if (!window.confirm(ewtSearchAdmin.i18n.confirm)) return;

			btn.disabled = true;
			wrap.style.display = 'block';
			bar.style.width = '0%';
			statusEl.textContent = ewtSearchAdmin.i18n.starting;

			var clearData = new FormData();
			clearData.append('action', 'ewt_search_clear_index');
			clearData.append('nonce', ewtSearchAdmin.nonce);

			fetch(ewtSearchAdmin.ajaxUrl, { method: 'POST', body: clearData, credentials: 'same-origin' })
				.then(function () { processBatch(0); })
				.catch(handleError);
		});

		function processBatch(offset) {
			var fd = new FormData();
			fd.append('action', 'ewt_search_rebuild_batch');
			fd.append('nonce', ewtSearchAdmin.nonce);
			fd.append('offset', offset);
			fd.append('batch_size', ewtSearchAdmin.batchSize);

			fetch(ewtSearchAdmin.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { handleError(); return; }
					var d = res.data;
					var total = d.total || 0;
					var doneCount = Math.min(d.next_offset, total);
					var pct = total > 0 ? Math.min(100, Math.round((doneCount / total) * 100)) : 100;

					bar.style.width = pct + '%';
					statusEl.textContent = ewtSearchAdmin.i18n.progress + ': ' + doneCount + ' / ' + total;

					if (d.done) {
						statusEl.textContent = ewtSearchAdmin.i18n.done + ' (' + total + ' içerik indekslendi)';
						btn.disabled = false;
						window.setTimeout(function () { window.location.reload(); }, 1200);
					} else {
						processBatch(d.next_offset);
					}
				})
				.catch(handleError);
		}

		function handleError() {
			statusEl.textContent = ewtSearchAdmin.i18n.error;
			btn.disabled = false;
		}
	});
})();
