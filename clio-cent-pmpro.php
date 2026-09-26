<?php
/**
 * Plugin Name: Clio CENT — PMPro Bridge
 * Plugin URI:  https://cliowebsites.com
 * Description: Members-only tickets and member discounts for Clio CENT, using Paid Memberships Pro. Requires both plugins active.
 * Version:     0.1.0
 * Author:      csdev@cliowebsites
 * Author URI:  https://cliowebsites.com
 * License:     Commercial
 * Text Domain: clio-cent-pmpro
 * Requires Plugins: clio-cent, paid-memberships-pro
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/Plugin.php';

add_action('plugins_loaded', static function (): void {
    if (! class_exists('Clio\Cent\Plugin') || ! function_exists('pmpro_hasMembershipLevel')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Clio CENT — PMPro Bridge needs both Clio CENT and Paid Memberships Pro active.', 'clio-cent-pmpro')
            );
        });

        return;
    }

    Plugin::getInstance(__FILE__)->boot();
}, 20); // after clio-cent's own plugins_loaded (default priority 10) has booted core
