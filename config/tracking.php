<?php

return [
    'timezone' => 'America/Bogota',
    'map_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'map_attribution' => '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    'center' => [4.6534, -74.0836],
    'max_accuracy' => 250,
    'max_batch' => 200,
    'history_limit' => 5000,
];
