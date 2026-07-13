<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompanyController extends Controller
{
    public function edit(MmsContext $context): View
    {
        $company = $context->company();

        return view('admin.company.edit', [
            'companyData' => $company,
            'logoUrl' => $context->logoUrl($company),
            'themes' => $this->themes(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'website' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'running_text' => ['nullable', 'string'],
            'fonte_token' => ['nullable', 'string', 'max:255'],
            'ui_theme' => ['nullable', 'string', 'max:100'],
            'attendance_location_name' => ['nullable', 'string', 'max:150'],
            'attendance_latitude' => ['nullable', 'numeric'],
            'attendance_longitude' => ['nullable', 'numeric'],
            'attendance_radius_meters' => ['nullable', 'integer', 'min:1'],
            'logo_selected' => ['nullable', 'boolean'],
            'logo_base64' => ['nullable', 'string'],
            'logo' => ['nullable', 'file', 'max:2048'],
        ]);
        unset($data['logo_selected'], $data['logo_base64']);

        $company = CompanyProfile::query()->firstOrNew(['id' => 1]);

        if ($request->boolean('logo_selected') && $request->file('logo') === null && ! $request->filled('logo_base64')) {
            return back()->withInput()->withErrors([
                'logo' => $this->missingUploadMessage(),
            ]);
        }

        if ($request->file('logo') !== null || $request->filled('logo_base64')) {
            try {
                $logoPath = $this->storeLogo($request);
            } catch (ValidationException $e) {
                return back()->withInput()->withErrors($e->errors());
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors('Upload logo gagal: ' . $e->getMessage());
            }

            $this->deletePublicFile($company->logo_path);
            $data['logo_path'] = $logoPath;
        }

        $company->fill($data);
        $company->id = 1;
        $company->save();

        return redirect()->route('admin.company.edit')->with('success', 'Identitas Perusahaan berhasil diperbarui!');
    }

    public function logo(string $filename): BinaryFileResponse
    {
        if (! preg_match('/^company_logo_[A-Za-z0-9_\-]+\.(jpg|jpeg|png)$/i', $filename)) {
            abort(404);
        }

        $path = storage_path('app/company/' . $filename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function legacyLogo(string $filename): BinaryFileResponse
    {
        if (! preg_match('/^[A-Za-z0-9_\-]+\.(jpg|jpeg|png)$/i', $filename)) {
            abort(404);
        }

        foreach ($this->legacyLogoCandidates($filename) as $path) {
            if (is_file($path)) {
                return response()->file($path, [
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        abort(404);
    }

    /**
     * @return array<string, string>
     */
    private function themes(): array
    {
        $themes = ['original' => 'Original (Standar)'];
        foreach (glob(public_path('assets/css/theme-*.css')) ?: [] as $file) {
            $slug = strtolower(preg_replace('/^theme-|\.css$/', '', basename($file)));
            $themes[$slug] = ucwords(str_replace(['-', '_'], ' ', $slug));
        }

        return $themes;
    }

    private function storeLogo(Request $request): string
    {
        if ($request->filled('logo_base64')) {
            return $this->storeBase64Logo((string) $request->input('logo_base64'));
        }

        if (! $request->hasFile('logo')) {
            throw ValidationException::withMessages(['logo' => 'File logo tidak ditemukan pada request.']);
        }

        $file = $request->file('logo');
        if (! $file || ! $file->isValid()) {
            throw ValidationException::withMessages(['logo' => 'Upload logo tidak valid atau melebihi batas upload server.']);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            throw ValidationException::withMessages(['logo' => 'Format logo harus JPG atau PNG.']);
        }

        if (@getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['logo' => 'File yang dipilih bukan gambar yang valid.']);
        }

        $directory = $this->ensureLogoDirectory();

        $filename = 'company_logo_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $file->move($directory, $filename);

        return 'company-logo/' . $filename;
    }

    private function storeBase64Logo(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(png|jpe?g);base64,([A-Za-z0-9+\/=\r\n]+)$/i', $dataUrl, $matches)) {
            throw ValidationException::withMessages(['logo' => 'Data logo dari browser tidak valid. Pilih file JPG atau PNG.']);
        }

        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $binary = base64_decode(str_replace(["\r", "\n"], '', $matches[2]), true);
        if ($binary === false) {
            throw ValidationException::withMessages(['logo' => 'Data logo gagal dibaca oleh server.']);
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['logo' => 'Ukuran logo maksimal 2MB.']);
        }

        if (@getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages(['logo' => 'File yang dipilih bukan gambar yang valid.']);
        }

        $directory = $this->ensureLogoDirectory();
        $filename = 'company_logo_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

        if (@file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $binary) === false) {
            throw ValidationException::withMessages(['logo' => 'Upload logo gagal. Server tidak bisa menulis file ke storage/app/company.']);
        }

        return 'company-logo/' . $filename;
    }

    private function ensureLogoDirectory(): string
    {
        $directory = storage_path('app/company');
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw ValidationException::withMessages([
                'logo' => 'Upload logo gagal. Folder storage/app/company tidak bisa dibuat di server hosting.',
            ]);
        }
        if (! is_writable($directory)) {
            throw ValidationException::withMessages([
                'logo' => 'Upload logo gagal. Folder storage/app/company tidak writable. Set permission folder storage ke 775.',
            ]);
        }

        return $directory;
    }

    private function deletePublicFile(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $relativePath = ltrim($path, '/');
        if (str_starts_with($relativePath, 'company-logo/')) {
            $filename = basename($relativePath);
            $file = storage_path('app/company/' . $filename);
            if (is_file($file)) {
                @unlink($file);
            }

            return;
        }

        Storage::disk('public_root')->delete($relativePath);

        foreach ($this->publicRootCandidates() as $root) {
            $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function missingUploadMessage(): string
    {
        $uploadError = $_FILES['logo']['error'] ?? null;
        $errorLabel = is_int($uploadError) ? $this->uploadErrorLabel($uploadError) : 'file tidak masuk ke PHP';

        return sprintf(
            'Upload logo gagal terbaca oleh server (%s). file_uploads=%s, upload_max_filesize=%s, post_max_size=%s, content_length=%s. Jika file kecil tetap gagal, cek PHP Selector/cPanel: file_uploads harus On dan upload_tmp_dir harus writable.',
            $errorLabel,
            ini_get('file_uploads') ? 'On' : 'Off',
            ini_get('upload_max_filesize') ?: '-',
            ini_get('post_max_size') ?: '-',
            $_SERVER['CONTENT_LENGTH'] ?? '-'
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

    /**
     * @return array<int, string>
     */
    private function publicRootCandidates(): array
    {
        $paths = [
            $_SERVER['DOCUMENT_ROOT'] ?? null,
            public_path(),
            base_path('public_html'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . 'public_html',
        ];

        $roots = [];
        foreach ($paths as $path) {
            $path = $path ? realpath($path) : false;
            if ($path && is_dir($path)) {
                $roots[] = $path;
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * @return array<int, string>
     */
    private function legacyLogoCandidates(string $filename): array
    {
        $paths = [storage_path('app/company/' . $filename)];

        foreach ($this->publicRootCandidates() as $root) {
            $paths[] = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR . $filename;
        }

        return array_values(array_unique($paths));
    }
}
