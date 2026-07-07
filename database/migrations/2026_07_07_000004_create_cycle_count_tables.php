<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cycle_count_sessions')) {
            Schema::create('cycle_count_sessions', function (Blueprint $table): void {
                $table->id();
                $table->string('session_number', 40)->unique();
                $table->date('count_date')->index();
                $table->string('count_area', 120)->nullable();
                $table->enum('status', ['draft', 'posted'])->default('draft')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->foreignId('posted_by')->nullable();
                $table->timestamp('posted_at')->nullable();
            });
        }

        if (! Schema::hasTable('cycle_count_session_items')) {
            Schema::create('cycle_count_session_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('session_id')->index();
                $table->foreignId('item_id')->index();
                $table->decimal('system_qty', 18, 4)->default(0);
                $table->decimal('counted_qty', 18, 4)->default(0);
                $table->decimal('variance_qty', 18, 4)->default(0);
                $table->string('reason', 160)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['session_id', 'item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_count_session_items');
        Schema::dropIfExists('cycle_count_sessions');
    }
};
