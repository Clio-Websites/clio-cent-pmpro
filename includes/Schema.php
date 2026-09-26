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
    public const VERSION = '1';
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
        if (get_option(self::OPTION_DB_VERSION) !== self::VERSION) {
            self::migrate();
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
  restrict_levels varchar(190) NOT NULL DEFAULT '',
  discount_mode varchar(20) NOT NULL DEFAULT 'none',
  discount_type varchar(10) NOT NULL DEFAULT 'percent',
  discount_amount bigint(20) unsigned NOT NULL DEFAULT 0,
  discount_levels varchar(190) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ticket_type_id (ticket_type_id)
) {$c};"
        );

        update_option(self::OPTION_DB_VERSION, self::VERSION);
    }
}
