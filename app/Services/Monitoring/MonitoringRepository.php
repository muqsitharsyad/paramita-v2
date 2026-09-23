<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\DTO\ScopeContext;
use Illuminate\Support\Facades\DB;

class MonitoringRepository
{
    public function sources(string $operation, array $filters, bool $all = false): array
    {
        $rows = DB::table('vendors as v')
            ->join('endpoint_bindings as b', 'b.vendor_id', '=', 'v.id')
            ->join('contracts as c', 'c.id', '=', 'b.contract_id')
            ->join('binding_revisions as r', function ($join) {
                $join->on('r.id', '=', 'b.active_revision_id')->on('r.binding_id', '=', 'b.id');
            })
            ->join('contract_versions as cv', function ($join) {
                $join->on('cv.id', '=', 'r.contract_version_id')->on('cv.contract_id', '=', 'c.id');
            })
            ->join('connection_revisions as cr', 'cr.id', '=', 'r.connection_revision_id')
            ->join('connections as conn', function ($join) {
                $join->on('conn.id', '=', 'cr.connection_id')->on('conn.vendor_id', '=', 'v.id');
            })
            ->where('v.status', 'approved')->where('b.is_enabled', true)->where('r.status', 'approved')
            ->whereIn('cv.status', ['published', 'deprecated'])
            ->where(function ($q) {
                $q->whereNull('cv.retire_at')->orWhere('cv.retire_at', '>', now());
            })
            ->where('c.operation_key', $operation)
            ->select('v.id', 'v.code', 'v.legal_name as name', 'v.scope_revision', 'r.id as binding_revision', 'r.scope_revision as binding_scope_revision', 'cr.id as connection_revision', 'cv.id as contract_version')
            ->get();
        $scopes = DB::table('vendor_ut_scope as s')->join('ut_regions as u', 'u.id', '=', 's.ut_id')
            ->whereIn('s.vendor_id', $rows->pluck('id'))->where('u.is_active', true)->select('s.vendor_id', 'u.code')->get()->groupBy('vendor_id');
        $sources = [];
        foreach ($rows as $row) {
            $source = (array) $row;
            $source['ut_codes'] = ($scopes[$row->id] ?? collect())->pluck('code')->sort()->values()->all();
            if (! $source['ut_codes'] || (isset($filters['ut_code']) && ! in_array($filters['ut_code'], $source['ut_codes'], true))) {
                continue;
            }
            if (! $all && $filters['vendor_codes'] && ! in_array($row->code, $filters['vendor_codes'], true)) {
                continue;
            }
            $sources[] = $source;
        }
        usort($sources, fn ($a, $b) => strcmp($a['code'], $b['code']));
        if (! $all && $filters['vendor_codes'] && array_diff($filters['vendor_codes'], array_column($sources, 'code'))) {
            throw new MonitoringException(422, 'SOURCE_NOT_ELIGIBLE');
        }

        return $all || $filters['vendor_codes'] ? $sources : array_slice($sources, 0, 10);
    }

    public function validateMasters(array $filters): void
    {
        if (isset($filters['ut_code']) && ! DB::table('ut_regions')->where('code', $filters['ut_code'])->where('is_active', true)->exists()) {
            throw new MonitoringException(422, 'INVALID_UT');
        }
        $codes = array_filter(explode(',', $filters['program_codes'] ?? ''));
        if ($codes && DB::table('programs')->where('is_active', true)->whereIn('code', $codes)->count() !== count($codes)) {
            throw new MonitoringException(422, 'INVALID_PROGRAM');
        }
    }

    public function page(string $type, array $filters, ScopeContext $scope): array
    {
        $table = match ($type) {
            'ut' => 'ut_regions', 'programs' => 'programs', 'catalog' => 'catalog_items', 'vendors' => 'vendors', default => throw new MonitoringException(404, 'NOT_FOUND')
        };
        $key = $type === 'catalog' ? 'catalog_key' : 'code';
        $name = match ($type) {
            'catalog' => 'title', 'vendors' => 'legal_name', default => 'name'
        };
        $q = DB::table($table);
        $type === 'vendors' ? $q->where('status', 'approved') : $q->where('is_active', true);
        if ($type === 'ut' && isset($filters['ut_code'])) {
            $q->where('code', $filters['ut_code']);
        }
        if ($type === 'programs' && ($filters['program_codes'] ?? '') !== '') {
            $q->whereIn('code', explode(',', $filters['program_codes']));
        }
        if ($type === 'vendors' && isset($filters['ut_code'])) {
            $q->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')->from('vendor_ut_scope as s')->join('ut_regions as u', 'u.id', '=', 's.ut_id')->whereColumn('s.vendor_id', 'vendors.id')->where('u.code', $filters['ut_code'])->where('u.is_active', true);
            });
        }
        if ($type === 'catalog' && isset($filters['item_type'])) {
            $q->where('item_type', $filters['item_type']);
        }
        if (isset($filters['search'])) {
            $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';
            $q->where(function ($sub) use ($key, $name, $search) {
                $sub->where($key, 'like', $search)->orWhere($name, 'like', $search);
            });
        }
        $total = $q->count();
        $columns = [$key.' as code', $name.' as name'];
        if ($type === 'ut') {
            $columns[] = 'type';
        }
        if ($type === 'catalog') {
            $columns = array_merge($columns, ['item_type', 'item_code', 'edition']);
        }
        $data = $filters['limit'] === 0 ? [] : $q->orderBy($key)->offset($filters['offset'])->limit($filters['limit'])->get($columns)->map(fn ($r) => (array) $r)->all();

        return ['data' => $data, 'pagination' => MonitoringPayload::pagination($filters['limit'], $filters['offset'], $total, count($data))];
    }

    public function labels(array $row): array
    {
        foreach (['ut_code' => ['ut_regions', 'ut_name'], 'program_code' => ['programs', 'program_name']] as $key => [$table, $label]) {
            if (($row[$key] ?? null) === null) {
                continue;
            }
            $name = DB::table($table)->where('code', $row[$key])->where('is_active', true)->value('name');
            if ($name === null) {
                throw new MonitoringException(502, 'UNKNOWN_MASTER_CODE');
            }
            $row[$label] = $name;
        }
        if (($row['process_status_code'] ?? null) !== null) {
            $status = DB::table('process_statuses')
                ->where('code', $row['process_status_code'])
                ->where('is_active', true)
                ->first(['label', 'bucket']);
            if ($status === null) {
                throw new MonitoringException(502, 'UNKNOWN_PROCESS_STATUS_CODE');
            }
            $row['process_status_name'] = $status->label;
            $row['process_status_bucket'] = $status->bucket;
        }
        if (($row['reason_code'] ?? null) !== null) {
            $reason = DB::table('retry_reasons')
                ->where('code', $row['reason_code'])
                ->where('is_active', true)
                ->value('label');
            if ($reason === null) {
                throw new MonitoringException(502, 'UNKNOWN_RETRY_REASON_CODE');
            }
            $row['reason_name'] = $reason;
        }
        if (($row['package_code'] ?? null) !== null) {
            $title = DB::table('catalog_items')
                ->where(function ($query) use ($row): void {
                    $query->where('catalog_key', $row['package_code'])
                        ->orWhere('item_code', $row['package_code']);
                })
                ->where('item_type', 'package')
                ->where('is_active', true)
                ->orderByDesc('edition')
                ->value('title');
            if ($title === null) {
                throw new MonitoringException(502, 'UNKNOWN_PACKAGE_CODE');
            }
            $row['package_name'] = $title;
        }
        if (isset($row['latest_event']) && is_array($row['latest_event'])) {
            $row['latest_event'] = $this->labels($row['latest_event']);
        }
        if (isset($row['catalog_key']) && ! DB::table('catalog_items')->where('catalog_key', $row['catalog_key'])->where('item_type', $row['item_type'])->where('item_code', $row['item_code'])->where('edition', $row['edition'])->where('is_active', true)->exists()) {
            throw new MonitoringException(502, 'UNKNOWN_CATALOG_KEY');
        }
        if (array_key_exists('stock_quantity', $row)) {
            $required = $row['required_quantity'] ?? null;
            $stock = $row['stock_quantity'];
            $row['stock_status'] = $required === null || $stock === null
                ? 'unknown'
                : ($stock < $required ? 'shortage' : ($stock === $required ? 'adequate' : 'surplus'));
        }

        return $row;
    }

    public function labelGroups(array $rows, string $groupBy): array
    {
        if (! in_array($groupBy, ['ut', 'program'], true) || $rows === []) {
            return $rows;
        }
        $table = $groupBy === 'ut' ? 'ut_regions' : 'programs';
        $codes = array_values(array_unique(array_filter(array_column($rows, 'group_code'))));
        $labels = DB::table($table)->whereIn('code', $codes)->where('is_active', true)->pluck('name', 'code');
        if ($labels->count() !== count($codes)) {
            throw new MonitoringException(502, 'UNKNOWN_GROUP_CODE');
        }

        return array_map(function (array $row) use ($labels): array {
            $row['group_name'] = $labels[(string) $row['group_code']];

            return $row;
        }, $rows);
    }

    public function freshness(array $meta): array
    {
        $generatedAt = is_string($meta['generated_at'] ?? null) ? $meta['generated_at'] : null;
        $dataAsOf = is_string($meta['data_as_of'] ?? null) ? $meta['data_as_of'] : $generatedAt;
        $timestamp = $dataAsOf ? strtotime($dataAsOf) : false;
        $ageSeconds = $timestamp === false ? null : max(0, now()->timestamp - $timestamp);
        $maxAge = (int) config('services.vendor_data_max_age_seconds', 3600);

        return [
            'state' => $ageSeconds !== null && $ageSeconds <= $maxAge ? 'fresh' : 'stale',
            'generated_at' => $generatedAt,
            'data_as_of' => $dataAsOf,
            'age_seconds' => $ageSeconds,
        ];
    }
}
