<?php

namespace App\Support;

/**
 * Role names, referenced everywhere instead of raw strings so a rename is a
 * single edit and a typo fails at the linter rather than at runtime.
 */
final class Roles
{
    public const OWNER = 'owner';    // administrator — cannot be deleted

    public const MEMBER = 'member';  // team member

    public const ALL = [self::OWNER, self::MEMBER];
}
