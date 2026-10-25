<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('dispensation_items', 'dispensation_id')) {
                $table->foreignId('dispensation_id')
                    ->nullable()
                    ->constrained('dispensations')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('dispensation_items', 'prescription_item_id')) {
                $table->foreignId('prescription_item_id')
                    ->nullable()
                    ->constrained('prescription_items')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('dispensation_items', 'inventory_id')) {
                $table->foreignId('inventory_id')
                    ->nullable()
                    ->constrained('inventories')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('dispensation_items', 'quantity')) {
                $table->unsignedInteger('quantity')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispensation_items', function (Blueprint $table) {
            // No eliminamos columnas ni datos durante un rollback.
        });
    }
};
