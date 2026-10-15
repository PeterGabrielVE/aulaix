<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

/**
 * The manager's list: the active institution's members, twenty per page, and
 * its pending invitations, both filtered by the same search.
 */
final readonly class ListMembersHandler
{
    private const PER_PAGE = 20;

    public function __construct(private MemberDirectory $directory) {}

    /**
     * @return array{members: MemberPage, invitations: list<InvitationView>}
     */
    public function __invoke(?string $search, int $page): array
    {
        $search = $search === null || trim($search) === '' ? null : trim($search);

        return [
            'members' => $this->directory->members($search, max(1, $page), self::PER_PAGE),
            'invitations' => $this->directory->pendingInvitations($search),
        ];
    }
}
