<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * One rule per Clio CENT ticket type, or per service: who may book it (membership restriction) and
 * what it costs them (membership discount). Either half can be on its own, or both together.
 * Ticket rules live in this add-on's own table; service rules in post meta on the service.
 */
class Rules
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function find(int $ticketTypeId): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Schema::table() . ' WHERE ticket_type_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $ticketTypeId
        )) ?: null;
    }

    /**
     * A rule's fields from the editor's input (same for a ticket or a service), or null when nothing
     * is configured (both halves "none"), so no pointless "all none" rule is kept.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>|null
     */
    private function build(array $input): ?array
    {
        $restrictMode = in_array($input['restrict_mode'] ?? '', ['none', 'any', 'specific'], true) ? $input['restrict_mode'] : 'none';
        $discountMode = in_array($input['discount_mode'] ?? '', ['none', 'any', 'specific'], true) ? $input['discount_mode'] : 'none';

        if ($restrictMode === 'none' && $discountMode === 'none') {
            return null;
        }

        return [
            'restrict_mode'   => $restrictMode,
            'restrict_levels' => $restrictMode === 'specific' ? $this->packLevels((array) ($input['restrict_levels'] ?? [])) : '',
            'discount_mode'   => $discountMode,
            'discount_type'   => 'percent',
            'discount_amount' => $this->discountAmount($discountMode, (string) ($input['discount_amount'] ?? '')),
            'discount_levels' => $discountMode === 'specific' ? $this->packDiscountLevels((array) ($input['discount_levels'] ?? [])) : '',
        ];
    }

    // -------------------------------------------------------------------------------------
    // Services (appointments): the same rule, kept as post meta on the service's own post, so it
    // goes away with the service and needs no table of its own.
    // -------------------------------------------------------------------------------------

    public const SERVICE_META = '_clio_cent_pmpro_rule';

    public function findForService(int $serviceId): ?object
    {
        $data = get_post_meta($serviceId, self::SERVICE_META, true);

        return is_array($data) && $data ? (object) $data : null;
    }

    /** @param array<string,mixed> $input */
    public function saveForService(int $serviceId, array $input): void
    {
        $data = $this->build($input);

        if ($data === null) {
            delete_post_meta($serviceId, self::SERVICE_META);
        } else {
            update_post_meta($serviceId, self::SERVICE_META, $data);
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public function save(int $ticketTypeId, array $input): void
    {
        global $wpdb;

        $data = $this->build($input);

        // Nothing configured: don't leave a pointless "all none" row behind.
        if ($data === null) {
            $this->delete($ticketTypeId);

            return;
        }

        $data = ['ticket_type_id' => $ticketTypeId] + $data;

        $now = current_time('mysql', true);

        if ($this->find($ticketTypeId)) {
            $data['updated_at'] = $now;
            $wpdb->update(Schema::table(), $data, ['ticket_type_id' => $ticketTypeId]);
        } else {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $wpdb->insert(Schema::table(), $data);
        }
    }

    /** A plain 0-100 percentage; member discounts are percent-off only, kept simple on purpose. */
    private function discountAmount(string $mode, string $input): int
    {
        return $mode === 'none' ? 0 : min(100, absint($input));
    }

    public function delete(int $ticketTypeId): void
    {
        global $wpdb;

        $wpdb->delete(Schema::table(), ['ticket_type_id' => $ticketTypeId]);
    }

    /** @return int[] */
    public function unpackLevels(string $csv): array
    {
        return array_values(array_filter(array_map('absint', explode(',', $csv))));
    }

    /** @param int[] $levelIds */
    private function levelNames(array $levelIds): string
    {
        $names = array_filter(array_map(
            static function (int $id): string {
                $level = function_exists('pmpro_getLevel') ? pmpro_getLevel($id) : null;

                return $level ? (string) $level->name : '';
            },
            $levelIds
        ));

        return implode(', ', $names);
    }

    /** @param array<int,mixed> $levels */
    private function packLevels(array $levels): string
    {
        return implode(',', array_values(array_unique(array_filter(array_map('absint', $levels)))));
    }

    /**
     * The "member discount" specific-levels side needs its own percentage per level (unlike
     * restriction, which is just membership yes/no), so it's stored as a level id => config map
     * instead of a plain CSV: {"3":{"mode":"custom","amount":10},"7":{"mode":"default"}}. "default"
     * means "use whatever Settings -> Membership has set as level 7's site-wide default", resolved
     * at discount time so raising the default later updates every rule using it.
     *
     * @param array<int|string,mixed> $rows Keyed by level id, e.g. $_POST's discount_levels[3][...].
     */
    private function packDiscountLevels(array $rows): string
    {
        $clean = [];

        foreach ($rows as $levelId => $row) {
            $levelId = absint($levelId);

            if ($levelId <= 0 || ! is_array($row)) {
                continue;
            }

            $clean[$levelId] = ($row['mode'] ?? '') === 'default'
                ? ['mode' => 'default']
                : ['mode' => 'custom', 'amount' => min(100, absint($row['amount'] ?? ''))];
        }

        return $clean ? (string) wp_json_encode($clean) : '';
    }

    /** @return array<int,array{mode:string,amount?:int}> */
    public function unpackDiscountLevels(string $json): array
    {
        $decoded = $json !== '' ? json_decode($json, true) : [];

        if (! is_array($decoded)) {
            return [];
        }

        $clean = [];

        foreach ($decoded as $levelId => $row) {
            $levelId = absint($levelId);

            if ($levelId > 0 && is_array($row)) {
                $clean[$levelId] = $row;
            }
        }

        return $clean;
    }

    /**
     * Is this person allowed to book this ticket? null = allowed (no rule, or they qualify);
     * a string is the reason to refuse (shown to the customer, replacing the ticket's stepper).
     */
    public function restrictionReason(object $rule, int $userId): ?string
    {
        if ($rule->restrict_mode === 'none') {
            return null;
        }

        $messages = Settings::messages();

        if ($userId <= 0) {
            return $messages['login'];
        }

        if ($rule->restrict_mode === 'any') {
            return pmpro_hasMembershipLevel(null, $userId) ? null : $messages['any'];
        }

        $levelIds = $this->unpackLevels((string) $rule->restrict_levels);

        foreach ($levelIds as $levelId) {
            if (pmpro_hasMembershipLevel($levelId, $userId)) {
                return null;
            }
        }

        return str_replace('{level}', $this->levelNames($levelIds), $messages['specific']);
    }

    /**
     * What this rule takes off a ticket's current unit price for this person (minor units), 0 if
     * nothing applies. Never more than the price itself.
     */
    public function discountFor(object $rule, int $userId, int $priceMinor): int
    {
        if ($rule->discount_mode === 'none' || $userId <= 0 || $priceMinor <= 0) {
            return 0;
        }

        if ($rule->discount_mode === 'any') {
            return pmpro_hasMembershipLevel(null, $userId)
                ? $this->percentOff((int) $rule->discount_amount, $priceMinor)
                : 0;
        }

        // Specific levels, each with its own percentage (or a site-wide default): a member could
        // hold more than one qualifying level at once, so take whichever discount is largest.
        $best = 0;

        foreach ($this->unpackDiscountLevels((string) $rule->discount_levels) as $levelId => $config) {
            if (! pmpro_hasMembershipLevel($levelId, $userId)) {
                continue;
            }

            $amount = $this->resolveLevelAmount($levelId, $config);
            $best   = max($best, $this->percentOff($amount, $priceMinor));
        }

        return $best;
    }

    /** @param array{mode?:string,amount?:int} $config */
    private function resolveLevelAmount(int $levelId, array $config): int
    {
        if (($config['mode'] ?? '') === 'default') {
            return Settings::defaultDiscounts()[$levelId] ?? 0;
        }

        return (int) ($config['amount'] ?? 0);
    }

    private function percentOff(int $percent, int $priceMinor): int
    {
        return max(0, min((int) round($priceMinor * min(100, $percent) / 100), $priceMinor));
    }
}
