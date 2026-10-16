from pathlib import Path
import subprocess,json,os,hashlib,re
ROOT=Path(__file__).resolve().parents[1];out=ROOT/'Docs/narrativa-v1';php='C:/PHP/php-8.4.26/php.exe';opts=['-n','-d','extension_dir=C:/PHP/php-8.4.26/ext','-d','extension=mbstring','-d','memory_limit=768M']
env=dict(os.environ,RUMOHUB_ENABLED='0',RUMOHUB_BENEFITS='0')
suites=['narrative','narrative-modes','scenes-strength','scene-consequences','scene-failures','weekend-editorial','social-context','finishing','match-momentum','match-balance','normal-xp','lifecycle','package-engine','package-cohorts','package-seasons','presentation','season-presentation','wheel']
mechanics=(ROOT/'Docs/estabilizacao-v04/baseline.json').exists()
if mechanics:suites+=['execution-v04','mechanics-boundary']
if mechanics:
 out=ROOT/'Docs/estabilizacao-v04/regressao';out.mkdir(exist_ok=True)
 env['NARRATIVE_EVIDENCE_DIR']=str(out)
results=[]
for name in suites:
 p=subprocess.run([php,*opts,'tests/'+name+'.php'],cwd=ROOT,env=env,capture_output=True,timeout=180)
 result={'suite':name,'exit':p.returncode,'stdout':p.stdout.decode('utf-8',errors='replace'),'stderr':p.stderr.decode('utf-8',errors='replace')};results.append(result);print(name, p.returncode,flush=True)
 (out/'suites.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
 if p.returncode:print(result['stdout'][-1600:]);print(result['stderr'][-1600:])
protected=['app/Probability.php','app/MatchBalance.php','app/MatchMomentum.php','app/TeamStrength.php','app/Progression.php','app/Evolution.php','app/TransferMarket.php','app/Rules.php','app/CareerBenefits.php','app/Identity.php','public/.htaccess','public/web.config']
refs=[n for n in subprocess.check_output(['git','ls-tree','-r','--name-only','dbda309','--','database'],cwd=ROOT).decode().splitlines() if n.count('/')==1 and n.endswith('.json')]
protected+=refs
guard=[]
for name in protected:
 old=subprocess.check_output(['git','show','dbda309:'+name],cwd=ROOT)
 now=(ROOT/name).read_bytes().replace(b'\r\n',b'\n');before=old.replace(b'\r\n',b'\n')
 guard.append({'file':name,'sha256':hashlib.sha256(now).hexdigest(),'unchanged':now==before,'intentional_v04':mechanics and name=='app/Probability.php'})
(out/'protected-files.json').write_text(json.dumps(guard,indent=2),encoding='utf-8')
if not all(r['unchanged'] or r['intentional_v04'] for r in guard):raise SystemExit('Arquivo fora do escopo alterado')
old=subprocess.check_output(['git','show','dbda309:app/Game.php'],cwd=ROOT).decode().replace('\r\n','\n');now=(ROOT/'app/Game.php').read_text(encoding='utf-8')
methods={}
for name,pattern in {'chance':r'public static function chance\(.*?(?=    public static function command)','effects':r'private static function effects\(.*?(?=    private static function fieldDecision)'}.items():
 a=re.search(pattern,old,re.S).group(0);b=re.search(pattern,now,re.S).group(0);methods[name]={'unchanged':a==b,'sha256':hashlib.sha256(b.encode()).hexdigest(),'intentional_v04':mechanics and name=='chance'}
(out/'protected-methods.json').write_text(json.dumps(methods,indent=2),encoding='utf-8')
if not all(v['unchanged'] or v['intentional_v04'] for v in methods.values()):raise SystemExit('Método probabilístico/XP alterado')
lint=[]
for name in ['app/Scenes.php','app/NarrativeCatalog.php','app/NarrativeSelection.php','app/NarrativeContext.php','app/Game.php','app/PlayMode.php','app/view.php','tests/narrative.php','tests/narrative-modes.php','tests/narrative-baseline.php','tests/narrative-preview.php']:
 p=subprocess.run([php,'-n','-l',name],cwd=ROOT,capture_output=True);lint.append({'file':name,'exit':p.returncode})
(out/'lint.json').write_text(json.dumps(lint,indent=2),encoding='utf-8')
if any(r['exit'] for r in results+lint):raise SystemExit(1)
