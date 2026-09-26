<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * Whether a restricted ticket a person doesn't qualify for disappears entirely, or still shows,
 * greyed out, with its reason (clio_cent_can_user_book_ticket already does the latter on its
 * own — this only controls whether clio_cent_event_tickets also hides it) — plus the wording of
 * that reason, since the defaults are a bit dry for customer-facing copy.
 */
class Settings
{
    private const OPTION = 'clio_cent_pmpro_settings';

    /** @return array<string,string> */
    public static function defaultMessages(): array
    {
        return [
            'login'    => __('This ticket is for members only. Please log in.', 'clio-cent-pmpro'),
            'any'      => __('This ticket is for members only.', 'clio-cent-pmpro'),
            'specific' => __('This ticket is only available to certain membership levels.', 'clio-cent-pmpro'),
        ];
    }

    public static function displayMode(): string
    {
        $stored = get_option(self::OPTION, []);
        $mode   = is_array($stored) ? (string) ($stored['display_mode'] ?? 'reason') : 'reason';

        return in_array($mode, ['reason', 'hide'], true) ? $mode : 'reason';
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        $stored = get_option(self::OPTION, []);
        $saved  = is_array($stored) ? (array) ($stored['messages'] ?? []) : [];

        $messages = self::defaultMessages();

        foreach ($messages as $key => $default) {
            if (! empty($saved[$key])) {
                $messages[$key] = (string) $saved[$key];
            }
        }

        return $messages;
    }

    /** @param array<string,string> $messages */
    public static function save(string $displayMode, array $messages = []): void
    {
        $clean = [];

        foreach (self::defaultMessages() as $key => $default) {
            $text = trim((string) ($messages[$key] ?? ''));

            if ($text !== '' && $text !== $default) {
                $clean[$key] = $text;
            }
        }

        update_option(self::OPTION, [
            'display_mode' => in_array($displayMode, ['reason', 'hide'], true) ? $displayMode : 'reason',
            'messages'     => $clean,
        ]);
    }
}
