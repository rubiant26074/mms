<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backup.index');
    }

    public function download(): StreamedResponse
    {
        $database = config('database.connections.mysql.database');
        $filename = 'promindo_mms_backup_' . now()->format('Ymd_His') . '.sql';

        return response()->streamDownload(function () use ($database): void {
            echo "-- MMS Laravel Backup\n-- Database: `{$database}`\n-- Created: " . now()->toDateTimeString() . "\n\n";
            echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
            $tables = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);
            foreach ($tables as $table) {
                $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                $createSql = (array_values((array) $create)[1] ?? '');
                echo "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";
                DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        $data = (array) $row;
                        $cols = implode('`,`', array_keys($data));
                        $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : DB::getPdo()->quote((string) $v), array_values($data)));
                        echo "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$vals});\n";
                    }
                });
                echo "\n";
            }
            echo "SET FOREIGN_KEY_CHECKS=1;\n";
        }, $filename, ['Content-Type' => 'application/sql']);
    }

    public function restore(Request $request)
    {
        return back()->withErrors('Restore database belum diaktifkan di Laravel native untuk mencegah overwrite database aktif tanpa audit tambahan.');
    }
}
