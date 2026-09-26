<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * One rule per Clio CENT ticket type: who may book it (membership restriction) and what it costs
 * them (membership discount). Either half can be on its own, or both together.
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
     * @param array<string,mixed> $input
     */
    public function save(int $ticketTypeId, array $input): void
    {
        global $wpdb;

        $restrictMode = in_array($input['restrict_mode'] ?? '', ['none', 'any', 'specific'], true) ? $input['restrict_mode'] : 'none';
        $discountMode = in_array($input['discount_mode'] ?? '', ['none', 'any', 'specific'], true) ? $input['discount_mode'] : 'none';
        $discountType = ($input['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';

        // Nothing configured: don't leave a pointless "all none" row behind.
        if ($restrictMode === 'none' && $discountMode === 'none') {
            $this->delete($ticketTypeId);

            return;
        }

        $data = [
            'ticket_type_id'  => $ticketTypeId,
            'restrict_mode'   => $restrictMode,
            'restrict_levels' => $restrictMode === 'specific' ? $this->packLevels((array) ($input['restrict_levels'] ?? [])) : '',
            'discount_mode'   => $discountMode,
            'discount_type'   => $discountType,
            'discount_amount' => $this->discountAmount($discountMode, $discountType, (string) ($input['discount_amount'] ?? '')),
            'discount_levels' => $discountMode === 'specific' ? $this->packLevels((array) ($input['discount_levels'] ?? [])) : '',
        ];

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

    /**
     * A percent is a plain 0-100 integer; a fixed amount is what the admin typed in the
     * event's own currency ("5.00"), converted to minor units the same way ticket prices are.
     */
    private function discountAmount(string $mode, string $type, string $input): int
    {
        if ($mode === 'none') {
            return 0;
        }

        return $type === 'fixed' ? \Clio\Cent\Money::toMinor($input) : min(100, absint($input));
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

        $qualifies = $rule->discount_mode === 'any'
            ? pmpro_hasMembershipLevel(null, $userId)
            : (bool) array_filter(
                $this->unpackLevels((string) $rule->discount_levels),
                static fn ($levelId) => pmpro_hasMembershipLevel($levelId, $userId)
            );

        if (! $qualifies) {
            return 0;
        }

        $off = $rule->discount_type === 'percent'
            ? (int) round($priceMinor * min(100, (int) $rule->discount_amount) / 100)
            : (int) $rule->discount_amount;

        return max(0, min($off, $priceMinor));
    }
}
