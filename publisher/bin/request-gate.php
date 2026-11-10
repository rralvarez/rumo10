<?php
declare(strict_types=1);
// Private control-plane prelude: install only after authorized initial quiescence.
require_once dirname(__DIR__).'/lib/RequestDrain.php';
(static function(mixed $root):void{
 try{
  if(!is_string($root))throw new RuntimeException('Gate not configured');
  $lease=(new \Rumo\Publisher\RequestDrain($root))->enter();
  \Rumo\Publisher\RequestDrain::retainThroughRequestShutdown($lease);
  // Flush the current session while the retained lease is held. Current audited sites do not reopen it in later shutdown callbacks.
  register_shutdown_function(static function(){if(session_status()===PHP_SESSION_ACTIVE)session_write_close();});
 }catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');header('Retry-After: 60');exit('Estamos atualizando o site. Volte em instantes.');}
})($rumoRequestGateRoot??null);
