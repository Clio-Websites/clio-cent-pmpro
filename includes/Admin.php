<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * The admin side: a rule editor per existing ticket type, its own metabox on the (standard) event
 * edit screen, and one settings tab (Settings -> Membership) for the display mode.
 *
 * A brand new ticket added in the same submission has no rule editor yet (it doesn't have a real
 * id to key on until the event save finishes) — save the event once, then configure its rule.
 */
class Admin
{
    private const TAB = 'membership';

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_action('add_meta_boxes_' . \Clio\CentPro\PostTypes::EVENT, [$this, 'addMetabox']);
        add_action('clio_centpro_event_saved', [$this, 'saveFields']);
        add_action('clio_centpro_ticket_type_deleted', [$this, 'onTicketDeleted']);

        add_action('add_meta_boxes_' . \Clio\CentPro\PostTypes::EMPLOYEE, [$this, 'addServicesMetabox']);
        add_action('clio_centpro_employee_saved', [$this, 'saveServiceFields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        add_filter('clio_centpro_settings_tabs', [$this, 'addSettingsTab']);
        add_action('clio_centpro_settings_tab_' . self::TAB, [$this, 'renderSettingsTab']);
        add_action('clio_centpro_save_settings_tab_' . self::TAB, [$this, 'saveSettingsTab']);
    }

    /**
     * Only on the event edit screen — the rule editor's level pickers are the only thing here
     * that needs JS (adding/removing level chips from a dropdown); the settings tab is plain
     * inputs, no script needed.
     */
    public function enqueueAssets(): void
    {
        $screen = get_current_screen();

        if (! $screen || ! in_array($screen->post_type, [\Clio\CentPro\PostTypes::EVENT, \Clio\CentPro\PostTypes::EMPLOYEE], true)) {
            return;
        }

        // filemtime, not the plugin version, so every edit to these files busts the browser's
        // cache on its own instead of relying on remembering to bump a hardcoded string here.
        $cssPath = $this->plugin->dir . 'assets/css/admin.css';
        $jsPath  = $this->plugin->dir . 'assets/js/admin.js';

        wp_enqueue_style('clio-cent-pmpro-admin', $this->plugin->url . 'assets/css/admin.css', [], (string) filemtime($cssPath));
        wp_enqueue_script('clio-cent-pmpro-admin', $this->plugin->url . 'assets/js/admin.js', [], (string) filemtime($jsPath), true);
    }

    // -------------------------------------------------------------------------------------
    // Per-ticket rule editor, its own metabox on the (standard) event edit screen
    // -------------------------------------------------------------------------------------

    public function addMetabox(\WP_Post $post): void
    {
        add_meta_box(
            'clio_cent_pmpro_rules',
            __('Membership (PMPro)', 'clio-cent-pmpro'),
            [$this, 'renderMetabox'],
            \Clio\CentPro\PostTypes::EVENT,
            'normal',
            'default'
        );
    }

    public function renderMetabox(\WP_Post $post): void
    {
        if (! function_exists('pmpro_getAllLevels')) {
            return;
        }

        $levels = pmpro_getAllLevels(true, true);

        $tickets = \Clio\CentPro\Plugin::getInstance()->tickets->forEvent((int) $post->ID);

        if (! $tickets) {
            echo '<p class="description">' . esc_html__('Add a ticket type first, then come back here to set its membership rules.', 'clio-cent-pmpro') . '</p>';

            return;
        }

        foreach ($tickets as $ticket) {
            $rule = $this->plugin->rules->find((int) $ticket->id);
            $this->plugin->view('ticket-rule', [
                'ticket' => $ticket,
                'rule'   => $rule,
                'levels' => $levels,
                'plugin' => $this->plugin,
            ]);
        }
    }

    public function saveFields(int $eventId): void
    {
        $posted = (array) wp_unslash($_POST['pmpro_rules'] ?? []);

        if (! $posted) {
            return;
        }

        $ticketIds = array_map(
            static fn ($t) => (int) $t->id,
            \Clio\CentPro\Plugin::getInstance()->tickets->forEvent($eventId)
        );

        foreach ($posted as $ticketId => $row) {
            $ticketId = absint($ticketId);

            if (! in_array($ticketId, $ticketIds, true)) {
                continue; // not one of this event's own tickets — ignore
            }

            $this->plugin->rules->save($ticketId, (array) $row);
        }
    }

    // -------------------------------------------------------------------------------------
    // Per-service rule editor, its own metabox on the employee edit screen (services are edited
    // there, as rows of the employee's "Services" box)
    // -------------------------------------------------------------------------------------

    public function addServicesMetabox(\WP_Post $post): void
    {
        add_meta_box(
            'clio_cent_pmpro_service_rules',
            __('Membership (PMPro)', 'clio-cent-pmpro'),
            [$this, 'renderServicesMetabox'],
            \Clio\CentPro\PostTypes::EMPLOYEE,
            'normal',
            'default'
        );
    }

    public function renderServicesMetabox(\WP_Post $post): void
    {
        if (! function_exists('pmpro_getAllLevels')) {
            return;
        }

        $levels   = pmpro_getAllLevels(true, true);
        $services = \Clio\CentPro\Plugin::getInstance()->services->forEmployee((int) $post->ID);

        if (! $services) {
            echo '<p class="description">' . esc_html__('Add a service first (and save), then come back here to set its membership rules.', 'clio-cent-pmpro') . '</p>';

            return;
        }

        foreach ($services as $service) {
            $this->plugin->view('ticket-rule', [
                'ticket' => $service,
                'rule'   => $this->plugin->rules->findForService((int) $service->id),
                'levels' => $levels,
                'prefix' => 'pmpro_service_rules',
                'plugin' => $this->plugin,
            ]);
        }
    }

    /** Runs inside the employee screen's own (nonce-checked) save, like saveFields() for events. */
    public function saveServiceFields(int $employeeId): void
    {
        $posted = (array) wp_unslash($_POST['pmpro_service_rules'] ?? []);

        if (! $posted) {
            return;
        }

        $serviceIds = array_map(
            static fn ($s) => (int) $s->id,
            \Clio\CentPro\Plugin::getInstance()->services->forEmployee($employeeId)
        );

        foreach ($posted as $serviceId => $row) {
            $serviceId = absint($serviceId);

            if (in_array($serviceId, $serviceIds, true)) { // only this employee's own services
                $this->plugin->rules->saveForService($serviceId, (array) $row);
            }
        }
    }

    public function onTicketDeleted(int $id): void
    {
        $this->plugin->rules->delete($id);
    }

    // -------------------------------------------------------------------------------------
    // Settings -> Membership (the display mode)
    // -------------------------------------------------------------------------------------

    /** @param array<string,string> $tabs */
    public function addSettingsTab(array $tabs): array
    {
        $tabs[self::TAB] = __('Membership', 'clio-cent-pmpro');

        return $tabs;
    }

    public function renderSettingsTab(): void
    {
        $mode      = Settings::displayMode();
        $messages  = Settings::messages();
        $defaults  = Settings::defaultMessages();
        $discounts = Settings::defaultDiscounts();
        $levels    = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
        ?>
        <h2><?php esc_html_e('Restricted tickets and services', 'clio-cent-pmpro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('When someone doesn\'t qualify', 'clio-cent-pmpro'); ?></th>
                <td>
                    <p><label><input type="radio" name="pmpro_display_mode" value="reason" <?php checked($mode, 'reason'); ?>> <?php esc_html_e('Show it, greyed out, with the reason', 'clio-cent-pmpro'); ?></label></p>
                    <p><label><input type="radio" name="pmpro_display_mode" value="hide" <?php checked($mode, 'hide'); ?>> <?php esc_html_e('Hide it entirely (the ticket from the event page, the service from the services page)', 'clio-cent-pmpro'); ?></label></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Not logged in', 'clio-cent-pmpro'); ?></th>
                <td><input type="text" name="pmpro_messages[login]" value="<?php echo esc_attr($messages['login']); ?>" placeholder="<?php echo esc_attr($defaults['login']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Needs any membership', 'clio-cent-pmpro'); ?></th>
                <td><input type="text" name="pmpro_messages[any]" value="<?php echo esc_attr($messages['any']); ?>" placeholder="<?php echo esc_attr($defaults['any']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Needs a specific level', 'clio-cent-pmpro'); ?></th>
                <td>
                    <input type="text" name="pmpro_messages[specific]" value="<?php echo esc_attr($messages['specific']); ?>" placeholder="<?php echo esc_attr($defaults['specific']); ?>" class="large-text">
                    <p class="description"><?php esc_html_e('{level} is replaced with the qualifying level\'s name(s), e.g. "Membership {level} required".', 'clio-cent-pmpro'); ?></p>
                </td>
            </tr>
        </table>
        <p class="description"><?php esc_html_e('Leave a message empty to use the default shown as its placeholder.', 'clio-cent-pmpro'); ?></p>

        <?php if ($levels) : ?>
            <h2><?php esc_html_e('Default member discounts', 'clio-cent-pmpro'); ?></h2>
            <p class="description"><?php esc_html_e('Set once per level here; a ticket\'s or service\'s own "member discount" rule can then say "use the default" for a level instead of repeating the same percentage everywhere.', 'clio-cent-pmpro'); ?></p>
            <table class="form-table" role="presentation">
                <?php foreach ($levels as $level) :
                    $levelId = (int) $level->id;
                    $amount  = $discounts[$levelId] ?? '';
                ?>
                    <tr>
                        <th><?php echo esc_html($level->name); ?></th>
                        <td><input type="number" min="0" max="100" name="pmpro_default_discounts[<?php echo esc_attr($levelId); ?>]" value="<?php echo esc_attr($amount); ?>" class="small-text" placeholder="0"> %</td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="description"><?php esc_html_e('Leave a level at 0 for no site-wide default — "use the default" then applies no discount for it.', 'clio-cent-pmpro'); ?></p>
        <?php endif; ?>
        <?php
    }

    public function saveSettingsTab(): void
    {
        $messages = array_map('sanitize_text_field', wp_unslash((array) ($_POST['pmpro_messages'] ?? [])));

        Settings::save(sanitize_key((string) ($_POST['pmpro_display_mode'] ?? 'reason')), $messages);
        Settings::saveDefaultDiscounts(wp_unslash((array) ($_POST['pmpro_default_discounts'] ?? [])));
    }
}
