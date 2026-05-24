<?php

$composer = json_decode(
    file_get_contents(__DIR__ . '/../../composer.json'),
    true
);

return [
    'name' => '💋Kiss',
    'tagline' => 'Keep It Simply Static',
    'description' => $composer['description'] ?? '',
    'version' => $composer['version'] ?? '0.0',
    'license' => $composer['license'] ?? '',
    'homepage' => $composer['homepage'] ?? '',
];
