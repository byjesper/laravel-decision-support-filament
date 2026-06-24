<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupport\Models\Guide;

/**
 * A host-style policy that allows viewing — used to prove a pinned runner's
 * default authorization flows through a registered Guide policy.
 */
final class AllowGuidePolicy
{
    public function view(TestUser $user, Guide $guide): bool
    {
        return true;
    }
}
