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
