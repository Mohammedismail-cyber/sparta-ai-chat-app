<?php

function available_themes(): array
{
    return [
        'paper'  => ['label' => 'Paper',  'swatch' => '#ffffff'],
        'zinc'   => ['label' => 'Zinc',   'swatch' => '#f4f4f5'],
        'purple' => ['label' => 'Purple', 'swatch' => '#7c3aed'],
        'blue'   => ['label' => 'Blue',   'swatch' => '#2563eb'],
        'green'  => ['label' => 'Green',  'swatch' => '#16a34a'],
        'rose'   => ['label' => 'Rose',   'swatch' => '#e11d48'],
        'dark'   => ['label' => 'Dark',   'swatch' => '#09090b'],
    ];
}

function normalize_theme(?string $theme): string
{
    $theme = $theme ?: 'paper';
    return array_key_exists($theme, available_themes()) ? $theme : 'paper';
}
