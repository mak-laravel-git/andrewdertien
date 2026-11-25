<?php

namespace App\Enums;

/**
 * Where the member's share of a toss has got to.
 */
enum PayoutStatus: string
{
    /** Nothing to pay the member: the toss was not for a member, or the fees used it all up. */
    case NONE = 'none';

    /** Paid for, and waiting to be added to the member's wallet. */
    case PENDING = 'pending';

    /** Added to the member's wallet. */
    case CREDITED = 'credited';

    /** The last attempt to work out the member's share failed. It is tried again later. */
    case FAILED = 'failed';

    /** Taken back out of the member's wallet, because the toss was refunded. */
    case REVERSED = 'reversed';
}
