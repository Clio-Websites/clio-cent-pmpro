<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * One setting: whether a restricted ticket a person doesn't qualify for disappears entirely, or
 * still shows, greyed out, with its reason (clio_cent_can_user_book_ticket already does the
 * latter on its own — this only controls whether clio_cent_event_tickets also hides it).
 */
class Settings
{
    private const OPTION = 'clio_cent_pmpro_settings';

    public static function displayMode(): string
    {
        $stored = get_option(self::OPTION, []);
        $mode   = is_array($stored) ? (string) ($stored['display_mode'] ?? 'reason') : 'reason';

        return in_array($mode, ['reason', 'hide'], true) ? $mode : 'reason';
    }

    public static function save(string $displayMode): void
    {
        update_option(self::OPTION, [
            'display_mode' => in_array($displayMode, ['reason', 'hide'], true) ? $displayMode : 'reason',
        ]);
    }
}
