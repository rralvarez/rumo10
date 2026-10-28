<?php
declare(strict_types=1);
final class CareerBenefits {
 public static function enabled():bool{return Identity::enabled() && config('RUMOHUB_BENEFITS','0')==='1';}
 public static function call(string $action,array $data=[]):array {
  if(!self::enabled() || !isset($_SESSION['hub_token']))throw new RuntimeException('Benefícios centrais indisponíveis.');
  return Identity::service($action,['token'=>$_SESSION['hub_token']]+$data);
 }
 public static function quota(Repository $r,int $account):array {
  $hub=$r->query('SELECT hub_usuario_id FROM rumo10_identidades WHERE conta_id=?',[$account])->fetchColumn();
  if(!$hub || (int)($_SESSION['hub_user']['id']??0)!==(int)$hub)throw new RuntimeException('Vínculo central não confirmado.');
  $u=self::call('benefits')['user'];if((int)$u['id']!==(int)$hub || !isset($u['benefits']['rumo10']))throw new RuntimeException('Direito específico do jogo ausente.');
  $paid=$u['benefits']['rumo10']['paid'];$used=count(CareerSlots::listing($r,$account));
  $ref=self::call('referrals');$sources=$r->query("SELECT o.tipo,COUNT(*) AS total FROM rumo10_origem_vagas o JOIN rumo10_carreiras c ON c.id=o.carreira_id LEFT JOIN rumo10_carreiras_arquivadas a ON a.carreira_id=c.id WHERE (c.conta_id=? OR (c.conta_id IS NULL AND a.conta_id=?)) AND NOT EXISTS(SELECT 1 FROM rumo10_historico_encerrado h WHERE h.carreira_id=c.id) GROUP BY o.tipo",[$account,$account])->fetchAll();
  $counts=array_column($sources,'total','tipo');$extras=(int)($counts['INDICACAO']??0);$nonExtra=$used-$extras;
  return ['plan'=>$paid?'pago':'gratuito','source'=>$u['benefits']['rumo10']['source'],'limit'=>$paid?10:min(5,max(2,$nonExtra)+min(3,$extras+(int)$ref['available'])),'used'=>$used,'base'=>2,'extras'=>$extras,'non_extra'=>$nonExtra,'credits'=>$ref['available'],'credits_used'=>$ref['used'],'referrals'=>$ref];
 }
 public static function allocate(Repository $r,int $account,array $quota,string $key):array {
  if($quota['plan']==='pago'){if($quota['used']>=10)throw new InvalidArgumentException('Limite de 10 carreiras atingido.');return ['type'=>'PAGO'];}
  if($quota['used']>=5)throw new InvalidArgumentException('O plano gratuito permite no máximo cinco carreiras simultâneas. As legadas estão preservadas.');
  if($quota['non_extra']<2)return ['type'=>'BASE'];
  if($quota['extras']>=3 || $quota['credits']<1)throw new InvalidArgumentException('As duas vagas-base estão ocupadas. Convide um novo amigo para conquistar uma carreira extra.');
  $credit=self::call('reserve-credit',['key'=>$key]);return ['type'=>'INDICACAO','credit_id'=>(int)$credit['credit_id'],'reservation'=>$credit['reservation']];
 }
 public static function queue(Repository $r,int $account,int $career,array $allocation):void {
  $r->query('INSERT INTO rumo10_origem_vagas(carreira_id,tipo,credito_id,reserva) VALUES(?,?,?,?)',[$career,$allocation['type'],$allocation['credit_id']??null,$allocation['reservation']??null]);
  $r->query("INSERT INTO rumo10_beneficios_outbox(conta_id,carreira_id,evento,chave) VALUES(?,?,'primeira_carreira',?)",[$account,$career,hash('sha256','first-career|'.$account.'|'.$career)]);
  if(isset($allocation['credit_id']))$r->query("INSERT INTO rumo10_beneficios_outbox(conta_id,carreira_id,evento,chave,credito_id,reserva) VALUES(?,?,'consumir_credito',?,?,?)",[$account,$career,hash('sha256','credit|'.$allocation['reservation']),$allocation['credit_id'],$allocation['reservation']]);
 }
 public static function flush(Repository $r,int $account):void {
  if(!self::enabled() || $r->db->inTransaction())return;
  foreach($r->query("SELECT * FROM rumo10_beneficios_outbox WHERE conta_id=? AND estado='pendente' ORDER BY id LIMIT 20",[$account])->fetchAll() as $event){
   try{$data=['career_id'=>(int)$event['carreira_id'],'key'=>$event['evento']==='consumir_credito'?$event['reserva']:$event['chave']];if($event['credito_id'])$data['credit_id']=(int)$event['credito_id'];
    self::call($event['evento']==='consumir_credito'?'consume-credit':'first-career',$data);$r->query("UPDATE rumo10_beneficios_outbox SET estado='confirmado',tentativas=tentativas+1 WHERE id=?",[$event['id']]);
   }catch(Throwable $e){$r->query('UPDATE rumo10_beneficios_outbox SET tentativas=tentativas+1 WHERE id=?',[$event['id']]);error_log('rumo10: confirmação de benefício pendente; '.$e::class);}
  }
 }
 public static function release(array $allocation):void {if(isset($allocation['credit_id']))try{self::call('release-credit',['credit_id'=>$allocation['credit_id'],'key'=>$allocation['reservation']]);}catch(Throwable $e){error_log('rumo10: reserva de crédito pendente de reconciliação; '.$e::class);}}
 public static function invitation():void {
  $data=self::call('referrals');$url=rtrim(config('APP_URL','http://rumo10.local'),'/').'/convite?code='.($data['token']??'');
  echo '<section class="panel"><h1>Convidar amigos</h1><p>Você ganha 1 crédito para abrir uma carreira extra quando seu amigo entrar pelo seu link, criar a conta e começar a primeira carreira no Rumo à Camisa 10. Limite de 5 carreiras simultâneas no gratuito. Quando encerrar uma carreira criada com crédito, o crédito não volta; para criar outra extra será necessária uma nova indicação.</p><p>Cadastro isolado não gera crédito. Créditos acumulam; quem tem acesso pago ou cortesia não os gasta para criar carreiras.</p><label>Seu link<input id="invite-link" class="form-control" value="'.e($url).'" readonly></label><button type="button" class="btn btn-primary" data-copy-invite>Copiar link</button> <a class="btn btn-outline-light" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text='.e(rawurlencode('Venha jogar Rumo à Camisa 10 comigo: '.$url)).'">Compartilhar no WhatsApp</a><p data-copy-status role="status"></p><p>Disponíveis: '.e($data['available']).' · utilizados ou em confirmação: '.e($data['used']).'</p><h2>Suas indicações</h2>';
  if(!$data['items'])echo '<p>Aguardando novos cadastros pelo seu link.</p>';
  foreach($data['items'] as $item)echo '<p>'.e($item['reference']).' · '.e($item['state']).'</p>';
  echo '<p>O nome do amigo só aparece com consentimento. A preferência é controlada por ele na <a href="'.e(rtrim(config('RUMOHUB_URL'),'/').'/conta').'">conta Rumo</a>.</p><a href="/carreiras">Voltar a Minhas carreiras</a></section>';
 }
}
