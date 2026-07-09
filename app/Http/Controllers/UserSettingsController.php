<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'signature_selected' => ['nullable', 'boolean'],
            'avatar_selected' => ['nullable', 'boolean'],
            'face_reference_selected' => ['nullable', 'boolean'],
            'signature_base64' => ['nullable', 'string'],
            'avatar_base64' => ['nullable', 'string'],
            'face_reference_base64' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'face_reference_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
        ]);

        $user = $this->currentUser();
        $updates = [];

        try {
            $signaturePath = $this->storeUploadedImage($request, 'signature', 'signature_file', 'signature_base64', 'sig_' . $user->id, 4096);
            $avatarPath = $this->storeUploadedImage($request, 'avatar', 'avatar_file', 'avatar_base64', 'ava_' . $user->id, 4096);
            $faceReferencePath = $this->storeUploadedImage($request, 'face_reference', 'face_reference_file', 'face_reference_base64', 'face_' . $user->id, 8192);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors('Upload profil gagal: ' . $e->getMessage());
        }

        if ($signaturePath !== null) {
            $updates['signature_path'] = $signaturePath;
        }

        if ($avatarPath !== null) {
            $updates['avatar_path'] = $avatarPath;
        }

        if ($faceReferencePath !== null) {
            $updates['face_reference_path'] = $faceReferencePath;
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

    public function media(string $type, string $filename): BinaryFileResponse
    {
        $directory = $this->mediaDirectory($type);
        if ($directory === null || ! $this->isSafeImageFilename($filename)) {
            abort(404);
        }

        $path = storage_path('app/user-media/' . $directory . '/' . $filename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function legacyMedia(string $directory, string $filename): BinaryFileResponse
    {
        if (! in_array($directory, ['signatures', 'avatars', 'face-reference'], true) || ! $this->isSafeImageFilename($filename)) {
            abort(404);
        }

        foreach ($this->legacyMediaCandidates($directory, $filename) as $path) {
            if (is_file($path)) {
                return response()->file($path, [
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        abort(404);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function storeUploadedImage(Request $request, string $type, string $field, string $base64Field, string $prefix, int $maxKb): ?string
    {
        if ($request->filled($base64Field)) {
            return $this->storeBase64Image($request->string($base64Field)->toString(), $type, $prefix, $maxKb);
        }

        if (! $request->hasFile($field)) {
            if ($request->boolean($type . '_selected')) {
                throw ValidationException::withMessages([
                    $field => $this->missingUploadMessage($field),
                ]);
            }

            return null;
        }

        $file = $request->file($field);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true) || @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([$field => 'File harus berupa gambar JPG atau PNG yang valid.']);
        }

        $directory = $this->ensureMediaDirectory($type);
        $filename = $prefix . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $file->move($directory, $filename);

        return 'user-media/' . $type . '/' . $filename;
    }

    private function storeBase64Image(string $dataUrl, string $type, string $prefix, int $maxKb): string
    {
        if (! preg_match('/^data:image\/(png|jpe?g);base64,([A-Za-z0-9+\/=\r\n]+)$/i', $dataUrl, $matches)) {
            throw ValidationException::withMessages([$type . '_file' => 'Data gambar dari browser tidak valid. Pilih file JPG atau PNG.']);
        }

        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $binary = base64_decode(str_replace(["\r", "\n"], '', $matches[2]), true);
        if ($binary === false || strlen($binary) > $maxKb * 1024 || @getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages([$type . '_file' => 'File harus berupa gambar JPG/PNG valid dan maksimal ' . round($maxKb / 1024, 1) . 'MB.']);
        }

        $directory = $this->ensureMediaDirectory($type);
        $filename = $prefix . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (@file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $binary) === false) {
            throw ValidationException::withMessages([$type . '_file' => 'Server tidak bisa menulis file ke storage/app/user-media.']);
        }

        return 'user-media/' . $type . '/' . $filename;
    }

    private function deletePublicFile(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $relativePath = ltrim($path, '/');
        if (str_starts_with($relativePath, 'user-media/')) {
            $parts = explode('/', $relativePath, 3);
            if (count($parts) === 3) {
                $file = storage_path('app/user-media/' . $parts[1] . '/' . basename($parts[2]));
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            return;
        }

        Storage::disk('public_root')->delete($relativePath);
    }

    private function ensureMediaDirectory(string $type): string
    {
        $directory = storage_path('app/user-media/' . $type);
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw ValidationException::withMessages([$type . '_file' => 'Folder storage/app/user-media/' . $type . ' tidak bisa dibuat di server.']);
        }
        if (! is_writable($directory)) {
            throw ValidationException::withMessages([$type . '_file' => 'Folder storage/app/user-media/' . $type . ' tidak writable. Set permission storage ke 775.']);
        }

        return $directory;
    }

    private function mediaDirectory(string $type): ?string
    {
        return in_array($type, ['signature', 'avatar', 'face_reference'], true) ? $type : null;
    }

    private function isSafeImageFilename(string $filename): bool
    {
        return preg_match('/^[A-Za-z0-9_\-]+\.(jpg|jpeg|png)$/i', $filename) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function legacyMediaCandidates(string $directory, string $filename): array
    {
        $paths = [storage_path('app/user-media/' . $directory . '/' . $filename)];
        $publicRoots = [
            $_SERVER['DOCUMENT_ROOT'] ?? null,
            public_path(),
            base_path('public_html'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . 'public_html',
        ];

        foreach ($publicRoots as $root) {
            $root = $root ? realpath($root) : false;
            if ($root && is_dir($root)) {
                $paths[] = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $filename;
            }
        }

        return array_values(array_unique($paths));
    }

    private function missingUploadMessage(string $field): string
    {
        $uploadError = $_FILES[$field]['error'] ?? null;
        $errorLabel = is_int($uploadError) ? $this->uploadErrorLabel($uploadError) : 'file tidak masuk ke PHP';

        return sprintf(
            'Upload gambar gagal terbaca oleh server (%s). file_uploads=%s, upload_max_filesize=%s, post_max_size=%s. Hosting ini membutuhkan fallback browser atau file_uploads harus On.',
            $errorLabel,
            ini_get('file_uploads') ? 'On' : 'Off',
            ini_get('upload_max_filesize') ?: '-',
            ini_get('post_max_size') ?: '-'
        );
    }

    private function uploadErrorLabel(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'file melebihi upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'file melebihi batas form',
            UPLOAD_ERR_PARTIAL => 'upload terputus/partial',
            UPLOAD_ERR_NO_FILE => 'server menerima request tanpa file',
            UPLOAD_ERR_NO_TMP_DIR => 'folder temporary upload tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'server gagal menulis file temporary',
            UPLOAD_ERR_EXTENSION => 'upload diblokir extension PHP',
            default => 'error upload tidak dikenal: ' . $error,
        };
    }

}
