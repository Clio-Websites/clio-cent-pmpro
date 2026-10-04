<?php
/**
 * View: one ticket's (or service's) membership rule, in the "Membership (PMPro)" metabox on the
 * event edit screen (or the employee edit screen, for their services). Member discounts are
 * percent-off only, kept deliberately simple.
 *
 * @var object                 $ticket  The ticket type, or the service (both have id and name).
 * @var string|null            $prefix  Input name prefix; 'pmpro_rules' (tickets) by default.
 * @var object|null            $rule
 * @var object[]               $levels  PMPro's own level objects (id, name).
 * @var Clio\CentPmpro\Plugin  $plugin
 */

defined('ABSPATH') || exit;

$id            = (int) $ticket->id;
$prefix        = $prefix ?? 'pmpro_rules'; // 'pmpro_service_rules' on the employee screen
$name          = static fn (string $key) => "{$prefix}[{$id}][{$key}]";
$restrictMode  = $rule->restrict_mode ?? 'none';
$discountMode  = $rule->discount_mode ?? 'none';
$discountAmt   = $rule ? (string) (int) $rule->discount_amount : '';
$restrictIds   = $rule ? array_map('absint', array_filter(explode(',', (string) $rule->restrict_levels))) : [];
$discountLevels = $rule ? $plugin->rules->unpackDiscountLevels((string) $rule->discount_levels) : [];

$levelLabels = [];
foreach ($levels as $level) {
    $levelLabels[(int) $level->id] = $level->name;
}
?>
<fieldset class="clio-cent-pmpro-rule">
    <legend><?php echo esc_html($ticket->name); ?></legend>

    <div class="clio-cent-pmpro-cols">
        <div class="clio-cent-pmpro-col">
            <strong><?php esc_html_e('Who can book it', 'clio-cent-pmpro'); ?></strong>
            <p>
                <label><input type="radio" name="<?php echo esc_attr($name('restrict_mode')); ?>" value="none" data-mode-radio <?php checked($restrictMode, 'none'); ?>> <?php esc_html_e('Anyone', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('restrict_mode')); ?>" value="any" data-mode-radio <?php checked($restrictMode, 'any'); ?>> <?php esc_html_e('Any active member', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('restrict_mode')); ?>" value="specific" data-mode-radio <?php checked($restrictMode, 'specific'); ?>> <?php esc_html_e('Only these levels:', 'clio-cent-pmpro'); ?></label>
            </p>
            <?php if ($levels) : ?>
                <div class="clio-cent-pmpro-level-picker" data-level-picker data-show-when="specific">
                    <div class="clio-cent-pmpro-chip-list" data-level-list>
                        <?php foreach ($restrictIds as $levelId) : ?>
                            <span class="clio-cent-pmpro-chip" data-id="<?php echo esc_attr($levelId); ?>">
                                <span><?php echo esc_html($levelLabels[$levelId] ?? ('#' . $levelId)); ?></span>
                                <input type="hidden" name="<?php echo esc_attr($name('restrict_levels')); ?>[]" value="<?php echo esc_attr($levelId); ?>">
                                <button type="button" class="clio-cent-pmpro-chip-remove" data-level-remove aria-label="<?php esc_attr_e('Remove', 'clio-cent-pmpro'); ?>">&times;</button>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <select class="clio-cent-pmpro-level-select" data-level-select>
                        <option value=""><?php esc_html_e('Add a level…', 'clio-cent-pmpro'); ?></option>
                        <?php foreach ($levels as $level) : ?>
                            <option value="<?php echo esc_attr($level->id); ?>" data-label="<?php echo esc_attr($level->name); ?>" <?php disabled(in_array((int) $level->id, $restrictIds, true)); ?>><?php echo esc_html($level->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <template data-level-template><span class="clio-cent-pmpro-chip" data-id="__ID__"><span>__LABEL__</span><input type="hidden" name="<?php echo esc_attr($name('restrict_levels')); ?>[]" value="__ID__"><button type="button" class="clio-cent-pmpro-chip-remove" data-level-remove aria-label="<?php esc_attr_e('Remove', 'clio-cent-pmpro'); ?>">&times;</button></span></template>
                </div>
            <?php endif; ?>
        </div>

        <div class="clio-cent-pmpro-col">
            <strong><?php esc_html_e('Member discount', 'clio-cent-pmpro'); ?></strong>
            <p>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_mode')); ?>" value="none" data-mode-radio <?php checked($discountMode, 'none'); ?>> <?php esc_html_e('None', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_mode')); ?>" value="any" data-mode-radio <?php checked($discountMode, 'any'); ?>> <?php esc_html_e('Any active member', 'clio-cent-pmpro'); ?></label>
                <label><input type="radio" name="<?php echo esc_attr($name('discount_mode')); ?>" value="specific" data-mode-radio <?php checked($discountMode, 'specific'); ?>> <?php esc_html_e('Only these levels:', 'clio-cent-pmpro'); ?></label>
            </p>

            <p data-show-when="any">
                <input type="number" min="0" max="100" name="<?php echo esc_attr($name('discount_amount')); ?>" value="<?php echo esc_attr($discountAmt); ?>" class="small-text" placeholder="0"> % <?php esc_html_e('off', 'clio-cent-pmpro'); ?>
            </p>

            <?php if ($levels) : ?>
                <div data-show-when="specific">
                    <p class="description"><?php esc_html_e('Add the qualifying levels and set each one\'s own percentage — or defer to the site-wide default set in Settings → Membership.', 'clio-cent-pmpro'); ?></p>
                    <div class="clio-cent-pmpro-level-picker" data-level-picker>
                        <div class="clio-cent-pmpro-chip-list" data-level-list>
                            <?php foreach ($discountLevels as $levelId => $config) :
                                $useDefault = ($config['mode'] ?? '') === 'default';
                                $rowAmount  = ! $useDefault ? (string) (int) ($config['amount'] ?? 0) : '';
                            ?>
                                <span class="clio-cent-pmpro-chip clio-cent-pmpro-chip-discount" data-id="<?php echo esc_attr($levelId); ?>">
                                    <span class="clio-cent-pmpro-chip-head">
                                        <span><?php echo esc_html($levelLabels[$levelId] ?? ('#' . $levelId)); ?></span>
                                        <button type="button" class="clio-cent-pmpro-chip-remove" data-level-remove aria-label="<?php esc_attr_e('Remove', 'clio-cent-pmpro'); ?>">&times;</button>
                                    </span>
                                    <span class="clio-cent-pmpro-chip-row">
                                        <input type="hidden" name="<?php echo esc_attr($name('discount_levels')); ?>[<?php echo esc_attr($levelId); ?>][mode]" value="custom">
                                        <label><input type="checkbox" value="default" data-use-default name="<?php echo esc_attr($name('discount_levels')); ?>[<?php echo esc_attr($levelId); ?>][mode]" <?php checked($useDefault); ?>> <?php esc_html_e('Use default', 'clio-cent-pmpro'); ?></label>
                                        <span class="clio-cent-pmpro-chip-amount"<?php echo $useDefault ? ' hidden' : ''; ?>>
                                            <input type="number" min="0" max="100" name="<?php echo esc_attr($name('discount_levels')); ?>[<?php echo esc_attr($levelId); ?>][amount]" value="<?php echo esc_attr($rowAmount); ?>" class="small-text" placeholder="0"> %
                                        </span>
                                    </span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <select class="clio-cent-pmpro-level-select" data-level-select>
                            <option value=""><?php esc_html_e('Add a level…', 'clio-cent-pmpro'); ?></option>
                            <?php foreach ($levels as $level) : ?>
                                <option value="<?php echo esc_attr($level->id); ?>" data-label="<?php echo esc_attr($level->name); ?>" <?php disabled(isset($discountLevels[(int) $level->id])); ?>><?php echo esc_html($level->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <template data-level-template><span class="clio-cent-pmpro-chip clio-cent-pmpro-chip-discount" data-id="__ID__"><span class="clio-cent-pmpro-chip-head"><span>__LABEL__</span><button type="button" class="clio-cent-pmpro-chip-remove" data-level-remove aria-label="<?php esc_attr_e('Remove', 'clio-cent-pmpro'); ?>">&times;</button></span><span class="clio-cent-pmpro-chip-row"><input type="hidden" name="<?php echo esc_attr($name('discount_levels')); ?>[__ID__][mode]" value="custom"><label><input type="checkbox" value="default" data-use-default name="<?php echo esc_attr($name('discount_levels')); ?>[__ID__][mode]"> <?php esc_html_e('Use default', 'clio-cent-pmpro'); ?></label><span class="clio-cent-pmpro-chip-amount"><input type="number" min="0" max="100" name="<?php echo esc_attr($name('discount_levels')); ?>[__ID__][amount]" class="small-text" placeholder="0"> %</span></span></span></template>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</fieldset>
