<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\ScopeContext;
use DateTimeImmutable;
use DateTimeZone;

final class MonitoringFilters
{
    public const BUCKETS = ['on_process' => '01', 'on_delivery' => '02', 'retry' => '03,04,05', 'returned' => '06', 'delivered' => '07'];

    public static function stock(array $input, ScopeContext $scope): array
    {
        return (new self)->normalize($input, $scope, 'inventory.list');
    }

    public static function orders(array $input, ScopeContext $scope): array
    {
        return (new self)->normalize($input, $scope, 'orders.list');
    }

    public function normalize(array $input, ScopeContext $scope, string $operation): array
    {
        if (! in_array($scope->role, ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor'], true)) {
            throw new MonitoringException(403, 'FORBIDDEN');
        }
        $orders = str_starts_with($operation, 'orders.');
        $summary = str_ends_with($operation, '.summary');
        $matrix = $operation === 'inventory.lookup';
        $allowed = ['ut_code', 'program_codes', 'search', 'limit', 'offset', 'vendor_codes'];
        if ($orders) {
            $allowed = array_merge($allowed, ['ordered_from', 'ordered_to', 'occurred_from', 'occurred_to', 'period_code', 'process_status_code', 'process_status_bucket']);
        }
        if (str_starts_with($operation, 'inventory.')) {
            $allowed[] = 'item_type';
        }
        if (! $matrix && str_starts_with($operation, 'inventory.')) {
            $allowed = array_merge($allowed, ['stock_status', 'updated_from', 'updated_to']);
        }
        if ($summary) {
            $allowed = array_merge($allowed, ['vendor_scope', 'group_by']);
        }
        if (str_ends_with($operation, '.list')) {
            $allowed = array_merge($allowed, ['sort', 'snapshot_id', 'cursor']);
        }
        if ($operation === 'orders.events') {
            $allowed[] = 'kind';
        }
        foreach ($input as $key => $value) {
            if (! in_array($key, $allowed, true) || (! is_scalar($value) && $value !== null)) {
                $this->invalid();
            }
        }
        $out = [];
        foreach ($input as $key => $value) {
            if ($value !== null && $value !== '') {
                $out[$key] = (string) $value;
            }
        }
        if (isset($out['cursor'])) {
            throw new MonitoringException(422, 'CURSOR_NOT_SUPPORTED');
        }
        $defaultLimit = ($operation === 'options.ut' || ($out['group_by'] ?? '') === 'ut') ? 40 : 25;
        $out['limit'] = $this->integer($out['limit'] ?? $defaultLimit, 0, $matrix ? 25 : 100);
        $out['offset'] = $this->integer($out['offset'] ?? 0, 0, 10000);
        if ($out['limit'] === 0 && $out['offset'] !== 0) {
            $this->invalid();
        }
        $out['vendor_codes'] = $this->codes($out['vendor_codes'] ?? '', 10);
        if ($summary) {
            $out['vendor_scope'] ??= 'all';
            if (! in_array($out['vendor_scope'], ['all', 'selected'], true)) {
                $this->invalid();
            }
            if ($out['vendor_scope'] === 'selected' && ! $out['vendor_codes']) {
                $this->invalid();
            }
            $out['group_by'] ??= 'none';
            if (! in_array($out['group_by'], $orders ? ['none', 'ut', 'program'] : ['none'], true)) {
                $this->invalid();
            }
        }
        if (isset($out['sort']) && $out['sort'] !== 'id:asc') {
            $this->invalid();
        }
        if (isset($out['snapshot_id']) && ! preg_match('/^[a-f0-9]{64}$/D', $out['snapshot_id'])) {
            $this->invalid();
        }
        if (isset($out['search'])) {
            $out['search'] = trim($out['search']);
            if ($out['search'] === '') {
                unset($out['search']);
            } elseif (mb_strlen($out['search']) < 2 || mb_strlen($out['search']) > 100) {
                $this->invalid();
            }
        }
        if (isset($out['ut_code'])) {
            $this->codes($out['ut_code'], 1);
        }
        if (! $scope->isAllRegions) {
            if (! $scope->assignedUtCode || (isset($out['ut_code']) && $out['ut_code'] !== $scope->assignedUtCode)) {
                throw new MonitoringException(403, 'SCOPE_FORBIDDEN');
            }
            $out['ut_code'] = $scope->assignedUtCode;
        }
        $programs = $this->codes($out['program_codes'] ?? '', 20);
        if ($scope->role === 'tutor') {
            if (! $scope->allowedProgramCodes) {
                throw new MonitoringException(403, 'PROGRAM_ASSIGNMENT_REQUIRED');
            }
            if (array_diff($programs, $scope->allowedProgramCodes)) {
                throw new MonitoringException(403, 'SCOPE_FORBIDDEN');
            }
            $programs = $programs ?: $scope->allowedProgramCodes;
            if (count($programs) > 20) {
                throw new MonitoringException(403, 'PROGRAM_SCOPE_TOO_LARGE');
            }
        }
        sort($programs, SORT_STRING);
        $out['program_codes'] = implode(',', $programs);
        if (str_starts_with($operation, 'inventory.')) {
            $out['item_type'] ??= 'package';
            if (! in_array($out['item_type'], ['package', 'book'], true)) {
                $this->invalid();
            }
        }
        if (isset($out['stock_status']) && ! in_array($out['stock_status'], ['shortage', 'adequate', 'surplus', 'unknown'], true)) {
            $this->invalid();
        }
        if (isset($out['process_status_bucket'], $out['process_status_code'])) {
            $this->invalid();
        }
        if (isset($out['process_status_bucket'])) {
            $out['process_status_code'] = self::BUCKETS[$out['process_status_bucket']] ?? $this->invalid();
            unset($out['process_status_bucket']);
        }
        if (isset($out['process_status_code'])) {
            $codes = $this->codes($out['process_status_code'], 7);
            if (array_diff($codes, ['01', '02', '03', '04', '05', '06', '07'])) {
                $this->invalid();
            }
            $out['process_status_code'] = implode(',', $codes);
        }
        if (isset($out['period_code'])) {
            $this->codes($out['period_code'], 1);
        }
        if ($orders && ! isset($out['period_code']) && ! in_array($operation, ['orders.detail', 'orders.events'], true)) {
            $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
            $out['ordered_from'] ??= $today->modify('-29 days')->format(DATE_RFC3339);
            $out['ordered_to'] ??= $today->modify('+1 day')->format(DATE_RFC3339);
        }
        if ($operation === 'orders.analytics') {
            $out['occurred_from'] ??= $out['ordered_from'];
            $out['occurred_to'] ??= $out['ordered_to'];
        }
        foreach (['ordered', 'updated', 'occurred'] as $prefix) {
            foreach (['from', 'to'] as $end) {
                $key = $prefix.'_'.$end;
                if (isset($out[$key])) {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $out[$key])) {
                        $this->invalid();
                    }
                    try {
                        $date = new DateTimeImmutable($out[$key]);
                    } catch (\Exception) {
                        $this->invalid();
                    }
                    $errors = DateTimeImmutable::getLastErrors();
                    if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
                        $this->invalid();
                    }
                    $out[$key] = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
                }
            }
            if (isset($out[$prefix.'_from'], $out[$prefix.'_to'])) {
                $seconds = strtotime($out[$prefix.'_to']) - strtotime($out[$prefix.'_from']);
                if ($seconds <= 0 || $seconds > 366 * 86400) {
                    $this->invalid();
                }
            }
        }
        if ($operation === 'orders.events') {
            $out['kind'] ??= 'history';
            if (! in_array($out['kind'], ['history', 'retry'], true)) {
                $this->invalid();
            }
        }
        ksort($out);

        return $out;
    }

    public static function upstream(array $filters): array
    {
        return array_diff_key($filters, array_flip(['vendor_scope', 'vendor_codes', 'snapshot_id', 'cursor']));
    }

    private function integer(mixed $value, int $min, int $max): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value) || (float) $value > $max || (int) $value < $min) {
            $this->invalid();
        }

        return (int) $value;
    }

    private function codes(string $value, int $max): array
    {
        if ($value === '') {
            return [];
        }
        $codes = explode(',', $value);
        if (count($codes) > $max) {
            $this->invalid();
        }
        foreach ($codes as $code) {
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/D', $code)) {
                $this->invalid();
            }
        }
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    private function invalid(): never
    {
        throw new MonitoringException(422, 'INVALID_PARAMETERS');
    }
}
