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
        $request->validate([
            'signature_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'face_reference_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
        ]);

        $user = $this->currentUser();
        $updates = [];

        $uploads = [
            'signature_file' => [
                'column' => 'signature_path',
                'directory' => 'uploads/signatures',
                'prefix' => 'sig_' . $user->id,
                'error' => 'Upload tanda tangan gagal. Pastikan folder public/uploads/signatures writable di server.',
            ],
            'avatar_file' => [
                'column' => 'avatar_path',
                'directory' => 'uploads/avatars',
                'prefix' => 'ava_' . $user->id,
                'error' => 'Upload avatar gagal. Pastikan folder public/uploads/avatars writable di server.',
            ],
            'face_reference_file' => [
                'column' => 'face_reference_path',
                'directory' => 'uploads/face-reference',
                'prefix' => 'face_' . $user->id,
                'error' => 'Upload wajah referensi gagal. Pastikan folder public/uploads/face-reference writable di server.',
            ],
        ];

        foreach ($uploads as $field => $upload) {
            $path = $this->storeUploadedImage($request, $field, $upload['directory'], $upload['prefix']);
            if ($path !== null) {
                $updates[$upload['column']] = $path;
            } elseif ($request->hasFile($field)) {
                $this->deletePendingUploads($updates);

                return back()->withErrors($upload['error']);
            }
        }

        if ($updates !== []) {
            $oldPaths = [];
            foreach (array_keys($updates) as $column) {
                $oldPaths[] = $user->{$column};
            }

            $user->forceFill($updates)->save();

            foreach ($oldPaths as $oldPath) {
                $this->deletePublicFile($oldPath);
            }
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

        try {
            $disk = Storage::disk('public_root');
            $disk->makeDirectory($directory);

            $file = $request->file($field);
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
            $filename = $prefix . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $path = $file->storeAs($directory, $filename, 'public_root');
        } catch (\Throwable) {
            return null;
        }

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

    /**
     * @param array<string, string> $paths
     */
    private function deletePendingUploads(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deletePublicFile($path);
        }
    }
}
