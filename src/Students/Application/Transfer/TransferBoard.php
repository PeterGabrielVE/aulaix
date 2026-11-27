<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

/**
 * The transfers of the open institution: the ones it must answer or
 * answered (incoming) and the ones it asked for (outgoing).
 */
final readonly class TransferBoard
{
    /**
     * @param  list<TransferView>  $incoming
     * @param  list<TransferView>  $outgoing
     */
    public function __construct(
        public array $incoming,
        public array $outgoing,
    ) {}
}
