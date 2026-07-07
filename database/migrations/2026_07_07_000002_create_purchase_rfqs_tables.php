<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_rfqs')) {
            Schema::create('purchase_rfqs', function (Blueprint $table): void {
                $table->id();
                $table->string('rfq_number', 40)->unique('uniq_rfq_number');
                $table->date('rfq_date');
                $table->date('due_date')->nullable();
                $table->enum('status', ['draft', 'sent', 'evaluated', 'closed', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('rfq_date', 'idx_rfq_date');
                $table->index('status', 'idx_rfq_status');
            });
        }

        if (! Schema::hasTable('purchase_rfq_quotes')) {
            Schema::create('purchase_rfq_quotes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('rfq_id');
                $table->unsignedBigInteger('item_id')->nullable();
                $table->string('item_name', 200);
                $table->text('specification')->nullable();
                $table->decimal('qty', 10, 4)->default(0);
                $table->string('unit', 30)->default('');
                $table->unsignedBigInteger('supplier_id');
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->integer('lead_time_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('rfq_id', 'idx_rfq');
                $table->index('supplier_id', 'idx_supplier');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_rfq_quotes');
        Schema::dropIfExists('purchase_rfqs');
    }
};
