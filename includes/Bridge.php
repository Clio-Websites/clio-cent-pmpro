<?php

namespace Clio\CentPmpro;

defined('ABSPATH') || exit;

/**
 * Where a rule actually takes effect: refuse or discount a ticket, and — only when Settings ->
 * Membership is set to "hide" — remove a restricted ticket from the page entirely instead of
 * showing it greyed out with its reason.
 */
class Bridge
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        add_filter('clio_cent_can_user_book_ticket', [$this, 'checkRestriction'], 10, 4);
        add_filter('clio_cent_get_user_ticket_price', [$this, 'applyDiscount'], 10, 3);
        add_filter('clio_cent_event_tickets', [$this, 'maybeHideTickets'], 10, 3);
        add_action('pmpro_member_links_bottom', [$this, 'renderMemberLink']);
    }

    /**
     * PMPro's own account page only shows its "Member Links" section at all when something is
     * hooked to pmpro_member_links_top/bottom — this puts "My Bookings" right there for anyone
     * who has set a My Bookings page in Clio CENT -> Settings -> General.
     */
    public function renderMemberLink(): void
    {
        $pageId = (int) \Clio\Cent\Plugin::getInstance()->settings->get('my_bookings_page_id');

        if ($pageId <= 0 || get_post_status($pageId) !== 'publish') {
            return;
        }

        printf(
            '<li class="pmpro_list_item"><a href="%s">%s</a></li>',
            esc_url(get_permalink($pageId)),
            esc_html__('My Bookings', 'clio-cent-pmpro')
        );
    }

    /**
     * @param true|\WP_Error|false $allowed
     * @return true|\WP_Error|false
     */
    public function checkRestriction($allowed, object $ticket, object $event, int $userId)
    {
        $rule = $this->plugin->rules->find((int) $ticket->id);

        if (! $rule) {
            return $allowed;
        }

        $reason = $this->plugin->rules->restrictionReason($rule, $userId);

        return $reason === null ? $allowed : new \WP_Error('clio_cent_pmpro_restricted', $reason);
    }

    public function applyDiscount(int $price, object $ticket, int $userId): int
    {
        $rule = $this->plugin->rules->find((int) $ticket->id);

        if (! $rule) {
            return $price;
        }

        return max(0, $price - $this->plugin->rules->discountFor($rule, $userId, $price));
    }

    /**
     * @param object[] $tickets
     * @return object[]
     */
    public function maybeHideTickets(array $tickets, object $event, int $userId): array
    {
        if (Settings::displayMode() !== 'hide') {
            return $tickets;
        }

        return array_values(array_filter($tickets, function ($ticket) use ($userId) {
            $rule = $this->plugin->rules->find((int) $ticket->id);

            return ! $rule || $this->plugin->rules->restrictionReason($rule, $userId) === null;
        }));
    }
}
