<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prescription_id')
                ->constrained('prescriptions')
                ->restrictOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->foreignId('dispensed_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('idempotency_key', 100)->unique();

            $table->string('status', 20)->default('COMPLETED');

            $table->timestampTz('dispensed_at');

            $table->text('observations')->nullable();

            $table->timestamps();

            $table->index(['patient_id', 'dispensed_at']);
            $table->index(['prescription_id', 'dispensed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensations');
    }
};
