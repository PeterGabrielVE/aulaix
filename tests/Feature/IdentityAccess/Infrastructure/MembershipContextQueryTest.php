<?php

use AulaX\IdentityAccess\Application\Members\MembershipContextQuery;
use AulaX\IdentityAccess\Application\Port\MembershipSession;

/**
 * SPEC-013 NAV-R02.4 – R02.6: a person's roles in the open institution, the
 * one that leads (IAM-R12.2) and in how many institutions they are active.
 */
test('tells the roles, the highest-priority one and the active institutions of an account', function () {
    $institution = tenant();
    $teacher = userWithRole($institution, 'Docente');
    $teacher->assignRole('Director');

    $context = app(MembershipContextQuery::class)($teacher->id);

    expect($context->roles)->toBe(['Director', 'Docente'])
        ->and($context->primaryRole)->toBe('Director')
        ->and($context->activeInstitutions)->toBe(1);
});

test('takes the active institutions from the session, counted where all of them were visible', function () {
    // With an institution open, RLS hides the other memberships (ADR 0006):
    // the handoff from the central domain remembers how many there were.
    $institution = tenant();
    $member = userWithRole($institution, 'Docente');
    $other = tenant();
    joinInstitution($member, $other, 'Representante');
    useTenant($institution);

    expect(app(MembershipContextQuery::class)($member->id)->activeInstitutions)->toBe(1);

    app(MembershipSession::class)->rememberActiveInstitutions(2);

    expect(app(MembershipContextQuery::class)($member->id)->activeInstitutions)->toBe(2);
});

test('has no primary role for a member without base roles', function () {
    $institution = tenant();
    $member = userWithRole($institution, 'Docente');
    $member->syncRoles([]);

    $context = app(MembershipContextQuery::class)($member->id);

    expect($context->roles)->toBe([])
        ->and($context->primaryRole)->toBeNull();
});
