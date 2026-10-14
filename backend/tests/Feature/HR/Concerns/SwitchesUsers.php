<?php

namespace Tests\Feature\HR\Concerns;

use App\Models\Company;
use App\Models\User;

/**
 * The Sanctum guard caches the resolved user between requests in one test,
 * so switching actors needs the guards forgotten first.
 */
trait SwitchesUsers
{
    protected function switchTo(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }
}
