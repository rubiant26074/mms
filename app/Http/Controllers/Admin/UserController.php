<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(MmsContext $context): View
    {
        $context->syncLegacySession(auth()->user()->loadMissing('role.permissions'));

        return view('admin.users.index', [
            'users' => User::query()->with('role')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(), false);
    }

    public function edit(User $user): View
    {
        return $this->form($user, true);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);
        $data['signature_path'] = $this->storeSignature($request);
        User::query()->create($data);

        return redirect()->route('admin.users.index')->with('success', 'Data User berhasil disimpan!');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $signature = $this->storeSignature($request);
        if ($signature !== null) {
            $data['signature_path'] = $signature;
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Data User berhasil disimpan!');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id() || $user->role?->role_slug === 'admin') {
            return back()->withErrors('User ini tidak boleh dihapus.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }

    private function form(User $user, bool $isEdit): View
    {
        return view('admin.users.form', [
            'userData' => $user,
            'isEdit' => $isEdit,
            'roles' => Role::query()->orderBy('role_name')->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user)],
            'fullname' => ['required', 'string', 'max:100'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:4'],
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);
    }

    private function storeSignature(Request $request): ?string
    {
        if (! $request->hasFile('signature')) {
            return null;
        }

        $path = $request->file('signature')->store('uploads/signatures', 'public_root');

        return str_replace('\\', '/', $path);
    }
}
