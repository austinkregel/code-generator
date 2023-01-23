<?php

use Illuminate\Support\Str;

return [
    'use_strict_types' => false,

    // Database models
    'default_model_namespace' => 'App\\Models\\',
    'default_model_extends' => '',
    'default_model_traits' => [
        'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
    ],
    'has_many_description' => 'The %s that this item owns',
    'has_one_description' => 'The %s that this item owns',
    'belongs_to_description' => 'The %s that owns this item',
    'belongs_to_many_description' => 'The %s owners of this item',
    'model_to_table' => function (string $model) {
        return match ($model) {
            // Not every model will be plural, but convention dictates most will.
            default => Str::snake(Str::pluralStudly($model), '_'),
        };
    },

    // Repositories
    'default_repository_namespace' => 'App\\Repositories\\',
    'default_repository_extends' => '',
    'default_repository_traits' => [],
    // Repository interfaces
    'default_repository_interface_namespace' => 'App\\Contracts\\Repositories\\',
    'default_repository_interface_extends' => '',
    'default_repository_interface_traits' => [],

];
