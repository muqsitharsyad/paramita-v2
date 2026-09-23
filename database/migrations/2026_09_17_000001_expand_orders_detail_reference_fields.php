<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELDS = [
        'student_identifier' => null,
        'student_phone' => null,
        'address_line' => null,
    ];

    public function up(): void
    {
        $template = DB::table('json_templates')->where('name', 'orders.detail')->first();
        if ($template === null) {
            return;
        }

        $payload = json_decode((string) $template->template_data, true, flags: JSON_THROW_ON_ERROR);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        foreach (array_reverse(self::FIELDS, true) as $key => $value) {
            $data = array_merge([$key => $value], $data);
        }

        $payload['data'] = $data;
        DB::table('json_templates')->where('id', $template->id)->update([
            'template_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $template = DB::table('json_templates')->where('name', 'orders.detail')->first();
        if ($template === null) {
            return;
        }

        $payload = json_decode((string) $template->template_data, true, flags: JSON_THROW_ON_ERROR);
        foreach (array_keys(self::FIELDS) as $key) {
            unset($payload['data'][$key]);
        }

        DB::table('json_templates')->where('id', $template->id)->update([
            'template_data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }
};
