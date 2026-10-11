/**
 * Bağlantı Yöneticisi — Admin JS
 * 1) Yazı düzenleme ekranındaki meta box: analiz et → önerileri göster → seçilenleri uygula.
 * 2) Toplu Uygula sayfası: seçili içerik türleri için önizleme/uygula batch motoru
 *    (Site Arama indeks yeniden oluşturma ile aynı "arka arkaya küçük istekler" mantığı).
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		if (typeof ewtLinkAdmin === 'undefined') return;
		initMetaBox();
		initBulkPage();
	});

	var TYPE_ICON = { internal: '🔗', external: '🌍', ozel_ders: '⭐' };

	/* =========================================================
	 * 1) META BOX (post.php / post-new.php)
	 * ========================================================= */
	function initMetaBox() {
		var box = document.getElementById('ewt_link_box');
		if (!box) return;

		var postId       = box.getAttribute('data-post-id');
		var analyzeBtn   = document.getElementById('ewt_link_analyze_btn');
		var quickBtn     = document.getElementById('ewt_link_quick_apply_btn');
		var statusEl     = document.getElementById('ewt_link_status');
		var resultsEl    = document.getElementById('ewt_link_results');

		analyzeBtn.addEventListener('click', function () {
			setBusy(true, ewtLinkAdmin.i18n.analyzing);
			var fd = new FormData();
			fd.append('action', 'ewt_link_analyze_post');
			fd.append('nonce', ewtLinkAdmin.postNonce);
			fd.append('post_id', postId);

			postForm(fd).then(function (data) {
				setBusy(false, '');
				renderResults(data, true);
			}).catch(function () { setBusy(false, ewtLinkAdmin.i18n.error); });
		});

		quickBtn.addEventListener('click', function () {
			if (!window.confirm(ewtLinkAdmin.i18n.confirmQuick)) return;
			setBusy(true, ewtLinkAdmin.i18n.applying);
			var fd = new FormData();
			fd.append('action', 'ewt_link_apply_post');
			fd.append('nonce', ewtLinkAdmin.postNonce);
			fd.append('post_id', postId);

			postForm(fd).then(function (data) {
				setBusy(false, '');
				renderResults(data, false);
			}).catch(function () { setBusy(false, ewtLinkAdmin.i18n.error); });
		});

		function setBusy(busy, msg) {
			analyzeBtn.disabled = busy;
			quickBtn.disabled = busy;
			statusEl.textContent = msg;
		}

		function renderResults(data, isPreview) {
			if (!data || !data.applied || !data.applied.length) {
				resultsEl.innerHTML = '<p class="description">' + ewtLinkAdmin.i18n.noMatches + '</p>';
				return;
			}

			if (!isPreview) {
				statusEl.textContent = ewtLinkAdmin.i18n.applied;
				resultsEl.innerHTML = buildSummaryHtml(data, false);
				return;
			}

			var html = '<ul class="ewt-link-results-list">';
			data.applied.forEach(function (item) {
				var icon = TYPE_ICON[item.type] || '🔗';
				html += '<li>' +
					'<label>' +
					'<input type="checkbox" class="ewt-link-key-cb" data-key="' + escapeAttr(item.key) + '" checked /> ' +
					icon + ' <strong>' + escapeHtml(item.label) + '</strong> — ' +
					'“' + escapeHtml(item.anchor) + '” → <code>' + escapeHtml(item.url) + '</code>' +
					'</label></li>';
			});
			html += '</ul>';
			html += '<p><button type="button" class="button button-primary" id="ewt_link_apply_selected_btn">✅ Seçilenleri İçeriğe Ekle</button></p>';
			resultsEl.innerHTML = html;

			document.getElementById('ewt_link_apply_selected_btn').addEventListener('click', function () {
				var keys = Array.prototype.slice.call(resultsEl.querySelectorAll('.ewt-link-key-cb:checked')).map(function (cb) {
					return cb.getAttribute('data-key');
				});
				if (!keys.length) return;

				setBusy(true, ewtLinkAdmin.i18n.applying);
				var fd = new FormData();
				fd.append('action', 'ewt_link_apply_post');
				fd.append('nonce', ewtLinkAdmin.postNonce);
				fd.append('post_id', postId);
				keys.forEach(function (k) { fd.append('keys[]', k); });

				postForm(fd).then(function (applyData) {
					setBusy(false, '');
					statusEl.textContent = ewtLinkAdmin.i18n.applied;
					resultsEl.innerHTML = buildSummaryHtml(applyData, true);
				}).catch(function () { setBusy(false, ewtLinkAdmin.i18n.error); });
			});
		}

		function buildSummaryHtml(data, wasSelective) {
			var html = '<p class="ewt-success">✅ ' + (data.applied ? data.applied.length : 0) + ' bağlantı eklendi' +
				(data.ozel_ders_linked ? ' (özel ders sayfasına link juice sağlandı ⭐)' : '') + '.</p>';
			if (data.safety_failed) {
				html += '<p class="ewt-error">⚠️ Bazı bölümlerde güvenlik kontrolünden geçilemedi, o kısımlar değiştirilmeden bırakıldı.</p>';
			}
			html += '<p><button type="button" class="button" onclick="window.location.reload();">🔄 Sayfayı Yenile</button></p>';
			return html;
		}
	}

	/* =========================================================
	 * 2) TOPLU UYGULA SAYFASI
	 * ========================================================= */
	function initBulkPage() {
		var previewBtn = document.getElementById('ewt_link_bulk_preview_btn');
		if (!previewBtn) return;

		var applyBtn  = document.getElementById('ewt_link_bulk_apply_btn');
		var statusEl  = document.getElementById('ewt_link_bulk_status');
		var wrap      = document.getElementById('ewt_link_bulk_progress_wrap');
		var bar       = document.getElementById('ewt_link_bulk_progress_bar');
		var logEl     = document.getElementById('ewt_link_bulk_log');
		var allCb     = document.querySelector('.ewt-link-bulk-type[value="__all__"]');
		var typeCbs   = Array.prototype.slice.call(document.querySelectorAll('.ewt-link-bulk-type')).filter(function (cb) { return cb !== allCb; });

		typeCbs.forEach(function (cb) {
			cb.addEventListener('change', function () {
				if (cb.checked) allCb.checked = false;
			});
		});
		allCb.addEventListener('change', function () {
			if (allCb.checked) typeCbs.forEach(function (cb) { cb.checked = false; });
		});

		previewBtn.addEventListener('click', function () { start(false); });
		applyBtn.addEventListener('click', function () {
			if (!window.confirm(ewtLinkAdmin.i18n.confirmBulkApply)) return;
			start(true);
		});

		function selectedTypes() {
			if (allCb.checked) return null;
			var picked = typeCbs.filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
			return picked.length ? picked : null;
		}

		function start(apply) {
			previewBtn.disabled = true;
			applyBtn.disabled = true;
			wrap.style.display = 'block';
			bar.style.width = '0%';
			logEl.innerHTML = '';
			statusEl.textContent = ewtLinkAdmin.i18n.starting;
			processBatch(0, apply, selectedTypes());
		}

		function processBatch(offset, apply, types) {
			var fd = new FormData();
			fd.append('action', 'ewt_link_bulk_batch');
			fd.append('nonce', ewtLinkAdmin.bulkNonce);
			fd.append('offset', offset);
			fd.append('batch_size', 15);
			if (apply) fd.append('apply', '1');
			if (types) types.forEach(function (t) { fd.append('post_types[]', t); });

			fetch(ewtLinkAdmin.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { handleError(); return; }
					var d = res.data;
					var total = d.total || 0;
					var doneCount = Math.min(d.next_offset, total);
					var pct = total > 0 ? Math.min(100, Math.round((doneCount / total) * 100)) : 100;

					bar.style.width = pct + '%';
					statusEl.textContent = doneCount + ' / ' + total;

					(d.results || []).forEach(function (r) {
						if (r.applied_count > 0) {
							var detailItems = '';
							(r.details || []).forEach(function (item) {
								var icon = TYPE_ICON[item.type] || '🔗';
								detailItems += '<li>' + icon + ' “' + escapeHtml(item.anchor) + '” → <code>' + escapeHtml(item.url) + '</code></li>';
							});
							var titleHtml = r.edit_url
								? '<a href="' + escapeAttr(r.edit_url) + '" target="_blank" onclick="event.stopPropagation();">' + escapeHtml(r.title) + '</a>'
								: escapeHtml(r.title);

							logEl.innerHTML += '<details class="ewt-link-bulk-item" open>' +
								'<summary>✅ <strong>' + titleHtml + '</strong> — ' + r.applied_count + ' bağlantı' +
								(r.ozel_ders ? ' ⭐' : '') + '</summary>' +
								'<ul class="ewt-link-results-list">' + detailItems + '</ul>' +
								'</details>';
						}
					});
					logEl.scrollTop = logEl.scrollHeight;

					if (d.done) {
						statusEl.textContent = ewtLinkAdmin.i18n.done + ' (' + total + ' içerik tarandı)';
						previewBtn.disabled = false;
						applyBtn.disabled = false;
					} else {
						processBatch(d.next_offset, apply, types);
					}
				})
				.catch(handleError);
		}

		function handleError() {
			statusEl.textContent = ewtLinkAdmin.i18n.error;
			previewBtn.disabled = false;
			applyBtn.disabled = false;
		}
	}

	/* =========================================================
	 * YARDIMCI
	 * ========================================================= */
	function postForm(fd) {
		return fetch(ewtLinkAdmin.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) throw new Error('fail');
				return res.data;
			});
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function escapeAttr(s) { return escapeHtml(s); }
})();
