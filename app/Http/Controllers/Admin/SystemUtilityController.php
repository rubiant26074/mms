<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SystemUtilityController extends Controller
{
    private array $allowedCommands = [
        'migrate' => [
            'name' => 'Database Migration (migrate)',
            'desc' => 'Menjalankan semua file migrasi database baru yang belum dieksekusi.',
            'cmd' => 'migrate',
            'params' => ['--force' => true],
            'danger' => false,
        ],
        'migrate:rollback' => [
            'name' => 'Rollback Terakhir (migrate:rollback)',
            'desc' => 'Membatalkan batch migrasi database terakhir yang dilakukan.',
            'cmd' => 'migrate:rollback',
            'params' => ['--force' => true],
            'danger' => true,
        ],
        'db:seed' => [
            'name' => 'Seeding Database (db:seed)',
            'desc' => 'Mengisi tabel database dengan data awal/seeder bawaan.',
            'cmd' => 'db:seed',
            'params' => ['--force' => true],
            'danger' => false,
        ],
        'optimize:clear' => [
            'name' => 'Clear All Cache (optimize:clear)',
            'desc' => 'Membersihkan seluruh cache: aplikasi, konfigurasi, rute, dan blade view sekaligus.',
            'cmd' => 'optimize:clear',
            'params' => [],
            'danger' => false,
        ],
        'cache:clear' => [
            'name' => 'Clear App Cache (cache:clear)',
            'desc' => 'Membersihkan cache data internal aplikasi.',
            'cmd' => 'cache:clear',
            'params' => [],
            'danger' => false,
        ],
        'view:clear' => [
            'name' => 'Clear View Cache (view:clear)',
            'desc' => 'Membersihkan cache kompilasi file tampilan Blade.',
            'cmd' => 'view:clear',
            'params' => [],
            'danger' => false,
        ],
        'config:clear' => [
            'name' => 'Clear Config Cache (config:clear)',
            'desc' => 'Membersihkan file cache konfigurasi aplikasi.',
            'cmd' => 'config:clear',
            'params' => [],
            'danger' => false,
        ],
        'route:clear' => [
            'name' => 'Clear Route Cache (route:clear)',
            'desc' => 'Membersihkan cache kompilasi rute aplikasi.',
            'cmd' => 'route:clear',
            'params' => [],
            'danger' => false,
        ],
        'optimize' => [
            'name' => 'Optimize System (optimize)',
            'desc' => 'Membuat cache baru untuk konfigurasi dan rute agar mempercepat load aplikasi.',
            'cmd' => 'optimize',
            'params' => [],
            'danger' => false,
        ],
        'storage:link' => [
            'name' => 'Storage Symlink (storage:link)',
            'desc' => 'Membuat symbolic link dari folder storage/app/public ke folder public/storage agar file dapat diakses publik.',
            'cmd' => 'storage:link',
            'params' => [],
            'danger' => false,
        ],
    ];

    public function index(): View
    {
        return view('admin.system.index', [
            'commands' => $this->allowedCommands,
        ]);
    }

    public function runCommand(Request $request): RedirectResponse
    {
        $request->validate([
            'command' => ['required', 'string'],
        ]);

        $key = $request->input('command');

        if (! array_key_exists($key, $this->allowedCommands)) {
            return back()->withErrors('Perintah tidak valid atau tidak diperbolehkan demi keamanan.');
        }

        $commandConfig = $this->allowedCommands[$key];

        try {
            $exitCode = Artisan::call($commandConfig['cmd'], $commandConfig['params']);
            $output = Artisan::output();

            if ($exitCode === 0) {
                return back()->with([
                    'success' => "Perintah 'php artisan {$commandConfig['cmd']}' berhasil dijalankan.",
                    'cmd_output' => $output ?: 'Perintah berhasil diselesaikan tanpa output teks.',
                    'last_cmd' => $commandConfig['cmd'],
                ]);
            } else {
                return back()->withErrors([
                    'error' => "Perintah gagal dengan exit code: {$exitCode}.",
                    'cmd_output' => $output,
                ])->withInput();
            }
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => "Terjadi kesalahan saat mengeksekusi perintah: " . $e->getMessage(),
                'cmd_output' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            ])->withInput();
        }
    }
}
