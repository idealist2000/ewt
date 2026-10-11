jQuery(function ($) {

	/* ==========================================================
	   YARDIMCI: hata mesajı göster
	   ========================================================== */
	function showError($result, message) {
		$result.empty().show().append(
			$('<div class="ewt-generator-error">').text('⚠️ ' + message)
		);
	}

	/* ==========================================================
	   YARDIMCI: sonuç panelinin üst kısmını (rozet + yazdır butonu) kur
	   ========================================================== */
	function buildResultShell($result, typeLabel, topic) {
		$result.empty().show();

		var $head = $('<div class="ewt-generator-result-head">');
		$head.append($('<span class="ewt-generator-result-badge">').text(typeLabel));
		$head.append($('<span class="ewt-generator-result-topic">').text(topic));

		var $printBtn = $('<button type="button" class="ewt-gen-print-btn">🖨️ Yazdır / PDF Kaydet</button>');
		$printBtn.on('click', function () { window.print(); });

		var $actions = $('<div class="ewt-generator-result-actions">').append($printBtn);

		$result.append($head).append($actions);
		return $result;
	}

	/* ==========================================================
	   YARDIMCI: sonucun altına WhatsApp özel ders CTA'sı ekle
	   ========================================================== */
	function appendWhatsappCta($result, topic) {
		if (typeof ewtGenerator === 'undefined' || !ewtGenerator.whatsapp) return;
		var msg = encodeURIComponent('Merhaba, "' + topic + '" konusunda özel ders almak istiyorum.');
		var url = 'https://wa.me/' + ewtGenerator.whatsapp + '?text=' + msg;

		var $cta = $('<div class="ewt-generator-cta">');
		$cta.append($('<p>').text('Bu konuda birebir destek ister misiniz?'));
		$cta.append(
			$('<a class="ewt-generator-cta-btn" target="_blank" rel="noopener">')
				.attr('href', url)
				.text('💬 WhatsApp\'tan Özel Ders Sor')
		);
		$result.append($cta);
	}

	/* ==========================================================
	   İNTERAKTİF QUIZ RENDER + PUANLAMA
	   Doğru cevap index'i BİZİM kodumuzca (data-correct) tutulur;
	   AI çıktısı sadece soru/seçenek/açıklama METNİ olarak kullanılır (.text() ile,
	   hiçbir yerde .html() ile basılmaz) — bu yüzden AI çıktısı burada güvenlidir.
	   ========================================================== */
	function renderQuiz(quiz, $holder) {
		var $wrap = $('<div class="ewt-quiz">');
		$wrap.append($('<h4 class="ewt-quiz-title">').text(quiz.title || 'Quiz'));

		quiz.questions.forEach(function (q, qi) {
			var $q = $('<div class="ewt-quiz-question">').attr('data-correct', q.correct);
			$q.append($('<p class="ewt-quiz-q-text">').text((qi + 1) + '. ' + q.q));

			var $opts = $('<div class="ewt-quiz-options">');
			q.options.forEach(function (opt, oi) {
				var id = 'ewt-quiz-' + Math.random().toString(36).slice(2, 7) + '-q' + qi + '-o' + oi;
				var $input = $('<input type="radio">').attr({ name: 'ewt-quiz-q' + qi + '-' + $wrap.index(), id: id, value: oi });
				var $label = $('<label class="ewt-quiz-option">').attr('for', id);
				$label.append($input).append($('<span>').text(opt));
				$opts.append($label);
			});
			$q.append($opts);

			if (q.explanation) {
				$q.append($('<p class="ewt-quiz-explanation">').text(q.explanation).hide());
			}

			$wrap.append($q);
		});

		var $scoreBtn = $('<button type="button" class="ewt-quiz-score-btn">✅ Puanla</button>');
		var $scoreResult = $('<p class="ewt-quiz-score-result">');

		$scoreBtn.on('click', function () {
			var correct = 0, total = quiz.questions.length, unanswered = 0;

			$wrap.find('.ewt-quiz-question').each(function () {
				var $qEl = $(this);
				var correctIdx = parseInt($qEl.attr('data-correct'), 10);
				var $checked = $qEl.find('input:checked');
				var pickedIdx = $checked.length ? parseInt($checked.val(), 10) : -1;

				if (pickedIdx === -1) unanswered++;
				else if (pickedIdx === correctIdx) correct++;

				$qEl.find('.ewt-quiz-option').removeClass('ewt-correct ewt-incorrect');
				$qEl.find('.ewt-quiz-option').each(function (oi) {
					if (oi === correctIdx) $(this).addClass('ewt-correct');
					else if (oi === pickedIdx) $(this).addClass('ewt-incorrect');
				});
				$qEl.find('.ewt-quiz-explanation').show();
				$qEl.find('input').prop('disabled', true);
			});

			var msg = correct + ' / ' + total + ' doğru.';
			if (unanswered) msg += ' (' + unanswered + ' soru boş bırakıldı)';
			$scoreResult.text(msg);
			$scoreBtn.prop('disabled', true);
		});

		$wrap.append($scoreBtn).append($scoreResult);
		$holder.empty().append($wrap);
	}

	/* ==========================================================
	   FORM GÖNDERİMİ (sayfada birden fazla [ewt_generator] örneği desteklenir)
	   ========================================================== */
	$('.ewt-generator-form').each(function () {
		var $form = $(this);
		var $container = $form.closest('.ewt-generator');
		var $result = $container.find('.ewt-generator-result');
		var cptContext = $container.data('cptContext') || '';
		var renderedAt = $form.data('renderedAt') || 0;

		$form.on('submit', function (e) {
			e.preventDefault();

			var $btn = $form.find('.ewt-generator-submit');
			var $typeSel = $form.find('.ewt-gen-type');
			var genType = $typeSel.val();
			var level = $form.find('.ewt-gen-level').val();
			var topic = $.trim($form.find('.ewt-gen-topic').val());
			var nonce = $form.find('input[name="ewt_gen_nonce"]').val();
			var honeypot = $form.find('input[name="ewt_gen_hp"]').val();

			if (!topic) return;

			var originalBtnText = $btn.text();
			$btn.prop('disabled', true).text('⏳ Oluşturuluyor...');
			$result.hide().empty();

			$.post(ewtGenerator.ajaxUrl, {
				action: 'ewt_frontend_generate',
				nonce: nonce,
				gen_type: genType,
				level: level,
				topic: topic,
				cpt_context: cptContext,
				rendered_at: renderedAt,
				ewt_gen_hp: honeypot
			}).done(function (res) {
				if (!res || !res.success) {
					showError($result, (res && res.data && res.data.message) ? res.data.message : 'Bir hata oluştu, lütfen tekrar deneyin.');
					return;
				}
				var data = res.data;
				buildResultShell($result, data.type_label, data.topic);

				if (data.result_type === 'quiz') {
					var $quizHolder = $('<div>');
					$result.append($quizHolder);
					renderQuiz(data.quiz, $quizHolder);
				} else {
					$result.append($('<div class="ewt-generator-body">').html(data.html));
				}

				appendWhatsappCta($result, data.topic);

				var el = $result.get(0);
				if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}).fail(function () {
				showError($result, 'Bağlantı hatası, lütfen tekrar deneyin.');
			}).always(function () {
				$btn.prop('disabled', false).text(originalBtnText);
			});
		});
	});

});
