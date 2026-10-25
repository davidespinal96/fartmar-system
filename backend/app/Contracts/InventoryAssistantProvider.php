<?php

namespace App\Contracts;

interface InventoryAssistantProvider
{
    /** @return array{intent:string,days?:int} */
    public function interpret(string $question): array;
}
