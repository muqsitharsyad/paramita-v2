<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Illuminate\Support\Facades\DB;

/**
 * Checks only the envelope that Paramita needs. Field names and field types are
 * defined directly by template_data and are enforced by VendorPayloadContract.
 */
final class TemplateContractGuard
{
    /**
     * @return array{schema: null, problems: list<string>, row_schema: null}
     */
    public function inspect(string $operationKey, array $templateData): array
    {
        $problems = [];

        if (! array_key_exists('data', $templateData)) {
            $problems[] = 'Column data is required';
        } elseif (! is_array($templateData['data'])) {
            $problems[] = 'Column data must be ARRAY or OBJECT';
        }

        if (! array_key_exists('meta', $templateData)) {
            $problems[] = 'Column meta is required';
        } elseif (! is_array($templateData['meta']) || array_is_list($templateData['meta'])) {
            $problems[] = 'Column meta must be OBJECT';
        }

        return ['schema' => null, 'row_schema' => null, 'problems' => $problems];
    }

    /** @return array<string, list<string>> */
    public function auditAllActive(): array
    {
        $report = [];
        foreach (DB::table('json_templates')->where('is_active', true)->orderBy('name')->get() as $template) {
            $data = json_decode((string) $template->template_data, true);
            $report[(string) $template->name] = is_array($data) && ! array_is_list($data)
                ? $this->inspect((string) $template->name, $data)['problems']
                : ['Format JSON bukan object yang valid'];
        }

        return $report;
    }
}
