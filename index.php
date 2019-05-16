<?php 

use rasteiner\kqlparser\Interpreter;

load([
    'rasteiner\kqlparser\Interpreter' => 'Interpreter.php'
], __DIR__ . '/src');

Kirby::plugin('rasteiner/kql', [
    'components' => [
        'query' => function(string $query, array $data) {
            return (new Interpreter($data))->parse($query);
        }
    ]
]);
