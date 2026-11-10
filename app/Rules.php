<?php
declare(strict_types=1);
final class Rules {
    public const VERSION = '1.0.0';
    public const AGE = 16;
    public const SKILLS = ['velocidade','resistencia','forca','visao_jogo','passe','finalizacao','drible','desarme','posicionamento'];
    public const STATES = ['energia','ritmo_jogo','confianca','entrosamento','confianca_treinador','confianca_diretoria','apoio_torcida','confianca_imprensa','lideranca','foco','moral_pessoal','risco_lesao','risco_cartao','risco_polemica','risco_encontrar_rivais'];
    public const POSITIONS = ['zagueiro','lateral','volante','meia','ponta','centroavante'];
    public const TEAMS = ['União do Bairro','Estrela da Serra','Vila Operária','Atlético do Parque','Ferroviários do Vale','Esporte Horizonte','Riacho United','Deportivo Aurora'];
    public const WEIGHTS = [
        'passe'=>['passe'=>.55,'visao_jogo'=>.25,'posicionamento'=>.20],
        'chute'=>['finalizacao'=>.45,'posicionamento'=>.25,'forca'=>.15,'drible'=>.10,'visao_jogo'=>.05],
        'drible'=>['drible'=>.60,'velocidade'=>.30,'visao_jogo'=>.10],
        'desarme'=>['desarme'=>.55,'posicionamento'=>.30,'forca'=>.15],
        'social'=>['visao_jogo'=>.40,'posicionamento'=>.30,'passe'=>.30],
    ];
    public const HANDICAPS = [
        ['nome'=>'Talento de rua','peso'=>25,'efeitos'=>['drible'=>7,'passe'=>4,'energia'=>-5]],
        ['nome'=>'Disciplina de base','peso'=>30,'efeitos'=>['posicionamento'=>5,'resistencia'=>5,'confianca_treinador'=>8]],
        ['nome'=>'Recomeço difícil','peso'=>20,'efeitos'=>['confianca'=>-10,'energia'=>-8,'foco'=>8]],
        ['nome'=>'Explosão e ansiedade','peso'=>25,'efeitos'=>['velocidade'=>8,'confianca'=>-5,'risco_cartao'=>8]],
    ];
    public const EXCEPTIONAL_WEIGHT = 15;
    public const SEVERE_WEIGHT = 15;

    public static function weights(string $action): array {
        if (class_exists('BalanceService')) {
            $params = BalanceService::params('action.technical.weights');
            if (isset($params[$action]) && is_array($params[$action])) {
                return $params[$action];
            }
        }
        return self::WEIGHTS[$action] ?? [];
    }

    public static function exceptionalWeight(): int {
        return class_exists('BalanceService')
            ? (int)BalanceService::param('balance.roll.intensity', 'exceptional_weight', self::EXCEPTIONAL_WEIGHT)
            : self::EXCEPTIONAL_WEIGHT;
    }

    public static function severeWeight(): int {
        return class_exists('BalanceService')
            ? (int)BalanceService::param('balance.roll.intensity', 'severe_weight', self::SEVERE_WEIGHT)
            : self::SEVERE_WEIGHT;
    }

    public static function xpThreshold(int $level): int {
        $base = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'cost_base', 20) : 20;
        $div = class_exists('BalanceService') ? (int)BalanceService::param('dev.v3.parameters', 'cost_divisor', 5) : 5;
        return $base + intdiv($level, max(1, $div));
    }
}
