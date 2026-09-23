<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UserManagementController extends Controller
{
    private const INTERNAL_ROLES = ['admin', 'kepala_ut_pusat', 'kepala_ut_daerah', 'tutor'];

    private const STATUSES = ['active', 'inactive', 'suspended'];

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $role = (string) $request->query('role');
        $status = (string) $request->query('status');

        $users = User::query()
            ->leftJoin('ut_regions', 'ut_regions.id', '=', 'users.ut_id')
            ->whereNull('users.vendor_id')
            ->whereIn('users.role', self::INTERNAL_ROLES)
            ->when($query !== '', fn ($builder) => $builder->where(function ($nested) use ($query): void {
                $nested->where('users.name', 'like', "%{$query}%")
                    ->orWhere('users.email', 'like', "%{$query}%");
            }))
            ->when(in_array($role, self::INTERNAL_ROLES, true), fn ($builder) => $builder->where('users.role', $role))
            ->when(in_array($status, self::STATUSES, true), fn ($builder) => $builder->where('users.status', $status))
            ->select('users.*', 'ut_regions.name as ut_name')
            ->orderBy('users.name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'query' => $query,
            'selectedRole' => $role,
            'selectedStatus' => $status,
            'roles' => self::INTERNAL_ROLES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => $data['status'],
                'ut_id' => $this->scopeUtId($data),
                'vendor_id' => null,
                'permission_revision' => 1,
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$data['role']]);
            $this->syncPrograms($user, $data);
        });

        return redirect()->route('admin.users.index')->with('success', __('ui.users.created'));
    }

    public function edit(User $user): View
    {
        $this->ensureInternalUser($user);

        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureInternalUser($user);
        $data = $this->validated($request, $user);
        $this->guardAdministratorAccess($request, $user, $data);

        DB::transaction(function () use ($user, $data): void {
            $accessChanged = $user->role !== $data['role']
                || $user->status !== $data['status']
                || $user->ut_id !== $this->scopeUtId($data)
                || $this->programIds($user) !== $this->validatedProgramIds($data);

            $attributes = [
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'role' => $data['role'],
                'status' => $data['status'],
                'ut_id' => $this->scopeUtId($data),
            ];
            if (! empty($data['password'])) {
                $attributes['password'] = Hash::make($data['password']);
            }
            if ($accessChanged) {
                $attributes['permission_revision'] = $user->permission_revision + 1;
            }

            $user->update($attributes);
            $user->syncRoles([$data['role']]);
            $this->syncPrograms($user, $data);

            if ($accessChanged || ! empty($data['password'])) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        return redirect()->route('admin.users.index')->with('success', __('ui.users.updated'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $role = (string) $request->input('role');
        $scopedRole = in_array($role, ['kepala_ut_daerah', 'tutor'], true);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(self::INTERNAL_ROLES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'ut_id' => [Rule::requiredIf($scopedRole), 'nullable', 'integer', Rule::exists('ut_regions', 'id')->where('is_active', true)],
            'program_ids' => [Rule::requiredIf($role === 'tutor'), 'array'],
            'program_ids.*' => ['integer', 'distinct', Rule::exists('programs', 'id')->where('is_active', true)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', 'max:1024', PasswordRule::min(12)],
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(?User $user = null): array
    {
        return [
            'managedUser' => $user,
            'roles' => self::INTERNAL_ROLES,
            'statuses' => self::STATUSES,
            'regions' => DB::table('ut_regions')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'programs' => DB::table('programs')->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'selectedPrograms' => $user ? $this->programIds($user) : [],
        ];
    }

    /** @param array<string, mixed> $data */
    private function scopeUtId(array $data): ?int
    {
        return in_array($data['role'], ['kepala_ut_daerah', 'tutor'], true) ? (int) $data['ut_id'] : null;
    }

    /** @param array<string, mixed> $data */
    private function syncPrograms(User $user, array $data): void
    {
        DB::table('tutor_program')->where('user_id', $user->id)->delete();
        if ($data['role'] !== 'tutor') {
            return;
        }

        $now = now();
        DB::table('tutor_program')->insert(array_map(
            fn (int $programId): array => [
                'user_id' => $user->id,
                'program_id' => $programId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $this->validatedProgramIds($data),
        ));
    }

    /** @return list<int> */
    private function programIds(User $user): array
    {
        return DB::table('tutor_program')->where('user_id', $user->id)->orderBy('program_id')->pluck('program_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param array<string, mixed> $data
     * @return list<int>
     */
    private function validatedProgramIds(array $data): array
    {
        if ($data['role'] !== 'tutor') {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $data['program_ids'] ?? [])));
        sort($ids);

        return $ids;
    }

    /** @param array<string, mixed> $data */
    private function guardAdministratorAccess(Request $request, User $user, array $data): void
    {
        $removesAdminAccess = $data['role'] !== 'admin' || $data['status'] !== 'active';
        if (! $removesAdminAccess) {
            return;
        }

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages(['role' => __('ui.users.cannot_change_self_access')]);
        }

        if ($user->role === 'admin' && $user->status === 'active') {
            $activeAdmins = User::query()->where('role', 'admin')->where('status', 'active')->count();
            if ($activeAdmins <= 1) {
                throw ValidationException::withMessages(['role' => __('ui.users.last_admin_required')]);
            }
        }
    }

    private function ensureInternalUser(User $user): void
    {
        abort_unless($user->vendor_id === null && in_array($user->role, self::INTERNAL_ROLES, true), 404);
    }
}
