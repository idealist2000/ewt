document.addEventListener('click',function(event){
 const say=event.target.closest('.ewt-esp-say');
 if(say){if('speechSynthesis' in window){window.speechSynthesis.cancel();const utterance=new SpeechSynthesisUtterance(say.dataset.word||'');utterance.lang='en-US';utterance.rate=.85;window.speechSynthesis.speak(utterance);}return;}
 const btn=event.target.closest('.ewt-esp-check,.ewt-esp-reset');if(!btn)return;
 const quiz=btn.closest('.ewt-esp-quiz');if(!quiz)return;
 const fields=Array.from(quiz.querySelectorAll('.ewt-esp-question'));
 if(btn.classList.contains('ewt-esp-reset')){fields.forEach(q=>{q.classList.remove('ewt-esp-right','ewt-esp-wrong','ewt-esp-unanswered');q.querySelectorAll('input[type=radio]').forEach(input=>input.checked=false);const fb=q.querySelector('.ewt-esp-feedback');if(fb)fb.textContent='';});quiz.querySelector('.ewt-esp-result').textContent='';return;}
 let score=0,answered=0;
 fields.forEach(q=>{
  const chosen=q.querySelector('input[type=radio]:checked');const feedback=q.querySelector('.ewt-esp-feedback');
  q.classList.remove('ewt-esp-right','ewt-esp-wrong','ewt-esp-unanswered');
  if(!chosen){q.classList.add('ewt-esp-unanswered');if(feedback)feedback.textContent='Please choose an answer.';return;}
  answered++;const right=chosen.value===q.dataset.answer;
  q.classList.add(right?'ewt-esp-right':'ewt-esp-wrong');if(right)score++;
  const correct=q.querySelector('input[value="'+q.dataset.answer+'"]');
  if(feedback)feedback.textContent=right?'Correct!':'Not quite. Correct answer: '+(correct?.closest('label')?.innerText||'').trim();
 });
 quiz.querySelector('.ewt-esp-result').textContent=answered<fields.length?'Answered '+answered+' of '+fields.length+'. Current score: '+score+'/'+fields.length+'.':'Your score: '+score+' / '+fields.length+'.';
});
