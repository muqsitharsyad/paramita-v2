<?php

declare(strict_types=1);

/**
 * Paramita vendor contract map.
 *
 * `envelope` = JSON Schema validating the whole response body (or the object under `data`).
 * `row`      = JSON Schema validating each element of `data` when the operation returns a list.
 * `envelope_on` = where the envelope schema applies: `body` (default) or `data`
 *                 (order.detail / orders.summary return a single object under `data`).
 *
 * PRD §465/§206: admin may tighten via a NEW contract version, but may not silently
 * diverge from the published schema — validation always reads these files.
 */
return [
    'schemas' => [
        'inventory.list' => [
            'envelope' => 'envelope.list.schema.json',
            'row' => 'inventory.row.schema.json',
        ],
        'inventory.lookup' => [
            'envelope' => 'envelope.list.schema.json',
            'row' => 'inventory.row.schema.json',
        ],
        'inventory.summary' => [
            'envelope' => 'inventory.summary.schema.json',
            'row' => null,
            'envelope_on' => 'body',
        ],
        'orders.list' => [
            'envelope' => 'envelope.list.schema.json',
            'row' => 'order.row.schema.json',
        ],
        'orders.summary' => [
            'envelope' => 'summary.row.schema.json',
            'row' => null,
            'envelope_on' => 'data',
        ],
        'orders.detail' => [
            'envelope' => 'order.detail.schema.json',
            'row' => null,
            'envelope_on' => 'data',
        ],
        'orders.events' => [
            'envelope' => 'envelope.list.schema.json',
            'row' => 'event.row.schema.json',
        ],
    ],
    'default_schema' => 'envelope.list.schema.json',

    /*
    |--------------------------------------------------------------------------
    | UI languages
    |--------------------------------------------------------------------------
    | Codes must match the `lang/{code}` directories. The first entry is the
    | fallback. PRD §5 says the vendor-facing UI and documentation are in
    | Indonesian; English is provided as an additional UI language.
    */
    'default_locale' => 'id',
    'locales' => [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
    ],
];
