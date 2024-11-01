<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

add_action('woocommerce_shipping_init', function() {
    if (!class_exists('SmartPointsLockersAcsPlugin_ShippingMethod')) {
        class SmartPointsLockersAcsPlugin_ShippingMethod extends WC_Shipping_Method {
            public function __construct() {
                $this->id = SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID;
                $this->title = esc_html__('Παραλαβή από ACS SmartPoint', 'smartpoints-lockers-acs');
                $this->method_title = esc_html__('Παραλαβή από ACS SmartPoint', 'smartpoints-lockers-acs');
                $this->method_description = '';

                $this->availability = 'including';
                $this->countries = array(
                    'GR',
                );

                $settings = SmartPointsLockersAcsPlugin::getSettings();
                $this->enabled = $settings['enabled'];
                $this->init();
            }

            function init() {
                $this->init_form_fields();
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            function admin_options() {
                $httpResponse = SmartPointsLockersAcsPlugin::fetch_points_json();
                ?>
                <h2><?php echo esc_html($this->title); ?></h2>
                <?php if ($httpResponse['status_code'] == 200): ?>
                    <p style="font-weight: bold; color: #00870d;"><?php echo esc_html__('Valid ACS Credentials.', 'smartpoints-lockers-acs'); ?></p>
                <?php else: ?>
                    <p style="font-weight: bold; color: #F00;"><?php echo esc_html__('Invalid ACS Credentials.', 'smartpoints-lockers-acs'); ?></p>
                <?php endif; ?>
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
                <?php
            }

            function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => esc_html__('Activation', 'smartpoints-lockers-acs'),
                        'type' => 'checkbox',
                        'description' => esc_html__('Activation/Deactivation of shipping method', 'smartpoints-lockers-acs'),
                        'default' => 'no'
                    ),
                    'baseCost' => array(
                        'title' => esc_html__('Base shipping cost per order', 'smartpoints-lockers-acs') . ' (&euro;)',
                        'type' => 'number',
                        'description' => esc_html__('Shipping cost for pickup from ACS Point', 'smartpoints-lockers-acs'),
                        'default' => 0,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'weightUpperLimit' => array(
                        'title' => esc_html__('Weight limit', 'smartpoints-lockers-acs') . ' (kg)',
                        'type' => 'number',
                        'description' => esc_html__('Upper limit for package weight (kg) in order to pickup from ACS Point', 'smartpoints-lockers-acs'),
                        'default' => 30,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'freeShippingUpperLimit' => array(
                        'title' => esc_html__('Free delivery', 'smartpoints-lockers-acs') . ' (&euro;)',
                        'type' => 'number',
                        'description' => esc_html__('for order value higher than (Leave it empty if you don\'t have free delivery)', 'smartpoints-lockers-acs'),
                        'default' => '',
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'baseCostKgLimit' => array(
                        'title' => esc_html__('Base shipping cost kg limit', 'smartpoints-lockers-acs') . ' (kg)',
                        'type' => 'number',
                        'description' => esc_html__('Base shipping cost is valid up to LAST rate', 'smartpoints-lockers-acs'),
                        'default' => 1,
                    ),
                    'rates_a' => array(
                        'title' => esc_html__('Rates Region A', 'smartpoints-lockers-acs'),
                        'type' => 'title',
                        'description' => esc_html__('Define shipping rates Region A & calculations based on weight ranges.', 'smartpoints-lockers-acs'),
                    ),
                    'regionzoneid_a' => array(
                        'title' => esc_html__('Shipping Zone ID for Rates Region A', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Define here the Shipping ID from WooCommerce for Rates Region A', 'smartpoints-lockers-acs'),
                        'default' => 21
                    ),
                    'rate_1' => array(
                        'title' => esc_html__('0 - 1 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 0 and 1 kg', 'smartpoints-lockers-acs'),
                        'default' => 2.66,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_2' => array(
                        'title' => esc_html__('1.01 - 2 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 1.01 and 2 kg', 'smartpoints-lockers-acs'),
                        'default' => 2.98,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_3' => array(
                        'title' => esc_html__('2.01 - 3 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 2.01 and 3 kg', 'smartpoints-lockers-acs'),
                        'default' => 3.79,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_4' => array(
                        'title' => esc_html__('3.01 - 4 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 3.01 and 4 kg', 'smartpoints-lockers-acs'),
                        'default' => 4.84,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_5' => array(
                        'title' => esc_html__('4.01 - 5 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 4.01 and 5 kg', 'smartpoints-lockers-acs'),
                        'default' => 5.89,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_6' => array(
                        'title' => esc_html__('5.01 - 6 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 5.01 and 6 kg', 'smartpoints-lockers-acs'),
                        'default' => 6.93,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'costPerKg_a' => array(
                        'title' => esc_html__('Cost per extra kg for Region A', 'smartpoints-lockers-acs') . ' (&euro;)',
                        'type' => 'number',
                        'description' => esc_html__('Extra cost per kilo', 'smartpoints-lockers-acs'),
                        'default' => 1.05,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rates_b' => array(
                        'title' => esc_html__('Rates Region B ', 'smartpoints-lockers-acs'),
                        'type' => 'title',
                        'description' => esc_html__('Define shipping rates for Region B & calculations based on weight ranges.', 'smartpoints-lockers-acs'),
                    ),
                    'regionzoneid_b' => array(
                        'title' => esc_html__('Shipping Zone ID for Rates Region B', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Define here the Shipping ID from WooCommerce for Rates Region B ', 'smartpoints-lockers-acs'),
                        'default' => 1
                    ),
                    'rate_1b' => array(
                        'title' => esc_html__('0 - 1 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 0 and 1 kg', 'smartpoints-lockers-acs'),
                        'default' => 2.66,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_2b' => array(
                        'title' => esc_html__('1.01 - 2 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 1.01 and 2 kg', 'smartpoints-lockers-acs'),
                        'default' => 2.98,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_3b' => array(
                        'title' => esc_html__('2.01 - 3 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 2.01 and 3 kg', 'smartpoints-lockers-acs'),
                        'default' => 3.79,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_4b' => array(
                        'title' => esc_html__('3.01 - 4 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 3.01 and 4 kg', 'smartpoints-lockers-acs'),
                        'default' => 4.84,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_5b' => array(
                        'title' => esc_html__('4.01 - 5 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 4.01 and 5 kg', 'smartpoints-lockers-acs'),
                        'default' => 5.89,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'rate_6b' => array(
                        'title' => esc_html__('5.01 - 6 kg', 'smartpoints-lockers-acs'),
                        'type' => 'number',
                        'description' => esc_html__('Cost for weights between 5.01 and 6 kg', 'smartpoints-lockers-acs'),
                        'default' => 6.93,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'costPerKg_b' => array(
                        'title' => esc_html__('Cost per extra kg for Region B', 'smartpoints-lockers-acs') . ' (&euro;)',
                        'type' => 'number',
                        'description' => esc_html__('Extra cost per kilo', 'smartpoints-lockers-acs'),
                        'default' => 1.05,
                        'custom_attributes' => array(
                            'step' => 0.01
                        )
                    ),
                    'Credentials' => array(
                        'title' => esc_html__('Credentials for ACS & Google Maps', 'smartpoints-lockers-acs'),
                        'type' => 'title',
                        'description' => esc_html__('Define the Credentials for ACS & Google Maps.', 'smartpoints-lockers-acs'),
                    ),
                    'acsCompanyID' => array(
                        'title' => esc_html__('Company ID', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Provided by ACS courier', 'smartpoints-lockers-acs'),
                        'default' => ''
                    ),
                    'acsCompanyPassword' => array(
                        'title' => esc_html__('Company Password', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Provided by ACS courier', 'smartpoints-lockers-acs'),
                        'default' => ''
                    ),
                    'acsUserID' => array(
                        'title' => esc_html__('User ID', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Provided by ACS courier', 'smartpoints-lockers-acs'),
                        'default' => ''
                    ),
                    'acsUserPassword' => array(
                        'title' => esc_html__('User Password', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Provided by ACS courier', 'smartpoints-lockers-acs'),
                        'default' => ''
                    ),
                    'acsApiKey' => array(
                        'title' => esc_html__('Api Key', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Provided by ACS courier', 'smartpoints-lockers-acs'),
                        'default' => ''
                    ),
                    'googleMapsKey' => array(
                        'title' => esc_html__('Google Api Key', 'smartpoints-lockers-acs'),
                        'type' => 'text',
                        'description' => esc_html__('Google Maps API Key required for map functionalities.', 'smartpoints-lockers-acs'),
                        'default' => ''
                    ),
                );
            }

            public function calculate_shipping($packages = array()) {
                $settings = SmartPointsLockersAcsPlugin::getSettings();
                $baseCost = $settings['baseCost'];
                $baseCostKgLimit = $settings['baseCostKgLimit'] ?? 0;
                $costPerKg_a = $settings['costPerKg_a'] ?? 0;
                $costPerKg_b = $settings['costPerKg_b'] ?? 0;
                $freeShippingLimit = intval($settings['freeShippingUpperLimit'] ?? 0);

                $optionCost = 0;
                $weightTotal = 0;

                // Calculate the order total cost
                $orderTotalCost = WC()->cart->get_subtotal();

                $zone = WC_Shipping_Zones::get_zone_matching_package($packages);
                $zone_id = $zone->get_id(); // Get the ID of the shipping zone for the customer

                // Check if the order total cost is less than the free shipping limit
                if (!empty($freeShippingLimit) && $orderTotalCost >= $freeShippingLimit) {
                    $optionCost = 0;        
                } else {
                    foreach ($packages['contents'] as $item_id => $values) {
                        $_product = $values['data'];
                        $qty = $values['quantity'] ?? 1;
                        $tempWeight = (float) $_product->get_weight();
                        $weightTotal += $qty * $tempWeight;
                    }

                    $RegionZoneID_A = intval($settings['regionzoneid_a'] ?? 21);
                    $RegionZoneID_B = intval($settings['regionzoneid_b'] ?? 1);

                    // Check if the weight is within the defined ranges and set the option cost accordingly based on the shipping zone
                    if ($zone_id === $RegionZoneID_A) { // Region A
                        if ($weightTotal >= 0 && $weightTotal <= 1) {
                            $optionCost = $settings['rate_1'];
                        } elseif ($weightTotal > 1.01 && $weightTotal <= 2) {
                            $optionCost = $settings['rate_2'];
                        } elseif ($weightTotal > 2.01 && $weightTotal <= 3) {
                            $optionCost = $settings['rate_3'];
                        } elseif ($weightTotal > 3.01 && $weightTotal <= 4) {
                            $optionCost = $settings['rate_4'];
                        } elseif ($weightTotal > 4.01 && $weightTotal <= 5) {
                            $optionCost = $settings['rate_5'];
                        } elseif ($weightTotal > 5.01 && $weightTotal <= 6) {
                            $optionCost = $settings['rate_6'];
                        } elseif ($weightTotal > 6.01) {
                            // For packages over 6 kg, charge rate for 6 kg plus additional cost per kg
                            $optionCost = $settings['rate_6'] + $costPerKg_a * ($weightTotal - 6);
                        }
                    } elseif ($zone_id === $RegionZoneID_B) { // Region B
                        if ($weightTotal >= 0 && $weightTotal <= 1) {
                            $optionCost = $settings['rate_1b'];
                        } elseif ($weightTotal > 1.01 && $weightTotal <= 2) {
                            $optionCost = $settings['rate_2b'];
                        } elseif ($weightTotal > 2.01 && $weightTotal <= 3) {
                            $optionCost = $settings['rate_3b'];
                        } elseif ($weightTotal > 3.01 && $weightTotal <= 4) {
                            $optionCost = $settings['rate_4b'];
                        } elseif ($weightTotal > 4.01 && $weightTotal <= 5) {
                            $optionCost = $settings['rate_5b'];
                        } elseif ($weightTotal > 5.01 && $weightTotal <= 6) {
                            $optionCost = $settings['rate_6b'];
                        } elseif ($weightTotal > 6.01) {
                            // For packages over 6 kg, charge rate for 6 kg plus additional cost per kg
                            $optionCost = $settings['rate_6b'] + $costPerKg_b * ($weightTotal - 6);
                        }
                    }

                    // Add base cost if applicable
                    if ($optionCost > 0) {
                        $optionCost += $baseCost;
                    }
                }
               
                $rate = array(
                    'id' => $this->id,
                    'label' => esc_html($this->title),
                    'cost' => $optionCost,
                    'calc_tax' => 'per_order'
                );
                $this->add_rate($rate);
            }
        }
    }
});

function acs_pp_add_shipping_method($methods) {
    $methods[] = 'SmartPointsLockersAcsPlugin_ShippingMethod';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'acs_pp_add_shipping_method');

register_deactivation_hook(__FILE__, 'acs_pp_deactivate');
function acs_pp_deactivate() {
    $timestamp = wp_next_scheduled(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . 'cron_hook');
    wp_unschedule_event($timestamp, SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . 'cron_hook');
}

register_activation_hook(__FILE__, 'acs_pp_activate');
function acs_pp_activate() {
    if (!wp_next_scheduled(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . 'cron_hook')) {
        wp_schedule_event(time(), 'hourly', SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . 'cron_hook');
    }
}
