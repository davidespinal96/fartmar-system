<?php

use App\Services\MockInventoryAssistantProvider;

require __DIR__.'/../vendor/autoload.php';

$provider = new MockInventoryAssistantProvider;
$cases = json_decode(file_get_contents(__DIR__.'/assistant_eval_cases.json'), true, flags: JSON_THROW_ON_ERROR);
$correct = 0;
foreach ($cases as $case) {
    $actual = $provider->interpret($case['question'])['intent'];
    $ok = $actual === $case['expected'];
    $correct += $ok ? 1 : 0;
    printf("%s | esperado=%s obtenido=%s | %s\n", $ok ? 'OK' : 'FALLO', $case['expected'], $actual, $case['question']);
}
printf("Aciertos: %d/%d (%.1f%%)\n", $correct, count($cases), 100 * $correct / count($cases));
exit($correct === count($cases) ? 0 : 1);
