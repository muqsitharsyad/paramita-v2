<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LIST_OPERATIONS = [
        'inventory.list',
        'inventory.lookup',
        'orders.list',
        'orders.events',
    ];

    public function up(): void
    {
        $this->mutateTemplates(function (string $name, array $template): array {
            $template['meta'] ??= [];
            $template['meta']['generated_at'] ??= '2026-09-21T00:00:00Z';
            $template['meta']['data_as_of'] ??= $template['meta']['generated_at'];
            $template['meta']['scope'] ??= ['ut_code' => null, 'program_codes' => []];

            if (in_array($name, self::LIST_OPERATIONS, true)) {
                unset($template['meta']['next_cursor']);
            }

            if ($name === 'inventory.lookup') {
                $source = is_array($template['data'][0] ?? null) ? $template['data'][0] : [];
                $template['data'] = [[
                    'catalog_key' => (string) ($source['catalog_key'] ?? 'PKT-MKDU4109'),
                    'stock_quantity' => (int) ($source['stock_quantity'] ?? 120),
                    'availability' => (string) ($source['availability'] ?? 'available'),
                    'updated_at' => (string) ($source['updated_at'] ?? '2026-09-21T00:00:00Z'),
                ]];
            }

            return $template;
        });
    }

    public function down(): void
    {
        $this->mutateTemplates(function (string $name, array $template): array {
            if ($name === 'orders.detail') {
                unset($template['meta']);
            } else {
                unset($template['meta']['data_as_of']);
            }

            if (in_array($name, self::LIST_OPERATIONS, true)) {
                $template['meta']['next_cursor'] = null;
            }

            if ($name === 'inventory.lookup') {
                $template['data'] = [[
                    'id' => 'inv-001', 'catalog_key' => 'PKT-MKDU4109', 'item_type' => 'package',
                    'item_code' => 'MKDU4109', 'edition' => '2025.1', 'title' => 'Paket Bahan Ajar Pendidikan Kewarganegaraan',
                    'size_labels' => 'Paket', 'ut_code' => 'UN31.UT15', 'program_code' => '61201',
                    'stock_quantity' => 120, 'required_quantity' => 100,
                    'unit_weights_kg' => 0.8, 'total_weight_kg' => 96.0, 'total_height_cm' => 20.0,
                    'total_area_m2' => 1.2, 'updated_at' => '2026-09-12T10:00:00+00:00',
                ]];
            }

            return $template;
        });
    }

    private function mutateTemplates(callable $callback): void
    {
        DB::table('json_templates')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'template_data'])
            ->each(function (object $record) use ($callback): void {
                $template = json_decode((string) $record->template_data, true);
                if (! is_array($template)) {
                    return;
                }

                $updated = $callback((string) $record->name, $template);
                DB::table('json_templates')->where('id', $record->id)->update([
                    'template_data' => json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }
};
