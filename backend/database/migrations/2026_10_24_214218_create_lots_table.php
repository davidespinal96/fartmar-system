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
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medication_id')
                ->constrained('medications')
                ->restrictOnDelete();

            $table->string('lot_number', 100);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiration_date');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['medication_id', 'lot_number']);
            $table->index(['expiration_date', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
