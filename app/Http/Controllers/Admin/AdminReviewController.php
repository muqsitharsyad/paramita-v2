<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdminReviewController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
    ) {}

    public function index(Request $request): View
    {
        $vendorQuery = trim((string) $request->query('vendor_q', ''));
        $pendingQuery = trim((string) $request->query('pending_q', ''));
        $reviewQuery = trim((string) $request->query('review_q', ''));
        $endpointQuery = trim((string) $request->query('endpoint_q', ''));
        $section = (string) ($request->route('section') ?? 'overview');
        $pendingVendorsQuery = DB::table('vendors')
            ->leftJoin('users as u', 'u.vendor_id', '=', 'vendors.id')
            ->whereIn('vendors.status', ['pending_verification', 'pending_approval'])
            ->select('vendors.*', 'u.id as user_id', 'u.name as user_name', 'u.status as user_status', 'u.email as user_email', 'u.email_verified_at');
        if ($pendingQuery !== '') {
            $pendingVendorsQuery->where(function ($query) use ($pendingQuery): void {
                $query->where('vendors.code', 'like', "%{$pendingQuery}%")
                    ->orWhere('vendors.legal_name', 'like', "%{$pendingQuery}%")
                    ->orWhere('vendors.contact_email', 'like', "%{$pendingQuery}%")
                    ->orWhere('u.email', 'like', "%{$pendingQuery}%");
            });
        }
        $pendingVendors = $pendingVendorsQuery->orderBy('vendors.created_at', 'desc')->paginate(10, ['*'], 'page_pending')->withQueryString();

        $activeVendorsQuery = DB::table('vendors as v')
            ->leftJoin('users as u', function ($join): void {
                $join->on('u.vendor_id', '=', 'v.id')->where('u.role', '=', 'vendor');
            })
            ->where('v.status', 'approved')
            ->select('v.id', 'v.code', 'v.legal_name', 'v.contact_name', 'v.contact_email', 'v.approved_at', 'v.scope_revision', 'u.name as user_name', 'u.email as user_email', 'u.status as user_status');
        if ($vendorQuery !== '') {
            $activeVendorsQuery->where(function ($query) use ($vendorQuery): void {
                $query->where('v.code', 'like', "%{$vendorQuery}%")
                    ->orWhere('v.legal_name', 'like', "%{$vendorQuery}%")
                    ->orWhere('v.contact_email', 'like', "%{$vendorQuery}%")
                    ->orWhere('u.email', 'like', "%{$vendorQuery}%");
            });
        }
        $activeVendors = $activeVendorsQuery->orderBy('v.legal_name')->paginate(10, ['*'], 'page_vendors')->withQueryString();

        $submissionsQuery = DB::table('submissions as s')
            ->join('binding_revisions as br', 'br.id', '=', 's.binding_revision_id')
            ->join('connection_revisions as cr', 'cr.id', '=', 'br.connection_revision_id')
            ->join('endpoint_bindings as eb', 'eb.id', '=', 'br.binding_id')
            ->join('vendors as v', 'v.id', '=', 'eb.vendor_id')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->join('endpoint_test_runs as tr', 'tr.id', '=', 's.test_run_id')
            ->where('s.status', 'pending');
        if ($reviewQuery !== '') {
            $submissionsQuery->where(function ($query) use ($reviewQuery): void {
                $query->where('v.code', 'like', "%{$reviewQuery}%")
                    ->orWhere('v.legal_name', 'like', "%{$reviewQuery}%")
                    ->orWhere('c.operation_key', 'like', "%{$reviewQuery}%")
                    ->orWhere('c.label', 'like', "%{$reviewQuery}%")
                    ->orWhere('br.path', 'like', "%{$reviewQuery}%");
            });
        }
        $submissionsCount = (clone $submissionsQuery)->count('s.id');
        $submissions = (clone $submissionsQuery)
            ->select('v.id as vendor_id', 'v.code as vendor_code', 'v.legal_name as vendor_name')
            ->groupBy('v.id', 'v.code', 'v.legal_name')
            ->orderBy('v.legal_name')
            ->paginate(10, ['*'], 'page_reviews')->withQueryString();
        $submissionVendorIds = $submissions->getCollection()->pluck('vendor_id')->all();
        $submissionsByVendor = (clone $submissionsQuery)
            ->whereIn('v.id', $submissionVendorIds)
            ->select('s.id as submission_id', 's.created_at as submitted_at', 'v.id as vendor_id', 'v.code as vendor_code', 'v.legal_name as vendor_name', 'c.operation_key', 'c.label as contract_label', 'br.revision', 'br.path', 'cr.base_url', 'cr.auth_type', 'tr.status as test_status', 'tr.report_json', 'tr.finished_at as test_finished_at')
            ->orderBy('v.legal_name')
            ->orderBy('s.created_at', 'desc')
            ->get()
            ->groupBy('vendor_code');

        $activeBindingsQuery = DB::table('endpoint_bindings as eb')
            ->join('binding_revisions as br', 'br.id', '=', 'eb.active_revision_id')
            ->join('connection_revisions as cr', 'cr.id', '=', 'br.connection_revision_id')
            ->join('vendors as v', 'v.id', '=', 'eb.vendor_id')
            ->join('contracts as c', 'c.id', '=', 'eb.contract_id')
            ->leftJoin('endpoint_test_runs as tr', function ($join): void {
                $join->on('tr.binding_revision_id', '=', 'br.id')
                    ->whereRaw('tr.id = (select max(id) from endpoint_test_runs where binding_revision_id = br.id)');
            })
            ->where('eb.is_enabled', true)
            ->where('br.status', 'approved')
            ->select('v.code as vendor_code', 'v.legal_name as vendor_name', 'c.operation_key', 'c.label as contract_label', 'br.revision', 'br.path', 'cr.base_url', 'cr.auth_type', 'tr.status as test_status', 'tr.finished_at as test_finished_at');
        if ($endpointQuery !== '') {
            $activeBindingsQuery->where(function ($query) use ($endpointQuery): void {
                $query->where('v.code', 'like', "%{$endpointQuery}%")
                    ->orWhere('v.legal_name', 'like', "%{$endpointQuery}%")
                    ->orWhere('c.operation_key', 'like', "%{$endpointQuery}%")
                    ->orWhere('c.label', 'like', "%{$endpointQuery}%")
                    ->orWhere('br.path', 'like', "%{$endpointQuery}%")
                    ->orWhere('cr.base_url', 'like', "%{$endpointQuery}%");
            });
        }
        $activeBindingsCount = (clone $activeBindingsQuery)->count('eb.id');
        $activeBindings = (clone $activeBindingsQuery)
            ->select('v.id as vendor_id', 'v.code as vendor_code', 'v.legal_name as vendor_name')
            ->groupBy('v.id', 'v.code', 'v.legal_name')
            ->orderBy('v.legal_name')
            ->paginate(10, ['*'], 'page_endpoints')->withQueryString();
        $activeVendorIds = $activeBindings->getCollection()->pluck('vendor_id')->all();
        $activeBindingsByVendor = (clone $activeBindingsQuery)
            ->whereIn('v.id', $activeVendorIds)
            ->select('v.id as vendor_id', 'v.code as vendor_code', 'v.legal_name as vendor_name', 'c.operation_key', 'c.label as contract_label', 'br.revision', 'br.path', 'cr.base_url', 'cr.auth_type', 'tr.status as test_status', 'tr.finished_at as test_finished_at')
            ->orderBy('v.legal_name')
            ->orderBy('c.operation_key')
            ->get()
            ->groupBy('vendor_code');

        $stats = [
            'vendors' => (int) DB::table('vendors')->where('status', 'approved')->count(),
            'pending_vendors' => (int) DB::table('vendors')->whereIn('status', ['pending_verification', 'pending_approval'])->count(),
            'pending_endpoints' => $submissionsCount,
            'active_bindings' => $activeBindingsCount,
        ];

        return view('admin.dashboard', compact('pendingVendors', 'activeVendors', 'submissions', 'submissionsByVendor', 'activeBindings', 'activeBindingsByVendor', 'stats', 'vendorQuery', 'pendingQuery', 'reviewQuery', 'endpointQuery', 'section'));
    }

    public function approveVendor(Request $request, int $vendorId): RedirectResponse
    {
        $vendor = DB::table('vendors')->where('id', $vendorId)->first();
        if (! $vendor) {
            return back()->withErrors(['msg' => 'Vendor tidak ditemukan.']);
        }

        DB::transaction(function () use ($request, $vendorId): void {
            DB::table('vendors')->where('id', $vendorId)->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'updated_at' => now(),
            ]);
            DB::table('users')->where('vendor_id', $vendorId)->where('role', 'vendor')->update([
                'status' => 'active',
                'email_verified_at' => DB::raw('COALESCE(email_verified_at, CURRENT_TIMESTAMP)'),
                'updated_at' => now(),
            ]);
            foreach (DB::table('contracts')->pluck('id') as $contractId) {
                DB::table('endpoint_bindings')->updateOrInsert(
                    ['vendor_id' => $vendorId, 'contract_id' => $contractId],
                    ['is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });

        return back()->with('success', "Vendor {$vendor->legal_name} disetujui dan sekarang terlihat di daftar Vendor Aktif.");
    }

    public function approveSubmission(Request $request, int $submissionId): RedirectResponse
    {
        try {
            $this->approvalService->approve($submissionId, $request->user()->id);

            return back()->with('success', 'Endpoint disetujui. URL draft kini menjadi endpoint aktif.');
        } catch (\Throwable $exception) {
            return back()->withErrors(['msg' => $exception->getMessage()]);
        }
    }
}
