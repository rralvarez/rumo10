'use strict';
// Sorteios e probabilidades pertencem exclusivamente ao servidor.
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
  if (form.dataset.busy) { event.preventDefault(); return; }
  form.dataset.busy = '1';
  form.classList.add('submitting');
  form.setAttribute('aria-busy', 'true');
  // Não desabilitar antes do envio: o valor do botão identifica a escolha.
  setTimeout(() => form.querySelectorAll('button').forEach(button => { button.disabled = true; }), 0);
}));
window.addEventListener('pageshow', () => document.querySelectorAll('form').forEach(form => {
  delete form.dataset.busy;
  form.removeAttribute('aria-busy');
  form.querySelectorAll('button').forEach(button => { button.disabled = false; });
}));

// Anima somente a apresentação de um resultado já decidido e salvo pelo PHP.
document.querySelectorAll('.action-result[data-result-id]').forEach(result => {
  const key = 'rumo10-result:' + result.dataset.resultId;
  let seen = true;
  try { seen = sessionStorage.getItem(key) === '1'; sessionStorage.setItem(key, '1'); } catch (_) { /* Resultado permanece legível sem storage. */ }
  if (seen || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const skip = result.querySelector('.skip-result');
  const status = result.querySelector('.result-status');
  const finish = () => {
    result.classList.remove('is-revealing');
    status.textContent = 'Resultado salvo';
    skip.hidden = true;
  };
  result.classList.add('is-revealing');
  status.textContent = 'Resolvendo a ação…';
  skip.hidden = false;
  const timer = setTimeout(finish, 650);
  skip.addEventListener('click', () => { clearTimeout(timer); finish(); }, { once: true });
});
