<?php

use AulaX\Platform\Application\Port\TotpCodes;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\RecoveryCodes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;

/*
 * Helpers shared by the tests of the platform panel (SPEC-014).
 */

function adminUrl(string $path = '/'): string
{
    return 'http://admin.'.config('app.domain').$path;
}

/**
 * An active operator with password «clave-de-operador» and a real TOTP
 * secret, its last step well in the past.
 */
function storedOperator(string $email = 'ana@aulax.ve', OperatorRole $role = OperatorRole::SuperAdmin): OperatorId
{
    $repository = app(OperatorRepository::class);
    $operator = Operator::invite('Ana Rivas', OperatorEmail::fromString($email), $role);
    $operator->activate(Hash::make('clave-de-operador'), app(TotpCodes::class)->newSecret(), step: 1);
    $id = $repository->save($operator);
    $repository->saveRecoveryCodes($id, RecoveryCodes::fromPlain(array_map(fn (int $n) => "recup-{$n}", range(1, 10))));

    return $id;
}

/**
 * The TOTP code of an operator for now.
 */
function currentCode(OperatorId $id): string
{
    $secret = app(OperatorRepository::class)->byId($id)->totpSecret;
    $google2fa = new Google2FA;

    return $google2fa->oathTotp($secret, intdiv(now()->getTimestamp(), 30));
}

/**
 * The panel's login form, sent from its own page.
 */
function panelLogIn(string $email, string $password, string $code): TestResponse
{
    return test()->from(adminUrl('/login'))->post(adminUrl('/login'), ['email' => $email, 'password' => $password, 'code' => $code]);
}

/**
 * An operator signed in through the real login, its TOTP just typed.
 */
function signedInOperator(string $email = 'ana@aulax.ve', OperatorRole $role = OperatorRole::SuperAdmin): OperatorId
{
    $id = storedOperator($email, $role);
    panelLogIn($email, 'clave-de-operador', currentCode($id))->assertRedirect(route('platform.home'));

    return $id;
}
