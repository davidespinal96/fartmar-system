<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_id')
                ->constrained('inventories')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('type', 30);
            $table->integer('quantity');
            $table->integer('balance_before');
            $table->integer('balance_after');

            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('observations')->nullable();

            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(['inventory_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
