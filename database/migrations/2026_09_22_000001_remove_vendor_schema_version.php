<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('json_templates')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'template_data'])
            ->each(function (object $record): void {
                $template = json_decode((string) $record->template_data, true);
                if (! is_array($template) || ! is_array($template['meta'] ?? null)) {
                    return;
                }

                unset($template['meta']['schema_version']);

                DB::table('json_templates')->where('id', $record->id)->update([
                    'template_data' => json_encode(
                        $template,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    ),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Intentionally not restored. Contract compatibility is identified by /v1; template
        // revisions remain available internally without requiring vendors to echo a version.
    }
};
