<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Usuario propietario compartido por test: los datos creados directamente por modelos y
 * las peticiones autenticadas pertenecen a la misma cuenta (aislamiento por owner_id).
 */
function tenant(array $attributes = []): \App\Models\User
{
    if (! app()->bound('test.tenant')) {
        $user = \App\Models\User::factory()->create();
        app(\App\Services\Accounts\AccountBootstrap::class)->run($user, false, false);
        app()->instance('test.tenant', $user);
    }

    $user = app('test.tenant');

    if ($attributes !== []) {
        $user->forceFill($attributes)->save();
    }

    return $user;
}

pest()->beforeEach(function () {
    $class = strtolower(static::class);

    if (! str_contains($class, 'auth') && ! str_contains($class, 'settings')) {
        \Illuminate\Support\Facades\Auth::setUser(tenant());
    }
})->in('Feature');
