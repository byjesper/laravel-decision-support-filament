<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use ByJesper\DecisionSupportFilament\Pages\GuideRunner;

/**
 * A host-style runner pinned to one guide key — the shape a real host uses to
 * place a single guide in its own navigation. It overrides nothing but the key,
 * so it also exercises the package's default (policy-aware) {@see canAccess()}.
 */
final class PinnedGuideRunner extends GuideRunner
{
    protected static ?string $guideKey = 'eligibility';
}
