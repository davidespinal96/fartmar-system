<?php

namespace App\Services;

use App\Contracts\InventoryAssistantProvider;

/** Deterministic offline provider. It never receives database or patient data. */
class MockInventoryAssistantProvider implements InventoryAssistantProvider
{
    public function interpret(string $question): array
    {
        $text = mb_strtolower($question);
        if (preg_match('/(stock bajo|minimo|mínimo|por debajo)/u', $text)) {
            return ['intent' => 'low_stock'];
        }
        if (preg_match('/(vence|vencen|vencimiento|expira|expiran)/u', $text)) {
            preg_match('/(\d{1,3})\s*d[ií]as/u', $text, $match);

            return ['intent' => 'expiring', 'days' => isset($match[1]) ? min(365, (int) $match[1]) : 90];
        }
        if (preg_match('/(inventario|existencias|stock|lotes)/u', $text)) {
            return ['intent' => 'inventory_summary'];
        }

        return ['intent' => 'unsupported'];
    }
}
