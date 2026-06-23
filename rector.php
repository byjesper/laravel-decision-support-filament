<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/config',
    ])
    ->withSets([
        LaravelSetList::LARAVEL_130,
        LevelSetList::UP_TO_PHP_84,
    ])
    ->withSkip([
        __DIR__.'/tests',
    ]);
