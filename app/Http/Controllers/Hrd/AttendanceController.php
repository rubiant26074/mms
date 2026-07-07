<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompanyProfile;
use App\Models\User;
use App\Services\MmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, MmsContext $context): View
    {
        $this->ensureSchema();
        $user = auth()->user()->loadMissing('role.permissions');
        $context->syncLegacySession($user);

        $today = now()->toDateString();
        $filterDate = trim((string) $request->query('date', $today));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
            $filterDate = $today;
        }

        $company = CompanyProfile::query()->find(1) ?? new CompanyProfile();
        $geoRequired = $company->attendance_latitude !== null
            && $company->attendance_longitude !== null
            && (int) $company->attendance_radius_meters > 0;

        $todayAttendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        $isHrd = $user->hasPermission('hrd_attendance_manage');
        $records = Attendance::query()
            ->with(['user.role'])
            ->when($isHrd,
                fn ($query) => $query->whereDate('date', $filterDate)->orderBy('clock_in'),
                fn ($query) => $query->where('user_id', $user->id)->latest('date')->limit(30)
            )
            ->get();

        return view('hrd.attendance.index', [
            'company' => $company,
            'currentUser' => $user,
            'todayAttendance' => $todayAttendance,
            'records' => $records,
            'today' => $today,
            'filterDate' => $filterDate,
            'isHrd' => $isHrd,
            'geoRequired' => $geoRequired,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSchema();
        /** @var User $user */
        $user = auth()->user();
        $today = now()->toDateString();
        $now = now()->format('H:i:s');

        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'selfie_in' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'selfie_out' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        if (trim((string) $user->face_reference_path) === '') {
            return back()->withErrors('Wajah belum didaftarkan. Silakan masuk ke User Setting lalu registrasikan wajah terlebih dahulu.');
        }

        $company = CompanyProfile::query()->find(1) ?? new CompanyProfile();
        $distance = $this->distanceMeters(
            (float) $data['latitude'],
            (float) $data['longitude'],
            $company->attendance_latitude !== null ? (float) $company->attendance_latitude : null,
            $company->attendance_longitude !== null ? (float) $company->attendance_longitude : null,
        );

        $geoRequired = $company->attendance_latitude !== null
            && $company->attendance_longitude !== null
            && (int) $company->attendance_radius_meters > 0;

        if ($geoRequired && ($distance === null || $distance > (int) $company->attendance_radius_meters)) {
            $distanceLabel = $distance !== null ? number_format($distance, 1, ',', '.') . ' m' : 'tidak diketahui';
            $officeLabel = trim((string) $company->attendance_location_name) !== '' ? $company->attendance_location_name : 'titik absensi admin';

            return back()->withErrors("Lokasi Anda di luar radius absensi. Jarak saat ini {$distanceLabel} dari {$officeLabel}.");
        }

        $field = $data['type'] === 'in' ? 'selfie_in' : 'selfie_out';
        if (! $request->hasFile($field)) {
            return back()->withErrors('Foto selfie wajib diambil dari kamera depan.');
        }

        $photoPath = $this->storePhoto($request, $field, 'att_' . $data['type'] . '_' . $user->id);

        if ($data['type'] === 'in') {
            $exists = Attendance::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $today)
                ->exists();

            if ($exists) {
                return back()->withErrors('Anda sudah melakukan absen masuk hari ini.');
            }

            Attendance::query()->create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in' => $now,
                'clock_in_photo' => $photoPath,
                'clock_in_latitude' => $data['latitude'],
                'clock_in_longitude' => $data['longitude'],
                'clock_in_distance_meters' => $distance,
                'status' => $now > '08:15:00' ? 'late' : 'present',
                'attendance_method' => 'selfie_geotag',
            ]);

            return back()->with('success', "Berhasil absen masuk pada jam {$now}.");
        }

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendance) {
            return back()->withErrors('Absen pulang tidak bisa dilakukan sebelum absen masuk.');
        }
        if (trim((string) $attendance->clock_out) !== '') {
            return back()->withErrors('Anda sudah melakukan absen pulang hari ini.');
        }

        $attendance->update([
            'clock_out' => $now,
            'clock_out_photo' => $photoPath,
            'clock_out_latitude' => $data['latitude'],
            'clock_out_longitude' => $data['longitude'],
            'clock_out_distance_meters' => $distance,
            'attendance_method' => 'selfie_geotag',
        ]);

        return back()->with('success', "Berhasil absen pulang pada jam {$now}.");
    }

    private function ensureSchema(): void
    {
        $statements = [
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS face_reference_path VARCHAR(255) NULL AFTER avatar_path",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_photo VARCHAR(255) NULL AFTER clock_in",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_photo VARCHAR(255) NULL AFTER clock_out",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_latitude DECIMAL(10,7) NULL AFTER clock_in_photo",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_longitude DECIMAL(10,7) NULL AFTER clock_in_latitude",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_latitude DECIMAL(10,7) NULL AFTER clock_out_photo",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_longitude DECIMAL(10,7) NULL AFTER clock_out_latitude",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_in_distance_meters DECIMAL(10,2) NULL AFTER clock_in_longitude",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS clock_out_distance_meters DECIMAL(10,2) NULL AFTER clock_out_longitude",
            "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS attendance_method VARCHAR(30) NULL AFTER notes",
            "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_location_name VARCHAR(150) NULL AFTER ui_theme",
            "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_latitude DECIMAL(10,7) NULL AFTER attendance_location_name",
            "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_longitude DECIMAL(10,7) NULL AFTER attendance_latitude",
            "ALTER TABLE company_profile ADD COLUMN IF NOT EXISTS attendance_radius_meters INT NULL AFTER attendance_longitude",
        ];

        foreach ($statements as $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable) {
                // ignore legacy compatibility DDL issues
            }
        }
    }

    private function storePhoto(Request $request, string $field, string $prefix): string
    {
        $file = $request->file($field);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = $prefix . '_' . now()->format('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $file->storeAs('uploads/attendance', $filename, 'public_root');

        return str_replace('\\', '/', $path);
    }

    private function distanceMeters(float $lat1, float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat2 === null || $lng2 === null) {
            return null;
        }

        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth * $c;
    }
}
