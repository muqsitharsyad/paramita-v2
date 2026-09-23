<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOperationalData extends Command
{
    protected $signature = 'paramita:prune-data';

    protected $description = 'Prune old test runs and operational metrics per PRD §14.3';

    public function handle(): int
    {
        $cutoff90Days = now()->subDays(90);
        $deletedRuns = DB::table('endpoint_test_runs')->where('created_at', '<', $cutoff90Days)->delete();

        $this->info("Pruned $deletedRuns old test runs (> 90 days).");

        return 0;
    }
}
