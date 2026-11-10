<?php
declare(strict_types=1);
final class IdentityError extends InvalidArgumentException {
 public function __construct(string $message,public readonly int $status){parent::__construct($message);}
}
