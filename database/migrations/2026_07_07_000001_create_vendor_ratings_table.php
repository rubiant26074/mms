<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_ratings')) {
            return;
        }

        Schema::create('vendor_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->char('rating_period', 7);
            $table->decimal('lead_time_score', 5, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('price_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'rating_period'], 'uniq_vendor_period');
            $table->index('rating_period', 'idx_rating_period');
            $table->index('supplier_id', 'idx_supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ratings');
    }
};
