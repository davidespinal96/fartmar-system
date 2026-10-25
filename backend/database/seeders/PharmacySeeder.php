<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Lot;
use App\Models\Medication;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'code' => 'BOD-CALI',
                'name' => 'Bodega principal Cali',
                'address' => 'Cali, Valle del Cauca',
                'is_active' => true,
            ],
            [
                'code' => 'BOD-NORTE',
                'name' => 'Bodega norte',
                'address' => 'Zona norte de Cali',
                'is_active' => true,
            ],
            [
                'code' => 'BOD-SUR',
                'name' => 'Bodega sur',
                'address' => 'Zona sur de Cali',
                'is_active' => true,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse
            );
        }

        $medications = [
            [
                'code' => 'MED-001',
                'name' => 'Acetaminofén',
                'active_ingredient' => 'Acetaminofén',
                'presentation' => 'Tableta',
                'concentration' => '500 mg',
                'requires_prescription' => false,
                'is_controlled' => false,
                'is_active' => true,
            ],
            [
                'code' => 'MED-002',
                'name' => 'Amoxicilina',
                'active_ingredient' => 'Amoxicilina',
                'presentation' => 'Cápsula',
                'concentration' => '500 mg',
                'requires_prescription' => true,
                'is_controlled' => false,
                'is_active' => true,
            ],
            [
                'code' => 'MED-003',
                'name' => 'Ibuprofeno',
                'active_ingredient' => 'Ibuprofeno',
                'presentation' => 'Tableta',
                'concentration' => '400 mg',
                'requires_prescription' => false,
                'is_controlled' => false,
                'is_active' => true,
            ],
            [
                'code' => 'MED-004',
                'name' => 'Tramadol',
                'active_ingredient' => 'Tramadol',
                'presentation' => 'Cápsula',
                'concentration' => '50 mg',
                'requires_prescription' => true,
                'is_controlled' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MED-005',
                'name' => 'Metformina',
                'active_ingredient' => 'Metformina',
                'presentation' => 'Tableta',
                'concentration' => '850 mg',
                'requires_prescription' => true,
                'is_controlled' => false,
                'is_active' => true,
            ],
            [
                'code' => 'MED-006',
                'name' => 'Loratadina',
                'active_ingredient' => 'Loratadina',
                'presentation' => 'Tableta',
                'concentration' => '10 mg',
                'requires_prescription' => false,
                'is_controlled' => false,
                'is_active' => true,
            ],
        ];

        foreach ($medications as $medication) {
            Medication::updateOrCreate(
                ['code' => $medication['code']],
                $medication
            );
        }

        $lots = [
            [
                'medication_code' => 'MED-001',
                'lot_number' => 'LOT-ACE-001',
                'manufacturing_date' => '2026-01-01',
                'expiration_date' => '2026-11-15',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-001',
                'lot_number' => 'LOT-ACE-002',
                'manufacturing_date' => '2026-03-01',
                'expiration_date' => '2027-03-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-001',
                'lot_number' => 'LOT-ACE-003',
                'manufacturing_date' => '2026-04-01',
                'expiration_date' => '2027-06-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-002',
                'lot_number' => 'LOT-AMX-001',
                'manufacturing_date' => '2025-01-01',
                'expiration_date' => '2026-09-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-002',
                'lot_number' => 'LOT-AMX-002',
                'manufacturing_date' => '2026-02-01',
                'expiration_date' => '2026-11-10',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-002',
                'lot_number' => 'LOT-AMX-003',
                'manufacturing_date' => '2026-04-01',
                'expiration_date' => '2027-04-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-003',
                'lot_number' => 'LOT-IBU-001',
                'manufacturing_date' => '2026-01-01',
                'expiration_date' => '2026-11-20',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-003',
                'lot_number' => 'LOT-IBU-002',
                'manufacturing_date' => '2026-02-01',
                'expiration_date' => '2027-02-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-003',
                'lot_number' => 'LOT-IBU-003',
                'manufacturing_date' => '2026-03-01',
                'expiration_date' => '2027-05-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-004',
                'lot_number' => 'LOT-TRA-001',
                'manufacturing_date' => '2026-01-01',
                'expiration_date' => '2026-11-12',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-004',
                'lot_number' => 'LOT-TRA-002',
                'manufacturing_date' => '2026-02-01',
                'expiration_date' => '2027-02-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-004',
                'lot_number' => 'LOT-TRA-003',
                'manufacturing_date' => '2026-03-01',
                'expiration_date' => '2027-03-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-005',
                'lot_number' => 'LOT-MET-001',
                'manufacturing_date' => '2026-01-01',
                'expiration_date' => '2026-11-18',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-005',
                'lot_number' => 'LOT-MET-002',
                'manufacturing_date' => '2026-02-01',
                'expiration_date' => '2027-02-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-005',
                'lot_number' => 'LOT-MET-003',
                'manufacturing_date' => '2026-03-01',
                'expiration_date' => '2027-03-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-006',
                'lot_number' => 'LOT-LOR-001',
                'manufacturing_date' => '2026-01-01',
                'expiration_date' => '2026-11-25',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-006',
                'lot_number' => 'LOT-LOR-002',
                'manufacturing_date' => '2026-02-01',
                'expiration_date' => '2027-02-01',
                'is_active' => true,
            ],
            [
                'medication_code' => 'MED-006',
                'lot_number' => 'LOT-LOR-003',
                'manufacturing_date' => '2026-03-01',
                'expiration_date' => '2027-03-01',
                'is_active' => true,
            ],
        ];

        foreach ($lots as $lotData) {
            $medication = Medication::where(
                'code',
                $lotData['medication_code']
            )->firstOrFail();

            Lot::updateOrCreate(
                [
                    'medication_id' => $medication->id,
                    'lot_number' => $lotData['lot_number'],
                ],
                [
                    'manufacturing_date' => $lotData['manufacturing_date'],
                    'expiration_date' => $lotData['expiration_date'],
                    'is_active' => $lotData['is_active'],
                ]
            );
        }

        $warehouses = Warehouse::pluck('id', 'code');

        $inventories = [
            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-ACE-001', 'quantity' => 100, 'minimum_stock' => 20],
            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-ACE-002', 'quantity' => 150, 'minimum_stock' => 30],
            ['warehouse' => 'BOD-NORTE', 'lot' => 'LOT-ACE-003', 'quantity' => 80, 'minimum_stock' => 15],

            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-AMX-001', 'quantity' => 40, 'minimum_stock' => 10],
            ['warehouse' => 'BOD-NORTE', 'lot' => 'LOT-AMX-002', 'quantity' => 60, 'minimum_stock' => 15],
            ['warehouse' => 'BOD-SUR', 'lot' => 'LOT-AMX-003', 'quantity' => 90, 'minimum_stock' => 20],

            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-IBU-001', 'quantity' => 5, 'minimum_stock' => 10],
            ['warehouse' => 'BOD-NORTE', 'lot' => 'LOT-IBU-002', 'quantity' => 70, 'minimum_stock' => 15],
            ['warehouse' => 'BOD-SUR', 'lot' => 'LOT-IBU-003', 'quantity' => 100, 'minimum_stock' => 20],

            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-TRA-001', 'quantity' => 20, 'minimum_stock' => 5],
            ['warehouse' => 'BOD-NORTE', 'lot' => 'LOT-TRA-002', 'quantity' => 30, 'minimum_stock' => 5],
            ['warehouse' => 'BOD-SUR', 'lot' => 'LOT-TRA-003', 'quantity' => 25, 'minimum_stock' => 5],

            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-MET-001', 'quantity' => 50, 'minimum_stock' => 10],
            ['warehouse' => 'BOD-NORTE', 'lot' => 'LOT-MET-002', 'quantity' => 75, 'minimum_stock' => 15],
            ['warehouse' => 'BOD-SUR', 'lot' => 'LOT-MET-003', 'quantity' => 90, 'minimum_stock' => 20],

            ['warehouse' => 'BOD-CALI', 'lot' => 'LOT-LOR-001', 'quantity' => 40, 'minimum_stock' => 10],
            ['warehouse' => 'BOD-NORTE', 'lot' => 'LOT-LOR-002', 'quantity' => 60, 'minimum_stock' => 15],
            ['warehouse' => 'BOD-SUR', 'lot' => 'LOT-LOR-003', 'quantity' => 80, 'minimum_stock' => 20],
        ];

        foreach ($inventories as $item) {
            $warehouseId = $warehouses[$item['warehouse']];

            $lot = Lot::where('lot_number', $item['lot'])->firstOrFail();

            $inventory = Inventory::firstOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'lot_id' => $lot->id,
                ],
                [
                    'quantity' => $item['quantity'],
                    'minimum_stock' => $item['minimum_stock'],
                ]
            );

            if ($inventory->wasRecentlyCreated || ! $inventory->movements()->exists()) {
                $quantity = (int) $inventory->quantity;
                InventoryMovement::create([
                    'inventory_id' => $inventory->id,
                    'user_id' => User::where('role', 'admin')->value('id'),
                    'type' => 'RECEIPT',
                    'quantity' => $quantity,
                    'balance_before' => 0,
                    'balance_after' => $quantity,
                    'reference_type' => 'OPENING_BALANCE',
                    'reference_id' => $inventory->id,
                    'idempotency_key' => 'opening-balance-'.$inventory->id,
                    'observations' => 'Saldo inicial sintético de demostración.',
                    'occurred_at' => now(),
                ]);
            }
        }
    }
}
