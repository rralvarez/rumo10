<?php
declare(strict_types=1);
$personal=['effects'=>['moral_pessoal'=>3],'failure_effects'=>['moral_pessoal'=>-1]];
$community=['effects'=>['moral_pessoal'=>3,'apoio_torcida'=>2],'failure_effects'=>['moral_pessoal'=>-1]];
return [
 1150=>[0=>$community,2=>['effects'=>['lideranca'=>2,'moral_pessoal'=>2],'failure_effects'=>['lideranca'=>-1]]],
 1151=>[2=>$personal],1156=>[2=>$community],
 1157=>[0=>$community,1=>['effects'=>['lideranca'=>2,'moral_pessoal'=>2],'failure_effects'=>['lideranca'=>-1]]],
 1158=>[2=>$personal],1162=>[0=>$personal,1=>['effects'=>['moral_pessoal'=>3],'failure_effects'=>[]]],
 1164=>[1=>['effects'=>['moral_pessoal'=>3,'foco'=>2],'failure_effects'=>['moral_pessoal'=>-1]]],
 1165=>[1=>$community],1166=>[2=>$personal],1169=>[1=>$community],
 1173=>[0=>$personal],1175=>[1=>$personal],1176=>[0=>['effects'=>['moral_pessoal'=>3,'foco'=>2],'failure_effects'=>['foco'=>-1]]],
 1178=>[3=>['effects'=>['moral_pessoal'=>4],'failure_effects'=>[]]],1184=>[0=>$community],
 1187=>[0=>$personal,2=>['effects'=>['moral_pessoal'=>3,'foco'=>1],'failure_effects'=>[]]],
 1189=>[2=>$community],1190=>[2=>$personal],1195=>[1=>['effects'=>['confianca'=>3,'moral_pessoal'=>3],'failure_effects'=>['moral_pessoal'=>-1]]],
 1197=>[0=>$community,1=>['effects'=>['lideranca'=>3,'moral_pessoal'=>2],'failure_effects'=>['lideranca'=>-1]]],
 1198=>[0=>['effects'=>['moral_pessoal'=>3,'confianca'=>2],'failure_effects'=>['moral_pessoal'=>-1]]],
];
