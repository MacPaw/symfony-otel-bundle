<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Foreach_\ForeachToInArrayRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveNullTagValueNodeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\DeadCode\Rector\For_\RemoveDeadLoopRector;
use Rector\DeadCode\Rector\Foreach_\RemoveUnusedForeachKeyRector;
use Rector\DeadCode\Rector\If_\RemoveTypedPropertyDeadInstanceOfRector;
use Rector\DeadCode\Rector\If_\RemoveUnusedNonEmptyArrayBeforeForeachRector;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\EarlyReturn\Rector\Foreach_\ChangeNestedForeachIfsToEarlyContinueRector;
use Rector\EarlyReturn\Rector\If_\ChangeIfElseValueAssignToEarlyReturnRector;
use Rector\EarlyReturn\Rector\If_\ChangeNestedIfsToEarlyReturnRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;
use Rector\EarlyReturn\Rector\If_\RemoveAlwaysElseRector;
use Rector\EarlyReturn\Rector\Return_\PreparedValueToEarlyReturnRector;
use Rector\EarlyReturn\Rector\Return_\ReturnBinaryOrToEarlyReturnRector;
use Rector\EarlyReturn\Rector\StmtsAwareInterface\ReturnEarlyIfVariableRector;
use Rector\Php70\Rector\Break_\BreakNotInLoopOrSwitchToReturnRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/test_app',
    ])
    ->withImportNames(
        removeUnusedImports: true,
    )
    ->withParallel(100, 5)
    ->withRules([
        ChangeIfElseValueAssignToEarlyReturnRector::class,
        ChangeNestedForeachIfsToEarlyContinueRector::class,
        ChangeNestedIfsToEarlyReturnRector::class,
        ChangeOrIfContinueToMultiContinueRector::class,
        PreparedValueToEarlyReturnRector::class,
        RemoveNullTagValueNodeRector::class,
        RemoveParentCallWithoutParentRector::class,
        RemoveTypedPropertyDeadInstanceOfRector::class,
        RemoveUnusedConstructorParamRector::class,
        RemoveUnusedForeachKeyRector::class,
        RemoveUnusedNonEmptyArrayBeforeForeachRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        BreakNotInLoopOrSwitchToReturnRector::class,
        ForeachToInArrayRector::class,
        RemoveDeadContinueRector::class,
        RemoveDeadLoopRector::class,
        RemoveAlwaysElseRector::class,
        ReturnBinaryOrToEarlyReturnRector::class,
        ReturnEarlyIfVariableRector::class,
    ])
    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withTypeCoverageLevel(100);
