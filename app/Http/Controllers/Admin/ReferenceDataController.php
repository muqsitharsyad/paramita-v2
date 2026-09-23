<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reference data the vendors must speak: UT regions (ut_code), study programmes (program_code),
 * catalogue items (catalog_key) and process statuses (process_status_code).
 *
 * Paramita is the OWNER of these codes — a vendor cannot guess "24 = Bandung", so the admin keeps
 * them here and the vendor portal shows the same lists as a reference.
 */
class ReferenceDataController extends Controller
{
    /** table key => [model-ish config] */
    private const TABLES = [
        'ut' => [
            'table' => 'ut_regions',
            'title' => 'UT Daerah',
            'columns' => ['code', 'name', 'type'],
            'labels' => ['code' => 'Kode UT', 'name' => 'Nama UT', 'type' => 'Tipe'],
            'rules' => [
                'code' => 'required|string|max:100',
                'name' => 'required|string|max:255',
                'type' => 'required|in:daerah,luar_negeri',
            ],
        ],
        'program' => [
            'table' => 'programs',
            'title' => 'Program Studi',
            'columns' => ['code', 'name'],
            'labels' => ['code' => 'Kode Prodi', 'name' => 'Nama Prodi'],
            'rules' => [
                'code' => 'required|string|max:100',
                'name' => 'required|string|max:255',
            ],
        ],
        'catalog' => [
            'table' => 'catalog_items',
            'title' => 'Katalog Paket / Buku',
            'columns' => ['catalog_key', 'item_type', 'item_code', 'edition', 'title'],
            'labels' => [
                'catalog_key' => 'Catalog Key', 'item_type' => 'Tipe',
                'item_code' => 'Kode Item', 'edition' => 'Edisi', 'title' => 'Judul',
            ],
            'rules' => [
                'catalog_key' => 'required|string|max:100',
                'item_type' => 'required|in:package,book',
                'item_code' => 'required|string|max:100',
                'edition' => 'nullable|string|max:50',
                'title' => 'required|string|max:500',
            ],
        ],
        'status' => [
            'table' => 'process_statuses',
            'title' => 'Status Proses',
            'columns' => ['code', 'label', 'bucket'],
            'labels' => ['code' => 'Kode Status', 'label' => 'Label', 'bucket' => 'Bucket'],
            'rules' => [
                'code' => 'required|string|max:10',
                'label' => 'required|string|max:100',
                'bucket' => 'nullable|string|max:50',
            ],
        ],
        'retry-reason' => [
            'table' => 'retry_reasons',
            'title' => 'Alasan Retry',
            'columns' => ['code', 'label', 'description', 'sort_order'],
            'labels' => ['code' => 'Kode Alasan', 'label' => 'Alasan', 'description' => 'Keterangan', 'sort_order' => 'Urutan'],
            'rules' => [
                'code' => 'required|string|max:50|regex:/^[A-Z0-9_]+$/',
                'label' => 'required|string|max:150',
                'description' => 'nullable|string|max:500',
                'sort_order' => 'required|integer|min:0|max:9999',
            ],
        ],
        'period' => [
            'table' => 'periods',
            'title' => 'Masa Pemesanan',
            'columns' => ['code', 'name', 'sort_order'],
            'labels' => ['code' => 'Kode Masa', 'name' => 'Label Masa', 'sort_order' => 'Urutan'],
            'rules' => [
                'code' => 'required|string|max:20|regex:/^[0-9]{4,5}$/',
                'name' => 'required|string|max:255',
                'sort_order' => 'required|integer|min:0|max:9999',
            ],
        ],
    ];

    public function index(Request $request, string $type): View
    {
        $config = $this->config($type);

        $query = DB::table($config['table'])->orderBy('id');
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($config, $search) {
                foreach ($config['columns'] as $column) {
                    $inner->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        $rows = $query->paginate(25)->withQueryString();

        return view('admin.reference.index', [
            'type' => $type,
            'config' => $config,
            'rows' => $rows,
            'search' => $search,
            'types' => $this->typeLabels(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->config($type);
        $data = $request->validate($config['rules']);

        $data['is_active'] = true;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // Codes are the contract with the vendor: duplicates would make a code ambiguous.
        $key = $config['columns'][0];
        if (DB::table($config['table'])->where($key, $data[$key])->exists()) {
            return back()->withErrors([$key => "Kode {$data[$key]} sudah ada."])->withInput();
        }

        DB::table($config['table'])->insert($data);

        return redirect()->route('admin.reference.index', $type)
            ->with('success', $config['title'].' berhasil ditambahkan.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $data = $request->validate($config['rules']);
        $data['updated_at'] = now();

        $key = $config['columns'][0];
        $duplicate = DB::table($config['table'])
            ->where($key, $data[$key])
            ->where('id', '!=', $id)
            ->exists();
        if ($duplicate) {
            return back()->withErrors([$key => "Kode {$data[$key]} sudah dipakai baris lain."])->withInput();
        }

        DB::table($config['table'])->where('id', $id)->update($data);

        return redirect()->route('admin.reference.index', $type)
            ->with('success', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        DB::table($config['table'])->where('id', $id)->delete();

        return redirect()->route('admin.reference.index', $type)
            ->with('success', $config['title'].' berhasil dihapus.');
    }

    public function toggle(string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        $row = DB::table($config['table'])->where('id', $id)->first();
        if ($row === null) {
            return back()->with('error', 'Data tidak ditemukan.');
        }

        DB::table($config['table'])->where('id', $id)->update([
            'is_active' => ! ((bool) ($row->is_active ?? false)),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.reference.index', $type)
            ->with('success', 'Status aktif '.$config['title'].' diperbarui.');
    }

    /** @return array{table: string, title: string, columns: list<string>, labels: array<string,string>, rules: array<string,string>} */
    private function config(string $type): array
    {
        abort_unless(isset(self::TABLES[$type]), 404);

        return self::TABLES[$type];
    }

    /** @return array<string, string> */
    private function typeLabels(): array
    {
        $labels = [];
        foreach (self::TABLES as $key => $config) {
            $labels[$key] = $config['title'];
        }

        return $labels;
    }
}
