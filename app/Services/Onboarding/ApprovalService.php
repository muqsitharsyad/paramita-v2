<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Exception;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /**
     * Submit a test-passed binding revision for admin review.
     * PRD §5.2: Only if report is passed, same snapshot, completed <= 24h ago.
     */
    public function submit(int $bindingRevisionId, int $testRunId): int
    {
        return DB::transaction(function () use ($bindingRevisionId, $testRunId) {
            $rev = DB::table('binding_revisions')->where('id', $bindingRevisionId)->lockForUpdate()->first();
            $run = DB::table('endpoint_test_runs')->where('id', $testRunId)->first();

            if (! $rev || ! $run) {
                throw new Exception('Binding revision or test run not found');
            }

            if ($run->status !== 'passed') {
                throw new Exception('Cannot submit: test run did not pass');
            }

            if (now()->greaterThan($run->expires_at)) {
                throw new Exception('Cannot submit: test run expired (> 24 hours)');
            }

            DB::table('binding_revisions')->where('id', $rev->id)->update([
                'status' => 'submitted',
                'updated_at' => now(),
            ]);

            return DB::table('submissions')->insertGetId([
                'binding_revision_id' => $rev->id,
                'test_run_id' => $run->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Admin approves submitted binding revision.
     * PRD §5.2: Atomic activation; invalidates old pointer, sets active_revision_id.
     */
    public function approve(int $submissionId, int $adminUserId): void
    {
        DB::transaction(function () use ($submissionId, $adminUserId) {
            $sub = DB::table('submissions')->where('id', $submissionId)->lockForUpdate()->first();
            if (! $sub || $sub->status !== 'pending') {
                throw new Exception('Submission not pending');
            }

            $run = DB::table('endpoint_test_runs')->where('id', $sub->test_run_id)->first();
            if (now()->greaterThan($run->expires_at)) {
                throw new Exception('Report expired at time of approval — auto retest required');
            }

            $rev = DB::table('binding_revisions')->where('id', $sub->binding_revision_id)->first();

            // Mark revision approved
            DB::table('binding_revisions')->where('id', $rev->id)->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);

            // Atomically switch active_revision_id on binding
            DB::table('endpoint_bindings')->where('id', $rev->binding_id)->update([
                'active_revision_id' => $rev->id,
                'updated_at' => now(),
            ]);

            // Mark submission approved
            DB::table('submissions')->where('id', $sub->id)->update([
                'status' => 'approved',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
