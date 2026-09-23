<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use RuntimeException;

final class MonitoringException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
