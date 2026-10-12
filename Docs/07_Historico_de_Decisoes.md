# Histórico de decisões técnicas
Regra confirmada: briefing v0.2 é escopo, não comprovação de funcionalidade. Alterações permanentes no backend, sem segredos em logs.

Propostas e decisões da implementação (12/10/2026, não aprovação definitiva de produto): PHP sem framework nem Composer, Bootstrap local; carreira com jogador integrado, atributos/estados relacionais por chave; liga fictícia e transferências regionais fictícias; cinco oportunidades para titular; S10/S11 inelegíveis enquanto não há patrocinador/rival; S12 válida no fim de semana. Linguagem do CT/classico/escudo adaptada à equipe amadora, preservando intenção e 48 alternativas.

Repositório remoto estava vazio. Checkout inicial em pasta gravável porque escrita no diretório IIS foi negada pelo Windows; arquivos posteriormente copiados para C:\inetpub\wwwroot\rumo10. O usuário aplicou ajuste administrativo da raiz para public/. Nenhum binding nem hosts recriado. Erro FastCGI monitorChangesTo diagnosticado por resposta IIS detalhada temporária; detalhe removido após diagnóstico. Reparo proposto restrito ao handler do site, usando instância PHP 8.4 alternativa já existente; ver relatório 10 para estado final.

Implementação atual: comando guarda hash, versão, chance, sorteio, classe, efeitos anteriores/posteriores. Reprodução via mesma chave não reaplica. SELECT FOR UPDATE impede corrida de comandos distintos na mesma versão.

Pendências: instrumentar testes de concorrência multi-processo e fornecer administração auditável de regras. Auditoria atual não é histórico visível com busca; feedback e resumos aparecem na interface.

Critérios de teste: repetir token, mudar payload com mesmo token, versões obsoletas, consultar somente carreira da conta autenticada.