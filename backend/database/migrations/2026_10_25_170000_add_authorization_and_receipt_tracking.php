<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_medication_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->string('authorization_key', 100)->unique();
            $table->string('status', 20)->default('AUTHORIZED');
            $table->text('reason')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('dispensation_items', function (Blueprint $table) {
            $table->foreignId('controlled_authorization_id')->nullable()
                ->constrained('controlled_medication_authorizations')->restrictOnDelete();
        });

        Schema::table('inventory_transfer_items', function (Blueprint $table) {
            $table->unsignedInteger('received_quantity')->default(0)->after('quantity');
            $table->text('discrepancy_reason')->nullable();
        });

        Schema::table('inventory_transfers', function (Blueprint $table) {
            $table->text('discrepancy_observations')->nullable();
        });

        Schema::create('patient_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('action', 40);
            $table->timestampTz('accessed_at');
            $table->index(['patient_id', 'accessed_at']);
            $table->index(['user_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_access_logs');
        Schema::table('inventory_transfers', fn (Blueprint $table) => $table->dropColumn('discrepancy_observations'));
        Schema::table('inventory_transfer_items', fn (Blueprint $table) => $table->dropColumn(['received_quantity', 'discrepancy_reason']));
        Schema::table('dispensation_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('controlled_authorization_id'));
        Schema::dropIfExists('controlled_medication_authorizations');
    }
};
