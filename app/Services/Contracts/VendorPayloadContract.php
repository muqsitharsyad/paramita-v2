<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Enforces the ADMIN JSON template as a binding contract for each vendor test.
 *
 * Model (per user requirement):
 *   - Admin changes the template (e.g. field `id` becomes `ids`)  -> the contract changed.
 *   - The vendor MUST follow it. If the vendor's live response does not match, the test is
 *     INVALID, the binding cannot be submitted/approved, and the user pages will not receive
 *     that vendor's data (the gateway throws, the source is reported as unavailable).
 *   - The vendor portal shows exactly WHICH field / type is wrong, in readable Indonesian.
 *
 * How it works: `json_templates.template_data` is the response JSON itself. Its shape is compiled
 * into a JSON Schema (required keys + JSON types, recursively) and the RAW vendor payload is
 * validated against it. There is no second rules form, field map, or schema source.
 */
class VendorPayloadContract
{
    /** Cap the report so one broken endpoint cannot flood the vendor page. */
    private const MAX_REPORTED_PROBLEMS = 6;

    /** How many raw validator errors to collect before grouping (grouping shrinks them). */
    private const MAX_COLLECTED_ERRORS = 400;

    /** Rows of the last compiled template, used to state the expected type of a missing field. */
    private static array $lastExampleRows = [];

    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator;
        // Collect plenty of raw errors: the report groups them by field afterwards, so a cap here
        // would silently hide later rows and non-missing-field problems (type mismatches).
        $this->validator->setMaxErrors(self::MAX_COLLECTED_ERRORS);
    }

    /**
     * Shape of an operation's response, read from its template: `list` (data is an array of rows),
     * `object_under_data` (data is a single object) or `body` (the envelope itself is the object).
     * Replaces the per-operation schema file mapping — the template owns the whole contract.
     */
    public function planFor(string $operationKey): string
    {
        $template = DB::table('json_templates')
            ->where('name', $operationKey)
            ->where('is_active', true)
            ->value('template_data');
        if (! is_string($template) || $template === '') {
            return 'list';
        }

        $example = json_decode($template, true);
        if (! is_array($example) || array_is_list($example)) {
            return 'list';
        }

        // The stored JSON itself is the contract.
        return array_is_list($example['data'] ?? null) ? 'list' : 'object_under_data';
    }

    /** Compiled contract schema for an operation key, or null when the template is unusable. */
    public function schemaFor(string $operationKey): ?object
    {
        $template = DB::table('json_templates')
            ->where('name', $operationKey)
            ->where('is_active', true)
            ->value('template_data');
        if (! is_string($template) || $template === '') {
            return null;
        }

        return $this->schemaFromTemplateData((array) json_decode($template, true), $operationKey);
    }

    /**
     * Compile a contract schema from template data that has NOT been stored yet (admin save).
     *
     * @param  array<string, mixed>  $data
     */
    private function schemaFromTemplateData(array $example, ?string $operationKey = null): ?object
    {
        if ($example === [] || array_is_list($example)) {
            return null;
        }

        // One source only: field names, required fields and JSON types come directly from this JSON.
        $dataValue = $example['data'] ?? null;
        $rows = is_array($dataValue) && array_is_list($dataValue) ? $dataValue : [$dataValue ?? []];
        self::$lastExampleRows = array_values(array_filter(
            $rows,
            static fn ($row) => is_array($row)
        ));

        $schema = $this->compile($example);

        // orders.summary legitimately returns one summary object for group_by=none and a list of
        // summary rows for group_by=program|ut. Both shapes reuse the field contract compiled from
        // the single object stored in template_data; there is still no second field definition.
        if ($operationKey === 'orders.summary' && isset($schema['properties']['data'])) {
            $rowSchema = $schema['properties']['data'];
            $schema['properties']['data'] = [
                'anyOf' => [
                    $rowSchema,
                    ['type' => 'array', 'items' => $rowSchema],
                ],
            ];
        }

        return json_decode(json_encode($schema, JSON_THROW_ON_ERROR));
    }

    /**
     * Build a permissive-but-precise schema: every key present in the example is required and
     * typed; extra vendor fields stay allowed (the contract is a floor, not a cage). A null example
     * explicitly means a nullable scalar. It never permits an object or array in that field.
     */
    private function compile(array $example): array
    {
        if (array_is_list($example)) {
            $item = $example[0] ?? null;

            return [
                'type' => 'array',
                'items' => is_array($item)
                    ? $this->compile($item)
                    : ['type' => ['string', 'number', 'integer', 'boolean', 'null']],
            ];
        }

        $properties = [];
        foreach ($example as $key => $value) {
            $properties[$key] = is_array($value)
                ? $this->compile($value)
                : $this->scalarSchema($value);
        }

        return [
            'type' => 'object',
            'required' => array_keys($properties),
            'properties' => $properties,
            'additionalProperties' => true,
        ];
    }

    /**
     * @return array{type: list<string>|string}
     *
     * A synthetic example must never over-constrain real data:
     *  - numeric examples accept integer OR number (`9.6` vs `10`);
     *  - a string example accepts string; use null in the template when any nullable value is valid.
     */
    private function scalarSchema(mixed $value): array
    {
        return match (true) {
            is_int($value), is_float($value) => ['type' => ['integer', 'number']],
            is_bool($value) => ['type' => 'boolean'],
            $value === null => ['type' => ['string', 'number', 'integer', 'boolean', 'null']],
            default => ['type' => 'string'],
        };
    }

    /**
     * Validate ONE row against the rules the template declares (used by the admin guard, so the
     * admin screen reports with the same wording the vendor sees).
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function validateAgainstTemplate(string $operationKey, array $row, ?array $templateData = null): array
    {
        // When the admin is SAVING, judge the example by the template being saved — reading the
        // stored row instead would compare the new example against the previous contract.
        $schema = $templateData === null
            ? $this->schemaFor($operationKey)
            : $this->schemaFromTemplateData($templateData, $operationKey);

        if ($schema === null) {
            return [];
        }

        $rowSchema = $schema->properties->data->items ?? $schema->items ?? $schema;

        $result = $this->validator->validate(json_decode(json_encode($row)), $rowSchema);
        if ($result->isValid() || ($error = $result->error()) === null) {
            return [];
        }

        return $this->readable($operationKey, (string) json_encode(
            (new ErrorFormatter)->format($error),
            JSON_UNESCAPED_SLASHES
        ));
    }

    /**
     * Validate a live vendor payload against the admin template contract.
     *
     * @return list<string> readable problems, empty when compliant
     */
    public function validate(string $operationKey, object|array $payload): array
    {
        $schema = $this->schemaFor($operationKey);
        if ($schema === null) {
            return [];
        }

        $decoded = is_array($payload) ? json_decode(json_encode($payload, JSON_THROW_ON_ERROR)) : $payload;

        // Opis returns a Result object — it does NOT throw. Reading isValid()/error() is what
        // makes the contract actually bind; a try/catch-only check silently passes everything.
        $result = $this->validator->validate($decoded, $schema);
        if ($result->isValid()) {
            return $this->semanticProblems($operationKey, json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true));
        }

        $error = $result->error();
        if ($error === null) {
            return ['Response vendor tidak sesuai template kontrak admin.'];
        }

        return $this->readable($operationKey, (string) json_encode(
            (new ErrorFormatter)->format($error),
            JSON_UNESCAPED_SLASHES
        ));
    }

    /** @return list<string> */
    private function semanticProblems(string $operationKey, array $payload): array
    {
        $problems = [];

        $this->checkStandardValues($payload, '', $problems);

        $generatedAt = $payload['meta']['generated_at'] ?? null;
        $dataAsOf = $payload['meta']['data_as_of'] ?? null;
        if (is_string($generatedAt) && is_string($dataAsOf)
            && $this->isRfc3339($generatedAt) && $this->isRfc3339($dataAsOf)
            && new DateTimeImmutable($dataAsOf) > new DateTimeImmutable($generatedAt)) {
            $problems[] = 'Column meta.data_as_of cannot be later than meta.generated_at.';
        }

        $meta = $payload['meta'] ?? [];
        $data = $payload['data'] ?? null;
        if (is_array($meta) && is_array($data)
            && array_is_list($data)
            && isset($meta['limit'], $meta['offset'], $meta['total_filtered'], $meta['has_more'])
            && is_int($meta['limit']) && is_int($meta['offset']) && is_int($meta['total_filtered']) && is_bool($meta['has_more'])) {
            $rowCount = count($data);
            if ($meta['limit'] <= 0 || $meta['offset'] < 0 || $meta['total_filtered'] < 0 || $rowCount > $meta['limit']) {
                $problems[] = 'Pagination metadata must use limit > 0, non-negative offset/total_filtered, and data count <= limit.';
            }
            if ($meta['has_more'] !== ($meta['total_filtered'] > $meta['offset'] + $rowCount)) {
                $problems[] = 'Column meta.has_more does not match offset, returned row count, and total_filtered.';
            }
        }

        return array_slice(array_values(array_unique($problems)), 0, self::MAX_REPORTED_PROBLEMS);
    }

    /** @param list<string> $problems */
    private function checkStandardValues(array $value, string $path, array &$problems): void
    {
        foreach ($value as $key => $child) {
            $childPath = $path === '' ? (string) $key : $path.'.'.$key;
            if (is_array($child)) {
                $this->checkStandardValues($child, $childPath, $problems);

                continue;
            }
            if ($child === null || ! is_string($child)) {
                continue;
            }
            if ((preg_match('/(_at|_from|_to)$/', (string) $key) === 1 || $key === 'data_as_of') && ! $this->isRfc3339($child)) {
                $problems[] = "Column {$childPath} must be an RFC3339 timestamp.";
            }
            if (str_ends_with((string) $key, '_url')) {
                $scheme = strtolower((string) parse_url($child, PHP_URL_SCHEME));
                if (! filter_var($child, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                    $problems[] = "Column {$childPath} must be an absolute HTTP(S) URL.";
                }
            }
        }
    }

    private function isRfc3339(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return false;
        }

        try {
            $parsed = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return false;
            }

            // PHP normalizes impossible calendar values (for example 30 February). Comparing the
            // parsed calendar components prevents normalized invalid input from being accepted.
            $inputCalendar = substr($value, 0, 19);

            return $parsed->format('Y-m-d\TH:i:s') === $inputCalendar;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    private function readable(string $operationKey, string $rawMessage): array
    {
        $payload = $rawMessage;
        // Only strip a "prefix: {...}" wrapper — a raw JSON body starts with { or [ and must not
        // be split at its first colon (that would corrupt the JSON and lose the field names).
        if (! str_starts_with(ltrim($rawMessage), '{') && ! str_starts_with(ltrim($rawMessage), '[')) {
            if (($pos = strpos($rawMessage, ':')) !== false) {
                $payload = trim(substr($rawMessage, $pos + 1));
            }
        }
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return ['Response vendor tidak sesuai template kontrak admin.'];
        }

        // Group identical problems across rows: the same field missing from 40 rows is ONE alert
        // naming the affected rows, not 40 alerts the reader has to scan.
        $groups = [];
        foreach ($decoded as $path => $messages) {
            $location = $this->parsePath((string) $path);
            $column = $this->columnLabel($location['pointer']);
            foreach ((array) $messages as $message) {
                $text = trim((string) $message);

                if (preg_match('/^The required propert(?:y|ies) \((.+)\) are missing$/', $text, $m)) {
                    foreach (preg_split('/,\s*/', $m[1]) ?: [] as $missing) {
                        $field = trim($missing, " '");
                        $this->addGroup(
                            $groups,
                            'missing|'.$location['pointer'].'|'.$field,
                            'Column '.$field.' is required'
                                .$this->typeSuffix($this->expectedTypeFor($operationKey, $location['pointer'], $field)),
                            $location['rows']
                        );
                    }

                    continue;
                }

                if (preg_match('/^The data \(([^)]+)\) must match (?:the type|one of the types):\s*(.+)$/', $text, $m)) {
                    $expected = $this->typeList($m[2]);
                    $this->addGroup(
                        $groups,
                        'type|'.$location['pointer'].'|'.$m[1].'|'.$expected,
                        'Column '.$column.' must be '.$expected.', got '.self::typeName($m[1]),
                        $location['rows']
                    );

                    continue;
                }

                $this->addGroup(
                    $groups,
                    'other|'.$location['pointer'].'|'.$text,
                    'Column '.$column.' '.$this->alertSentence($text, $location['pointer']),
                    $location['rows']
                );
            }
        }

        if ($groups === []) {
            return ['Response tidak sesuai template kontrak admin.'];
        }

        $lines = [];
        foreach ($groups as $group) {
            // One line per distinct structure/type problem. The affected row count is NOT part
            // of the message: the same mismatch in 4 rows or 400 rows is still one finding.
            $lines[] = $group['label'];
            if (count($lines) === self::MAX_REPORTED_PROBLEMS) {
                break;
            }
        }
        if (count($groups) > self::MAX_REPORTED_PROBLEMS) {
            $lines[] = '(+'.(count($groups) - self::MAX_REPORTED_PROBLEMS).' masalah lain)';
        }

        return $lines;
    }

    /** `[integer|number]` -> ` (must be INTEGER or NUMBER)`; empty when the type is unknown. */
    private function typeSuffix(string $type): string
    {
        $clean = trim($type, '[]');
        if ($clean === '' || $clean === 'any') {
            return '';
        }
        $parts = array_filter(array_map(
            static fn (string $part) => self::typeName(trim($part)),
            explode('|', $clean)
        ));

        return $parts === [] ? '' : ' (must be '.implode(' or ', $parts).')';
    }

    /** `integer, number` (validator wording) -> `INTEGER or NUMBER` (alert wording). */
    private function typeList(string $list): string
    {
        $parts = array_filter(array_map(
            static fn (string $part) => self::typeName(trim($part)),
            explode(', ', $list)
        ));

        return $parts === [] ? 'valid' : implode(' or ', $parts);
    }

    private static function typeName(string $type): string
    {
        return match (strtolower(trim($type))) {
            'int', 'integer' => 'INTEGER',
            'float', 'double', 'number' => 'NUMBER',
            'string' => 'STRING',
            'bool', 'boolean' => 'BOOLEAN',
            'array', 'list' => 'ARRAY',
            'object', 'stdclass' => 'OBJECT',
            'null' => 'NULL',
            default => strtoupper(trim($type)),
        };
    }

    /** `$[*].stock_quantity` -> `stock_quantity`; `$.meta.generated_at` -> `meta.generated_at`. */
    private function columnLabel(string $pointer): string
    {
        $label = trim(str_replace('[*]', '', $pointer), '$.');

        return $label !== '' ? $label : 'response';
    }

    /** Short alert wording for the remaining validator messages. */
    private function alertSentence(string $text, string $pointer = ''): string
    {
        $rules = [
            '/^The data must match the format:\s*(.+)$/' => 'must be a valid $1 value',
            '/^The data must be at least (\S+)$/' => 'must be at least $1',
            '/^The data must be at most (\S+)$/' => 'must be at most $1',
            '/^The data must be one of:\s*(.+)$/' => 'must be one of: $1',
            '/^The matched number is not an integer$/' => 'must be an INTEGER',
            '/^Is not a string$/' => 'must be a STRING',
        ];
        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $text)) {
                return (string) preg_replace($pattern, $replacement, $text);
            }
        }

        return 'must be '.lcfirst((string) preg_replace('/^The data /', '', $text));
    }

    /** @param array<string, array{label: string, rows: list<int>}> $groups */
    private function addGroup(array &$groups, string $key, string $label, array $rows): void
    {
        if (! isset($groups[$key])) {
            $groups[$key] = ['label' => $label, 'rows' => []];
        }
        foreach ($rows as $row) {
            if (! in_array($row, $groups[$key]['rows'], true)) {
                $groups[$key]['rows'][] = $row;
            }
        }
        sort($groups[$key]['rows']);
    }

    /** Expected JSON type read directly from the example value. */
    private function expectedTypeFor(string $operationKey, string $pointer, string $field): string
    {
        return $this->exampleType($field);
    }

    /**
     * Build the report pointer and the affected row indexes from a validator path.
     *
     * `/data/0/catalog_key` -> pointer `$.data[*].catalog_key`, rows [0]
     * `/data/0/total/0/x`   -> pointer `$.data[*].total[0].x`, rows [0]
     *
     * @return array{rows: list<int>, pointer: string}
     */
    private function parsePath(string $path): array
    {
        $segments = array_values(array_filter(
            explode('/', str_replace(['\\/', '~1'], ['/', '/'], $path)),
            static fn (string $segment) => $segment !== ''
        ));

        $rows = [];
        $pointer = '$';
        foreach ($segments as $index => $segment) {
            if (ctype_digit($segment)) {
                $rows[] = (int) $segment;
                // A list index inside data is summarised as [*]; indexes nested deeper keep
                // their position because they identify a real element (e.g. total[0]).
                $pointer .= $index <= 1 ? '[*]' : '['.$segment.']';

                continue;
            }
            // The envelope wrapper `data` is only noise for a row-indexed pointer.
            $pointer .= ($index === 0 && $segment === 'data' && count($segments) > 1 && ctype_digit($segments[1] ?? ''))
                ? ''
                : '.'.$segment;
        }

        return ['rows' => array_values(array_unique($rows)), 'pointer' => $pointer];
    }

    /** The JSON type declared for a field by the contract example. */
    private function exampleType(string $field): string
    {
        foreach (self::lastExample() as $row) {
            if (is_array($row) && array_key_exists($field, $row)) {
                return match (true) {
                    is_int($row[$field]), is_float($row[$field]) => '[integer|number]',
                    is_bool($row[$field]) => '[boolean]',
                    is_array($row[$field]) => '[array|object]',
                    $row[$field] === null => '[string|null]',
                    default => '[string]',
                };
            }
        }

        return '[any]';
    }

    /**
     * Remember the last compiled example so a missing-field alert can state the expected type
     * (the validator itself only says the property is absent).
     *
     * @return list<array<string, mixed>>
     */
    private static function lastExample(): array
    {
        return self::$lastExampleRows;
    }
}
