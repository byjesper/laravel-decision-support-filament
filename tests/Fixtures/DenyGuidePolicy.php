<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupport\Models\Guide;

/**
 * A host-style policy that denies everything — used to prove the resource
 * defers authorization to whatever Guide policy the host registers.
 */
final class DenyGuidePolicy
{
    public function viewAny(TestUser $user): bool
    {
        return false;
    }

    public function view(TestUser $user, Guide $guide): bool
    {
        return false;
    }

    public function create(TestUser $user): bool
    {
        return false;
    }

    public function update(TestUser $user, Guide $guide): bool
    {
        return false;
    }

    public function delete(TestUser $user, Guide $guide): bool
    {
        return false;
    }
}
