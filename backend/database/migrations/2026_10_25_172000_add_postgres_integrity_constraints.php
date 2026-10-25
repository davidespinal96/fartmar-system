<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_quantity_nonnegative CHECK (quantity >= 0 AND minimum_stock >= 0)');
        DB::statement('ALTER TABLE prescription_items ADD CONSTRAINT prescription_quantities_valid CHECK (prescribed_quantity >= 0 AND dispensed_quantity >= 0 AND dispensed_quantity <= prescribed_quantity)');
        DB::statement('ALTER TABLE inventory_transfer_items ADD CONSTRAINT transfer_receipt_within_quantity CHECK (quantity >= 0 AND received_quantity >= 0 AND received_quantity <= quantity)');
        DB::statement("CREATE FUNCTION prevent_inventory_movement_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'inventory_movements is append-only'; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER inventory_movements_immutable BEFORE UPDATE OR DELETE ON inventory_movements FOR EACH ROW EXECUTE FUNCTION prevent_inventory_movement_mutation()');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS inventory_movements_immutable ON inventory_movements');
        DB::statement('DROP FUNCTION IF EXISTS prevent_inventory_movement_mutation()');
        DB::statement('ALTER TABLE inventory_transfer_items DROP CONSTRAINT IF EXISTS transfer_receipt_within_quantity');
        DB::statement('ALTER TABLE prescription_items DROP CONSTRAINT IF EXISTS prescription_quantities_valid');
        DB::statement('ALTER TABLE inventories DROP CONSTRAINT IF EXISTS inventories_quantity_nonnegative');
    }
};
