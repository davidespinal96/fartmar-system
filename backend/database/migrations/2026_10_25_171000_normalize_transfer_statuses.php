<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['PENDING' => 'SOLICITADO', 'APPROVED' => 'APROBADO', 'DISPATCHED' => 'EN_TRANSITO', 'RECEIVED' => 'RECIBIDO', 'PARTIALLY_RECEIVED' => 'RECIBIDO_PARCIAL', 'CANCELLED' => 'ANULADO'] as $from => $to) {
            DB::table('inventory_transfers')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        foreach (['SOLICITADO' => 'PENDING', 'APROBADO' => 'APPROVED', 'EN_TRANSITO' => 'DISPATCHED', 'RECIBIDO' => 'RECEIVED', 'RECIBIDO_PARCIAL' => 'PARTIALLY_RECEIVED', 'ANULADO' => 'CANCELLED'] as $from => $to) {
            DB::table('inventory_transfers')->where('status', $from)->update(['status' => $to]);
        }
    }
};
