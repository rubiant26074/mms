<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Send WhatsApp message via Fonnte Gateway.
     *
     * @param string $phone Raw phone number
     * @param string $message Message text
     * @param string|null $mediaUrl Optional media URL attachment
     * @return array [bool $success, string|null $error, string|null $response]
     */
    public function sendMessage(string $phone, string $message, ?string $mediaUrl = null): array
    {
        $token = \App\Models\CompanyProfile::query()->value('fonte_token') ?: config('services.fonnte.token');
        
        // Clean phone number: remove non-digit characters, replace leading '0' or '+62' with '62'
        $cleanedPhone = $this->cleanPhoneNumber($phone);
        
        if (empty($token)) {
            $error = 'Fonnte API Token tidak terkonfigurasi. Silakan isi Token WA Fonte di halaman Pengaturan Identitas Perusahaan.';
            $this->logMessage('failed', $cleanedPhone, $phone, $message, $mediaUrl, $error, null);
            return [false, $error, null];
        }

        try {
            $payload = [
                'target' => $cleanedPhone,
                'message' => $message,
                'countryCode' => '62',
            ];

            if ($mediaUrl) {
                $payload['url'] = $mediaUrl;
            }

            $response = Http::withHeaders([
                'Authorization' => $token
            ])->timeout(15)->post('https://api.fonnte.com/send', $payload);

            $body = $response->body();
            $data = $response->json();

            // Fonnte returns true or false in 'status' key
            $success = isset($data['status']) && $data['status'] === true;
            $error = $success ? null : ($data['reason'] ?? 'Gagal mengirim pesan via Fonnte.');

            $this->logMessage(
                $success ? 'success' : 'failed',
                $cleanedPhone,
                $phone,
                $message,
                $mediaUrl,
                $error,
                $body
            );

            return [$success, $error, $body];

        } catch (\Throwable $e) {
            $error = 'Exception: ' . $e->getMessage();
            $this->logMessage('failed', $cleanedPhone, $phone, $message, $mediaUrl, $error, null);
            Log::error('Fonnte send exception: ' . $e->getMessage());
            return [false, $error, null];
        }
    }

    private function cleanPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '62')) {
            // already in 62 format
        } else {
            // fallback
            $cleaned = '62' . $cleaned;
        }
        return $cleaned;
    }

    private function logMessage(
        string $status,
        string $phone,
        string $phoneRaw,
        string $message,
        ?string $mediaUrl,
        ?string $error,
        ?string $response
    ): void {
        try {
            DB::table('wa_message_logs')->insert([
                'status' => $status,
                'recipient_phone' => $phone,
                'recipient_phone_raw' => $phoneRaw,
                'message_text' => $message,
                'media_url' => $mediaUrl,
                'created_by' => auth()->id(),
                'error_message' => $error,
                'provider_response' => $response,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write wa_message_logs: ' . $e->getMessage());
        }
    }
}
