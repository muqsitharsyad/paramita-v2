<?php

declare(strict_types=1);

namespace Tests\Unit\Pagination;

use PHPUnit\Framework\TestCase;

/**
 * F0: Pure paginator algorithm test — vendor-segment pagination (PRD §10.2).
 * No DB, no HTTP. Only arithmetic over fixture-like counts.
 */
class PaginatorAlgorithmTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Algorithm: map global offset/limit to vendor segments
    // ──────────────────────────────────────────────────────────

    /**
     * Build cumulative ranges and map global O,L to per-vendor local offset/limit.
     *
     * @param  array<string,int>  $counts  vendor_code => total_filtered
     * @param  int  $offset  global offset
     * @param  int  $limit  global limit
     * @return array<string, array{local_offset:int,local_limit:int}>
     */
    private function vendorSegments(array $counts, int $offset, int $limit): array
    {
        // Sort by vendor_code ASC (immutable master order)
        ksort($counts);

        $segments = [];
        $cumulative = 0;
        $remaining = $limit;

        foreach ($counts as $vendor => $count) {
            $vendorStart = $cumulative;
            $vendorEnd = $cumulative + $count;

            // Overlap check: does [offset, offset+limit) intersect [vendorStart, vendorEnd)?
            $globalWindowEnd = $offset + $limit;

            if ($vendorEnd <= $offset || $vendorStart >= $globalWindowEnd) {
                // No overlap
                $cumulative += $count;

                continue;
            }

            $localOffset = max(0, $offset - $vendorStart);
            $available = $count - $localOffset;
            $localLimit = min($available, $remaining);

            if ($localLimit > 0) {
                $segments[$vendor] = [
                    'local_offset' => $localOffset,
                    'local_limit' => $localLimit,
                ];
                $remaining -= $localLimit;
            }

            $cumulative += $count;

            if ($remaining <= 0) {
                break;
            }
        }

        return $segments;
    }

    private function globalTotal(array $counts): int
    {
        return array_sum($counts);
    }

    // ──────────────────────────────────────────────────────────
    // Fixture from PRD §10.2: A=3, B=4; O=2, L=3
    // Expected: A local_offset=2 local_limit=1 + B offset=0 limit=2
    // ──────────────────────────────────────────────────────────

    public function test_prd_example_counts3_and4_offset2_limit3(): void
    {
        $counts = ['VENDOR-A' => 3, 'VENDOR-B' => 4];
        $segments = $this->vendorSegments($counts, offset: 2, limit: 3);

        $this->assertArrayHasKey('VENDOR-A', $segments);
        $this->assertSame(2, $segments['VENDOR-A']['local_offset']);
        $this->assertSame(1, $segments['VENDOR-A']['local_limit']);

        $this->assertArrayHasKey('VENDOR-B', $segments);
        $this->assertSame(0, $segments['VENDOR-B']['local_offset']);
        $this->assertSame(2, $segments['VENDOR-B']['local_limit']);

        // Total rows returned = 1 + 2 = 3 = L
        $totalRows = array_sum(array_column($segments, 'local_limit'));
        $this->assertSame(3, $totalRows);
    }

    public function test_first_page_fully_in_first_vendor(): void
    {
        $counts = ['VENDOR-A' => 10, 'VENDOR-B' => 5];
        $segments = $this->vendorSegments($counts, offset: 0, limit: 5);

        $this->assertCount(1, $segments, 'Only A needed for first 5 rows');
        $this->assertSame(0, $segments['VENDOR-A']['local_offset']);
        $this->assertSame(5, $segments['VENDOR-A']['local_limit']);
        $this->assertArrayNotHasKey('VENDOR-B', $segments);
    }

    public function test_page_straddles_two_vendors(): void
    {
        $counts = ['VENDOR-A' => 3, 'VENDOR-B' => 10];
        // offset=1, limit=5 → A provides 2 (rows 1,2), B provides 3 (rows 0,1,2)
        $segments = $this->vendorSegments($counts, offset: 1, limit: 5);

        $this->assertSame(1, $segments['VENDOR-A']['local_offset']);
        $this->assertSame(2, $segments['VENDOR-A']['local_limit']);
        $this->assertSame(0, $segments['VENDOR-B']['local_offset']);
        $this->assertSame(3, $segments['VENDOR-B']['local_limit']);
    }

    public function test_offset_beyond_first_vendor(): void
    {
        $counts = ['VENDOR-A' => 3, 'VENDOR-B' => 10];
        // offset=5, limit=3 → B offset=2 limit=3
        $segments = $this->vendorSegments($counts, offset: 5, limit: 3);

        $this->assertArrayNotHasKey('VENDOR-A', $segments);
        $this->assertSame(2, $segments['VENDOR-B']['local_offset']);
        $this->assertSame(3, $segments['VENDOR-B']['local_limit']);
    }

    public function test_offset_exceeds_all_counts(): void
    {
        $counts = ['VENDOR-A' => 3, 'VENDOR-B' => 4];
        $segments = $this->vendorSegments($counts, offset: 10, limit: 5);
        $this->assertEmpty($segments, 'No vendor data at offset beyond total');
    }

    public function test_three_vendors_split_across_all(): void
    {
        // A=5, B=5, C=5; offset=3, limit=8 → A(3,2) + B(0,5) + C(0,1)
        $counts = ['VENDOR-A' => 5, 'VENDOR-B' => 5, 'VENDOR-C' => 5];
        $segments = $this->vendorSegments($counts, offset: 3, limit: 8);

        $this->assertSame(3, $segments['VENDOR-A']['local_offset']);
        $this->assertSame(2, $segments['VENDOR-A']['local_limit']);
        $this->assertSame(0, $segments['VENDOR-B']['local_offset']);
        $this->assertSame(5, $segments['VENDOR-B']['local_limit']);
        $this->assertSame(0, $segments['VENDOR-C']['local_offset']);
        $this->assertSame(1, $segments['VENDOR-C']['local_limit']);

        $totalRows = array_sum(array_column($segments, 'local_limit'));
        $this->assertSame(8, $totalRows);
    }

    public function test_limit_larger_than_remaining_rows(): void
    {
        $counts = ['VENDOR-A' => 2, 'VENDOR-B' => 2];
        // offset=0, limit=25 but only 4 rows total
        $segments = $this->vendorSegments($counts, offset: 0, limit: 25);

        $totalRows = array_sum(array_column($segments, 'local_limit'));
        $this->assertSame(4, $totalRows, 'Returns only available rows, not limit');
    }

    public function test_single_vendor(): void
    {
        $counts = ['VENDOR-ONLY' => 100];
        $segments = $this->vendorSegments($counts, offset: 25, limit: 25);
        $this->assertSame(25, $segments['VENDOR-ONLY']['local_offset']);
        $this->assertSame(25, $segments['VENDOR-ONLY']['local_limit']);
    }

    public function test_empty_counts_no_segments(): void
    {
        $segments = $this->vendorSegments([], offset: 0, limit: 25);
        $this->assertEmpty($segments);
    }

    public function test_zero_count_vendor_skipped(): void
    {
        // Vendor B has 0 rows — should not appear in segments
        $counts = ['VENDOR-A' => 5, 'VENDOR-B' => 0, 'VENDOR-C' => 5];
        $segments = $this->vendorSegments($counts, offset: 0, limit: 3);
        $this->assertArrayNotHasKey('VENDOR-B', $segments);
    }

    public function test_vendor_order_is_alphabetic_by_code(): void
    {
        // Shuffle order to verify sort is enforced
        $counts = ['VENDOR-C' => 3, 'VENDOR-A' => 3, 'VENDOR-B' => 3];
        $segments = $this->vendorSegments($counts, offset: 0, limit: 25);
        $order = array_keys($segments);
        $this->assertSame(['VENDOR-A', 'VENDOR-B', 'VENDOR-C'], $order);
    }

    // ──────────────────────────────────────────────────────────
    // has_more and total_filtered derivation
    // ──────────────────────────────────────────────────────────

    public function test_has_more_derivation(): void
    {
        $counts = ['VENDOR-A' => 10, 'VENDOR-B' => 10];
        $total = $this->globalTotal($counts);
        $offset = 0;
        $limit = 15;

        $segments = $this->vendorSegments($counts, $offset, $limit);
        $rowsGiven = array_sum(array_column($segments, 'local_limit'));
        $hasMore = ($offset + $rowsGiven) < $total;

        $this->assertTrue($hasMore);
        $this->assertSame(20, $total);
    }

    public function test_has_more_false_on_last_page(): void
    {
        $counts = ['VENDOR-A' => 3, 'VENDOR-B' => 4];
        $total = $this->globalTotal($counts);
        $offset = 5;
        $limit = 25;

        $segments = $this->vendorSegments($counts, $offset, $limit);
        $rowsGiven = array_sum(array_column($segments, 'local_limit'));
        $hasMore = ($offset + $rowsGiven) < $total;

        $this->assertFalse($hasMore);
        $this->assertSame(2, $rowsGiven, 'Last page: only 2 rows remain');
    }
}
