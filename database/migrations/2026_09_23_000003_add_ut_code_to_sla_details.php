<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add ut_code to orders.analytics.sla_details so regional heads (kepala_ut_daerah)
     * can have their DO list scoped to their assigned UT. retry_details already carries
     * ut_code; sla_details was missing it, so the analisis-sla DO list could not be
     * filtered per-UT and leaked rows from every region.
     */
    public function up(): void
    {
        $template = DB::table('json_templates')->where('name', 'orders.analytics')->first();
        if ($template === null) {
            return;
        }

        $payload = json_decode((string) $template->template_data, true, flags: JSON_THROW_ON_ERROR);
        $rows = $payload['data']['sla_details'] ?? [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            // Insert ut_code right after order_number to mirror retry_details ordering.
            if (! array_key_exists('ut_code', $row)) {
                $withUt = [];
                foreach ($row as $key => $value) {
                    $withUt[$key] = $value;
                    if ($key === 'order_number') {
                        $withUt['ut_code'] = 'UN31.UT15';
                    }
                }
                if (! array_key_exists('ut_code', $withUt)) {
                    $withUt = ['ut_code' => 'UN31.UT15'] + $withUt;
                }
                $rows[$index] = $withUt;
            }
        }
        $payload['data']['sla_details'] = $rows;

        DB::table('json_templates')->where('id', $template->id)->update([
            'template_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $template = DB::table('json_templates')->where('name', 'orders.analytics')->first();
        if ($template === null) {
            return;
        }

        $payload = json_decode((string) $template->template_data, true, flags: JSON_THROW_ON_ERROR);
        $rows = $payload['data']['sla_details'] ?? [];
        foreach ($rows as $index => $row) {
            if (is_array($row)) {
                unset($row['ut_code']);
                $rows[$index] = $row;
            }
        }
        $payload['data']['sla_details'] = $rows;

        DB::table('json_templates')->where('id', $template->id)->update([
            'template_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }
};
