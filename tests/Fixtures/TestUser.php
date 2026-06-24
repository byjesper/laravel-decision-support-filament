<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupportFilament\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class TestUser extends Authenticatable
{
    protected $guarded = [];
}
