<?php

declare(strict_types=1);

namespace Tests\Unit\ReferenceData;

use Database\Seeders\Support\OfficialUtUnits;
use PHPUnit\Framework\TestCase;

class OfficialUtUnitsTest extends TestCase
{
    public function test_official_ut_units_match_the_supplied_master_list(): void
    {
        $units = OfficialUtUnits::all();
        $byCode = array_column($units, null, 'code');

        $this->assertCount(40, $units);
        $this->assertCount(40, array_unique(array_column($units, 'code')));
        $this->assertCount(40, array_unique(array_column($units, 'name')));
        $this->assertSame('Layanan Luar Negeri', $byCode['UN31.UT40']['name']);
        $this->assertSame('luar_negeri', $byCode['UN31.UT40']['type']);
        $this->assertSame('Sorong', $byCode['UN31.UT1']['name']);
        $this->assertSame('Bandung', $byCode['UN31.UT15']['name']);
        $this->assertSame('Palangkaraya', $byCode['UN31.UT21']['name']);
        $this->assertSame('Ternate', $byCode['UN31.UT39']['name']);
        $this->assertCount(39, array_filter($units, static fn (array $unit): bool => $unit['type'] === 'daerah'));
    }
}
