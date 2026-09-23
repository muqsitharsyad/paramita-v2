<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

class MonitoringPayload
{
    public static function pagination(int $limit, int $offset, int $totalFiltered, int $dataCount): array
    {
        return [
            'limit' => $limit,
            'offset' => $offset,
            'total_filtered' => $totalFiltered,
            'has_more' => ($offset + $dataCount) < $totalFiltered,
            'next_offset' => (($offset + $dataCount) < $totalFiltered) ? ($offset + $dataCount) : null,
        ];
    }
}
