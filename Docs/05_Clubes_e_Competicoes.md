# Clubes e competições
Regra confirmada: 21 clubes reais como catálogo, sem escudos/marcas. Mauá FC não é Mauaense; São Bernardo FC não é EC São Bernardo. Divisões mudam por temporada e não devem ser inventadas.

Proposta provisória: metadados mínimos reais e torneio simulado definido em temporadas/times/partidas por carreira; sem tabelas nacionais de participações oficiais até existir pesquisa validada.

Implementação atual: seed textual dos 21 clubes, município/UF, duas cores/HEX e terceira quando informada no briefing. Origem real explícita. Liga não utiliza esses clubes como equipes oficiais. HEX são aproximações. Fontes consultadas e falhas de consulta registradas no documento 09; status inicial conservador briefing_provisorio evita declarar toda paleta oficialmente certificada.

Pendências: conferir todas as fontes com a FPF/clubes e versionar identidade visual, competições/edições/participações oficiais. Nenhuma divisão de 2026 é afirmada.

Critérios de teste: seed tem 21 slugs únicos, third color nos tricolores, valores HEX válidos e distinção dos homônimos. Aplicar seed novamente não apaga cores editadas.
Cores podem ser editadas localmente com bin/club-colors.php; catálogo web lê MySQL e reinstalação preserva alterações. Não há painel administrativo HTTP.
