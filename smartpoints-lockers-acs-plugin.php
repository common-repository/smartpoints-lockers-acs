<?php
/**
 * Plugin Name: Smartpoints Lockers for ACS
 * Description: The Smartpoints Lockers for ACS Plugin on your e-shop, offers at your customers the option to easily and quickly pick up their online orders from an ACS Smartpoint Locker or ACS store. In addition the plugin can offers special settings like table rates for different shipping zones and other useful Settings for Shipping Calculation
 * Version: 1.0.6
 * Author: HEADPLUS
 * Author URI: https://headplus.gr
 * Plugin URI: https://headplus.gr/product/smartpoints-lockers-for-acs/
 * License: GPL v3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: smartpoints-lockers-acs
 */

if (!defined('WPINC')) {
    die;
}

define('SMARTPOINTS_LOCKERS_ACS_PLUGIN_VERSION', '1.0.6');
define('SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID', 'smartpoints-lockers-acs-plugin');
define('SMARTPOINTS_LOCKERS_ACS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMARTPOINTS_LOCKERS_ACS_PLUGIN_DOMAIN', 'smartpoints-lockers-acs');

require_once SMARTPOINTS_LOCKERS_ACS_PLUGIN_DIR . 'index.php';
// Load the text domain for translations
add_action('plugins_loaded', 'smartpoints_lockers_acs_load_textdomain');
function smartpoints_lockers_acs_load_textdomain() {
    load_plugin_textdomain('smartpoints-lockers-acs', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

if (!class_exists('SmartPointsLockersAcsPlugin')) {
    class SmartPointsLockersAcsPlugin
    {
        protected $settings;

        static $labels = [
            'checkout_option_title' => 'Παραλαβή από ACS SmartPoint',
            'checkout_button_label' => 'Επιλέξτε Smart Point',
            'checkout_validation_error' => 'Έχετε επιλέξει να παραλάβετε από ACS Points όμως ΔΕΝ έχετε επιλέξει ACS Smart Point από τον χάρτη! Παρακαλούμε Επιλέξτε ένα ACS SmartPoint πατώντας την αντίστοιχη επιλογή στην ενότητα "Αποστολή"',
            'checkout_validation_error_weight' => 'Λυπούμαστε αλλά η %1$s δεν υποστηρίζεται για παραγγελίες με ογκομετρικό βάρος μεγαλύτερο από %2$d kg.',
            'checkout_selected_point_title' => 'Έχετε επιλέξει να παραλάβετε την παραγγελία σας από',
            'email_selected_point_title' => 'ΕΠΙΛΕΓΜΕΝΟ ACS SmartPoint',
        ];

        static $configuration = [
            'checkout_input_name' => 'acs_pp_point_id',
            'post_meta_field_name' => 'acs_pp_point',
        ];

        public function __construct()
        {
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($actions) {
                return array_merge($actions, [
                    '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section=smartpoints-lockers-acs')) . '">Settings</a>',
                    '<a href="' . esc_url('https://headplus.gr') . '" target="_blank">Support</a>',
                ]);
            });

            $this->settings = self::getSettings();
            if ($this->settings['enabled'] === 'yes') {
                $this->initWoo();
            }
        }

        function initWoo()
        {
            add_action('woocommerce_review_order_before_submit', array($this, 'pre_checkout_validation'), 10);
            add_action('woocommerce_after_checkout_validation', array($this, 'checkout_validation'), 10, 2);
            add_action('woocommerce_before_checkout_form', array($this, 'add_map_in_checkout'), 10, 1);
            add_action('woocommerce_after_shipping_rate', array($this, 'add_map_trigger_to_shipping_option'), 10, 2);
            add_action('woocommerce_after_order_notes', array($this, 'add_checkout_point_input'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_point_input'));
            add_action('woocommerce_order_details_after_customer_details', array($this, 'show_point_details_in_customer'), 10);
            add_action('woocommerce_admin_order_data_after_order_details', [$this, 'show_point_details_in_admin'], 10, 1);
            add_action(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . 'cron_hook', array($this, 'fetch_points_json'));
        }

        public static function getSettings()
        {
            $settings = get_option('woocommerce_smartpoints-lockers-acs_settings');
            if (empty($settings)) {
                $settings = array('enabled' => 'no');
            }
            return $settings;
        }

        public function get_order_weight()
        {
            $chosen_methods = WC()->session->get('chosen_shipping_methods');

            $packages = WC()->shipping->get_packages();

            $weight = 0;
            foreach ($packages as $i => $package) {
                if ($chosen_methods[$i] != SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID) {
                    continue;
                }

                foreach ($package['contents'] as $item_id => $values) {
                    $_product = $values['data'];
                    if (is_numeric($_product->get_weight())) {
                        $weight += $_product->get_weight() * $values['quantity'];
                    }
                }

                $weight += wc_get_weight($weight, 'kg');
            }

            return $weight;
        }

        public function pre_checkout_validation()
        {
            $chosen_methods = WC()->session->get('chosen_shipping_methods');

            if (!is_array($chosen_methods)) {
                return;
            }

            if (!in_array(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID, $chosen_methods)) {
                return;
            }

            $weightLimit = (int)$this->settings['weightUpperLimit'];
            if ($weightLimit == 0) {
                return;
            }
        }

        public function checkout_validation($data, $errors)
        {
            if (
                SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID == $data['shipping_method'][0]
                && (!isset($_POST['acs_pp_nonce_field']) || !wp_verify_nonce($_POST['acs_pp_nonce_field'], 'acs_pp_nonce_action'))
            ) {
                $errors->add('validation', __('Nonce verification failed', 'smartpoints-lockers-acs'));
            }

            if (
                SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID == $data['shipping_method'][0]
                && empty($_POST[self::$configuration['checkout_input_name']])
            ) {
                $errors->add('validation', __('Έχετε επιλέξει να παραλάβετε από ACS Points όμως ΔΕΝ έχετε επιλέξει ACS Smart Point από τον χάρτη! Παρακαλούμε Επιλέξτε ένα ACS SmartPoint πατώντας την αντίστοιχη επιλογή στην ενότητα "Αποστολή"', 'smartpoints-lockers-acs'));
            }

            $weightLimit = (int)$this->settings['weightUpperLimit'];
            if ($weightLimit != 0 && $this->get_order_weight() > $weightLimit) {
                // Translators: %1$s is the shipping method title, %2$d is the weight limit.
                $errors->add('validation', sprintf(
                    /* translators: %1$s is the shipping method title, %2$d is the weight limit. */
                    __('Λυπούμαστε αλλά η %1$s δεν υποστηρίζεται για παραγγελίες με ογκομετρικό βάρος μεγαλύτερο από %2$d kg.', 'smartpoints-lockers-acs'),
                    __('Παραλαβή από ACS SmartPoint', 'smartpoints-lockers-acs'),
                    $weightLimit
                ));
            }
        }

        public function add_map_in_checkout()
        {
            $googleMapsKey = $this->settings['googleMapsKey'];

            wp_enqueue_script(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-js-googleapis', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($googleMapsKey) . '&libraries=geometry', array(), SMARTPOINTS_LOCKERS_ACS_PLUGIN_VERSION, 'all');
            wp_enqueue_script(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-js-markerclusterer', SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL . 'js/markerclusterer.js', array(), SMARTPOINTS_LOCKERS_ACS_PLUGIN_VERSION, 'all');
            wp_enqueue_script(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-js-woo-script', SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL . 'js/woo-script.js', array(), SMARTPOINTS_LOCKERS_ACS_PLUGIN_VERSION, 'all');
            wp_enqueue_script(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-js-script', SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL . 'js/script.js', array(), SMARTPOINTS_LOCKERS_ACS_PLUGIN_VERSION, 'all');
            wp_enqueue_style(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-css-styles', SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL . 'css/styles.css', array(), SMARTPOINTS_LOCKERS_ACS_PLUGIN_VERSION, 'all');

			// Localize the script with the plugin URL
    		wp_localize_script(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-js-script', 'smartpoints_lockers_acs_plugin_data', array(
        'plugin_url' => SMARTPOINTS_LOCKERS_ACS_PLUGIN_URL
				));

            require SMARTPOINTS_LOCKERS_ACS_PLUGIN_DIR . 'smartpoints-lockers-acs-map.php';
        }

        public function add_map_trigger_to_shipping_option($method)
        {
            if ($method->id == SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID) {
                $buttonLabel = __('Επιλέξτε Smart Point', 'smartpoints-lockers-acs');
                echo '<div class="locker-container">
                <span class="point-distance"></span>
                <button type="button" id="locker-trigger" onclick="openMap();" class="pick-locker-button" hidden="hidden">
                ' . esc_html($buttonLabel) . '
                </button>
                </div>';
            }
        }

        public function add_checkout_point_input($checkout)
        {
            $field = self::$configuration['checkout_input_name'];
            wp_nonce_field('acs_pp_nonce_action', 'acs_pp_nonce_field');
            echo '<div id="user_link_hidden_checkout_field">
                <input type="hidden" class="input-hidden" name="' . esc_attr($field) . '" id="' . esc_attr($field) . '" value="">
                </div>';
        }

        public function save_checkout_point_input($order_id)
        {
            if (!isset($_POST['acs_pp_nonce_field']) || !wp_verify_nonce($_POST['acs_pp_nonce_field'], 'acs_pp_nonce_action')) {
                return; // Nonce verification failed
            }

            $value = isset($_POST[self::$configuration['checkout_input_name']]) ? intval($_POST[self::$configuration['checkout_input_name']]) : '';
            if (!empty($value) && $_POST['shipping_method'][0] == SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID) {
                global $wp_filesystem;
                if (empty($wp_filesystem)) {
                    require_once(ABSPATH . '/wp-admin/includes/file.php');
                    WP_Filesystem();
                }

                $pointsFile = $wp_filesystem->get_contents(SMARTPOINTS_LOCKERS_ACS_PLUGIN_DIR . 'data.json');
                $points = json_decode($pointsFile, true);
                $selectedPoint = null;
                foreach ($points['points'] as $point) {
                    if ($point['id'] == $value) {
                        $selectedPoint = $point;
                        break;
                    }
                }

                if ($selectedPoint) {
                    update_post_meta($order_id, self::$configuration['post_meta_field_name'], wp_json_encode($selectedPoint, JSON_UNESCAPED_UNICODE));

                    // Update shipping fields with selected point data
                    $order = wc_get_order($order_id);
                    $shipping_company = '***SMARTPOINT LOCKER (' . esc_html($selectedPoint['Acs_Station_Destination'] ?? '') . esc_html($selectedPoint['Acs_Station_Branch_Destination'] ?? '') . ')***';
                    $order->set_shipping_company($shipping_company);
                    $order->set_shipping_address_1(esc_html($selectedPoint['street']));
                    $order->set_shipping_city(esc_html($selectedPoint['city']));
                    $order->set_shipping_postcode(esc_html($selectedPoint['sa_zipcode']));
                    $order->save();
                }
            }
        }

        public function show_point_details_in_customer($order)
        {
            $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
            $pointDetailsRaw = get_post_meta($order_id, self::$configuration['post_meta_field_name'], true);
            if (!$pointDetailsRaw) {
                return;
            }
            $pointDetails = json_decode($pointDetailsRaw, true);
            $title = __('ΕΠΙΛΕΓΜΕΝΟ ACS SmartPoint', 'smartpoints-lockers-acs');
            $stationName = esc_html($pointDetails['name']);
            $stationCode = esc_html($pointDetails['Acs_Station_Destination'] ?? '') . esc_html($pointDetails['Acs_Station_Branch_Destination'] ?? '');
            $stationStreet = esc_html($pointDetails['street']);
            $stationCity = esc_html($pointDetails['city']);
            $stationArea = esc_html($pointDetails['area']);
            $stationZipcode = esc_html($pointDetails['sa_zipcode']);
            echo '<h3>' . esc_html($title) . '</h3>
            <p><a href="https://maps.google.com?q=' . esc_html($pointDetails['lat'] ?? '') . ',' . esc_html($pointDetails['lon'] ?? '') . '" target="_blank" style="color: #999;">
            ' . esc_html($stationName) . ' (' . esc_html($stationCode) . ')
            </a></p>';
        }

        public function show_point_details_in_admin($order)
        {
            return $this->show_point_details_in_customer($order);
        }

        public static function fetch_points_json($force = true)
        {
            $cached = get_transient(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-points');
            if (!$force && $cached !== false) {
                return $cached;
            }

            $settings = self::getSettings();

            $data = [
                'ACSAlias' => 'ACS_Get_Stations_For_Plugin',
                'ACSInputParameters' => [
                    'locale' => null,
                    'Company_ID' => sanitize_text_field($settings['acsCompanyID'] ?? null),
                    'Company_Password' => sanitize_text_field($settings['acsCompanyPassword'] ?? null),
                    'User_ID' => sanitize_text_field($settings['acsUserID'] ?? null),
                    'User_Password' => sanitize_text_field($settings['acsUserPassword'] ?? null),
                ],
            ];

            $args = array(
                'timeout' => 10,
                'body' => wp_json_encode($data),
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'ACSApiKey' => sanitize_text_field($settings['acsApiKey'] ?? null),
                ),
            );
            $responseRaw = wp_remote_post('https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest', $args);
            $httpCode = wp_remote_retrieve_response_code($responseRaw);
            $response = wp_remote_retrieve_body($responseRaw);
            $response = json_decode($response, true);

            $points = $response['ACSOutputResponce']['ACSTableOutput']['Table_Data1'] ?? [];

            $points = array_values(array_filter($points, function ($item) {
                return $item['type'] !== 'branch' || $item['Acs_Station_Branch_Destination'] == '1';
            }));

            $data = [
                'status_code' => $httpCode,
                'meta' => $response['ACSOutputResponce']['ACSTableOutput']['Table_Data'] ?? [],
                'points' => $points,
            ];
            set_transient(SMARTPOINTS_LOCKERS_ACS_PLUGIN_ID . '-points', $data, 60 * 30);

            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . '/wp-admin/includes/file.php');
                WP_Filesystem();
            }

            $wp_filesystem->put_contents(
                SMARTPOINTS_LOCKERS_ACS_PLUGIN_DIR . 'data.json',
                wp_json_encode([
                    'timestamp' => wp_date('Y-m-d H:i'),
                    'meta' => $response['ACSOutputResponce']['ACSTableOutput']['Table_Data'] ?? [],
                    'points' => $points,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            return $data;
        }
    }

    (new SmartPointsLockersAcsPlugin);
}
// Add Paid Support link to plugin row meta
add_filter('plugin_row_meta', 'spacs_plugin_meta', 10, 2);
function spacs_plugin_meta($links, $file) {
    // Ensure this is only applied to the specific plugin
    if ($file === plugin_basename(__FILE__)) {
        $support_url = esc_url('https://headplus.gr/product/ypostirixi-prostheton/');       
        $support_text = esc_html(__('Need Paid Support?', 'smartpoints-lockers-acs'));
        $row_meta = array(
            '<a href="' . $support_url . '" target="_blank"><span class="dashicons dashicons-money-alt"></span> ' . $support_text . '</a>',
        );

        $links = array_merge($links, $row_meta);
    }

    return $links;
}
