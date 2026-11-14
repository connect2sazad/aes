const menu = document.querySelector('.menu');
menu?.addEventListener('click', () => {
  const open = menu.getAttribute('aria-expanded') !== 'true';
  menu.setAttribute('aria-expanded', String(open));
  document.querySelector('#navigation').classList.toggle('open', open);
});
document.querySelectorAll('#navigation a').forEach(a => a.addEventListener('click', () => {
  menu.setAttribute('aria-expanded', 'false');
  document.querySelector('#navigation').classList.remove('open');
}));
document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('[data-filter]').forEach(b => {
    b.classList.toggle('active', b === button);
    b.setAttribute('aria-pressed', String(b === button));
  });
  let visible = 0;
  document.querySelectorAll('[data-category]').forEach(card => {
    card.hidden = button.dataset.filter !== 'all' && card.dataset.category !== button.dataset.filter;
    if (!card.hidden) visible++;
  });
  document.querySelector('#filter-empty').hidden = visible > 0 || button.dataset.filter === 'all';
}));
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(document.getElementById(button.dataset.copy).textContent); button.textContent = 'Copied'; }
  catch { button.textContent = 'Select the caption and copy manually'; }
}));
const helpSearch = document.querySelector('[data-help-search]');
helpSearch?.addEventListener('input', () => {
  const needle = helpSearch.value.trim().toLowerCase(); let count = 0;
  document.querySelectorAll('.help-topic').forEach(topic => {
    topic.hidden = needle !== '' && !topic.textContent.toLowerCase().includes(needle);
    if (!topic.hidden) { count++; if (needle) topic.open = true; }
  });
  document.querySelector('[data-help-results]').textContent = needle ? `${count} matching topics` : '';
});
document.querySelector('[data-print]')?.addEventListener('click', () => {
  const topics = [...document.querySelectorAll('.help-topic')];
  const states = topics.map(t => ({open:t.open, hidden:t.hidden}));
  topics.forEach(t => {t.open=true;t.hidden=false;});
  window.addEventListener('afterprint', () => topics.forEach((t,i) => {t.open=states[i].open;t.hidden=states[i].hidden;}), {once:true});
  window.print();
});
