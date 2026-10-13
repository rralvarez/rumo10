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
  if (form.closest('.transfer-actions') && form.closest('.wheel-drawing')) return;
  delete form.dataset.busy;
  form.removeAttribute('aria-busy');
  form.querySelectorAll('button').forEach(button => { button.disabled = false; });
}));

document.querySelectorAll('.career-wheel[data-wheel-selected="1"]').forEach(wheel => {
  const key = 'rumo10-wheel:' + wheel.dataset.wheelId;
  let seen = false;
  try { seen = sessionStorage.getItem(key) === '1'; sessionStorage.setItem(key, '1'); } catch (_) { /* Animação não determina o resultado. */ }
  if (seen || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !wheel.querySelector('.wheel-rotor').animate) return;
  const rotor = wheel.querySelector('.wheel-rotor');
  const status = wheel.querySelector('.wheel-status');
  const skip = wheel.querySelector('.wheel-skip');
  const parent = wheel.parentElement;
  const controls = Array.from(parent.querySelectorAll('.transfer-actions button'));
  wheel.classList.add('is-spinning'); parent.classList.add('wheel-drawing');
  status.textContent = 'A ROLETA ESTÁ GIRANDO…'; skip.hidden = false;
  controls.forEach(button => { button.disabled = true; });
  const spin = rotor.animate([
    { transform: `rotate(${-Number(wheel.dataset.wheelAngle)}deg)` },
    { transform: 'rotate(2160deg)' }
  ], { duration: 3800, easing: 'cubic-bezier(.12,.72,.08,1)', fill: 'forwards' });
  let done = false;
  const finish = () => {
    if (done) return; done = true;
    spin.cancel();
    wheel.classList.remove('is-spinning'); parent.classList.remove('wheel-drawing');
    status.textContent = 'RESULTADO CONFIRMADO'; skip.hidden = true;
    controls.forEach(button => { button.disabled = false; });
  };
  spin.onfinish = finish;
  skip.addEventListener('click', finish, { once: true });
});

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
