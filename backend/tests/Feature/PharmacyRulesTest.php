<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DispensationService;
use App\Services\InventoryTransferService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PharmacyRulesTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->startOfDay());
        $this->seed();
    }

    public function test_dispensing_allocates_stock_by_fefo_across_multiple_lots(): void
    {
        $prescription = Prescription::where('prescription_number', 'RX-DEMO-2')->firstOrFail();
        $line = $prescription->items()->firstOrFail();
        $line->update(['prescribed_quantity' => 150]);
        $warehouse = Warehouse::where('code', 'BOD-CALI')->firstOrFail();
        $user = User::where('email', 'auxiliar@fartmar.test')->firstOrFail();

        $result = app(DispensationService::class)->dispense([
            'prescription_id' => $prescription->id, 'warehouse_id' => $warehouse->id,
            'idempotency_key' => 'test-fefo-1', 'items' => [['prescription_item_id' => $line->id, 'quantity' => 110]],
        ], $user->id);

        $allocations = $result->items()->with('inventory.lot')->get();
        $this->assertCount(2, $allocations);
        $this->assertSame('LOT-ACE-001', $allocations[0]->inventory->lot->lot_number);
        $this->assertSame(100, $allocations[0]->quantity);
        $this->assertSame('LOT-ACE-002', $allocations[1]->inventory->lot->lot_number);
        $this->assertSame(10, $allocations[1]->quantity);
    }

    public function test_transfer_partial_receipt_keeps_a_discrepancy_until_completed(): void
    {
        $source = Warehouse::where('code', 'BOD-CALI')->firstOrFail();
        $destination = Warehouse::where('code', 'BOD-SUR')->firstOrFail();
        $inventory = Inventory::with('lot')->where('warehouse_id', $source->id)->whereHas('lot', fn ($q) => $q->where('lot_number', 'LOT-ACE-002'))->firstOrFail();
        $requester = User::where('email', 'auxiliar@fartmar.test')->firstOrFail();
        $regent = User::where('email', 'regente@fartmar.test')->firstOrFail();
        $service = app(InventoryTransferService::class);
        $transfer = $service->create([
            'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id,
            'idempotency_key' => 'test-transfer-partial', 'items' => [['inventory_id' => $inventory->id, 'quantity' => 10]],
        ], $requester->id);
        $this->assertSame('BORRADOR', $transfer->status);
        $this->assertSame('SOLICITADO', $service->submit($transfer->id, $requester->id)->status);
        $this->assertSame('APROBADO', $service->approve($transfer->id, $regent->id)->status);
        $this->assertSame('EN_TRANSITO', $service->dispatch($transfer->id, $requester->id)->status);
        $itemId = $transfer->items()->firstOrFail()->id;

        $partial = $service->receive($transfer->id, $requester->id, [[
            'item_id' => $itemId, 'received_quantity' => 4, 'discrepancy_reason' => 'Faltan seis unidades en el bulto.',
        ]]);
        $this->assertSame('RECIBIDO_PARCIAL', $partial->status);
        $this->assertSame(4, (int) $partial->items()->first()->received_quantity);

        $complete = $service->receive($transfer->id, $requester->id, [[
            'item_id' => $itemId, 'received_quantity' => 6,
        ]]);
        $this->assertSame('RECIBIDO', $complete->status);
        $this->assertSame(10, (int) $complete->items()->first()->received_quantity);
    }

    public function test_retries_cannot_oversell_the_last_unit(): void
    {
        $prescription = Prescription::where('prescription_number', 'RX-DEMO-2')->firstOrFail();
        $line = $prescription->items()->firstOrFail();
        $warehouse = Warehouse::where('code', 'BOD-CALI')->firstOrFail();
        $inventory = Inventory::with('lot')->where('warehouse_id', $warehouse->id)->whereHas('lot', fn ($q) => $q->where('lot_number', 'LOT-ACE-001'))->firstOrFail();
        Inventory::where('warehouse_id', $warehouse->id)
            ->whereHas('lot', fn ($query) => $query->where('medication_id', $inventory->lot->medication_id))
            ->update(['quantity' => 0]);
        $inventory->update(['quantity' => 1]);
        $user = User::where('email', 'auxiliar@fartmar.test')->firstOrFail();
        $service = app(DispensationService::class);
        $payload = ['prescription_id' => $prescription->id, 'warehouse_id' => $warehouse->id, 'items' => [['prescription_item_id' => $line->id, 'quantity' => 1]]];

        $service->dispense($payload + ['idempotency_key' => 'last-unit-first'], $user->id);
        try {
            $service->dispense($payload + ['idempotency_key' => 'last-unit-retry'], $user->id);
            $this->fail('The second request must fail after the final unit has been consumed.');
        } catch (ValidationException) {
            $this->assertSame(0, (int) $inventory->fresh()->quantity);
        }
    }

    public function test_concurrent_requests_cannot_both_consume_the_last_unit_on_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Esta prueba requiere PostgreSQL y pcntl; CI la ejecuta con PostgreSQL.');
        }

        $prescription = Prescription::where('prescription_number', 'RX-DEMO-2')->firstOrFail();
        $line = $prescription->items()->firstOrFail();
        $warehouse = Warehouse::where('code', 'BOD-CALI')->firstOrFail();
        $inventory = Inventory::with('lot')->where('warehouse_id', $warehouse->id)->whereHas('lot', fn ($q) => $q->where('lot_number', 'LOT-ACE-001'))->firstOrFail();
        Inventory::where('warehouse_id', $warehouse->id)
            ->whereHas('lot', fn ($query) => $query->where('medication_id', $inventory->lot->medication_id))
            ->update(['quantity' => 0]);
        $inventory->update(['quantity' => 1]);
        $user = User::where('email', 'auxiliar@fartmar.test')->firstOrFail();
        $payload = [
            'prescription_id' => $prescription->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['prescription_item_id' => $line->id, 'quantity' => 1]],
        ];
        DB::disconnect();
        $barrier = sys_get_temp_dir().'/fartmar-barrier-'.bin2hex(random_bytes(6));
        $resultPaths = [$barrier.'.one', $barrier.'.two'];
        $pids = [];

        foreach (['concurrent-one', 'concurrent-two'] as $index => $key) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('No fue posible iniciar el proceso concurrente.');
            }
            if ($pid === 0) {
                DB::purge();
                $deadline = microtime(true) + 10;
                while (! file_exists($barrier) && microtime(true) < $deadline) {
                    usleep(1000);
                }
                try {
                    app(DispensationService::class)->dispense($payload + ['idempotency_key' => $key], $user->id);
                    file_put_contents($resultPaths[$index], 'success');
                } catch (\Throwable) {
                    file_put_contents($resultPaths[$index], 'rejected');
                }
                DB::disconnect();
                exit(0);
            }
            $pids[] = $pid;
        }

        file_put_contents($barrier, 'start');
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::purge();

        $outcomes = array_map(fn ($path) => file_get_contents($path), $resultPaths);
        @unlink($barrier);
        foreach ($resultPaths as $path) {
            @unlink($path);
        }
        $this->assertSame(1, count(array_filter($outcomes, fn ($outcome) => $outcome === 'success')));
        $this->assertSame(1, count(array_filter($outcomes, fn ($outcome) => $outcome === 'rejected')));
        $this->assertSame(0, (int) $inventory->fresh()->quantity);
        $this->assertSame(1, $prescription->dispensations()->count());
    }

    public function test_controlled_medicine_requires_one_use_regent_approval_by_another_user(): void
    {
        $regent = User::where('email', 'regente@fartmar.test')->firstOrFail();
        $auxiliary = User::where('email', 'auxiliar@fartmar.test')->firstOrFail();
        $line = Prescription::where('prescription_number', 'RX-DEMO-1')->firstOrFail()->items()->firstOrFail();
        $warehouse = Warehouse::where('code', 'BOD-CALI')->firstOrFail();

        $this->actingAs($regent, 'sanctum')->postJson('/api/controlled-authorizations', [
            'prescription_item_id' => $line->id, 'warehouse_id' => $warehouse->id,
            'quantity' => 2, 'authorization_key' => 'controlled-approval-test', 'reason' => 'Validación de prueba técnica.',
        ])->assertCreated()->assertJsonPath('data.status', 'AUTHORIZED');
        $authorizationId = DB::table('controlled_medication_authorizations')
            ->where('authorization_key', 'controlled-approval-test')->value('id');

        $payload = [
            'prescription_id' => $line->prescription_id, 'warehouse_id' => $warehouse->id,
            'idempotency_key' => 'controlled-dispensation-test',
            'items' => [['prescription_item_id' => $line->id, 'quantity' => 2, 'controlled_authorization_id' => $authorizationId]],
        ];
        $this->actingAs($auxiliary, 'sanctum')->postJson('/api/dispensations', $payload)->assertCreated();
        $this->assertDatabaseHas('controlled_medication_authorizations', ['id' => $authorizationId, 'status' => 'USED']);
    }

    public function test_auditor_sees_masked_patient_data_and_access_is_logged(): void
    {
        $auditor = User::where('email', 'auditor@fartmar.test')->firstOrFail();
        $response = $this->actingAs($auditor, 'sanctum')->getJson('/api/patients?search=900000001')->assertOk();
        $response->assertJsonPath('data.0.document_number', '••••••01');
        $response->assertJsonMissingPath('data.0.first_name');
        $patientId = $response->json('data.0.id');
        $this->assertDatabaseHas('patient_access_logs', [
            'user_id' => $auditor->id, 'patient_id' => $patientId, 'action' => 'SEARCH_RESULT',
        ]);
    }
}
