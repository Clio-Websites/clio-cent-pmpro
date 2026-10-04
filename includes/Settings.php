<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * Whether a restricted ticket a person doesn't qualify for disappears entirely, or still shows,
 * greyed out, with its reason (clio_centpro_can_user_book_ticket already does the latter on its
 * own — this only controls whether clio_centpro_event_tickets also hides it) — plus the wording of
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

        self::update([
            'display_mode' => in_array($displayMode, ['reason', 'hide'], true) ? $displayMode : 'reason',
            'messages'     => $clean,
        ]);
    }

    /**
     * Site-wide default member discount per PMPro level (e.g. "level 3 gets 15% off events by
     * default"), set once in Settings -> Membership so a ticket's own rule can just say "use the
     * default" for a level instead of repeating the same percentage on every ticket of every event.
     * Percent-off only, same as a ticket's own member discount.
     *
     * @return array<int,int> Level id => percent (1-100).
     */
    public static function defaultDiscounts(): array
    {
        $stored = get_option(self::OPTION, []);
        $saved  = is_array($stored) ? (array) ($stored['default_discounts'] ?? []) : [];

        $clean = [];

        foreach ($saved as $levelId => $amount) {
            $levelId = absint($levelId);
            $amount  = (int) $amount;

            if ($levelId > 0 && $amount > 0) {
                $clean[$levelId] = min(100, $amount);
            }
        }

        return $clean;
    }

    /** @param array<int|string,mixed> $input Keyed by level id, e.g. $_POST's shape. */
    public static function saveDefaultDiscounts(array $input): void
    {
        $clean = [];

        foreach ($input as $levelId => $amount) {
            $levelId = absint($levelId);
            $amount  = min(100, absint($amount));

            if ($levelId > 0 && $amount > 0) {
                $clean[$levelId] = $amount;
            }
        }

        self::update(['default_discounts' => $clean]);
    }

    /** @param array<string,mixed> $partial Merged into the option so unrelated keys survive. */
    private static function update(array $partial): void
    {
        $stored = get_option(self::OPTION, []);

        update_option(self::OPTION, array_merge(is_array($stored) ? $stored : [], $partial));
    }
}
