/**
 * Ziyaretçi E-Kitap Oluşturucu — ön yüz mantığı
 * Kullanıcı girişi gerektirmez. Seçimler localStorage'da tutulur (sayfa yenilense de kaybolmaz).
 */
(function () {
	'use strict';

	var root = document.querySelector('.ewt-ebook');
	if (!root || typeof ewtEbook === 'undefined') return;

	var form        = root.querySelector('.ewt-ebook-form');
	var cptSel      = root.querySelector('.ewt-ebook-cpt');
	var levelSel    = root.querySelector('.ewt-ebook-level');
	var searchInput = root.querySelector('.ewt-ebook-search');
	var listEl      = root.querySelector('.ewt-ebook-list');
	var listTitle   = root.querySelector('.ewt-ebook-list-title');
	var moreBtn     = root.querySelector('.ewt-ebook-more');
	var selectAll   = root.querySelector('.ewt-ebook-select-all');
	var clearBtn    = root.querySelector('.ewt-ebook-clear');
	var cart        = root.querySelector('.ewt-ebook-cart');
	var cartList    = root.querySelector('.ewt-ebook-cart-list');
	var countEl     = root.querySelector('.ewt-ebook-count');
	var finalize    = root.querySelector('.ewt-ebook-finalize');
	var resultEl    = root.querySelector('.ewt-ebook-result');
	var submitBtn   = root.querySelector('.ewt-ebook-submit');
	var nonceField  = form.querySelector('#ewt_ebook_nonce');

	var MAX      = parseInt(root.getAttribute('data-max'), 10) || 30;
	var STORE    = 'ewtEbookCart';
	var selected = loadCart();   // { id: title }
	var page     = 1;
	var maxPages = 1;
	var loading  = false;
	var searchTimer = null;

	/* ---------------- yardımcılar ---------------- */

	function loadCart() {
		try {
			var raw = window.localStorage.getItem(STORE);
			var obj = raw ? JSON.parse(raw) : {};
			return (obj && typeof obj === 'object') ? obj : {};
		} catch (e) { return {}; }
	}

	function saveCart() {
		try { window.localStorage.setItem(STORE, JSON.stringify(selected)); } catch (e) {}
	}

	function selectedIds() {
		return Object.keys(selected);
	}

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', nonceField ? nonceField.value : '');
		Object.keys(data).forEach(function (k) {
			if (Array.isArray(data[k])) {
				data[k].forEach(function (v) { body.append(k + '[]', v); });
			} else {
				body.append(k, data[k]);
			}
		});

		return fetch(ewtEbook.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function notify(msg, type) {
		resultEl.hidden = false;
		resultEl.className = 'ewt-ebook-result ewt-ebook-result-' + (type || 'info');
		resultEl.innerHTML = '<p>' + esc(msg) + '</p>';
		resultEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}

	/* ---------------- sepet ---------------- */

	function renderCart() {
		var ids = selectedIds();
		countEl.textContent = ids.length;

		cart.hidden     = ids.length === 0;
		finalize.hidden = ids.length === 0;
		clearBtn.hidden = ids.length === 0;

		cartList.innerHTML = ids.map(function (id) {
			return '<li data-id="' + esc(id) + '"><span>' + esc(selected[id]) + '</span>' +
				'<button type="button" class="ewt-ebook-remove" aria-label="Kaldır">×</button></li>';
		}).join('');

		// listede görünen kutuların durumunu senkronla
		listEl.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
			cb.checked = Object.prototype.hasOwnProperty.call(selected, cb.value);
			cb.closest('.ewt-ebook-item').classList.toggle('is-selected', cb.checked);
		});
	}

	cartList.addEventListener('click', function (e) {
		var btn = e.target.closest('.ewt-ebook-remove');
		if (!btn) return;
		var id = btn.parentNode.getAttribute('data-id');
		delete selected[id];
		saveCart();
		renderCart();
	});

	function toggle(id, title, on) {
		if (on) {
			if (selectedIds().length >= MAX) {
				notify('Bir e-kitaba en fazla ' + MAX + ' konu ekleyebilirsin.', 'warn');
				return false;
			}
			selected[id] = title;
		} else {
			delete selected[id];
		}
		saveCart();
		renderCart();
		return true;
	}

	/* ---------------- liste ---------------- */

	function itemHtml(it) {
		return '<label class="ewt-ebook-item">' +
			'<input type="checkbox" value="' + esc(it.id) + '" data-title="' + esc(it.title) + '" />' +
			'<span class="ewt-ebook-item-body">' +
				'<span class="ewt-ebook-item-title">' + esc(it.title) + '</span>' +
				(it.levels ? '<span class="ewt-ebook-item-level">' + esc(it.levels) + '</span>' : '') +
				(it.excerpt ? '<span class="ewt-ebook-item-ex">' + esc(it.excerpt) + '</span>' : '') +
			'</span></label>';
	}

	function load(reset) {
		if (loading) return;
		if (!cptSel.value) return;

		loading = true;
		if (reset) {
			page = 1;
			listEl.innerHTML = '<p class="ewt-ebook-placeholder">Konular yükleniyor…</p>';
		} else {
			moreBtn.textContent = 'Yükleniyor…';
		}

		post('ewt_ebook_browse', {
			cpt: cptSel.value,
			level: levelSel.value || '',
			search: searchInput.value || '',
			page: page
		}).then(function (res) {
			loading = false;
			moreBtn.textContent = 'Daha fazla konu yükle';

			if (!res || !res.success) {
				var m = (res && res.data && res.data.message) || 'Konular yüklenemedi.';
				listEl.innerHTML = '<p class="ewt-ebook-placeholder">' + esc(m) + '</p>';
				return;
			}

			var d = res.data;
			maxPages = d.maxPages;

			// seviye <select>'ini ilk sayfada doldur
			if (d.levels && d.levels.length) {
				var cur = levelSel.value;
				levelSel.innerHTML = '<option value="">Tüm seviyeler</option>' +
					d.levels.map(function (l) {
						return '<option value="' + esc(l.slug) + '">' + esc(l.name) + ' (' + esc(l.count) + ')</option>';
					}).join('');
				levelSel.value = cur;
				levelSel.disabled = false;
			} else if (page === 1 && (!d.levels || !d.levels.length)) {
				levelSel.innerHTML = '<option value="">Tüm seviyeler</option>';
				levelSel.disabled = false;
			}

			if (!d.items.length && page === 1) {
				listEl.innerHTML = '<p class="ewt-ebook-placeholder">Bu filtreye uyan konu bulunamadı. Farklı bir seviye veya arama deneyin.</p>';
				listTitle.textContent = 'Konular';
				moreBtn.hidden = true;
				selectAll.hidden = true;
				return;
			}

			var html = d.items.map(itemHtml).join('');
			if (page === 1) {
				listEl.innerHTML = html;
			} else {
				listEl.insertAdjacentHTML('beforeend', html);
			}

			listTitle.textContent = 'Konular (' + d.total + ')';
			moreBtn.hidden   = page >= maxPages;
			selectAll.hidden = false;

			renderCart();
		}).catch(function () {
			loading = false;
			listEl.innerHTML = '<p class="ewt-ebook-placeholder">Bağlantı hatası. Lütfen tekrar deneyin.</p>';
		});
	}

	listEl.addEventListener('change', function (e) {
		var cb = e.target;
		if (cb.type !== 'checkbox') return;
		var ok = toggle(cb.value, cb.getAttribute('data-title'), cb.checked);
		if (!ok) cb.checked = false;
		cb.closest('.ewt-ebook-item').classList.toggle('is-selected', cb.checked);
	});

	moreBtn.addEventListener('click', function () {
		if (page < maxPages) { page++; load(false); }
	});

	selectAll.addEventListener('click', function () {
		var boxes = listEl.querySelectorAll('input[type="checkbox"]:not(:checked)');
		for (var i = 0; i < boxes.length; i++) {
			if (selectedIds().length >= MAX) {
				notify('En fazla ' + MAX + ' konu seçebilirsin. İlk ' + MAX + ' konu eklendi.', 'warn');
				break;
			}
			selected[boxes[i].value] = boxes[i].getAttribute('data-title');
		}
		saveCart();
		renderCart();
	});

	clearBtn.addEventListener('click', function () {
		selected = {};
		saveCart();
		renderCart();
	});

	cptSel.addEventListener('change', function () {
		levelSel.value = '';
		levelSel.disabled = true;
		levelSel.innerHTML = '<option value="">Yükleniyor…</option>';
		load(true);
	});

	levelSel.addEventListener('change', function () { load(true); });

	searchInput.addEventListener('input', function () {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function () { load(true); }, 450);
	});

	/* ---------------- üretim ---------------- */

	form.addEventListener('submit', function (e) {
		e.preventDefault();

		var ids = selectedIds();
		if (!ids.length) {
			notify('Önce listeden en az bir konu seç.', 'warn');
			return;
		}

		var formatEl = form.querySelector('input[name="ebook_format"]:checked');
		var format   = formatEl ? formatEl.value : 'pdf';
		var titleEl  = root.querySelector('.ewt-ebook-booktitle');
		var emailEl  = root.querySelector('.ewt-ebook-email');

		// PDF yeni sekmede açılacağı için pencereyi ŞİMDİ aç (pop-up engelleyici,
		// kullanıcı tıklamasından kopuk window.open çağrılarını bloke eder).
		var win = null;
		if (format === 'pdf') {
			win = window.open('', '_blank');
			if (win) {
				win.document.write('<!doctype html><meta charset="utf-8"><title>E-kitabın hazırlanıyor…</title>' +
					'<body style="font-family:system-ui,sans-serif;text-align:center;padding:60px;color:#16233a">' +
					'<h2>📘 E-kitabın hazırlanıyor…</h2><p>Birkaç saniye sürebilir.</p></body>');
			}
		}

		submitBtn.disabled = true;
		submitBtn.textContent = 'Hazırlanıyor…';
		resultEl.hidden = true;

		post('ewt_ebook_create', {
			ids: ids,
			format: format,
			book_title: titleEl ? titleEl.value : '',
			email: emailEl ? emailEl.value : '',
			rendered_at: form.getAttribute('data-rendered-at'),
			ewt_ebook_hp: form.querySelector('.ewt-ebook-hp').value
		}).then(function (res) {
			submitBtn.disabled = false;
			submitBtn.textContent = '📘 E-Kitabımı Oluştur';

			if (!res || !res.success) {
				if (win) win.close();
				var msg = (res && res.data && res.data.message) || 'E-kitap oluşturulamadı.';
				if (res && res.data && res.data.code === 'nonce_expired') {
					msg = 'Oturumun süresi doldu. Sayfayı yenileyip tekrar dene.';
				}
				notify(msg, 'error');
				return;
			}

			var d = res.data;

			if (d.format === 'pdf') {
				if (win) {
					win.location.href = d.url;
				} else {
					window.open(d.url, '_blank');
				}
				resultEl.hidden = false;
				resultEl.className = 'ewt-ebook-result ewt-ebook-result-ok';
				resultEl.innerHTML =
					'<h4>📘 ' + esc(d.title) + ' hazır!</h4>' +
					'<p>' + esc(d.count) + ' konu yeni sekmede açıldı. Açılan yazdırma penceresinde ' +
					'yazıcı olarak <b>"PDF olarak kaydet"</b> seçeneğini işaretle.</p>' +
					'<p><a class="ewt-ebook-dl" href="' + esc(d.url) + '" target="_blank" rel="noopener">Sekme açılmadıysa buraya tıkla →</a></p>';
			} else {
				resultEl.hidden = false;
				resultEl.className = 'ewt-ebook-result ewt-ebook-result-ok';
				resultEl.innerHTML =
					'<h4>📘 ' + esc(d.title) + ' hazır!</h4>' +
					'<p>' + esc(d.count) + ' konuluk EPUB dosyan oluşturuldu. Telefonunda Apple Books, Google Play Books ' +
					'veya Moon+ Reader gibi bir uygulamayla açabilirsin.</p>' +
					'<p><a class="ewt-ebook-dl" href="' + esc(d.url) + '" download>⬇ EPUB dosyasını indir</a></p>';
				resultEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		}).catch(function () {
			if (win) win.close();
			submitBtn.disabled = false;
			submitBtn.textContent = '📘 E-Kitabımı Oluştur';
			notify('Bağlantı hatası. Lütfen tekrar dene.', 'error');
		});
	});

	/* ---------------- başlangıç ---------------- */

	// Ders sayfasındaki CTA'dan gelindiyse (?ewt_add=ID) o ders sepete hazır eklenir.
	var pre = ewtEbook.preselect;
	if (pre && pre.id && !Object.prototype.hasOwnProperty.call(selected, String(pre.id))) {
		if (selectedIds().length < MAX) {
			selected[String(pre.id)] = pre.title;
			saveCart();
		}
	}

	renderCart();

	if (pre && pre.id) {
		// Adres çubuğunu temizle ki sayfa yenilendiğinde aynı ders tekrar eklenmeye çalışılmasın.
		try {
			var clean = window.location.pathname + window.location.hash;
			window.history.replaceState({}, '', clean);
		} catch (e) {}
		root.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	if (cptSel.value) load(true);
})();
