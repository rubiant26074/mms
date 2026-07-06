<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['roleData' => new Role(), 'isEdit' => false]);
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', ['roleData' => $role, 'isEdit' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        Role::query()->create($this->validated($request));

        return redirect()->route('admin.roles.index')->with('success', 'Role tersimpan!');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $role->update($this->validated($request, $role));

        return redirect()->route('admin.roles.index')->with('success', 'Role tersimpan!');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->role_slug === 'admin' || $role->users()->exists()) {
            return back()->withErrors('Role tidak bisa dihapus karena protected atau sedang dipakai.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role berhasil dihapus.');
    }

    public function permissions(?Role $role = null): View
    {
        $role ??= Role::query()->where('role_slug', '!=', 'admin')->orderBy('id')->first();

        return view('admin.roles.permissions', [
            'roles' => Role::query()->where('role_slug', '!=', 'admin')->orderBy('id')->get(),
            'selectedRole' => $role,
            'permissions' => Permission::query()->orderBy('id')->get(),
            'currentPermissions' => $role ? $role->permissions()->pluck('permissions.id')->all() : [],
        ]);
    }

    public function updatePermissions(Request $request, Role $role): RedirectResponse
    {
        if ($role->role_slug === 'admin') {
            return back()->withErrors('Administrator memiliki akses penuh otomatis.');
        }

        $permissionIds = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ])['permissions'] ?? [];

        $role->permissions()->sync($permissionIds);

        return redirect()->route('admin.roles.permissions', $role)->with('success', 'Hak akses berhasil diperbarui!');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'role_name' => ['required', 'string', 'max:50'],
            'role_slug' => ['required', 'string', 'max:50', Rule::unique('roles', 'role_slug')->ignore($role)],
            'description' => ['nullable', 'string'],
        ]);
        $data['role_slug'] = strtolower(str_replace(' ', '_', trim($data['role_slug'])));

        return $data;
    }
}
