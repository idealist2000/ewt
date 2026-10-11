(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var cfg = window.ewtChatbot || {};
		var toggleBtn = document.getElementById('ewt-chatbot-toggle');
		var closeBtn = document.getElementById('ewt-chatbot-close');
		var panel = document.getElementById('ewt-chatbot-panel');
		var messages = document.getElementById('ewt-chatbot-messages');
		var quickWrap = document.getElementById('ewt-chatbot-quick-replies');
		var form = document.getElementById('ewt-chatbot-question');
		var input = document.getElementById('ewt-chatbot-input');
		var micBtn = document.getElementById('ewt-chatbot-mic');
		var voiceInfo = document.getElementById('ewt-chatbot-voice-status');
		var activeParagraph = null;
		var requestPending = false;
		var pauseTimer = null;
		var pauseCandidate = null;
		var prompted = {};

		if (!toggleBtn || !panel || !messages || !quickWrap) return;

		function openPanel() {
			panel.classList.remove('ewt-hidden');
			toggleBtn.setAttribute('aria-expanded', 'true');
		}

		function closePanel() {
			panel.classList.add('ewt-hidden');
			toggleBtn.setAttribute('aria-expanded', 'false');
		}

		function addMessage(text, who, extraClass) {
			var div = document.createElement('div');
			div.className = 'ewt-msg ewt-' + (who || 'bot') + (extraClass ? ' ' + extraClass : '');
			div.textContent = text;
			messages.appendChild(div);
			messages.scrollTop = messages.scrollHeight;
			return div;
		}

		function setQuickReplies(options) {
			quickWrap.innerHTML = '';
			options.forEach(function (opt) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.textContent = opt.label;
				btn.disabled = requestPending;
				btn.addEventListener('click', function () {
					if (opt.echo !== false) addMessage(opt.label, 'user');
					opt.action();
				});
				quickWrap.appendChild(btn);
			});
		}

		function paragraphText() {
			return activeParagraph ? activeParagraph.textContent.replace(/\s+/g, ' ').trim() : '';
		}

		function showLessonOptions() {
			setQuickReplies([
				{label: '✨ Daha fazla örnek', action: function () { askAssistant('examples', ''); }},
				{label: '❓ Sorun var / soru sor', action: showQuestionForm},
				{label: 'Şimdilik hayır', action: closePanel}
			]);
		}

		function askAssistant(requestType, question) {
			if (requestPending || !paragraphText() || !cfg.ajaxUrl) return;
			requestPending = true;
			if (form) form.classList.add('ewt-hidden');
			setQuickReplies([]);
			var waiting = addMessage('Bu paragrafı inceliyorum…', 'bot', 'ewt-loading');
			var heading = document.querySelector('.ewt-single-title');
			var body = new URLSearchParams({
				action: 'ewt_lesson_assistant',
				nonce: cfg.nonce || '',
				request_type: requestType,
				context: paragraphText(),
				question: question || '',
				title: heading ? heading.textContent.trim() : document.title,
				post_id: String(cfg.postId || 0)
			});
			var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var timeoutId = controller ? window.setTimeout(function () { controller.abort(); }, 30000) : null;

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined,
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function (response) {
				return response.text().then(function (text) {
					var json;
					try { json = JSON.parse(text.replace(/^\uFEFF/, '').trim()); } catch (error) { json = null; }
					if (json && typeof json === 'object' && typeof json.success === 'boolean') return json;
					// Never display raw HTML: it may contain a hosting error page or PHP details.
					var code = ' (HTTP ' + response.status + ')';
					if ([408, 504, 524].indexOf(response.status) !== -1) {
						throw new Error('Sunucu zaman aşımına uğradı' + code + '. Lütfen biraz sonra tekrar deneyin.');
					}
					if (response.status === 403 || response.status === 401 || text.trim() === '-1') {
						throw new Error('İstek reddedildi' + code + '. Sayfayı yenileyin; sürerse site yöneticisi güvenlik/önbellek ayarlarını kontrol etmeli.');
					}
					if (response.status === 429) throw new Error('Çok fazla istek gönderildi' + code + '. Lütfen bir süre bekleyin.');
					if (response.status >= 500) {
						throw new Error('Sunucu isteği tamamlayamadı' + code + '. Site yöneticisi PHP hata günlüğünü ve zaman aşımı sınırlarını kontrol etmeli.');
					}
					if (text.trim() === '0') throw new Error('Yardım servisi bulunamadı. Sayfayı yenileyin; sürerse etkin tema sürümünü kontrol edin (EWT-ACTION).');
					throw new Error('Sunucu beklenen yanıtı göndermedi' + code + '. Site yöneticisine bu kodu iletin (EWT-JSON).');
				});
			}).then(function (json) {
				if (!json.success) throw new Error(json.data && json.data.message ? json.data.message : 'Yanıt üretilemedi.');
				if (!json.data || typeof json.data.answer !== 'string' || !json.data.answer.trim()) {
					throw new Error('Yanıt boş veya geçersiz (EWT-EMPTY). Lütfen yeniden deneyin.');
				}
				waiting.remove();
				addMessage(json.data.answer, 'bot');
			}).catch(function (error) {
				waiting.remove();
				var message = error.name === 'AbortError'
					? 'Yanıt bekleme süresi doldu (EWT-TIMEOUT). Lütfen biraz sonra tekrar deneyin.'
					: (error.message || 'Bağlantı kurulamadı. Lütfen yeniden deneyin.');
				addMessage(message, 'bot', 'ewt-error');
			}).then(function () {
				if (timeoutId !== null) window.clearTimeout(timeoutId);
				requestPending = false;
				showLessonOptions();
			});
		}

		function showQuestionForm() {
			if (!form || !input) return;
			form.classList.remove('ewt-hidden');
			setQuickReplies([{label: '⬅️ Vazgeç', echo: false, action: function () {
				form.classList.add('ewt-hidden');
				showLessonOptions();
			}}]);
			input.focus();
		}

		function offerHelp(paragraph) {
			activeParagraph = paragraph;
			openPanel();
			addMessage('Bu paragrafta biraz duraksadın. İpucu ister misin? Sorun varsa yazabilir veya mikrofonla sorabilirsin.', 'bot');
			showLessonOptions();
		}

		function setupPauseDetection() {
			if (!cfg.isLesson || !('IntersectionObserver' in window)) return;
			var paragraphs = Array.prototype.slice.call(document.querySelectorAll('.ewt-single-content p')).filter(function (p) {
				return p.textContent.trim().length >= 60;
			});
			if (!paragraphs.length) return;

			paragraphs.forEach(function (p, index) { p.dataset.ewtHelpId = String(index); });
			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.intersectionRatio < 0.55) {
						if (pauseCandidate === entry.target) {
							clearTimeout(pauseTimer);
							pauseCandidate = null;
						}
						return;
					}
					clearTimeout(pauseTimer);
					var p = entry.target;
					pauseCandidate = p;
					var id = p.dataset.ewtHelpId;
					if (prompted[id] || !panel.classList.contains('ewt-hidden') || document.hidden) return;
					var words = p.textContent.trim().split(/\s+/).length;
					var expectedReadingMs = (words / 180) * 60000;
					var delay = Math.max(9000, Math.min(22000, expectedReadingMs * 1.35));
					pauseTimer = window.setTimeout(function () {
						if (!document.hidden && pauseCandidate === p && panel.classList.contains('ewt-hidden')) {
							prompted[id] = true;
							offerHelp(p);
						}
					}, delay);
				});
			}, {threshold: [0.55]});
			paragraphs.forEach(function (p) { observer.observe(p); });
			document.addEventListener('visibilitychange', function () {
				if (document.hidden) {
					clearTimeout(pauseTimer);
					pauseCandidate = null;
				}
			});
		}

		function showTutoringCTA() {
			var phone = cfg.phone || '+90 530 571 40 67';
			var wa = cfg.whatsapp || '905305714067';
			addMessage('Özel ders için aşağıdaki seçeneklerle hemen ulaşabilirsin: 📞 ' + phone, 'bot');
			setQuickReplies([
				{label: '📞 Şimdi Ara', action: function () { window.location.href = 'tel:' + phone; }},
				{label: '💬 WhatsApp', action: function () { window.open('https://wa.me/' + wa, '_blank', 'noopener'); }},
				{label: '⬅️ Ana Menü', action: showMainMenu}
			]);
		}

		function showMainMenu() {
			if (form) form.classList.add('ewt-hidden');
			addMessage('Aşağıdaki konulardan birini seçebilirsin:', 'bot');
			var options = [];
			if (cfg.isLesson && activeParagraph) options.push({label: '📖 Bu paragraf için yardım', action: showLessonOptions});
			options.push(
				{label: 'Seviyemi nasıl bulurum?', action: function () {
					addMessage('Menüden okul kademeni veya A1-C2 seviyelerinden birini seçerek başlayabilirsin.', 'bot');
					setQuickReplies([{label: '⬅️ Ana Menü', action: showMainMenu}]);
				}},
				{label: 'TOEFL / YDS / GRE / GMAT', action: function () {
					addMessage('Üst menüden ilgili sınav bölümüne gidip seviyene uygun kategoriyi seçebilirsin.', 'bot');
					setQuickReplies([{label: '⬅️ Ana Menü', action: showMainMenu}]);
				}}
			);
			if (cfg.contactEnabled) options.push({label: 'Özel ders almak istiyorum', action: showTutoringCTA});
			setQuickReplies(options);
		}

		toggleBtn.setAttribute('aria-expanded', 'false');
		toggleBtn.addEventListener('click', function () {
			if (panel.classList.contains('ewt-hidden')) {
				openPanel();
				if (cfg.isLesson && activeParagraph) showLessonOptions();
			} else closePanel();
		});
		if (closeBtn) closeBtn.addEventListener('click', closePanel);

		if (form) form.addEventListener('submit', function (event) {
			event.preventDefault();
			var question = input.value.trim();
			if (question.length < 3) {
				voiceInfo.textContent = 'Lütfen en az 3 karakterlik bir soru yaz.';
				return;
			}
			addMessage(question, 'user');
			input.value = '';
			voiceInfo.textContent = '';
			askAssistant('question', question);
		});

		var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (micBtn && !SpeechRecognition) {
			micBtn.disabled = true;
			micBtn.title = 'Bu tarayıcı sesle yazmayı desteklemiyor';
		} else if (micBtn) {
			micBtn.addEventListener('click', function () {
				var recognition = new SpeechRecognition();
				recognition.lang = 'tr-TR';
				recognition.interimResults = false;
				recognition.maxAlternatives = 1;
				voiceInfo.textContent = 'Dinliyorum… Tarayıcın sesi yazıya dönüştürür; siteye yalnızca metin gönderilir.';
				micBtn.disabled = true;
				recognition.onresult = function (event) {
					input.value = (input.value + ' ' + event.results[0][0].transcript).trim().slice(0, 500);
					voiceInfo.textContent = 'Soru yazıya aktarıldı; göndermeden önce düzenleyebilirsin.';
				};
				recognition.onerror = function () {
					voiceInfo.textContent = 'Ses alınamadı. Mikrofon iznini kontrol edebilir veya sorunu yazabilirsin.';
				};
				recognition.onend = function () { micBtn.disabled = false; };
				recognition.start();
			});
		}

		showMainMenu();
		setupPauseDetection();
	});
}());
