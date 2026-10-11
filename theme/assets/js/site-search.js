/**
 * English Web TR — Site İçi Canlı Arama
 * Header'daki 🔍 butonuna basınca açılan tam ekran arama paneli.
 * Kullanıcı yazdıkça (debounce ile) REST API'ye sorgu atar ve sonuçları
 * Google tarzı bir açılır liste olarak anında gösterir.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var trigger    = document.getElementById('ewt-search-trigger');
		var overlay    = document.getElementById('ewt-search-overlay');
		if (!trigger || !overlay || typeof ewtSearch === 'undefined') return;

		var backdrop   = overlay.querySelector('[data-ewt-search-close]');
		var closeBtn   = document.getElementById('ewt-search-close-btn');
		var input      = document.getElementById('ewt-search-input');
		var list       = document.getElementById('ewt-search-results-list');
		var statusEl   = document.getElementById('ewt-search-status');
		var viewAllEl  = document.getElementById('ewt-search-view-all');

		var debounceTimer = null;
		var currentAbort  = null;
		var activeIndex   = -1;
		var lastQuery     = '';

		input.setAttribute('placeholder', ewtSearch.i18n.placeholder);

		/* ---------------- Aç / Kapat ---------------- */

		function openOverlay() {
			overlay.classList.add('is-open');
			overlay.setAttribute('aria-hidden', 'false');
			document.body.classList.add('ewt-search-lock');
			window.setTimeout(function () { input.focus(); }, 60);
		}

		function closeOverlay() {
			overlay.classList.remove('is-open');
			overlay.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('ewt-search-lock');
			trigger.focus();
		}

		trigger.addEventListener('click', openOverlay);
		if (closeBtn) closeBtn.addEventListener('click', closeOverlay);
		if (backdrop) backdrop.addEventListener('click', closeOverlay);

		document.addEventListener('keydown', function (e) {
			// Cmd/Ctrl + K: her yerden aramayı aç (isteğe bağlı kısayol)
			if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
				e.preventDefault();
				openOverlay();
				return;
			}
			if (!overlay.classList.contains('is-open')) return;
			if (e.key === 'Escape') {
				closeOverlay();
			} else if (e.key === 'ArrowDown') {
				e.preventDefault();
				moveActive(1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				moveActive(-1);
			} else if (e.key === 'Enter') {
				var items = list.querySelectorAll('li[data-url]');
				if (activeIndex >= 0 && items[activeIndex]) {
					e.preventDefault();
					window.location.href = items[activeIndex].getAttribute('data-url');
				} else if (items.length) {
					e.preventDefault();
					window.location.href = items[0].getAttribute('data-url');
				}
			}
		});

		/* ---------------- Klavye ile gezinme ---------------- */

		function moveActive(delta) {
			var items = list.querySelectorAll('li[data-url]');
			if (!items.length) return;

			if (activeIndex >= 0 && items[activeIndex]) {
				items[activeIndex].classList.remove('is-active');
			}
			activeIndex += delta;
			if (activeIndex < 0) activeIndex = items.length - 1;
			if (activeIndex >= items.length) activeIndex = 0;

			items[activeIndex].classList.add('is-active');
			items[activeIndex].scrollIntoView({ block: 'nearest' });
			input.setAttribute('aria-activedescendant', items[activeIndex].id);
		}

		/* ---------------- Arama ---------------- */

		input.addEventListener('input', function () {
			var term = input.value.trim();
			window.clearTimeout(debounceTimer);
			debounceTimer = window.setTimeout(function () {
				performSearch(term);
			}, 250);
		});

		function performSearch(term) {
			lastQuery = term;
			activeIndex = -1;

			if (term.length < ewtSearch.minChars) {
				list.innerHTML = '';
				viewAllEl.style.display = 'none';
				statusEl.textContent = term.length === 0 ? '' : ewtSearch.i18n.typeMore;
				input.setAttribute('aria-expanded', 'false');
				return;
			}

			if (currentAbort) currentAbort.abort();
			currentAbort = new AbortController();

			statusEl.textContent = ewtSearch.i18n.loading;

			var url = ewtSearch.restUrl + '?q=' + encodeURIComponent(term);

			fetch(url, { signal: currentAbort.signal, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (term !== lastQuery) return; // eskimiş yanıt, yok say
					renderResults(data, term);
				})
				.catch(function (err) {
					if (err && err.name === 'AbortError') return;
					statusEl.textContent = ewtSearch.i18n.noResults;
				});
		}

		function escapeHtml(str) {
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}

		function highlight(text, term) {
			var safeText = escapeHtml(text);
			var safeTerm = escapeHtml(term).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			if (!safeTerm) return safeText;
			var re = new RegExp('(' + safeTerm + ')', 'ig');
			return safeText.replace(re, '<mark>$1</mark>');
		}

		function renderResults(data, term) {
			list.innerHTML = '';
			var results = data.results || [];

			if (!results.length) {
				statusEl.textContent = ewtSearch.i18n.noResults;
				viewAllEl.style.display = 'none';
				input.setAttribute('aria-expanded', 'false');
				return;
			}

			input.setAttribute('aria-expanded', 'true');
			statusEl.textContent = data.total + ' ' + ewtSearch.i18n.resultsFound;

			results.forEach(function (item, idx) {
				var li = document.createElement('li');
				li.className = 'ewt-search-result-item';
				li.setAttribute('role', 'option');
				li.setAttribute('id', 'ewt-search-result-' + idx);
				li.setAttribute('data-url', item.url);

				var thumbHtml = item.thumbnail
					? '<img class="ewt-search-result-thumb" src="' + escapeHtml(item.thumbnail) + '" alt="" loading="lazy" />'
					: '<span class="ewt-search-result-thumb-placeholder">📘</span>';

				var metaHtml = '<span class="ewt-badge-cpt">' + escapeHtml(item.cpt_label || '') + '</span>';
				if (item.seviye) {
					metaHtml += '<span class="ewt-badge-seviye-mini">' + escapeHtml(item.seviye) + '</span>';
				}

				li.innerHTML =
					'<a href="' + escapeHtml(item.url) + '">' +
						thumbHtml +
						'<span class="ewt-search-result-text">' +
							'<span class="ewt-search-result-meta">' + metaHtml + '</span>' +
							'<p class="ewt-search-result-title">' + highlight(item.title, term) + '</p>' +
							'<p class="ewt-search-result-snippet">' + highlight(item.excerpt, term) + '</p>' +
						'</span>' +
					'</a>';

				list.appendChild(li);
			});

			if (data.total > results.length) {
				viewAllEl.style.display = 'inline-block';
				viewAllEl.href = data.search_page_url || (ewtSearch.homeUrl + '?s=' + encodeURIComponent(term));
				viewAllEl.textContent = ewtSearch.i18n.viewAll;
			} else {
				viewAllEl.style.display = 'none';
			}
		}
	});
})();
