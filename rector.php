<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\DeadCode\Rector\MethodCall\RemoveNullNamedArgOnNullDefaultParamRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSkip([
        // Public properties and constructor signatures are part of the package API;
        // promoting or freezing them is a separate, deliberate change.
        ClassPropertyAssignToConstructorPromotionRector::class,
        ReadOnlyPropertyRector::class,
        // ZVecDocIterator::$collection is never read on purpose: it pins the collection so
        // it cannot be destroyed under a live iterator.
        RemoveUnusedPrivatePropertyRector::class => [__DIR__ . '/src/ZVecDocIterator.php'],
        // Uninitialized typed properties throw when read; the defaults are deliberate.
        RemoveDefaultValueFromAssignedPropertyRector::class,
        // Would move an explanatory comment onto an unrelated argument.
        RemoveNullNamedArgOnNullDefaultParamRector::class,
    ])
    ->withPhpSets(php81: true)
    ->withPreparedSets(deadCode: true);
