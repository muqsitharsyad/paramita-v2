<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Contracts\VendorContractGuide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JsonTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $guide = new VendorContractGuide;
        $now = now();

        DB::table('contracts')->orderBy('id')->get(['operation_key', 'module', 'label'])->each(
            function (object $contract) use ($guide, $now): void {
                $example = $guide->canonicalExample((string) $contract->operation_key);
                if (is_array($example['meta'] ?? null)) {
                    unset($example['meta']['schema_version']);
                }

                DB::table('json_templates')->updateOrInsert(
                    ['name' => $contract->operation_key],
                    [
                        'category' => $contract->module,
                        'description' => 'Canonical vendor JSON response for '.$contract->label,
                        'template_data' => json_encode(
                            $example,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        ),
                        'version' => '1.0',
                        'is_active' => true,
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        );
    }
}
