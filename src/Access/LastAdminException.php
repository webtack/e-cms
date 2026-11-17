<?php

namespace Webtack\Ecms\Access;

use RuntimeException;

/**
 * Thrown by {@see LastAdminGuard} when a change would leave no active administrator.
 */
class LastAdminException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The last active administrator cannot be deactivated, deleted or moved to another role.');
    }
}
