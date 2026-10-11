/**
 * Dinleme & Konuşma Laboratuvarı — frontend davranışı (v1.6.0)
 *
 * jQuery GEREKTİRMEZ (saf JavaScript) — kısa kod herhangi bir sayfada
 * kullanılabildiği için harici bağımlılık yoktur.
 *
 * Güvenlik notu: AI'dan gelen tüm metinler DOM'a yalnızca .textContent ile
 * yazılır (hiçbir yerde innerHTML kullanılmaz) — XSS riski yoktur.
 */
(function () {
	'use strict';

	if (typeof ewtLab === 'undefined') return;

	var synth = window.speechSynthesis || null;

	/* ---------- yardımcılar ---------- */
	function el(tag, className, text) {
		var e = document.createElement(tag);
		if (className) e.className = className;
		if (text !== undefined) e.textContent = text;
		return e;
	}

	function pickEnglishVoice() {
		if (!synth) return null;
		var voices = synth.getVoices() || [];
		// Tercih sırası: en-GB → en-US → herhangi bir en-*
		var prefs = ['en-GB', 'en-US'];
		for (var p = 0; p < prefs.length; p++) {
			for (var i = 0; i < voices.length; i++) {
				if (voices[i].lang === prefs[p]) return voices[i];
			}
		}
		for (var j = 0; j < voices.length; j++) {
			if ((voices[j].lang || '').indexOf('en') === 0) return voices[j];
		}
		return null;
	}

	// Bazı tarayıcılar sesleri asenkron yükler; ilk çağrıda boş dönebilir.
	if (synth && synth.onvoiceschanged !== undefined) {
		synth.onvoiceschanged = function () { pickEnglishVoice(); };
	}

	function splitSentences(text) {
		// Uzun metni cümlelere böler: uzun tek utterance bazı tarayıcılarda kesiliyor.
		return text
			.replace(/\n+/g, ' ')
			.match(/[^.!?]+[.!?]+["']?|\S+$/g) || [text];
	}

	/* ==========================================================
	   HER LABORATUVAR ÖRNEĞİ İÇİN KURULUM
	   ========================================================== */
	function initLab(lab) {
		var state = {
			data: null,
			listenCount: 0,
			speaking: false,
			queue: [],
			queueIndex: 0,
			currentUtterance: null,
			recorder: null,
			recChunks: [],
			recTimerId: null,
			recSeconds: 0
		};

		var form       = lab.querySelector('.ewt-lab-form');
		var errorBox   = lab.querySelector('.ewt-lab-error');
		var submitBtn  = lab.querySelector('.ewt-lab-submit');
		var playBtn    = lab.querySelector('.ewt-lab-play');
		var pauseBtn   = lab.querySelector('.ewt-lab-pause');
		var restartBtn = lab.querySelector('.ewt-lab-restart');
		var rateSel    = lab.querySelector('.ewt-lab-rate');
		var countSpan  = lab.querySelector('.ewt-lab-listen-count');
		var passageEl  = lab.querySelector('.ewt-lab-passage');
		var passageTtl = lab.querySelector('.ewt-lab-passage-title');
		var toggleBtn  = lab.querySelector('.ewt-lab-toggle-text');
		var quizHolder = lab.querySelector('.ewt-lab-quiz');
		var speakHold  = lab.querySelector('.ewt-lab-speaking');
		var nextSpeak  = lab.querySelector('.ewt-lab-next-speaking');

		/* ---------- adım geçişleri ---------- */
		function goToStep(n) {
			stopSpeech();
			lab.querySelectorAll('.ewt-lab-panel').forEach(function (p) {
				p.classList.toggle('is-active', p.getAttribute('data-panel') === String(n));
			});
			lab.querySelectorAll('.ewt-lab-step-tab').forEach(function (t) {
				var s = parseInt(t.getAttribute('data-step'), 10);
				t.classList.toggle('is-active', s === n);
				t.classList.toggle('is-done', s < n);
			});
			lab.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		lab.querySelectorAll('.ewt-lab-next, .ewt-lab-prev').forEach(function (btn) {
			btn.addEventListener('click', function () {
				goToStep(parseInt(btn.getAttribute('data-goto'), 10));
			});
		});

		// Sekmelere tıklayarak da gezinilebilsin (ama veri üretilmeden 2-4'e gidilmesin)
		lab.querySelectorAll('.ewt-lab-step-tab').forEach(function (tab) {
			tab.addEventListener('click', function () {
				var n = parseInt(tab.getAttribute('data-step'), 10);
				if (n === 1 || state.data) goToStep(n);
			});
		});

		var againBtn = lab.querySelector('.ewt-lab-again');
		if (againBtn) againBtn.addEventListener('click', function () { goToStep(1); });

		/* ==========================================================
		   ADIM 1: ÜRETİM (AJAX)
		   ========================================================== */
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			errorBox.style.display = 'none';

			var fd = new FormData();
			fd.append('action', 'ewt_lab_generate');
			fd.append('nonce', form.querySelector('[name="ewt_lab_nonce"]').value);
			fd.append('ewt_lab_hp', form.querySelector('[name="ewt_lab_hp"]').value);
			fd.append('rendered_at', form.getAttribute('data-rendered-at') || '0');
			fd.append('level', form.querySelector('.ewt-lab-level').value);
			fd.append('topic', form.querySelector('.ewt-lab-topic').value);

			submitBtn.disabled = true;
			var oldLabel = submitBtn.textContent;
			submitBtn.textContent = '⏳ Hazırlanıyor... (10-20 sn sürebilir)';

			fetch(ewtLab.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) {
						var msg = (res && res.data && res.data.message) ? res.data.message : 'Bir hata oluştu, lütfen tekrar deneyin.';
						showError(msg);
						return;
					}
					state.data = res.data;
					state.listenCount = 0;
					renderListening();
					renderQuiz();
					renderSpeaking();
					goToStep(2);
				})
				.catch(function () {
					showError('Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.');
				})
				.finally(function () {
					submitBtn.disabled = false;
					submitBtn.textContent = oldLabel;
				});
		});

		function showError(msg) {
			errorBox.textContent = '⚠️ ' + msg;
			errorBox.style.display = 'block';
		}

		/* ==========================================================
		   ADIM 2: DİNLEME (Web Speech API)
		   ========================================================== */
		function renderListening() {
			passageTtl.textContent = state.data.title + '  ·  ' + state.data.level_label;
			passageEl.textContent = state.data.passage;
			passageEl.classList.add('is-hidden');
			toggleBtn.textContent = '👁️ Metni Göster';
			updateListenCount();

			if (!synth) {
				lab.querySelector('.ewt-lab-tts-unsupported').style.display = 'block';
				playBtn.disabled = true;
				pauseBtn.style.display = 'none';
				passageEl.classList.remove('is-hidden'); // ses yoksa metin açık gelsin
				toggleBtn.style.display = 'none';
			}
		}

		function updateListenCount() {
			countSpan.textContent = state.listenCount > 0 ? ('🔁 ' + state.listenCount + ' kez dinlendi') : '';
		}

		function stopSpeech() {
			if (synth) synth.cancel();
			state.speaking = false;
			state.queue = [];
			state.queueIndex = 0;
			playBtn.style.display = '';
			pauseBtn.style.display = 'none';
			playBtn.textContent = '▶️ Dinle';
		}

		function speakQueueNext() {
			if (!state.speaking || state.queueIndex >= state.queue.length) {
				if (state.queueIndex >= state.queue.length && state.speaking) {
					// Parça bitti
					state.listenCount++;
					updateListenCount();
				}
				stopSpeech();
				return;
			}
			var u = new SpeechSynthesisUtterance(state.queue[state.queueIndex]);
			var voice = pickEnglishVoice();
			if (voice) u.voice = voice;
			u.lang = voice ? voice.lang : 'en-US';
			u.rate = parseFloat(rateSel.value) || 1;
			u.onend = function () {
				state.queueIndex++;
				speakQueueNext();
			};
			u.onerror = function () { stopSpeech(); };
			state.currentUtterance = u;
			synth.speak(u);
		}

		playBtn.addEventListener('click', function () {
			if (!synth || !state.data) return;
			if (synth.paused) { // duraklatılmışsa devam et
				synth.resume();
				state.speaking = true;
				playBtn.style.display = 'none';
				pauseBtn.style.display = '';
				return;
			}
			stopSpeech();
			state.queue = splitSentences(state.data.passage);
			state.queueIndex = 0;
			state.speaking = true;
			playBtn.style.display = 'none';
			pauseBtn.style.display = '';
			speakQueueNext();
		});

		pauseBtn.addEventListener('click', function () {
			if (!synth) return;
			synth.pause();
			playBtn.textContent = '▶️ Devam Et';
			playBtn.style.display = '';
			pauseBtn.style.display = 'none';
		});

		restartBtn.addEventListener('click', function () {
			if (!synth || !state.data) return;
			stopSpeech();
			playBtn.click();
		});

		rateSel.addEventListener('change', function () {
			// Hız değişince mevcut okumayı yeni hızla baştaki cümleden değil,
			// kaldığı cümleden sürdürmek için: cancel + aynı index'ten devam.
			if (state.speaking && synth) {
				var idx = state.queueIndex;
				synth.cancel();
				state.queueIndex = idx;
				speakQueueNext();
			}
		});

		toggleBtn.addEventListener('click', function () {
			var hidden = passageEl.classList.toggle('is-hidden');
			toggleBtn.textContent = hidden ? '👁️ Metni Göster' : '🙈 Metni Gizle';
		});

		/* ==========================================================
		   ADIM 3: QUIZ
		   ========================================================== */
		function renderQuiz() {
			quizHolder.textContent = '';
			nextSpeak.style.display = 'none';

			var quiz = state.data.quiz;
			var title = el('h4', 'ewt-lab-quiz-title', '📝 ' + (quiz.title || 'Anlama Testi'));
			quizHolder.appendChild(title);
			quizHolder.appendChild(el('p', 'ewt-lab-quiz-hint', 'Soruları dinlediğin parçaya göre cevapla. Emin olamadığın soru olursa "← Tekrar Dinle" ile parçaya dönebilirsin.'));

			var uid = Math.random().toString(36).slice(2, 8);

			quiz.questions.forEach(function (q, qi) {
				var qEl = el('div', 'ewt-lab-quiz-question');
				qEl.setAttribute('data-correct', q.correct);
				qEl.appendChild(el('p', 'ewt-lab-quiz-q-text', (qi + 1) + '. ' + q.q));

				var opts = el('div', 'ewt-lab-quiz-options');
				q.options.forEach(function (opt, oi) {
					var id = 'ewt-lab-' + uid + '-q' + qi + '-o' + oi;
					var label = el('label', 'ewt-lab-quiz-option');
					label.setAttribute('for', id);
					var input = document.createElement('input');
					input.type = 'radio';
					input.name = 'ewt-lab-' + uid + '-q' + qi;
					input.id = id;
					input.value = oi;
					label.appendChild(input);
					label.appendChild(el('span', '', opt));
					opts.appendChild(label);
				});
				qEl.appendChild(opts);

				if (q.explanation) {
					var ex = el('p', 'ewt-lab-quiz-explanation', '💡 ' + q.explanation);
					ex.style.display = 'none';
					qEl.appendChild(ex);
				}
				quizHolder.appendChild(qEl);
			});

			var scoreBtn = el('button', 'ewt-lab-quiz-score-btn', '✅ Puanla');
			scoreBtn.type = 'button';
			var scoreOut = el('div', 'ewt-lab-quiz-score');

			scoreBtn.addEventListener('click', function () {
				var total = quiz.questions.length, correct = 0, answered = 0;
				quizHolder.querySelectorAll('.ewt-lab-quiz-question').forEach(function (qEl) {
					var want = parseInt(qEl.getAttribute('data-correct'), 10);
					var chosen = qEl.querySelector('input:checked');
					qEl.classList.remove('is-correct', 'is-wrong');
					var ex = qEl.querySelector('.ewt-lab-quiz-explanation');

					qEl.querySelectorAll('.ewt-lab-quiz-option').forEach(function (optEl, oi) {
						optEl.classList.remove('opt-correct', 'opt-wrong');
						if (oi === want) optEl.classList.add('opt-correct');
					});

					if (chosen) {
						answered++;
						if (parseInt(chosen.value, 10) === want) {
							correct++;
							qEl.classList.add('is-correct');
						} else {
							qEl.classList.add('is-wrong');
							chosen.closest('.ewt-lab-quiz-option').classList.add('opt-wrong');
						}
					}
					if (ex) ex.style.display = 'block';
				});

				var pct = Math.round((correct / total) * 100);
				var emoji = pct >= 80 ? '🎉' : (pct >= 50 ? '👍' : '💪');
				scoreOut.textContent = emoji + ' Sonuç: ' + correct + ' / ' + total + ' doğru (%' + pct + ')' +
					(answered < total ? ' — ' + (total - answered) + ' soru boş bırakıldı.' : '');
				scoreOut.classList.add('is-visible');
				nextSpeak.style.display = ''; // puanladıktan sonra konuşmaya geçiş açılır
			});

			quizHolder.appendChild(scoreBtn);
			quizHolder.appendChild(scoreOut);
		}

		/* ==========================================================
		   ADIM 4: KONUŞMA
		   ========================================================== */
		function speakSingle(text) {
			if (!synth) return;
			synth.cancel();
			var u = new SpeechSynthesisUtterance(text);
			var voice = pickEnglishVoice();
			if (voice) u.voice = voice;
			u.lang = voice ? voice.lang : 'en-US';
			u.rate = parseFloat(rateSel.value) || 1;
			synth.speak(u);
		}

		function listWithSpeakBtns(items, holder) {
			var ul = el('ul', 'ewt-lab-speak-list');
			items.forEach(function (t) {
				var li = el('li');
				li.appendChild(el('span', 'ewt-lab-speak-text', t));
				if (synth) {
					var b = el('button', 'ewt-lab-say-btn', '🔊');
					b.type = 'button';
					b.title = 'Bu cümleyi dinle';
					b.addEventListener('click', function () { speakSingle(t); });
					li.appendChild(b);
				}
				ul.appendChild(li);
			});
			holder.appendChild(ul);
		}

		function renderSpeaking() {
			speakHold.textContent = '';
			var sp = state.data.speaking;

			if (sp.warmup && sp.warmup.length) {
				speakHold.appendChild(el('h4', 'ewt-lab-speak-h', '🔥 Isınma Soruları (Warm-up)'));
				listWithSpeakBtns(sp.warmup, speakHold);
			}

			if (sp.questions && sp.questions.length) {
				speakHold.appendChild(el('h4', 'ewt-lab-speak-h', '💬 Tartışma Soruları'));
				speakHold.appendChild(el('p', 'ewt-lab-speak-hint', 'Her soruyu sesli cevapla. Cevaplarını en az 2-3 cümleye çıkarmaya çalış.'));
				listWithSpeakBtns(sp.questions, speakHold);
			}

			if (sp.roleplay) {
				speakHold.appendChild(el('h4', 'ewt-lab-speak-h', '🎭 Rol Yapma (Role-play)'));
				var rp = el('p', 'ewt-lab-roleplay');
				rp.textContent = sp.roleplay;
				speakHold.appendChild(rp);
			}

			if (sp.shadowing && sp.shadowing.length) {
				speakHold.appendChild(el('h4', 'ewt-lab-speak-h', '🗣️ Shadowing (Dinle &amp; Tekrar Et)'.replace('&amp;', '&')));
				speakHold.appendChild(el('p', 'ewt-lab-speak-hint', '🔊 butonuyla cümleyi dinle, hemen ardından aynı hız ve tonlamayla yüksek sesle tekrar et. Her cümleyi en az 3 kez çalış.'));
				listWithSpeakBtns(sp.shadowing, speakHold);
			}
		}

		/* ==========================================================
		   MİKROFON KAYDI (MediaRecorder) — kayıt cihazda kalır
		   ========================================================== */
		var recStart = lab.querySelector('.ewt-lab-rec-start');
		var recStop  = lab.querySelector('.ewt-lab-rec-stop');
		var recTimer = lab.querySelector('.ewt-lab-rec-timer');
		var recAudio = lab.querySelector('.ewt-lab-rec-audio');
		var recUnsup = lab.querySelector('.ewt-lab-rec-unsupported');

		if (!navigator.mediaDevices || !window.MediaRecorder) {
			recStart.style.display = 'none';
			recUnsup.style.display = 'block';
		} else {
			recStart.addEventListener('click', function () {
				navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
					state.recChunks = [];
					state.recorder = new MediaRecorder(stream);
					state.recorder.ondataavailable = function (ev) {
						if (ev.data && ev.data.size) state.recChunks.push(ev.data);
					};
					state.recorder.onstop = function () {
						stream.getTracks().forEach(function (t) { t.stop(); });
						var blob = new Blob(state.recChunks, { type: state.recorder.mimeType || 'audio/webm' });
						recAudio.src = URL.createObjectURL(blob);
						recAudio.style.display = 'block';
					};
					state.recorder.start();

					state.recSeconds = 0;
					recTimer.textContent = '00:00';
					recTimer.style.display = '';
					state.recTimerId = setInterval(function () {
						state.recSeconds++;
						var m = String(Math.floor(state.recSeconds / 60)).padStart(2, '0');
						var s = String(state.recSeconds % 60).padStart(2, '0');
						recTimer.textContent = m + ':' + s;
						if (state.recSeconds >= 180) recStop.click(); // maks 3 dk
					}, 1000);

					recStart.style.display = 'none';
					recStop.style.display = '';
				}).catch(function () {
					recUnsup.textContent = '⚠️ Mikrofon izni verilmedi. Tarayıcı ayarlarından mikrofon iznini açıp tekrar deneyin.';
					recUnsup.style.display = 'block';
				});
			});

			recStop.addEventListener('click', function () {
				if (state.recorder && state.recorder.state !== 'inactive') state.recorder.stop();
				clearInterval(state.recTimerId);
				recStop.style.display = 'none';
				recStart.style.display = '';
				recStart.textContent = '⏺️ Yeniden Kaydet';
			});
		}

		// Sayfadan ayrılırken konuşma sentezini durdur (bazı tarayıcılar takılı bırakıyor)
		window.addEventListener('beforeunload', function () {
			if (synth) synth.cancel();
		});
	}

	// defer ile yüklendiğinde DOMContentLoaded çoktan geçmiş olabilir:
	if (document.readyState !== 'loading') {
		document.querySelectorAll('.ewt-lab').forEach(function (lab) {
			if (!lab.hasAttribute('data-ewt-lab-init')) {
				lab.setAttribute('data-ewt-lab-init', '1');
				initLab(lab);
			}
		});
	} else {
		document.addEventListener('DOMContentLoaded', function () {
			document.querySelectorAll('.ewt-lab:not([data-ewt-lab-init])').forEach(function (lab) {
				lab.setAttribute('data-ewt-lab-init', '1');
				initLab(lab);
			});
		});
	}
})();
