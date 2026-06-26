<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupport\Models\Guide;

/**
 * A host-style policy for an editor: may view every guide and create new ones.
 * Used to prove an editor (canCreate) is not treated as a "reader" and so keeps
 * the full column set.
 */
final class EditorGuidePolicy
{
    public function viewAny(TestUser $user): bool
    {
        return true;
    }

    public function view(TestUser $user, Guide $guide): bool
    {
        return true;
    }

    public function create(TestUser $user): bool
    {
        return true;
    }

    public function update(TestUser $user, Guide $guide): bool
    {
        return true;
    }

    public function delete(TestUser $user, Guide $guide): bool
    {
        return true;
    }
}
