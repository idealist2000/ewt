jQuery(function ($) {

	/* ==========================================================
	   YARDIMCI: zorluk <select>'ini seçilen CPT'ye göre doldur
	   ========================================================== */
	function populateDifficultySelect($select, postType) {
		var map = (typeof ewtBulk !== 'undefined' && ewtBulk.difficultyMap) ? ewtBulk.difficultyMap : {};
		var opts = map[postType] || map['post'] || {};
		$select.empty();
		$.each(opts, function (value, label) {
			$select.append($('<option>').val(value).text(label));
		});
	}

	/* ==========================================================
	   YARDIMCI: seçilen CPT için dashboard'dan önceden oluşturulmuş
	   gerçek "Seviye" terimlerini "chip" olarak göster (bilgilendirme amaçlı)
	   ========================================================== */
	function renderSeviyeChips(postType) {
		var map = (typeof ewtBulk !== 'undefined' && ewtBulk.seviyeTermsMap) ? ewtBulk.seviyeTermsMap : {};
		var terms = map[postType] || [];
		var $chips = $('#ewt_bulk_seviye_chips');
		var $hint = $('#ewt_bulk_seviye_hint');
		if (!$chips.length) return;

		$chips.empty();
		if (!terms.length) {
			$hint.text('Henüz kategori yok. CSV kategori oluşturma seçeneği açıksa dolu seviye hücrelerinden kategori oluşturulur; boş IELTS hücreleri konuya göre eşleştirilir. Diğer boş hücrelerde yedek zorluk kullanılır.');
			return;
		}
		terms.forEach(function (t) {
			$chips.append($('<span class="ewt-seviye-chip">').text(t.name));
		});
		$hint.text('Mevcut kategoriler: ' + terms.length + '. CSV kategori oluşturma seçeneği açıksa dolu hücreler kullanılır/oluşturulur; boş IELTS hücreleri konuya göre atanır. Diğer durumlarda AI mevcut kategorilerden seçim yapar.');
	}

	function cptLabelToSlug(input) {
		if (!input) return '';
		var trimmed = input.trim();
		if (!trimmed) return '';
		var map = (typeof ewtBulk !== 'undefined' && ewtBulk.cptMap) ? ewtBulk.cptMap : {};
		// Doğrudan slug eşleşmesi
		if (map.hasOwnProperty(trimmed)) return trimmed;
		// Görünen ada göre eşleşme (büyük/küçük harf duyarsız)
		var lower = trimmed.toLowerCase();
		var found = '';
		$.each(map, function (slug, label) {
			if (label.toLowerCase() === lower) found = slug;
		});
		return found || trimmed; // Geçersiz dolu CPT sunucuda açık hata verir.
	}

	/* ==========================================================
	   BASİT CSV PARSER (virgül ayraçlı, çift tırnak destekli)
	   ========================================================== */
	function parseCSV(text) {
		var rows = [];
		var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
		lines.forEach(function (line) {
			if (line.trim() === '') return;
			var cells = [];
			var cur = '';
			var inQuotes = false;
			for (var i = 0; i < line.length; i++) {
				var ch = line[i];
				if (inQuotes) {
					if (ch === '"') {
						if (line[i + 1] === '"') { cur += '"'; i++; }
						else { inQuotes = false; }
					} else { cur += ch; }
				} else {
					if (ch === '"') { inQuotes = true; }
					else if (ch === ',') { cells.push(cur); cur = ''; }
					else { cur += ch; }
				}
			}
			cells.push(cur);
			rows.push(cells.map(function (c) { return c.trim(); }));
		});
		return rows;
	}

	function rowsToItems(rows) {
		if (!rows.length) return [];
		var headerCandidates = rows[0].map(function (c) { return c.toLowerCase(); });
		var isHeader = headerCandidates.some(function (c) {
			return ['keyword', 'anahtar', 'anahtar kelime', 'fk', 'cpt', 'tur', 'tür', 'seviye', 'zorluk', 'difficulty', 'kelime_sayisi', 'kelime sayısı', 'word_count'].indexOf(c) !== -1;
		});

		var colIndex = { keyword: 0, cpt: 1, difficulty: 2, wordCount: 3 };
		var dataRows = rows;

		if (isHeader) {
			dataRows = rows.slice(1);
			headerCandidates.forEach(function (h, idx) {
				if (['keyword', 'anahtar', 'anahtar kelime', 'fk'].indexOf(h) !== -1) colIndex.keyword = idx;
				else if (['cpt', 'tur', 'tür', 'icerik_turu', 'post_type'].indexOf(h) !== -1) colIndex.cpt = idx;
				else if (['seviye', 'zorluk', 'difficulty'].indexOf(h) !== -1) colIndex.difficulty = idx;
				else if (['kelime_sayisi', 'kelime sayısı', 'word_count', 'wordcount'].indexOf(h) !== -1) colIndex.wordCount = idx;
			});
		}

		var items = [];
		dataRows.forEach(function (cols) {
			var keyword = (cols[colIndex.keyword] || '').trim();
			if (!keyword) return;
			items.push({
				fk: keyword,
				post_type: cptLabelToSlug(cols[colIndex.cpt] || ''),
				difficulty: (cols[colIndex.difficulty] || '').trim(),
				word_count: (cols[colIndex.wordCount] || '').trim()
			});
		});
		return items;
	}

	/* ==========================================================
	   EŞZAMANLI (CONCURRENCY-LIMITED) İŞLEM MOTORU
	   Hem toplu içerik üretimi hem toplu görsel atama bu motoru paylaşır.
	   Ağ hatalarında (bağlantı kopması vb.) her istek 1 kez otomatik yeniden denenir.
	   ========================================================== */
	function postWithRetry(url, data, retries) {
		var deferred = $.Deferred();
		function attempt(remaining) {
			$.post(url, data).done(function (res) {
				deferred.resolve(res);
			}).fail(function () {
				if (remaining > 0) {
					attempt(remaining - 1);
				} else {
					deferred.resolve({ success: false, data: 'Bağlantı hatası (1 kez yeniden denendi, başarısız).' });
				}
			});
		}
		attempt(retries);
		return deferred.promise();
	}

	/**
	 * items: işlenecek dizi
	 * concurrency: aynı anda kaç workerFn çalışacağı
	 * workerFn(item, index) -> bir $.Deferred/promise döndürmeli, HER ZAMAN .always ile çözülmeli (reddedilmemeli)
	 * callbacks: { onItemDone(item, index, res), onDone(wasStopped) }
	 * Dönen nesne: { stop: function() }
	 */
	function runConcurrentQueue(items, concurrency, workerFn, callbacks) {
		callbacks = callbacks || {};
		var total = items.length;
		var nextIndex = 0, activeCount = 0, doneCount = 0, stopped = false;

		function tryFinish() {
			if (doneCount >= total || (stopped && activeCount === 0)) {
				callbacks.onDone && callbacks.onDone(stopped);
				return true;
			}
			return false;
		}

		function pump() {
			if (stopped) return;
			while (activeCount < concurrency && nextIndex < total) {
				var idx = nextIndex;
				nextIndex++;
				activeCount++;
				(function (index) {
					workerFn(items[index], index).always(function (res) {
						activeCount--;
						doneCount++;
						callbacks.onItemDone && callbacks.onItemDone(items[index], index, res);
						if (!tryFinish()) pump();
					});
				})(idx);
			}
		}

		if (total === 0) {
			callbacks.onDone && callbacks.onDone(false);
		} else {
			pump();
		}

		return { stop: function () { stopped = true; } };
	}

	function downloadCSV(rows, filename) {
		var csv = rows.map(function (r) {
			return r.map(function (cell) {
				var s = cell === null || cell === undefined ? '' : String(cell);
				if (/[",\n]/.test(s)) s = '"' + s.replace(/"/g, '""') + '"';
				return s;
			}).join(',');
		}).join('\n');
		var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}

	/* ==========================================================
	   TOPLU İÇERİK ÜRETİMİ SAYFASI
	   ========================================================== */
	var $postTypeSelect = $('#ewt_bulk_post_type');
	var $difficultySelect = $('#ewt_bulk_difficulty');
	var $startBtn = $('#ewt_bulk_start_btn');
	var $stopBtn = $('#ewt_bulk_stop_btn');
	var $exportBtn = $('#ewt_bulk_export_btn');
	var csvItems = null; // null = CSV kullanılmıyor, dizi = CSV yüklendi
	var contentQueue = null;
	var contentResultsLog = [];

	if ($postTypeSelect.length) {
		populateDifficultySelect($difficultySelect, $postTypeSelect.val());
		renderSeviyeChips($postTypeSelect.val());
		$postTypeSelect.on('change', function () {
			populateDifficultySelect($difficultySelect, $(this).val());
			renderSeviyeChips($(this).val());
		});
	}

	// CSV dosyası seçildiğinde oku ve önizle
	$('#ewt_bulk_csv_file').on('change', function (e) {
		var file = e.target.files[0];
		if (!file) return;
		var reader = new FileReader();
		reader.onload = function (ev) {
			var rows = parseCSV(ev.target.result);
			csvItems = rowsToItems(rows);

			var $body = $('#ewt_bulk_csv_preview_body').empty();
			csvItems.slice(0, 50).forEach(function (item) {
				var cptLabel = item.post_type ? (ewtBulk.cptMap[item.post_type] || item.post_type) : '(genel ayar)';
				$body.append(
					$('<tr>').append(
						$('<td>').text(item.fk),
						$('<td>').text(cptLabel),
						$('<td>').text(item.difficulty || '(boş → CSV seçeneğine göre otomatik)'),
						$('<td>').text(item.word_count || '(genel ayar)')
					)
				);
			});
			var extra = csvItems.length > 50 ? ' (ilk 50 satır önizleniyor)' : '';
			$('#ewt_bulk_csv_summary').text(csvItems.length + ' satır yüklendi' + extra + '. "Toplu Üretimi Başlat" bu listeyi kullanacak ve üretilen yazılar doğrudan yayınlanacak.');
			$('#ewt_bulk_csv_preview_wrap').show();
		};
		reader.readAsText(file, 'UTF-8');
	});

	$('#ewt_bulk_csv_clear_btn').on('click', function () {
		csvItems = null;
		$('#ewt_bulk_csv_file').val('');
		$('#ewt_bulk_csv_preview_wrap').hide();
		$('#ewt_bulk_csv_summary').text('');
	});

	$('#ewt_bulk_csv_template_btn').on('click', function () {
		var sample = 'keyword,cpt,seviye,kelime_sayisi\n' +
			'a1 seviyesi ingilizce kelimeler,ewt_primary,,350\n' +
			'present perfect tense konu anlatimi,ewt_secondary,,500\n' +
			'toefl reading strategies,ewt_toefl,toefl-80-100,600\n';
		var blob = new Blob([sample], { type: 'text/csv;charset=utf-8;' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = 'ewt-toplu-icerik-sablonu.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	});

	if ($startBtn.length) {
		$startBtn.on('click', function () {
			var globalPostType = $('#ewt_bulk_post_type').val();
			var globalDifficulty = $('#ewt_bulk_difficulty').val();
			var globalWordCount = $('#ewt_bulk_word_count').val();
			var provider = $('#ewt_bulk_provider').val();
			var status   = $('#ewt_bulk_status').val();
			var template = $('#ewt_bulk_prompt_template').val();
			var inlineImagesEnabled = $('#ewt_bulk_inline_images_enabled').is(':checked');
			var inlineImagesCount = inlineImagesEnabled ? parseInt($('#ewt_bulk_inline_images_count').val(), 10) : 0;
			var concurrency = Math.max(1, Math.min(8, parseInt($('#ewt_bulk_concurrency').val(), 10) || 4));

			var csvImportMode = !!(csvItems && csvItems.length);
			var csvCategoryMode = !!(csvItems && csvItems.length && $('#ewt_bulk_csv_categories').is(':checked'));
			var items;
			if (csvItems && csvItems.length) {
				items = csvItems.map(function (item) {
					return {
						fk: item.fk,
						post_type: item.post_type || globalPostType,
						difficulty: item.difficulty, // BOŞ bırakılmışsa (CSV'de gerçekten boşsa) otomatik tespite düşer; global zorluk buraya YAZILMAZ
						word_count: item.word_count || globalWordCount
					};
				});
			} else {
				var lines = $('#ewt_bulk_fk_list').val().split('\n')
					.map(function (l) { return l.trim(); })
					.filter(function (l) { return l.length > 0; });
				items = lines.map(function (fk) {
					return { fk: fk, post_type: globalPostType, difficulty: '', word_count: globalWordCount };
				});
			}

			if (!items.length) {
				alert('Lütfen en az bir focus keyword girin veya bir CSV yükleyin.');
				return;
			}

			$('#ewt_bulk_results_table').show();
			$('#ewt_bulk_results_body').empty();
			$exportBtn.hide();
			contentResultsLog = [];
			$startBtn.prop('disabled', true);
			$stopBtn.show().prop('disabled', false);

			var total = items.length, done = 0;
			$('#ewt_bulk_progress_text').text(' İşleniyor: 0/' + total + ' (eşzamanlı: ' + concurrency + ')');

			var rowsByIndex = {};

			contentQueue = runConcurrentQueue(items, concurrency, function (item) {
				var cptLabel = ewtBulk.cptMap[item.post_type] || item.post_type;
				var $row = $('<tr>').append(
					$('<td>').text(item.fk),
					$('<td>').text(cptLabel),
					$('<td>').html('⏳'),
					$('<td>').html('⏳ İşleniyor...'),
					$('<td>').text('-')
				);
				$('#ewt_bulk_results_body').append($row);
				rowsByIndex[item.fk + '|' + cptLabel] = $row;

				return postWithRetry(ewtBulk.ajaxUrl, {
					action: 'ewt_bulk_generate_content',
					nonce: ewtBulk.nonce,
					fk: item.fk,
					post_type: item.post_type,
					provider: provider,
					status: csvImportMode ? 'publish' : status,
					csv_import: csvImportMode ? '1' : '0',
					template: template,
					difficulty: item.difficulty,
					fallback_difficulty: globalDifficulty,
					word_count: item.word_count,
					inline_images: inlineImagesCount,
					csv_categories: csvCategoryMode ? '1' : '0'
				}, 1).then(function (res) {
					return { res: res, $row: $row, item: item, cptLabel: cptLabel };
				});
			}, {
				onItemDone: function (item, index, wrapped) {
					done++;
					var res = wrapped.res, $row = wrapped.$row, cptLabel = wrapped.cptLabel;
					var seviyeName = '—', statusText, resultHtml;
					if (res && res.success) {
						seviyeName = res.data.seviye_name || '—';
						statusText = '✅ Başarılı (' + res.data.status + ')';
						resultHtml = '<a href="' + res.data.edit_link + '" target="_blank">Düzenle</a>';
						contentResultsLog.push([item.fk, cptLabel, seviyeName, 'Başarılı', res.data.view_link]);
					} else {
						statusText = '❌ Hata';
						resultHtml = (res && res.data) ? res.data : 'Bilinmeyen hata';
						contentResultsLog.push([item.fk, cptLabel, '-', 'Hata', resultHtml]);
					}
					$row.find('td:eq(2)').text(seviyeName);
					$row.find('td:eq(3)').html(statusText);
					if (res && res.success) $row.find('td:eq(4)').html(resultHtml);
					else $row.find('td:eq(4)').text(resultHtml);
					$('#ewt_bulk_progress_text').text(' İşleniyor: ' + done + '/' + total + ' (eşzamanlı: ' + concurrency + ')');
				},
				onDone: function (wasStopped) {
					$('#ewt_bulk_progress_text').text((wasStopped ? ' Durduruldu: ' : ' Tamamlandı: ') + done + '/' + total);
					$startBtn.prop('disabled', false);
					$stopBtn.hide();
					if (contentResultsLog.length) $exportBtn.show();
				}
			});
		});
	}

	$stopBtn.on('click', function () {
		if (contentQueue) contentQueue.stop();
		$stopBtn.prop('disabled', true);
	});

	$exportBtn.on('click', function () {
		var rows = [['Focus Keyword', 'CPT', 'Seviye', 'Durum', 'Sonuç/Link']].concat(contentResultsLog);
		downloadCSV(rows, 'ewt-toplu-icerik-sonuclari.csv');
	});

	/* ==========================================================
	   TOPLU GÖRSEL ATAMA SAYFASI
	   ========================================================== */
	var $scanBtn  = $('#ewt_bulk_img_scan_btn');
	var $imgStartBtn = $('#ewt_bulk_img_start_btn');
	var $imgExportBtn = $('#ewt_bulk_img_export_btn');
	var scannedItems = [];
	var imgQueue = null;
	var imgResultsLog = [];

	if ($scanBtn.length) {
		$scanBtn.on('click', function () {
			var postType = $('#ewt_bulk_img_post_type').val();
			var limit = $('#ewt_bulk_img_limit').val();

			$scanBtn.prop('disabled', true).text('Taranıyor...');

			$.post(ewtBulk.ajaxUrl, {
				action: 'ewt_bulk_list_no_thumbnail',
				nonce: ewtBulk.nonce,
				post_type: postType,
				limit: limit
			}).done(function (res) {
				if (res.success) {
					scannedItems = res.data.items;
					$('#ewt_bulk_img_progress_text').text(' ' + scannedItems.length + ' yazı bulundu (görseli yok).');
					$imgStartBtn.prop('disabled', scannedItems.length === 0);
				} else {
					alert(res.data || 'Tarama başarısız.');
				}
			}).always(function () {
				$scanBtn.prop('disabled', false).text('1) Görseli Olmayan Yazıları Bul');
			});
		});
	}

	if ($imgStartBtn.length) {
		$imgStartBtn.on('click', function () {
			if (!scannedItems.length) return;
			var provider = $('#ewt_bulk_img_provider').val();
			var childObject = $('#ewt_bulk_img_child_object').is(':checked') ? 1 : 0;
			var concurrency = Math.max(1, Math.min(8, parseInt($('#ewt_bulk_img_concurrency').val(), 10) || 4));

			$('#ewt_bulk_img_results_table').show();
			$('#ewt_bulk_img_results_body').empty();
			$imgExportBtn.hide();
			imgResultsLog = [];
			$imgStartBtn.prop('disabled', true);
			$scanBtn.prop('disabled', true);

			var total = scannedItems.length, done = 0;
			$('#ewt_bulk_img_progress_text').text(' İşleniyor: 0/' + total + ' (eşzamanlı: ' + concurrency + ')');

			imgQueue = runConcurrentQueue(scannedItems, concurrency, function (item) {
				var $row = $('<tr><td>' + $('<div>').text(item.title).html() + '</td><td>⏳ İşleniyor...</td><td>-</td></tr>');
				$('#ewt_bulk_img_results_body').append($row);

				return postWithRetry(ewtBulk.ajaxUrl, {
					action: 'ewt_generate_featured_image',
					nonce: ewtBulk.imageNonce,
					post_id: item.id,
					prompt: item.title,
					provider: provider,
					child_object: childObject
				}, 1).then(function (res) {
					return { res: res, $row: $row, item: item };
				});
			}, {
				onItemDone: function (item, index, wrapped) {
					done++;
					var res = wrapped.res, $row = wrapped.$row;
					if (res && res.success) {
						$row.find('td:eq(1)').html('✅ Atandı');
						$row.find('td:eq(2)').html('<img src="' + res.data.thumbnail_url + '" style="height:40px;border-radius:4px;" />');
						imgResultsLog.push([item.title, 'Atandı', res.data.thumbnail_url]);
					} else {
						var errText = (res && res.data) ? res.data : 'Bilinmeyen hata';
						$row.find('td:eq(1)').html('❌ Hata');
						$row.find('td:eq(2)').text(errText);
						imgResultsLog.push([item.title, 'Hata', errText]);
					}
					$('#ewt_bulk_img_progress_text').text(' İşleniyor: ' + done + '/' + total + ' (eşzamanlı: ' + concurrency + ')');
				},
				onDone: function (wasStopped) {
					$('#ewt_bulk_img_progress_text').text((wasStopped ? ' Durduruldu: ' : ' Tamamlandı: ') + done + '/' + total);
					$imgStartBtn.prop('disabled', false);
					$scanBtn.prop('disabled', false);
					if (imgResultsLog.length) $imgExportBtn.show();
				}
			});
		});
	}

	$imgExportBtn.on('click', function () {
		var rows = [['Yazı', 'Durum', 'Görsel URL']].concat(imgResultsLog);
		downloadCSV(rows, 'ewt-toplu-gorsel-sonuclari.csv');
	});

});
