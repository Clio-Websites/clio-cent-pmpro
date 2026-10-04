<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * One table: a PMPro rule per Clio CENT ticket type (restriction and/or discount by membership
 * level). Never mixed into clio-cent's own `ticket_types` table — this is a separate add-on's data,
 * matched by `ticket_type_id`, and disappears cleanly if the bridge is ever removed.
 */
final class Schema
{
    public const VERSION = '2';
    public const OPTION_DB_VERSION = 'clio_cent_pmpro_db_version';

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'clio_cent_pmpro_rules';
    }

    public static function activate(): void
    {
        self::migrate();
    }

    public static function maybeUpgrade(): void
    {
        $installed = get_option(self::OPTION_DB_VERSION);

        if ($installed === self::VERSION) {
            return;
        }

        self::migrate();

        // v1 -> v2: "member discount, specific levels" moved from one flat amount shared by a
        // CSV of level ids to a per-level amount map (JSON), and discounts became percent-off
        // only (no more "fixed amount off"). Carry the old flat *percent* forward onto every
        // level it used to apply to, so existing percent-based rules keep discounting exactly as
        // before. A legacy "fixed amount off" rule can't be translated into a percentage at all
        // (e.g. a $5-off rule has no equivalent %) — reinterpreting its raw minor-unit amount as a
        // percentage would silently turn it into something like "100% off", which is worse than
        // just dropping it, so those come over at 0% and need a manual look.
        if ($installed !== false && version_compare((string) $installed, '2', '<')) {
            self::migrateDiscountLevelsToJson();
        }
    }

    private static function migrateDiscountLevelsToJson(): void
    {
        global $wpdb;
        $t = self::table();

        // "Any active member" rules just use discount_amount directly (no levels map) — a legacy
        // fixed-amount one needs clearing for the same reason as below, or it'd be misread as a
        // percentage once discount_type stops being consulted.
        $wpdb->query(
            "UPDATE {$t} SET discount_amount = 0 WHERE discount_mode = 'any' AND discount_type = 'fixed'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        $rows = $wpdb->get_results(
            "SELECT id, discount_type, discount_amount, discount_levels FROM {$t} WHERE discount_mode = 'specific' AND discount_levels != ''" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->discount_levels, true);

            if (is_array($decoded)) {
                continue; // already the new format
            }

            $levelIds = array_values(array_filter(array_map('absint', explode(',', (string) $row->discount_levels))));

            if (! $levelIds) {
                continue;
            }

            $amount = $row->discount_type === 'fixed' ? 0 : min(100, (int) $row->discount_amount);

            $map = [];

            foreach ($levelIds as $levelId) {
                $map[$levelId] = ['mode' => 'custom', 'amount' => $amount];
            }

            $wpdb->update($t, ['discount_levels' => wp_json_encode($map)], ['id' => (int) $row->id]);
        }
    }

    private static function migrate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        global $wpdb;
        $t = self::table();
        $c = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$t} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ticket_type_id bigint(20) unsigned NOT NULL,
  restrict_mode varchar(20) NOT NULL DEFAULT 'none',
  restrict_levels text NOT NULL,
  discount_mode varchar(20) NOT NULL DEFAULT 'none',
  discount_type varchar(10) NOT NULL DEFAULT 'percent',
  discount_amount bigint(20) unsigned NOT NULL DEFAULT 0,
  discount_levels text NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ticket_type_id (ticket_type_id)
) {$c};"
        );

        update_option(self::OPTION_DB_VERSION, self::VERSION);
    }
}
