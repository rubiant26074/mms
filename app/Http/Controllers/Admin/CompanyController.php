<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $company = CompanyProfile::query()->firstOrNew(['id' => 1]);

        if ($request->hasFile('logo')) {
            $logoPath = $this->storeLogo($request);
            if ($logoPath === null) {
                return back()->withInput()->withErrors('Upload logo gagal. Pastikan folder public/uploads/company writable di server.');
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

    private function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        try {
            $disk = Storage::disk('public_root');
            $directory = 'uploads/company';
            $disk->makeDirectory($directory);

            $file = $request->file('logo');
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
            $filename = 'company_logo_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $path = $file->storeAs($directory, $filename, 'public_root');

            return $path ? str_replace('\\', '/', $path) : null;
        } catch (\Throwable) {
            return null;
        }
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
