'use strict';
const fs=require('fs');const path=require('path');
const {chromium}=require('C:/Users/rodri/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'..');const fixture=JSON.parse(fs.readFileSync(path.join(root,'storage/private/package-mobile.json'),'utf8'));
const out=path.join(root,'storage/qa-package');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});const results=[];
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'});const page=await context.newPage();
  await page.goto('http://rumo10.local/login');await page.locator('[name=email]').fill(fixture.email);await page.locator('[name=password]').fill(fixture.password);await Promise.all([page.waitForURL('**/carreira'),page.locator('button[type=submit]').click()]);
  const before=await page.request.get('http://rumo10.local/api/carreira/estado').then(r=>r.json());
  for(const viewport of [{width:320,height:640},{width:390,height:844},{width:844,height:390},{width:320,height:360}]){
   await page.setViewportSize(viewport);
   for(const route of ['/carreira','/classificacao','/carreiras','/carreira/trofeus']){
    await page.goto('http://rumo10.local'+route);await page.waitForLoadState('networkidle');
    if(route==='/classificacao'){const cta=page.getByRole('link',{name:'← Voltar à carreira',exact:true});if(await cta.count()!==1)throw new Error('CTA da tabela ausente');await cta.click();await page.waitForURL('**/carreira');}
    await page.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));
    const layout=await page.evaluate(()=>{const nav=document.querySelector('header nav.authenticated');const footer=document.querySelector('footer');return {horizontal:document.documentElement.scrollWidth>innerWidth+2,fixed:getComputedStyle(nav).position==='fixed',navTop:nav.getBoundingClientRect().top,footerBottom:footer.getBoundingClientRect().bottom,height:innerHeight};});
    if(layout.horizontal)throw new Error('Overflow horizontal: '+route+' '+viewport.width);
    if(layout.fixed && layout.footerBottom>layout.navTop+2)throw new Error('Menu cobre conteúdo final: '+route+' '+viewport.width+' '+JSON.stringify(layout));
    results.push({viewport,route,layout});
   }
  }
  await page.setViewportSize({width:320,height:640});await page.goto('http://rumo10.local/carreiras');await page.evaluate(()=>document.documentElement.style.zoom='1.5');await page.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));await page.screenshot({path:path.join(out,'mobile-zoom-150.png'),fullPage:true});results.push({zoom:'150% CSS',route:'/carreiras'});
  await page.evaluate(()=>document.documentElement.style.zoom='1');await page.goto('http://rumo10.local/carreira/nova');await page.locator('[name=city]').focus();await page.setViewportSize({width:320,height:300});await page.getByRole('button',{name:'Criar jogador →'}).scrollIntoViewIfNeeded();
  const button=await page.getByRole('button',{name:'Criar jogador →'}).boundingBox();const nav=await page.locator('header nav').boundingBox();if(button.y+button.height>nav.y+2)throw new Error('Viewport reduzida cobre CTA de criação');results.push({viewport:{width:320,height:300},focusedInput:true,keyboard:'viewport reduzida; sem teclado físico'});
  await page.setViewportSize({width:390,height:844});await page.goto('http://rumo10.local/classificacao');await page.screenshot({path:path.join(out,'mobile-classificacao.png'),fullPage:true});await page.goto('http://rumo10.local/carreira');await page.screenshot({path:path.join(out,'mobile-carreira.png'),fullPage:true});
  const after=await page.request.get('http://rumo10.local/api/carreira/estado').then(r=>r.json());if(JSON.stringify(before)!==JSON.stringify(after))throw new Error('Navegação GET mudou a carreira.');
  fs.writeFileSync(path.join(out,'mobile-results.json'),JSON.stringify({checks:results.length+1,results,unchangedCareer:true,browser:'Chrome headless, IIS local'},null,2));console.log('OK: '+(results.length+1)+' verificações de viewport, retorno GET, footer/menu e foco. Screenshots locais sem dados reais.');await context.close();
 }finally{await browser.close();}
})().catch(e=>{console.error(String(e.message).replaceAll(fixture.password,'[fixture]').replaceAll(fixture.email,'[fixture]'));process.exitCode=1;});
