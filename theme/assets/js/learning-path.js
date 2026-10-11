(function () {
  'use strict';
  const form = document.getElementById('ewt-path-form');
  if (!form || typeof ewtLearningPath === 'undefined') return;
  const config = ewtLearningPath;
  const fields = ['level', 'audience', 'goal', 'target', 'order'];
  const select = key => form.elements.namedItem('ewt_' + key);
  const storageKey = 'ewt-learning-path-v1';
  function targets(value) {
    const input = select('target');
    input.replaceChildren(new Option('Genel hazırlık', ''));
    Object.entries(config.targets[select('goal').value] || {}).forEach(([key, label]) => input.add(new Option(label, key)));
    input.value = Object.hasOwn(config.targets[select('goal').value] || {}, value) ? value : '';
  }
  function preferences() {
    return Object.fromEntries(fields.map(key => [key, select(key).value]));
  }
  function remember() {
    try { localStorage.setItem(storageKey, JSON.stringify(preferences())); } catch (_) { /* Optional storage may be unavailable. */ }
  }
  const query = new URLSearchParams(window.location.search);
  const hasQuery = fields.some(key => query.has('ewt_' + key));
  if (!hasQuery) {
    try {
      const saved = JSON.parse(localStorage.getItem(storageKey));
      if (saved && typeof saved === 'object') {
        for (const key of ['level', 'audience', 'goal', 'order']) {
          if (Array.from(select(key).options).some(option => option.value === saved[key])) select(key).value = saved[key];
        }
        targets(saved.target);
      }
    } catch (_) { /* Defaults work without storage. */ }
  }
  const reset = document.getElementById('ewt-path-reset');
  reset.hidden = false;
  form.addEventListener('change', event => {
    if (event.target === select('goal')) targets('');
    remember();
  });
  form.addEventListener('submit', remember);
  reset.addEventListener('click', () => {
    select('level').value = 'a1'; select('audience').value = 'adult';
    select('goal').value = 'general'; select('order').value = 'relevant'; targets('');
    try { localStorage.removeItem(storageKey); } catch (_) {}
  });
  window.ewtPathPreferences = { get: preferences, applyLevel(level) {
    if (!config.levels.includes(level)) return;
    select('level').value = level;
    remember();
    form.requestSubmit();
  } };
}());
