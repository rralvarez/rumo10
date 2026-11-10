<?php
declare(strict_types=1);
namespace Rumo\Publisher;
require_once __DIR__.'/GitHub.php';
final class FenceReader extends GitHub {
 private string $repo;
 public function __construct(array $c){parent::__construct($c);$this->repo=$c['repository'];}
 public function get(string $path):array{
  need(str_starts_with($path,'/repos/'.$this->repo.'/'),'Fence path outside configured repository');
  $r=$this->request('https://api.github.com'.$path,true,4*1024*1024);need($r['status']===200,'Fence API denied/unavailable: '.$r['status']);return json($r['body']);
 }
}
final class LegacyFence {
 /** Read-only observation, NOT a remote lock. Manual channels and changes during window require operator fencing. */
 public static function verify(array $profile,callable $get):array{
  need(($profile['manual_channels_fenced']??false)===true,'Manual deployment channels not fenced');
  $repos=$profile['repositories']??[];$names=array_keys($repos);sort($names);need($names===['rralvarez/rumo10','rralvarez/rumohub'],'Both repositories must be inspected');$proof=[];
  foreach($repos as $repo=>$cfg){
   $base='/repos/'.$repo;$list=[];$count=null;
   for($page=1;$page<=10;$page++){$v=$get($repo,$base.'/actions/workflows?per_page=100&page='.$page);need(is_int($v['total_count']??null),'Invalid workflow listing');$count??=$v['total_count'];need($v['total_count']===$count,'Workflow listing changed during pagination');$chunk=$v['workflows']??null;need(is_array($chunk),'Invalid workflows');foreach($chunk as $w){need(isset($w['id'],$w['path'],$w['state']),'Incomplete workflow');need(!isset($list[$w['id']]),'Duplicate workflow');$list[$w['id']]=$w;}if(count($list)===$count)break;need(count($list)<$count&&count($chunk)===100,'Incomplete workflow pagination');}
   need(count($list)===$count,'Workflow pagination limit reached');$legacy=[];
   foreach($list as $w){$path=$w['path'];if(in_array($path,['.github/workflows/production.yml','.github/workflows/production-ftps.yml'],true)){need($w['state']==='disabled_manually','Legacy publication workflow must be explicitly disabled');$legacy[]=$path;}
    elseif($w['state']==='active'){
     $pin=$cfg['approved_active'][$path]??null;need(is_array($pin)&&preg_match('/^[a-f0-9]{40}$/D',$pin['commit']??'')===1,'Unapproved active workflow');
     $ref=$get($repo,$base.'/git/ref/heads/'.rawurlencode($pin['branch']??$cfg['branch']));$expected=($pin['track_head']??false)?($ref['object']['sha']??''):$pin['commit'];need(preg_match('/^[a-f0-9]{40}$/D',$expected)===1&&($ref['object']['sha']??'')===$expected,'Control branch moved');
     $file=$get($repo,$base.'/contents/'.$path.'?ref='.$expected);need(($file['encoding']??'')==='base64','Workflow content unavailable');$bytes=base64_decode(preg_replace('/\s+/','',$file['content']??''),true);need($bytes!==false&&hash('sha256',$bytes)===$pin['sha256'],'Active workflow hash changed');
    }else need(in_array($w['state'],['disabled_manually','disabled_inactivity','deleted'],true),'Unknown workflow state');
   }
   need(count($legacy)===2,'Legacy workflow inventory missing');
   foreach(['in_progress','queued','requested','waiting','pending'] as $status){$v=$get($repo,$base.'/actions/runs?status='.$status.'&per_page=100');need(($v['total_count']??null)===0&&($v['workflow_runs']??null)===[],'Outstanding workflow run: '.$status);}
   $proof[$repo]=['legacy_disabled'=>true,'pending_runs'=>0,'workflow_count'=>count($list)];
  }
  return ['ok'=>true,'legacy_publishers_fenced'=>true,'observation_only'=>true,'operator_manual_fence_required'=>true,'repositories'=>$proof];
 }
}
