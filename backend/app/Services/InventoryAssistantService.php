<?php

namespace App\Services;

use App\Contracts\InventoryAssistantProvider;
use App\Models\Inventory;
use App\Models\Medication;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryAssistantService
{
    public function __construct(private InventoryAssistantProvider $provider) {}

    public function ask(string $question, int $userId): array
    {
        $intent = $this->provider->interpret(mb_substr($question, 0, 500));
        if (! in_array($intent['intent'] ?? null, ['expiring', 'low_stock', 'inventory_summary'], true)) {
            return ['answer' => 'Puedo consultar existencias, stock bajo y lotes próximos a vencer. Reformula la pregunta dentro de ese alcance.', 'results' => []];
        }

        /** @var Builder<Inventory> $query */
        $query = Inventory::query()->with(['warehouse:id,name', 'lot:id,medication_id,lot_number,expiration_date', 'lot.medication:id,name']);
        $days = (int) ($intent['days'] ?? 90);
        $normalizedQuestion = mb_strtolower(Str::ascii($question));
        $medication = Medication::query()->where('is_active', true)->get(['id', 'name', 'code', 'active_ingredient'])
            ->first(function (Medication $item) use ($normalizedQuestion): bool {
                foreach ([$item->name, $item->code, $item->active_ingredient] as $term) {
                    $needle = mb_strtolower(Str::ascii((string) $term));
                    if ($needle !== '' && str_contains($normalizedQuestion, $needle)) {
                        return true;
                    }
                }

                return false;
            });
        $warehouse = Warehouse::query()->where('is_active', true)->get(['id', 'name', 'code'])
            ->first(function (Warehouse $item) use ($normalizedQuestion): bool {
                foreach ([$item->name, $item->code] as $term) {
                    $needle = mb_strtolower(Str::ascii($term));
                    if ($needle !== '' && str_contains($normalizedQuestion, $needle)) {
                        return true;
                    }
                }

                return false;
            });
        if ($medication) {
            $query->whereHas('lot', fn ($q) => $q->where('medication_id', $medication->id));
        }
        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }
        if ($intent['intent'] === 'expiring') {
            $query->whereHas('lot', fn ($q) => $q->whereBetween('expiration_date', [today(), today()->addDays($days)]));
        } elseif ($intent['intent'] === 'low_stock') {
            $query->whereColumn('quantity', '<=', 'minimum_stock');
        } elseif ($intent['intent'] === 'inventory_summary') {
            $query->where('quantity', '>', 0);
        }
        DB::table('assistant_audits')->insert(['user_id' => $userId, 'intent' => $intent['intent'], 'created_at' => now(), 'updated_at' => now()]);
        $rows = $query->limit(100)->get()->map(fn ($item) => [
            'warehouse' => $item->warehouse?->name,
            'medication' => $item->lot?->medication?->name,
            'lot' => $item->lot?->lot_number,
            'expiration_date' => $item->lot?->expiration_date?->toDateString(),
            'quantity' => $item->quantity,
            'minimum_stock' => $item->minimum_stock,
        ])->all();

        return ['answer' => count($rows) ? 'Encontré '.count($rows).' existencias que coinciden con la consulta.' : 'No encontré existencias que coincidan con la consulta.', 'results' => $rows];
    }
}
