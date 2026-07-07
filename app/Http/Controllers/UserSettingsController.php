<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserSettingsController extends Controller
{
    public function edit(MmsContext $context): View
    {
        $user = $this->currentUser();
        $context->syncLegacySession($user->loadMissing('role.permissions'));

        return view('users.settings', [
            'userData' => $user,
            'roleName' => $user->role?->role_name ?? $user->role?->role_slug ?? '',
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'signature_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'face_reference_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $user = $this->currentUser();
        $updates = [];

        $signaturePath = $this->storeUploadedImage($request, 'signature_file', 'uploads/signatures', 'sig_' . $user->id);
        if ($signaturePath !== null) {
            $this->deletePublicFile($user->signature_path);
            $updates['signature_path'] = $signaturePath;
        }

        $avatarPath = $this->storeUploadedImage($request, 'avatar_file', 'uploads/avatars', 'ava_' . $user->id);
        if ($avatarPath !== null) {
            $this->deletePublicFile($user->avatar_path);
            $updates['avatar_path'] = $avatarPath;
        }

        $faceReferencePath = $this->storeUploadedImage($request, 'face_reference_file', 'uploads/face-reference', 'face_' . $user->id);
        if ($faceReferencePath !== null) {
            $this->deletePublicFile($user->face_reference_path);
            $updates['face_reference_path'] = $faceReferencePath;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        return back()->with('success', $updates === [] ? 'Tidak ada file profil baru yang diunggah.' : 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = $this->currentUser();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors('Password lama tidak sesuai.');
        }

        $user->forceFill([
            'password' => Hash::make($data['new_password']),
        ])->save();

        return back()->with('success', 'Password berhasil diubah.');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function storeUploadedImage(Request $request, string $field, string $directory, string $prefix): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = $prefix . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = $file->storeAs($directory, $filename, 'public_root');

        return $path ? str_replace('\\', '/', $path) : null;
    }

    private function deletePublicFile(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public_root')->delete(ltrim($path, '/'));
    }
}
