<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * The admin side: a rule editor per existing ticket type (on the event edit screen, right after
 * the tickets table), and one settings tab (Settings -> Membership) for the display mode.
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
        add_action('clio_cent_event_form_after_tickets', [$this, 'renderFields']);
        add_action('clio_cent_event_saved', [$this, 'saveFields']);
        add_action('clio_cent_ticket_type_deleted', [$this, 'onTicketDeleted']);

        add_filter('clio_cent_settings_tabs', [$this, 'addSettingsTab']);
        add_action('clio_cent_settings_tab_' . self::TAB, [$this, 'renderSettingsTab']);
        add_action('clio_cent_save_settings_tab_' . self::TAB, [$this, 'saveSettingsTab']);
    }

    // -------------------------------------------------------------------------------------
    // Per-ticket rule editor, on the event form
    // -------------------------------------------------------------------------------------

    public function renderFields(?object $event): void
    {
        if (! function_exists('pmpro_getAllLevels')) {
            return;
        }

        $levels = pmpro_getAllLevels(true, true);

        echo '<div class="clio-cent-card">';
        echo '<h2>' . esc_html__('Membership (PMPro)', 'clio-cent-pmpro') . '</h2>';
        echo '<style>
            .clio-cent-pmpro-rule { border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 16px 16px; margin: 0 0 16px; }
            .clio-cent-pmpro-rule legend { font-weight: 600; padding: 0 6px; }
            .clio-cent-pmpro-cols { display: flex; flex-wrap: wrap; gap: 24px; }
            .clio-cent-pmpro-col { flex: 1 1 260px; min-width: 240px; }
            .clio-cent-pmpro-col p { margin: 6px 0; }
            .clio-cent-pmpro-col label { display: block; margin: 2px 0; }
            .clio-cent-pmpro-levels { padding-left: 20px; }
        </style>';

        if (! $event) {
            echo '<p class="description">' . esc_html__('Save the event first, then come back here to set membership rules for its tickets.', 'clio-cent-pmpro') . '</p></div>';

            return;
        }

        $tickets = \Clio\Cent\Plugin::getInstance()->tickets->forEvent((int) $event->id);

        if (! $tickets) {
            echo '<p class="description">' . esc_html__('Add a ticket type first.', 'clio-cent-pmpro') . '</p></div>';

            return;
        }

        foreach ($tickets as $ticket) {
            $rule = $this->plugin->rules->find((int) $ticket->id);
            $this->plugin->view('ticket-rule', [
                'ticket' => $ticket,
                'rule'   => $rule,
                'levels' => $levels,
            ]);
        }

        echo '</div>';
    }

    public function saveFields(int $eventId): void
    {
        $posted = (array) wp_unslash($_POST['pmpro_rules'] ?? []);

        if (! $posted) {
            return;
        }

        $ticketIds = array_map(
            static fn ($t) => (int) $t->id,
            \Clio\Cent\Plugin::getInstance()->tickets->forEvent($eventId)
        );

        foreach ($posted as $ticketId => $row) {
            $ticketId = absint($ticketId);

            if (! in_array($ticketId, $ticketIds, true)) {
                continue; // not one of this event's own tickets — ignore
            }

            $this->plugin->rules->save($ticketId, (array) $row);
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
        $mode = Settings::displayMode();
        ?>
        <h2><?php esc_html_e('Restricted tickets', 'clio-cent-pmpro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('When someone doesn\'t qualify', 'clio-cent-pmpro'); ?></th>
                <td>
                    <p><label><input type="radio" name="pmpro_display_mode" value="reason" <?php checked($mode, 'reason'); ?>> <?php esc_html_e('Show the ticket, greyed out, with the reason', 'clio-cent-pmpro'); ?></label></p>
                    <p><label><input type="radio" name="pmpro_display_mode" value="hide" <?php checked($mode, 'hide'); ?>> <?php esc_html_e('Hide the ticket entirely', 'clio-cent-pmpro'); ?></label></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function saveSettingsTab(): void
    {
        Settings::save(sanitize_key((string) ($_POST['pmpro_display_mode'] ?? 'reason')));
    }
}
