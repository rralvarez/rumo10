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
