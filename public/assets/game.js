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
  if (wheel.closest('.entry-draw')) wheel.closest('.entry-draw').classList.add('draw-complete');
  if (seen || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !wheel.querySelector('.wheel-rotor').animate) return;
  const rotor = wheel.querySelector('.wheel-rotor');
  const status = wheel.querySelector('.wheel-status');
  const skip = wheel.querySelector('.wheel-skip');
  const parent = wheel.parentElement;
  const entry = wheel.closest('.entry-draw');
  const offers = entry?.nextElementSibling;
  const controls = Array.from(parent.querySelectorAll('.transfer-actions button'));
  if (offers?.classList.contains('entry-offers')) controls.push(...offers.querySelectorAll('button'));
  if (wheel.closest('.entry-draw')) wheel.closest('.entry-draw').classList.remove('draw-complete');
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
    if (wheel.closest('.entry-draw')) wheel.closest('.entry-draw').classList.add('draw-complete');
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
  const finish = () => {
    result.classList.remove('is-revealing');
  };
  result.classList.add('is-revealing');
  setTimeout(finish, 650);
});

// Sugestões locais por UF; o PHP valida município e estado antes de criar a carreira.
const cityUf = document.querySelector('select[name="uf"]');
const cityList = document.getElementById('city-options');
if (cityUf && cityList) {
  let cityRequest;
  const loadCities = async () => {
    if (cityRequest) cityRequest.abort();
    cityRequest = new AbortController();
    cityList.replaceChildren();
    try {
      const response = await fetch('/api/cidades?uf=' + encodeURIComponent(cityUf.value), { signal: cityRequest.signal });
      if (!response.ok) return;
      const names = await response.json();
      cityList.replaceChildren(...names.map(name => { const option = document.createElement('option'); option.value = name; return option; }));
    } catch (_) { /* O campo continua disponível e a validação permanece no servidor. */ }
  };
  cityUf.addEventListener('change', loadCities);
  loadCities();
}
