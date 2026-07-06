<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $data['logo_path'] = str_replace('\\', '/', $request->file('logo')->store('uploads/company', 'public_root'));
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
}
