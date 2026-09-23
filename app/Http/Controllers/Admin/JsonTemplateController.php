<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JsonTemplate;
use App\Services\Contracts\TemplateContractGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JsonTemplateController extends Controller
{
    public function __construct(private readonly TemplateContractGuard $contractGuard) {}

    public function index(Request $request): View
    {
        $search = $request->query('q');
        $query = JsonTemplate::query();

        if ($search) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return view('admin.templates.index', [
            'templates' => $query->orderBy('name')->paginate(10),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.templates.form', ['template' => null]);
    }

    public function guide(): View
    {
        return view('admin.templates.guide');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['template_data'] = $this->validatedTemplateJson((string) $data['template_data']);

        JsonTemplate::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.templates.index')
            ->with('success', 'Format JSON berhasil dibuat. Format ini langsung menjadi acuan Test vendor.');
    }

    public function edit(int $id): View
    {
        return view('admin.templates.form', ['template' => JsonTemplate::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $template = JsonTemplate::findOrFail($id);
        $data = $this->validated($request, $id);
        $data['is_active'] = $request->boolean('is_active');
        $data['template_data'] = $this->validatedTemplateJson((string) $data['template_data']);
        $template->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('admin.templates.index')
            ->with('success', 'Format JSON diperbarui. Test vendor sekarang memakai format ini.');
    }

    public function destroy(int $id): RedirectResponse
    {
        JsonTemplate::findOrFail($id)->delete();

        return redirect()->route('admin.templates.index')->with('success', 'JSON Template berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255|unique:json_templates,name'.($id === null ? '' : ','.$id),
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'template_data' => 'required|json',
            'version' => 'nullable|string|max:20',
        ]);
    }

    private function validatedTemplateJson(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw ValidationException::withMessages([
                'template_data' => 'Format JSON harus berupa object dengan bagian data dan meta.',
            ]);
        }

        $problems = $this->contractGuard->inspect('', $decoded)['problems'];
        if ($problems !== []) {
            throw ValidationException::withMessages([
                'template_data' => implode("\n", array_map(static fn (string $line): string => '• '.$line, $problems)),
            ]);
        }

        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
