(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('ewt-topic-speaking');
    var cfg = window.ewtTopicSpeaking;
    if (!root || !cfg) return;
    function el(name) { return root.querySelector('[data-sp="' + name + '"]'); }
    var turn = 1, question = '', signature = '', next = null, busy = false;
    var recognition = null, speechTimer = null;
    var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    var canRecognize = !!Recognition && window.isSecureContext;
    var canSpeak = !!window.speechSynthesis && !!window.SpeechSynthesisUtterance;
    function status(text) { el('status').textContent = text; }
    function controls() {
      root.querySelectorAll('button').forEach(function (button) { button.disabled = busy; });
      el('answer').disabled = busy;
      el('mic').disabled = busy || !canRecognize;
      el('listen').disabled = busy || !canSpeak;
      el('example-audio').disabled = busy || !canSpeak;
      el('send').disabled = busy || !!recognition;
    }
    function stopAudio() { if (canSpeak) window.speechSynthesis.cancel(); }
    function stopMic() {
      if (recognition) recognition.stop();
      window.clearTimeout(speechTimer);
    }
    function read(text) {
      stopMic(); stopAudio();
      if (!canSpeak) return;
      var speech = new window.SpeechSynthesisUtterance(text);
      speech.lang = 'en-US'; speech.rate = 0.85;
      speech.onerror = function () { status('Sesli okuma kullanılamadı; metinden devam edebilirsin.'); };
      window.speechSynthesis.speak(speech);
    }
    async function request(mode) {
      busy = true; stopMic(); stopAudio(); controls();
      status(mode === 'start' ? 'Dersine uygun soru hazırlanıyor…' : 'Cevabın değerlendiriliyor…');
      var controller = new AbortController();
      var timeout = window.setTimeout(function () { controller.abort(); }, 30000);
      try {
        var response = await fetch(cfg.url, {
          method: 'POST', credentials: 'same-origin', signal: controller.signal,
          body: new URLSearchParams({action:'ewt_topic_speaking', nonce:cfg.nonce,
            post_id:String(cfg.postId), mode:mode, question:question, signature:signature,
            answer:mode === 'answer' ? el('answer').value.trim() : ''})
        });
        var text = await response.text();
        var json;
        try { json = JSON.parse(text.replace(/^\uFEFF/, '').trim()); }
        catch (error) { throw new Error('Sunucu yanıtı alınamadı (HTTP ' + response.status + '). Cevabın korunuyor.'); }
        if (!json || !json.success) throw new Error(json && json.data && json.data.message || 'İşlem tamamlanamadı.');
        var data = json.data;
        var fields = mode === 'start' ? ['task_tr','question','signature'] : ['feedback_tr','correction','example','next_question','signature'];
        if (!data || fields.some(function (field) { return typeof data[field] !== 'string' || !data[field].trim(); })) throw new Error('Eksik yanıt alındı. Lütfen tekrar dene.');
        if (mode === 'start') {
          turn = 1; question = data.question; signature = data.signature;
          el('task').textContent = data.task_tr;
          el('exercise').hidden = false; el('start').hidden = true;
          showQuestion();
        } else {
          el('review').textContent = data.feedback_tr;
          el('correction').textContent = data.correction;
          el('example').textContent = data.example;
          next = {question:data.next_question, signature:data.signature};
          el('form').hidden = true; el('feedback').hidden = false;
          el('next').textContent = turn === 3 ? 'Çalışmayı tamamla' : 'Sonraki soru';
          status('Geri bildirimin hazır. Örneği dinleyebilir veya aynı soruyu tekrar deneyebilirsin.');
        }
      } catch (error) {
        status(error.name === 'AbortError' ? 'Yanıt bekleme süresi doldu. Cevabın korunuyor; biraz sonra tekrar dene.' : error.message);
      } finally {
        window.clearTimeout(timeout); busy = false; controls();
      }
    }
    function showQuestion() {
      el('question').textContent = question;
      el('progress').textContent = 'Soru ' + turn + ' / 3';
      el('answer').value = ''; el('feedback').hidden = true; el('form').hidden = false;
      status(canRecognize ? 'İngilizce konuşabilir veya cevabını yazabilirsin.' : 'Bu tarayıcıda mikrofonla yazıya çevirme kullanılamıyor. Cevabını yazarak devam et.');
    }
    el('start').addEventListener('click', function () {
      if (busy) return;
      question = ''; signature = ''; next = null; request('start');
    });
    el('form').addEventListener('submit', function (event) {
      event.preventDefault();
      if (busy || recognition) return;
      if (el('answer').value.trim().length < 2) { status('Önce İngilizce cevabını yaz veya söyle.'); return; }
      request('answer');
    });
    el('listen').addEventListener('click', function () { read(question); });
    el('example-audio').addEventListener('click', function () { read(el('example').textContent); });
    el('retry').addEventListener('click', function () {
      if (busy) return;
      stopAudio(); el('feedback').hidden = true; el('form').hidden = false;
      status('Cevabını düzenle veya temizleyip yeniden konuş.'); el('answer').focus();
    });
    el('next').addEventListener('click', function () {
      if (busy || !next) return;
      stopMic(); stopAudio();
      if (turn === 3) {
        el('exercise').hidden = true; el('start').hidden = false;
        el('start').textContent = 'Yeni çalışma başlat';
        status('Üç soruluk çalışmayı tamamladın! Bu dersteki örnekleri konuşarak tekrar ettin.');
        return;
      }
      turn++; question = next.question; signature = next.signature; next = null; showQuestion();
    });
    el('mic').addEventListener('click', function () {
      if (busy || !canRecognize) return;
      if (recognition) { stopMic(); return; }
      stopAudio();
      var rec = new Recognition(); recognition = rec;
      rec.lang = 'en-US'; rec.interimResults = false; rec.continuous = false;
      rec.onresult = function (event) {
        var parts = [];
        for (var i = event.resultIndex; i < event.results.length; i++) if (event.results[i].isFinal) parts.push(event.results[i][0].transcript);
        el('answer').value = (el('answer').value + ' ' + parts.join(' ')).trim().slice(0,700);
        status('Sesin yazıya çevrildi. Metni kontrol edip Cevabımı değerlendir düğmesine bas.');
      };
      rec.onerror = function (event) {
        var messages = {'not-allowed':'Mikrofon izni verilmedi. Yazılı cevapla devam edebilirsin.',
          'no-speech':'Ses algılanmadı. Tekrar dene veya cevabını yaz.',
          'audio-capture':'Mikrofon bulunamadı. Yazılı cevapla devam edebilirsin.',
          'network':'Ses tanıma hizmetine bağlanılamadı. Cevabını yazabilirsin.'};
        status(messages[event.error] || 'Ses tanıma durdu. Tekrar dene veya cevabını yaz.');
      };
      rec.onend = function () {
        recognition = null; window.clearTimeout(speechTimer);
        el('mic').textContent = 'Mikrofonla cevapla'; el('mic').setAttribute('aria-pressed','false'); controls();
      };
      try {
        rec.start(); el('mic').textContent = 'Dinlemeyi durdur'; el('mic').setAttribute('aria-pressed','true');
        status('Dinliyorum… İngilizce cevap ver.'); controls();
        speechTimer = window.setTimeout(stopMic, 45000);
      } catch (error) { rec.onend(); status('Mikrofon başlatılamadı. Cevabını yazarak devam edebilirsin.'); }
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) { stopMic(); stopAudio(); } });
    window.addEventListener('pagehide', function () { stopMic(); stopAudio(); });
    controls();
  });
}());
