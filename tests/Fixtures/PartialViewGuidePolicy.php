<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupport\Models\Guide;

/**
 * A host-style policy for a reader who may view some guides but not others (and
 * cannot create): any guide except the one keyed `secret` is viewable. Used to
 * prove the list query is scoped to viewable rows and the runner is gated.
 */
final class PartialViewGuidePolicy
{
    public function viewAny(TestUser $user): bool
    {
        return true;
    }

    public function view(TestUser $user, Guide $guide): bool
    {
        return $guide->key !== 'secret';
    }

    public function create(TestUser $user): bool
    {
        return false;
    }
}
