<?php

namespace App\Support;

final class Permissions
{
    // Team members
    public const MEMBER_VIEW = 'member.view';

    public const MEMBER_CREATE = 'member.create';

    public const MEMBER_UPDATE = 'member.update';

    public const MEMBER_DELETE = 'member.delete';

    public const MEMBER_EXPORT = 'member.export';

    // Settings
    public const SETTINGS_VIEW = 'settings.view';

    /** Notification toggles and stored credentials. */
    public const SETTINGS_MANAGE = 'settings.manage';

    public const TAG_MANAGE = 'tag.manage';

    public const CRM_MANAGE = 'crm.manage';

    public const INTEGRATION_MANAGE = 'integration.manage';

    /**
     * Folding one contact into another.
     *
     * Owner-only because it is a deletion wearing a friendlier name: the
     * duplicate is soft-deleted and its leads and calls move away.
     */
    public const CUSTOMER_MERGE = 'customer.merge';

    /**
     * Removing a note.
     *
     * Owner-only, like every other deletion. Anyone may write one and edit
     * their own; taking one out of the record is a different act.
     */
    public const NOTE_DELETE = 'note.delete';

    // Activity log
    public const ACTIVITY_VIEW = 'activity.view';

    public const ALL = [
        self::MEMBER_VIEW, self::MEMBER_CREATE, self::MEMBER_UPDATE,
        self::MEMBER_DELETE, self::MEMBER_EXPORT,
        self::SETTINGS_VIEW, self::SETTINGS_MANAGE, self::TAG_MANAGE, self::CRM_MANAGE,
        self::INTEGRATION_MANAGE, self::CUSTOMER_MERGE, self::NOTE_DELETE,
        self::ACTIVITY_VIEW,
    ];

    /** Members get a deliberately small slice; everything else is Owner-only. */
    public const MEMBER_GRANTS = [
        self::MEMBER_VIEW,
    ];
}
