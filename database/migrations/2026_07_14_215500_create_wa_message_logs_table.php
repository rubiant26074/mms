<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('wa_message_logs')) {
            Schema::create('wa_message_logs', function (Blueprint $table) {
                $table->id();
                $table->string('status', 20);
                $table->string('recipient_phone', 30);
                $table->string('recipient_phone_raw', 50)->nullable();
                $table->text('message_text');
                $table->text('media_url')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('error_message')->nullable();
                $table->text('provider_response')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_message_logs');
    }
};
