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
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prescription_id')
                ->constrained('prescriptions')
                ->restrictOnDelete();

            $table->foreignId('medication_id')
                ->constrained('medications')
                ->restrictOnDelete();

            $table->unsignedInteger('prescribed_quantity');
            $table->unsignedInteger('dispensed_quantity')->default(0);

            $table->string('dosage', 150)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->unsignedInteger('duration_days')->nullable();

            $table->timestamps();

            $table->unique(['prescription_id', 'medication_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
