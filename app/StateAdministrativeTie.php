<?php
declare(strict_types=1);
final class StateAdministrativeTie extends RuntimeException {
    public function __construct(public readonly array $pending){parent::__construct('A competição aguarda decisão federativa explícita para o desempate.');}
}
