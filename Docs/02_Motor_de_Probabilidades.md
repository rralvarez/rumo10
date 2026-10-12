# Motor de probabilidades
Regra confirmada: sorteios e mutações exclusivamente no PHP; custos separados de sucesso/falha e comandos idempotentes.

Proposta provisória: P = clamp(5,95,50 + .65(A−50) + .12(C−50) + .10(E−50) + .10(R−50) − .55(D−50) − .30(O−50)). Pesos de habilidade em app/Rules.php; passe .55 passe/.25 visão/.20 posicionamento; chute .65 finalização/.20 posicionamento/.15 força; drible .60 drible/.30 velocidade/.10 visão; desarme .55 desarme/.30 posicionamento/.15 força. Social usa visão, posicionamento e passe como abstração provisória, sem tratar capacidade social como característica real da pessoa.

Implementação atual: random_int(1,10000), sucesso quando sorteio <= P×100. Segundo sorteio 1–100: 15% excepcional entre sucessos, 15% grave entre falhas. Efeito excepcional 1,25× arredondado. Falhas reduzem confiança em 2 ou 5; energia negativa e riscos positivos são custos em qualquer resultado. Bônus restantes só no sucesso. Energia positiva de descanso também condicionada neste protótipo; recuperação semanal fixa +20 acontece separadamente. S7 chute fracassado reduz entrosamento em 10. XP técnico evolui quando atinge 20+floor(nível/5), limite 100. Perfil inicial é única exceção: ajustes técnicos diretos limitados e persistidos.

Transação bloqueia carreira com SELECT FOR UPDATE. Chave única por carreira + hash de comando/payload; mesmo replay não aplica novamente. Versão velha não avança. Auditoria guarda estado anterior/posterior, chance, roll, intensidade, versão e efeitos. O navegador recebe probabilidades calculadas pelo servidor.

Pendências: calibrar dificuldade por adversário, aumentar diversidade social e tornar parâmetros editáveis por administrador. Parâmetros estão centralizados em código/configuração, não em formulário público.

Critérios de teste: limites, monotonicidade de seis fatores, distribuição de 20 mil sorteios com tolerância, tentativa de novo handicap, replay de transação real e versão obsoleta. Executar tests/run.php --mysql.