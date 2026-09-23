<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('json_templates')->orderBy('id')->each(function (object $template): void {
            $decoded = json_decode((string) $template->template_data, true);
            $response = is_array($decoded['response_example'] ?? null)
                ? $decoded['response_example']
                : $decoded;

            if (! is_array($response) || array_is_list($response)) {
                return;
            }

            // A null example means nullable; keep this field compatible with pre-carrier orders.
            if ($template->name === 'orders.detail' && isset($response['data']) && is_array($response['data'])) {
                $response['data']['handed_to_carrier_at'] = null;
            }

            DB::table('json_templates')->where('id', $template->id)->update([
                'template_data' => json_encode(
                    $response,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // The removed metadata was derived/duplicated data and cannot be restored safely.
        // The response JSON itself remains intact.
    }
};
