(function () {
  'use strict';
  const root = document.getElementById('ewt-placement-test');
  if (!root || typeof ewtPlacement === 'undefined' || typeof ewtPlacementScore === 'undefined') return;
  const questions = ewtPlacement.questions;
  const el = name => root.querySelector('[data-pt="' + name + '"]');
  let answers = [], index = 0, result = null;
  function render() {
    const question = questions[index];
    el('question').textContent = question.question;
    el('progress').textContent = 'Soru ' + (index + 1) + ' / ' + questions.length;
    el('meter').value = index + 1;
    el('meter').max = questions.length;
    el('options').replaceChildren();
    [...question.options, 'Bilmiyorum'].forEach((text, choice) => {
      const label = document.createElement('label');
      label.lang = choice === question.options.length ? 'tr' : 'en';
      const input = document.createElement('input');
      input.type = 'radio'; input.name = 'ewt_placement_answer';
      input.value = choice === question.options.length ? '-1' : String(choice);
      input.checked = answers[index] === Number(input.value);
      label.append(input, document.createTextNode(' ' + text));
      el('options').append(label);
    });
    el('error').textContent = '';
    el('back').disabled = index === 0;
    el('next').textContent = index === questions.length - 1 ? 'Sonucumu göster' : 'Sonraki soru';
    el('question').focus();
  }
  function capture() {
    const choice = el('options').querySelector('input:checked');
    if (choice) answers[index] = Number(choice.value);
    return Boolean(choice);
  }
  function start() {
    if (questions.length !== 30) return;
    answers = new Array(questions.length); index = 0; result = null;
    el('result').hidden = true; el('form').hidden = false;
    el('start').hidden = true; render();
  }
  function finish() {
    result = ewtPlacementScore.score(questions, answers);
    el('form').hidden = true; el('result').hidden = false;
    el('result-title').textContent = result.foundations ? 'Önerilen başlangıç: A1 temelleri' : 'Yaklaşık çalışma seviyen: ' + result.level.toUpperCase();
    el('summary').textContent = result.correct + '/30 doğru. Her seviyede en az 4/5 doğru gereken, A1’den kesintisiz ilerleyen bloklara göre başlangıç önerisi verildi. İleri sorulardaki tekil doğrular eksik temel bloklarını atlatmaz. Bu sonuç kapsamlı bir dil sınavının yerine geçmez.';
    const form = document.getElementById('ewt-path-form');
    const labels = ['audience', 'goal', 'target'].map(key => {
      const input = form.elements.namedItem('ewt_' + key);
      return input.selectedOptions[0].textContent;
    });
    el('context').textContent = 'İçerik hedefin: ' + labels.join(' · ') + '. Üstteki seçimleri değiştirebilirsin; öneriler en son seçimlerini kullanır.';
    el('breakdown').replaceChildren();
    result.blocks.forEach(block => {
      const li = document.createElement('li');
      li.textContent = block.level.toUpperCase() + ': ' + block.correct + '/' + block.total;
      el('breakdown').append(li);
    });
    el('review').replaceChildren();
    questions.forEach((question, i) => {
      const li = document.createElement('li');
      const selected = answers[i] === -1 ? 'Bilmiyorum' : question.options[answers[i]];
      li.textContent = question.question + ' — Cevabın: ' + selected + '. Doğru cevap: ' + question.options[question.answer] + '. ' + question.explanation;
      el('review').append(li);
    });
    el('result-title').focus();
  }
  el('start').addEventListener('click', start);
  el('restart').addEventListener('click', start);
  el('back').addEventListener('click', () => { capture(); if (index > 0) { index--; render(); } });
  el('cancel').addEventListener('click', () => { answers = []; el('form').hidden = true; el('start').hidden = false; el('start').focus(); });
  el('form').addEventListener('submit', event => {
    event.preventDefault();
    if (!capture()) { el('error').textContent = 'Bir cevap ya da “Bilmiyorum” seçeneğini işaretle.'; return; }
    if (index === questions.length - 1) finish();
    else { index++; render(); }
  });
  el('apply').addEventListener('click', () => { if (result && window.ewtPathPreferences) window.ewtPathPreferences.applyLevel(result.level); });
}());
