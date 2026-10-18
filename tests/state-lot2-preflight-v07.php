<?php
declare(strict_types=1);
/** Diagnostic probes, NOT acceptance tests for newly implemented competitions. */
putenv('RUMOHUB_ENABLED=0');
putenv('RUMOHUB_BENEFITS=0');
require dirname(__DIR__).'/app/bootstrap.php';
$checks=[];
$definition=CandangaoTrial::reference()['definitions']['df-a1'];
$definition['id']='synthetic-intergroup-probe';
$definition['teams']=['A','B','C','D'];
$definition['state_engine']=[
    'version'=>ConfiguredStateCompetition::VERSION,
    'tiebreakers'=>['points','wins','gd','gf','red','yellow','lots'],
    'stages'=>[['name'=>'liga','type'=>'league','return'=>false,
        'groups'=>[['A','B'],['C','D']],
        'fixtures'=>[[1,'A','C'],[1,'B','D'],[2,'A','D'],[2,'B','C']]]],
    'final_ranking'=>'campaign','relegation'=>0,
];
unset($definition['reference_clubs']);
$rejected=false;
try {ConfiguredStateCompetition::create($definition,$definition['teams']);}
catch(RuntimeException $e) {$rejected=$e->getMessage()==='Tabela inicial incompleta.';}
if(!$rejected)throw new RuntimeException('Rever diagnóstico: tabela intergrupos passou a ser suportada.');
$checks[]=['id'=>'intergroup_fixture','observed'=>'rejected_by_existing_engine',
    'scope'=>['PR','GO'],'synthetic_fixture'=>true];

// AC art.7 §1: equal points -> better initial campaign, before goal difference.
// Deliberately show why the existing aggregate advantage MUST NOT be reused.
$t=['matches'=>[],'phase_end'=>0];
Competition::knockout($t,[['better_initial','worse_initial']],'semifinal',2,'advantage');
foreach($t['matches'] as &$m){$m['status']='finalizada';$m['hg']=1;$m['ag']=0;}
unset($m);
// First leg worse wins 3–0; second leg better wins 1–0: three points each.
$t['matches'][0]['hg']=3;
$winner=Competition::resolve($t)[0];
if($winner!=='worse_initial')throw new RuntimeException('Rever diagnóstico: semântica de advantage mudou.');
$checks[]=['id'=>'points_before_goal_difference','scope'=>['AC'],
    'observed_existing_winner'=>$winner,'required_by_article_7_1'=>'better_initial',
    'synthetic_fixture'=>true,'reusable_without_extension'=>false];

// Existing routed pools cannot select semifinal losers for an actual bronze match.
$bronze=$definition;
$bronze['state_engine']['version']=ConfiguredStateCompetition::ROUTED_VERSION;
$bronze['state_engine']['stages']=[
    ['name'=>'liga','type'=>'league','return'=>false],
    ['name'=>'semifinal','type'=>'knockout','legs'=>2,'tie_rule'=>'penalties',
        'selection'=>'initial','home_basis'=>'initial','pairs'=>[[0,3],[1,2]]],
    ['name'=>'bronze','type'=>'knockout','legs'=>1,'tie_rule'=>'penalties',
        'selection'=>'routed','home_basis'=>'initial','pairs'=>[[0,1]],
        'entries'=>[['from'=>'loser','phase'=>'semifinal','index'=>0],
                    ['from'=>'loser','phase'=>'semifinal','index'=>1]]],
];
$rejected=false;
try {ConfiguredStateCompetition::validate($bronze,$bronze['teams']);}
catch(RuntimeException $e) {$rejected=$e->getMessage()==='Origem do classificado inválida.';}
if(!$rejected)throw new RuntimeException('Rever diagnóstico: seleção de perdedores mudou.');
$checks[]=['id'=>'bronze_loser_selection','scope'=>['AC'],
    'observed'=>'rejected_by_existing_engine','synthetic_fixture'=>true];

$path=dirname(__DIR__).'/Docs/mundializacao-v07/expansao-estadual/lote2';
if(!is_dir($path))mkdir($path,0777,true);
file_put_contents($path.'/preflight.json',json_encode([
    'kind'=>'capability_gap_diagnostics_not_new_competition_acceptance',
    'checks'=>$checks,'new_federations_integrated'=>0,'new_candidate_receipts'=>0,
    'national_entries'=>0,'national_renewals'=>0,
],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
echo 'OK: '.count($checks)." impedimentos técnicos reproduzidos; nenhuma competição nova validada.\n";
