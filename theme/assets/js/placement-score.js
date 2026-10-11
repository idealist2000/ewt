(function (root) {
  'use strict';
  const levels = ['a1', 'a2', 'b1', 'b2', 'c1', 'c2'];
  function score(questions, answers) {
    if (!Array.isArray(questions) || questions.length !== 30 || !Array.isArray(answers) || answers.length !== questions.length) throw new Error('Incomplete placement test');
    const blocks = levels.map(level => ({ level, total: 0, correct: 0 }));
    questions.forEach((question, index) => {
      const block = blocks.find(item => item.level === question.level);
      if (!block || !Array.isArray(question.options) || !Number.isInteger(question.answer) || question.answer < 0 || question.answer >= question.options.length) throw new Error('Invalid question');
      if (!Number.isInteger(answers[index]) || answers[index] < -1 || answers[index] >= question.options.length) throw new Error('Invalid answer');
      block.total++;
      if (answers[index] === question.answer) block.correct++;
    });
    if (blocks.some(block => block.total !== 5)) throw new Error('Invalid level coverage');
    let last = -1;
    for (let index = 0; index < blocks.length; index++) {
      if (blocks[index].correct < 4) break;
      last = index;
    }
    return { level: levels[Math.max(0, last)], foundations: last === -1, correct: blocks.reduce((sum, block) => sum + block.correct, 0), blocks };
  }
  const api = { score };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.ewtPlacementScore = api;
}(typeof window !== 'undefined' ? window : globalThis));
