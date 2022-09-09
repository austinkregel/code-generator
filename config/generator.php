<?php

use Illuminate\Support\Str;

return [
    'use_strict_types' => true,
 
    // Database models
    'default_model_namespace' => 'App\\Models\\',
    'default_model_extends' => 'App\\Models\\EloquentModelAbstract',
    'default_model_traits' => [
        'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
    ],
    'has_many_description' => 'The %s that this item owns',
    'has_one_description' => 'The %s that this item owns',
    'belongs_to_description' => 'The %s that owns this item',
    'belongs_to_many_description' => 'The %s owners of this item',
    'model_to_table' => function (string $model) {
        return match ($model) {
            // Not every model will be plural.
            "AdPerformance" => "ad_performance",
            "AdGroupPerformance" => "ad_group_performance",
            "CampaignPerformance" => "campaign_performance",
            "AccountPerformance" => "account_performance",
            "CampaignLocationTargetPerformance" => "campaign_location_target_performance",
            "KeywordPerformance" => "keyword_performance",
            default => Str::snake(Str::pluralStudly($model), '_'),
        };
    },

    // Repositories
    'default_repository_namespace' => 'App\\Repositories\\',
    'default_repository_extends' => 'App\\Repositories\\EloquentRepositoryAbstract',
    'default_repository_traits' => [],
    // Repository interfaces
    'default_repository_interface_namespace' => 'App\\Contracts\\Repositories\\',
    'default_repository_interface_extends' => 'App\\Contracts\\Repositories\\BaseRepositoryContract',
    'default_repository_interface_traits' => [],

    
];