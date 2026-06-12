<?php
/**
 * Plugin Name: SF Express Locker for WooCommerce
 * Plugin URI: https://localhost/gikgoods
 * Description: 在 WooCommerce 結帳時讓客戶選擇順豐智能櫃地址自取
 * Version: 1.3.0
 * Author: Your Name
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.0.1
 * Text Domain: sf-express-locker
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SF_LOCKER_VERSION', '1.3.0' );
define( 'SF_LOCKER_FILE', __FILE__ );
define( 'SF_LOCKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SF_LOCKER_URL', plugin_dir_url( __FILE__ ) );
define( 'SF_LOCKER_TABLE', 'sf_lockers' );
define( 'SF_LOCKER_PDF_URL', 'https://htm.sf-express.com/hk/tc/download/SF-Locker-Mailing-Service-Applicable-Locker-List_TC.pdf' );

final class SF_Locker_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
    }

    public function declare_wc_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SF_LOCKER_FILE, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SF_LOCKER_FILE, false );
        }
    }

    public function init() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
            return;
        }

        $this->load_dependencies();
        $this->init_hooks();
        $this->init_vendor();
        $this->fix_default_country();

        add_action( 'admin_init', array( $this, 'auto_setup' ) );
    }

    public function missing_woocommerce_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e( 'SF Express Locker 需要安裝並啟用 WooCommerce 才能使用。', 'sf-express-locker' ); ?></p>
        </div>
        <?php
    }

    private function load_dependencies() {
        require_once SF_LOCKER_PATH . 'includes/class-locker-data.php';
        require_once SF_LOCKER_PATH . 'includes/class-shipping-method.php';
        require_once SF_LOCKER_PATH . 'includes/class-checkout.php';
        require_once SF_LOCKER_PATH . 'includes/class-order.php';
        require_once SF_LOCKER_PATH . 'includes/class-pdf-importer.php';

        if ( is_admin() ) {
            require_once SF_LOCKER_PATH . 'includes/class-admin-page.php';
        }
    }

    private function init_hooks() {
        add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

        SF_Locker_Checkout::init();
        SF_Locker_Order::init();
        SF_Locker_Shipping_Method::register_filters();

        add_action( 'wp', array( $this, 'clear_shipping_cache' ), 0 );
        add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'remove_shipping_cost_from_label' ), 10, 2 );

        if ( is_admin() ) {
            SF_Locker_Admin_Page::init();
        }

        add_action( 'admin_init', array( $this, 'auto_import_check' ) );
        add_action( 'sf_locker_daily_maintenance', array( $this, 'run_auto_import' ) );
    }

    public function clear_shipping_cache() {
        if ( ! is_checkout() || is_wc_endpoint_url() ) {
            return;
        }
        if ( ! WC()->session ) {
            return;
        }
        for ( $i = 0; $i < 10; $i++ ) {
            WC()->session->__unset( 'shipping_for_package_' . $i );
        }
    }

    public function remove_shipping_cost_from_label( $label, $rate ) {
        return $rate->get_label();
    }

    private function init_vendor() {
        $vendor_autoload = SF_LOCKER_PATH . 'vendor/autoload.php';
        if ( file_exists( $vendor_autoload ) ) {
            require_once $vendor_autoload;
        }
    }

    public function fix_default_country() {
        add_filter( 'default_checkout_billing_country', function( $country ) {
            return 'HK';
        } );
        add_filter( 'default_checkout_shipping_country', function( $country ) {
            return 'HK';
        } );

        add_action( 'wp', array( $this, 'set_default_shipping_country' ), 20 );
    }

    public function set_default_shipping_country() {
        if ( ! is_checkout() || is_wc_endpoint_url() ) {
            return;
        }

        $customer = WC()->customer;
        if ( $customer && empty( $customer->get_shipping_country() ) ) {
            $customer->set_shipping_country( 'HK' );
            $customer->set_shipping_state( 'KOWLOON' );
            $customer->save();
        }
    }

    public function register_shipping_method( $methods ) {
        $methods['sf_express_locker'] = 'SF_Locker_Shipping_Method';
        return $methods;
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'sf_locker_daily_maintenance' );
        delete_option( 'sf_locker_setup_pending' );
    }

    public function auto_setup() {
        if ( ! get_option( 'sf_locker_setup_pending' ) ) {
            return;
        }

        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        update_option( 'woocommerce_default_country', 'HK:KOWLOON' );
        update_option( 'woocommerce_default_customer_address', 'base' );
        update_option( 'woocommerce_allowed_countries', 'specific' );
        update_option( 'woocommerce_specific_allowed_countries', array( 'HK' => 'HK' ) );
        update_option( 'woocommerce_ship_to_countries', 'specific' );
        update_option( 'woocommerce_specific_ship_to_countries', array( 'HK' => 'HK' ) );

        $existing = null;
        foreach ( WC_Shipping_Zones::get_zones() as $zone_data ) {
            if ( 'Hong Kong' === $zone_data['zone_name'] ) {
                $existing = new WC_Shipping_Zone( $zone_data['zone_id'] );
                break;
            }
        }

        if ( $existing ) {
            $zone = $existing;
        } else {
            $zone = new WC_Shipping_Zone();
            $zone->set_zone_name( 'Hong Kong' );
            $zone->set_zone_order( 0 );
        }

        $zone->add_location( 'HK', 'country' );
        $zone->save();

        $has_locker_method = false;
        foreach ( $zone->get_shipping_methods() as $method ) {
            if ( 'sf_express_locker' === $method->id ) {
                $has_locker_method = true;
                break;
            }
        }

        if ( ! $has_locker_method ) {
            $instance_id = $zone->add_shipping_method( 'sf_express_locker' );
            if ( $instance_id ) {
                update_option( 'woocommerce_sf_express_locker_' . $instance_id . '_settings', array(
                    'cost'                   => '0',
                    'free_shipping_threshold' => '',
                ) );
            }
        }

        delete_option( 'sf_locker_setup_pending' );
    }

    public function auto_import_check() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $last = get_option( 'sf_locker_last_auto_import', 0 );
        if ( is_numeric( $last ) && ( time() - (int) $last ) < DAY_IN_SECONDS ) {
            return;
        }

        $this->run_auto_import();
    }

    public function run_auto_import() {
        if ( ! class_exists( 'Smalot\PdfParser\Parser' ) ) {
            return;
        }

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp_file = download_url( SF_LOCKER_PDF_URL, 60 );
        if ( is_wp_error( $tmp_file ) ) {
            return;
        }

        $importer = new SF_Locker_PDF_Importer();
        $result = $importer->import( $tmp_file );
        @unlink( $tmp_file );

        if ( false !== $result ) {
            update_option( 'sf_locker_last_updated', current_time( 'Y-m-d H:i:s' ) );
        }

        update_option( 'sf_locker_last_auto_import', time() );
    }
}

register_activation_hook( SF_LOCKER_FILE, 'sf_locker_activate' );
register_deactivation_hook( SF_LOCKER_FILE, array( 'SF_Locker_Plugin', 'deactivate' ) );

function sf_locker_activate() {
    require_once SF_LOCKER_PATH . 'includes/class-locker-data.php';

    SF_Locker_Data::create_table();
    SF_Locker_Data::import_bundled_data();

    if ( ! wp_next_scheduled( 'sf_locker_daily_maintenance' ) ) {
        wp_schedule_event( time(), 'daily', 'sf_locker_daily_maintenance' );
    }

    add_option( 'sf_locker_setup_pending', 1 );
}

function sf_locker_adjust_flat_rate_cost( $rates ) {
    $locker_settings = null;
    global $wpdb;
    $options = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            'woocommerce_sf_express_locker_%_settings'
        )
    );
    foreach ( $options as $option ) {
        $settings = maybe_unserialize( $option->option_value );
        if ( is_array( $settings ) ) {
            $locker_settings = $settings;
            break;
        }
    }
    if ( ! $locker_settings ) {
        return $rates;
    }
    $base_fee    = (float) ( $locker_settings['base_fee'] ?? 0 );
    $per_kg_rate = (float) ( $locker_settings['per_kg_rate'] ?? 0 );

    $weight = WC()->cart ? WC()->cart->get_cart_contents_weight() : 0;
    $weight = max( 0, (float) $weight );
    $unit   = get_option( 'woocommerce_weight_unit', 'kg' );
    switch ( $unit ) {
        case 'g':  $weight /= 1000; break;
        case 'lbs': $weight *= 0.45359237; break;
        case 'oz':  $weight *= 0.0283495; break;
    }

    foreach ( $rates as $rate_id => $rate ) {
        if ( strpos( $rate_id, 'flat_rate' ) === false ) {
            continue;
        }
        $cost = $base_fee;
        if ( $weight > 1 ) {
            $cost += ceil( $weight - 1 ) * $per_kg_rate;
        }
        $cost = max( 0, $cost );
        $free_ths = $locker_settings['free_shipping_threshold'] ?? '';
        if ( $free_ths !== '' && (float) $free_ths > 0 ) {
            $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
            if ( $cart_total >= (float) $free_ths ) {
                $cost = 0;
            }
        }
        $rates[ $rate_id ]->cost = $cost;
    }
    return $rates;
}
add_filter( 'woocommerce_package_rates', 'sf_locker_adjust_flat_rate_cost', 100, 1 );

SF_Locker_Plugin::instance();
