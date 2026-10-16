<?php
declare(strict_types=1);
final class GameFeedback {
 public const CATEGORIES=['elogio'=>'Elogio','sugestao'=>'Sugestão','bug'=>'Problema / bug','dificuldade'=>'Dificuldade de jogo','outra'=>'Outra'];
 public static function ready(Repository $r):bool{return config('RUMO10_FEEDBACK','0')==='1'&&FeatureSchema::ready($r,['rumo10_feedback']);}
 public static function validate(array $input):array {
  foreach(['user_id','hub_usuario_id','account_id'] as $forbidden)if(isset($input[$forbidden]))throw new InvalidArgumentException('A identidade é definida pela sua sessão.');
  $rating=$input['rating']??null;if(!is_scalar($rating)||!preg_match('/^[1-5]$/',(string)$rating))throw new InvalidArgumentException('Escolha de 1 a 5 estrelas.');
  $category=$input['category']??'';$comment=$input['comment']??'';$key=$input['key']??'';
  if(!is_string($category)||($category!==''&&!isset(self::CATEGORIES[$category])))throw new InvalidArgumentException('Categoria inválida.');
  if(!is_string($comment)||!mb_check_encoding($comment,'UTF-8')||mb_strlen($comment)>1000)throw new InvalidArgumentException('Use no máximo 1.000 caracteres no comentário.');
  if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/',$key))throw new InvalidArgumentException('Recarregue o formulário antes de enviar.');
  $comment=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$comment));
  return ['rating'=>(int)$rating,'category'=>$category?:null,'comment'=>$comment,'key'=>$key];
 }
 public static function submit(Repository $r,int $account,array $input):void {
  if(!self::ready($r))throw new InvalidArgumentException('O feedback ainda não está disponível nesta instalação.');$v=self::validate($input);$hub=FeatureSchema::hub($r,$account);$hash=hash('sha256',json_encode(array_diff_key($v,['key'=>true]),JSON_THROW_ON_ERROR));
  $r->query("SET time_zone='+00:00'");$r->db->beginTransaction();try{
   $r->query('SELECT id FROM rumo10_contas WHERE id=? FOR UPDATE',[$account]);
   $old=$r->query('SELECT request_hash FROM rumo10_feedback WHERE hub_usuario_id=? AND chave=?',[$hub,$v['key']])->fetchColumn();
   if($old){if(!hash_equals($old,$hash))throw new InvalidArgumentException('Este envio já foi usado com outro conteúdo. Reabra o formulário.');$r->db->commit();return;}
   $count=(int)$r->query('SELECT COUNT(*) FROM rumo10_feedback WHERE hub_usuario_id=? AND criado_em>=UTC_TIMESTAMP()-INTERVAL 24 HOUR',[$hub])->fetchColumn();
   if($count>=5)throw new InvalidArgumentException('Você já enviou cinco avaliações nas últimas 24 horas. Tente novamente mais tarde.');
   $r->query('INSERT INTO rumo10_feedback(hub_usuario_id,chave,request_hash,nota,categoria,comentario,versao,criado_em) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$hub,$v['key'],$hash,$v['rating'],$v['category'],$v['comment'],'0.6.1']);$r->db->commit();
  }catch(Throwable $e){if($r->db->inTransaction())$r->db->rollBack();throw $e;}
 }
 public static function render(Repository $r):void {
  echo '<section class="panel feedback-page"><p class="tag">SUA EXPERIÊNCIA IMPORTA</p><h1>Avaliar o jogo</h1><p>É rápido e opcional. Sua avaliação é privada e não muda XP, conquistas ou benefícios.</p>';
  if(!self::ready($r)){echo '<p role="status">O formulário está em preparação. Tente novamente depois.</p><a href="/carreira">Voltar à carreira</a></section>';return;}
  if(!empty($_SESSION['feedback_received'])){unset($_SESSION['feedback_received']);echo '<p class="feedback-received" role="status">Avaliação recebida. Obrigado por ajudar a melhorar o jogo!</p>';}
  $key=is_string($_POST['key']??null)&&preg_match('/^[a-f0-9]{64}$/',$_POST['key'])?$_POST['key']:bin2hex(random_bytes(32));
  form('/feedback');echo '<input type="hidden" name="key" value="'.e($key).'"><fieldset class="feedback-rating"><legend>Como está sua experiência? <small>(obrigatório)</small></legend><div class="rating-stars">';
  for($n=1;$n<=5;$n++)echo '<label><input type="radio" name="rating" value="'.$n.'" required'.(((string)($_POST['rating']??''))===(string)$n?' checked':'').'><span aria-hidden="true">★</span><span class="visually-hidden">'.$n.' '.($n===1?'estrela':'estrelas').'</span></label>';
  echo '</div><p class="subtle">1: precisa melhorar · 5: estou gostando muito</p></fieldset><label for="feedback-category">Categoria <small>(opcional)</small></label><select id="feedback-category" class="form-select" name="category"><option value="">Escolha se quiser</option>';
  foreach(self::CATEGORIES as $value=>$label)echo '<option value="'.$value.'"'.(($_POST['category']??'')===$value?' selected':'').'>'.e($label).'</option>';
  echo '</select><label for="feedback-comment">Comentário <small>(opcional)</small></label><textarea id="feedback-comment" class="form-control" name="comment" rows="4" maxlength="1000" aria-describedby="feedback-count feedback-privacy">'.e(is_string($_POST['comment']??null)?$_POST['comment']:'').'</textarea><p id="feedback-count" class="subtle" role="status" aria-live="polite">0 de 1.000 caracteres</p><p id="feedback-privacy" class="subtle">Não envie senhas, dados pessoais ou informações de pagamento. Sem anexos nesta etapa.</p><button type="submit" class="btn btn-primary">Enviar avaliação</button><p data-feedback-status role="status" aria-live="polite"></p></form><a href="/carreira">Voltar à carreira</a></section>';
 }
}
