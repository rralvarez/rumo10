(() => {
'use strict';
// Sorteios e probabilidades pertencem exclusivamente ao servidor.
let pendingDecision = null;
const waitPanel = document.createElement('section');
waitPanel.className = 'decision-wait'; waitPanel.hidden = true;
waitPanel.setAttribute('role', 'status'); waitPanel.setAttribute('aria-live', 'polite');
const waitText = document.createElement('p');
const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-primary'; retry.textContent = 'Tentar confirmar novamente'; retry.hidden = true;
const reload = document.createElement('a'); reload.href = '/carreira'; reload.textContent = 'Consultar estado salvo'; reload.hidden = true;
waitPanel.append(waitText, retry, reload); document.body.append(waitPanel);
function releaseDecision() {
  document.querySelectorAll('[data-request-disabled]').forEach(button => { button.disabled = false; delete button.dataset.requestDisabled; });
  document.querySelectorAll('form[data-busy]').forEach(form => { delete form.dataset.busy; form.classList.remove('submitting'); form.removeAttribute('aria-busy'); });
  pendingDecision = null;
}
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', async event => {
  if (form.dataset.busy || pendingDecision) { event.preventDefault(); return; }
  const endpoint = new URL(form.action, location.href);
  const gameAction = endpoint.origin === location.origin && /^(\/decisao\/registrar|\/partida\/(decidir|avancar)|\/carreira\/(simular|handicap|clube-inicial)|\/competicao\/continuar|\/transferencia\/(roleta|responder))$/.test(endpoint.pathname);
  if (!gameAction || !window.fetch || !window.AbortController) {
    form.dataset.busy = '1'; form.classList.add('submitting'); form.setAttribute('aria-busy', 'true');
    setTimeout(() => form.querySelectorAll('button').forEach(button => { button.disabled = true; }), 0); return;
  }
  event.preventDefault();
  const submitter = event.submitter;
  const data = new FormData(form); if (submitter?.name) data.append(submitter.name, submitter.value);
  pendingDecision = form; form.dataset.busy = '1'; form.setAttribute('aria-busy', 'true');
  document.querySelectorAll('form button:not(:disabled)').forEach(button => { button.dataset.requestDisabled = '1'; button.disabled = true; });
  retry.hidden = reload.hidden = true; waitPanel.hidden = false;
  waitText.textContent = endpoint.pathname.startsWith('/transferencia/') ? 'Estamos preparando a próxima temporada e as oportunidades...' : endpoint.pathname === '/carreira/simular' ? 'Estamos acompanhando as partidas e salvando sua carreira...' : 'Estamos resolvendo sua decisão e salvando o resultado...';
  const controller = new AbortController(); const timer = setTimeout(() => controller.abort(), 45000);
  try {
    const response = await fetch(endpoint, { method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal });
    const html = await response.text();
    if (!response.ok) {
      const parsed = new DOMParser().parseFromString(html, 'text/html');
      throw new Error(parsed.querySelector('.alert-danger')?.textContent?.trim() || 'O servidor não conseguiu confirmar a ação.');
    }
    if (new URL(response.url).origin !== location.origin) throw new Error('Não foi possível confirmar a resposta.');
    clearTimeout(timer);
    // Documento entregue pelo próprio servidor; não resolver sorteios nem reconstruir o save no navegador.
    history.replaceState(null, '', response.url); document.open(); document.write(html); document.close();
  } catch (error) {
    releaseDecision(); waitPanel.hidden = false;
    waitText.textContent = error.name === 'AbortError' ? 'A resposta demorou. A ação pode já ter sido salva. Tente confirmar com o mesmo formulário ou consulte o estado salvo.' : (error instanceof TypeError ? 'A conexão falhou. A ação pode já ter sido salva. Tente confirmar novamente ou consulte o estado salvo.' : error.message);
    retry.hidden = reload.hidden = false;
    retry.onclick = () => form.requestSubmit(submitter || undefined);
    retry.focus();
  } finally { clearTimeout(timer); }
}));
window.addEventListener('pageshow', () => { releaseDecision(); waitPanel.hidden = true; });

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
    if (document.querySelector('[name="residence_country"]')?.value !== 'BR') return;
    try {
      const response = await fetch('/api/cidades?uf=' + encodeURIComponent(cityUf.value), { signal: cityRequest.signal });
      if (!response.ok) return;
      const names = await response.json();
      cityList.replaceChildren(...names.map(name => { const option = document.createElement('option'); option.value = name; return option; }));
    } catch (_) { /* O campo continua disponível e a validação permanece no servidor. */ }
  };
  cityUf.addEventListener('change', loadCities);
  const residence = document.querySelector('[name="residence_country"]');
  const updateResidence = () => {
    const brazil = residence?.value === 'BR';
    document.querySelectorAll('[data-brazil-location]').forEach(el => { el.hidden = !brazil; });
    document.querySelectorAll('[data-foreign-location]').forEach(el => { el.hidden = brazil; });
    loadCities();
  };
  residence?.addEventListener('change', updateResidence);
  updateResidence();
  loadCities();
}

const bottomNav = document.querySelector('header nav.authenticated');
if (bottomNav && 'ResizeObserver' in window) {
  const reserveNav = () => document.documentElement.style.setProperty('--bottom-nav-space',
    window.matchMedia('(max-width:760px)').matches ? `${Math.ceil(bottomNav.getBoundingClientRect().height) + 24}px` : '0px');
  new ResizeObserver(reserveNav).observe(bottomNav);
  window.addEventListener('resize', reserveNav);
  window.visualViewport?.addEventListener('resize', reserveNav);
  reserveNav();
}

document.querySelector('[data-copy-invite]')?.addEventListener('click', async () => {
  const field = document.getElementById('invite-link');
  const status = document.querySelector('[data-copy-status]');
  try { await navigator.clipboard.writeText(field.value); status.textContent = 'Link copiado.'; }
  catch (_) { field.focus(); field.select(); status.textContent = 'Selecione e copie o link acima.'; }
});

})();
