const assert = require('node:assert/strict');
const { test } = require('node:test');
const { score } = require('../theme/assets/js/placement-score.js');
const bank = require('../theme/assets/placement-questions.json');
const right = () => bank.map(q => q.answer);
test('bank covers six levels with unique IDs and explanations', () => {
  assert.equal(new Set(bank.map(q => q.id)).size, 30);
  for (const q of bank) {
    assert.equal(q.options.length, 4);
    assert.ok(q.explanation && q.question);
  }
  assert.deepEqual(score(bank, right()).blocks.map(b => b.total), [5, 5, 5, 5, 5, 5]);
});
test('all correct answers reach C2 and all unknowns recommend A1 foundations', () => {
  assert.equal(score(bank, right()).level, 'c2');
  const result = score(bank, bank.map(() => -1));
  assert.equal(result.level, 'a1');
  assert.equal(result.correct, 0);
  assert.equal(result.foundations, true);
});
test('four of five in each contiguous block advances; scattered advanced answers cannot skip foundations', () => {
  const answers = right();
  [4, 9, 14, 19].forEach(i => answers[i] = -1);
  answers[20] = -1; answers[21] = -1;
  const result = score(bank, answers);
  assert.equal(result.level, 'b2');
  assert.equal(result.foundations, false);
  const broken = right(); broken[0] = -1; broken[1] = -1;
  assert.equal(score(bank, broken).foundations, true);
});
test('incomplete, sparse, invalid and malformed tests are rejected', () => {
  assert.throws(() => score(bank, right().slice(1)));
  assert.throws(() => score(bank, new Array(30)));
  const bad = right(); bad[2] = 99;
  assert.throws(() => score(bank, bad));
  const malformed = bank.map(q => ({...q})); malformed[0].level = 'c2';
  assert.throws(() => score(malformed, right()));
});
