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
            'logo' => ['nullable', 'file', 'max:2048'],
        ]);

        $company = CompanyProfile::query()->firstOrNew(['id' => 1]);

        if ($request->file('logo') !== null) {
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

        $directory = 'uploads/company';
        $targetRoot = $this->writablePublicRoot($directory);
        if ($targetRoot === null) {
            throw ValidationException::withMessages([
                'logo' => 'Upload logo gagal. Folder uploads/company tidak writable pada document root hosting. Buat folder tersebut dan set permission 755/775.',
            ]);
        }

        $filename = 'company_logo_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $file->move($targetRoot . DIRECTORY_SEPARATOR . $directory, $filename);

        return $directory . '/' . $filename;
    }

    private function deletePublicFile(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $relativePath = ltrim($path, '/');
        Storage::disk('public_root')->delete($relativePath);

        foreach ($this->publicRootCandidates() as $root) {
            $file = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function writablePublicRoot(string $directory): ?string
    {
        foreach ($this->publicRootCandidates() as $root) {
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
            if (! is_dir($target) && ! @mkdir($target, 0775, true) && ! is_dir($target)) {
                continue;
            }

            $probe = $target . DIRECTORY_SEPARATOR . '.write-test-' . bin2hex(random_bytes(4));
            if (@file_put_contents($probe, 'ok') !== false) {
                @unlink($probe);

                return $root;
            }
        }

        return null;
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
}
