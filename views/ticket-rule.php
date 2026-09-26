<?php
/**
 * View: one ticket's membership rule, on the event edit screen (clio_cent_event_form_after_tickets).
 *
 * @var object      $ticket
 * @var object|null $rule
 * @var object[]    $levels  PMPro's own level objects (id, name).
 */

defined('ABSPATH') || exit;

$id            = (int) $ticket->id;
$name          = static fn (string $key) => "pmpro_rules[{$id}][{$key}]";
$restrictMode  = $rule->restrict_mode ?? 'none';
$discountMode  = $rule->discount_mode ?? 'none';
$discountType  = $rule->discount_type ?? 'percent';
$discountAmt   = $rule ? (int) $rule->discount_amount : 0;
$restrictIds   = $rule ? array_map('absint', array_filter(explode(',', (string) $rule->restrict_levels))) : [];
$discountIds   = $rule ? array_map('absint', array_filter(explode(',', (string) $rule->discount_levels))) : [];
?>
<fieldset class="clio-cent-pmpro-rule">
    <legend><?php echo esc_html($ticket->name); ?></legend>

    <div class="clio-cent-pmpro-cols">
        <div class="clio-cent-pmpro-col">
            <strong><?php esc_html_e('Who can book it', 'clio-cent-pmpro'); ?></strong>
            <p>
                <label><input type="radio" name="<?php echo esc_attr($name('restrict_mode')); ?>" value="none" <?php checked($restrictMode, 'none'); ?>> <?php esc_html_e('Anyone', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('restrict_mode')); ?>" value="any" <?php checked($restrictMode, 'any'); ?>> <?php esc_html_e('Any active member', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('restrict_mode')); ?>" value="specific" <?php checked($restrictMode, 'specific'); ?>> <?php esc_html_e('Only these levels:', 'clio-cent-pmpro'); ?></label>
            </p>
            <?php if ($levels) : ?>
                <p class="clio-cent-pmpro-levels">
                    <?php foreach ($levels as $level) : ?>
                        <label><input type="checkbox" name="<?php echo esc_attr($name('restrict_levels')); ?>[]" value="<?php echo esc_attr($level->id); ?>" <?php checked(in_array((int) $level->id, $restrictIds, true)); ?>> <?php echo esc_html($level->name); ?></label>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="clio-cent-pmpro-col">
            <strong><?php esc_html_e('Member discount', 'clio-cent-pmpro'); ?></strong>
            <p>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_mode')); ?>" value="none" <?php checked($discountMode, 'none'); ?>> <?php esc_html_e('None', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_mode')); ?>" value="any" <?php checked($discountMode, 'any'); ?>> <?php esc_html_e('Any active member', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_mode')); ?>" value="specific" <?php checked($discountMode, 'specific'); ?>> <?php esc_html_e('Only these levels:', 'clio-cent-pmpro'); ?></label>
            </p>
            <?php if ($levels) : ?>
                <p class="clio-cent-pmpro-levels">
                    <?php foreach ($levels as $level) : ?>
                        <label><input type="checkbox" name="<?php echo esc_attr($name('discount_levels')); ?>[]" value="<?php echo esc_attr($level->id); ?>" <?php checked(in_array((int) $level->id, $discountIds, true)); ?>> <?php echo esc_html($level->name); ?></label>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <p>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_type')); ?>" value="percent" <?php checked($discountType, 'percent'); ?>> <?php esc_html_e('Percent off', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_type')); ?>" value="fixed" <?php checked($discountType, 'fixed'); ?>> <?php esc_html_e('Fixed amount off (minor units)', 'clio-cent-pmpro'); ?></label>
                <input type="number" min="0" name="<?php echo esc_attr($name('discount_amount')); ?>" value="<?php echo esc_attr($discountAmt); ?>" class="small-text">
            </p>
        </div>
    </div>
</fieldset>
