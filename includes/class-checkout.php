<?php
defined( 'ABSPATH' ) || exit;

class SF_Locker_Checkout {

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'woocommerce_review_order_after_shipping', array( __CLASS__, 'render_locker_selector' ) );
        add_action( 'woocommerce_review_order_before_order_total', array( __CLASS__, 'render_shipping_row' ) );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_locker_meta' ), 10, 2 );
        add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_locker_selection' ), 10, 2 );
        add_action( 'wc_ajax_sf_search_lockers', array( __CLASS__, 'ajax_search_lockers' ) );
        add_action( 'wc_ajax_sf_get_districts', array( __CLASS__, 'ajax_get_districts' ) );
        add_action( 'wc_ajax_sf_get_locker', array( __CLASS__, 'ajax_get_locker' ) );
        add_action( 'wc_ajax_sf_get_districts_by_region', array( __CLASS__, 'ajax_get_districts_by_region' ) );
    }

    public static function enqueue_assets() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_style(
            'sf-locker-selector',
            SF_LOCKER_URL . 'assets/css/locker-selector.css',
            array(),
            SF_LOCKER_VERSION
        );

        wp_enqueue_script(
            'sf-locker-selector',
            SF_LOCKER_URL . 'assets/js/locker-selector.js',
            array( 'jquery' ),
            SF_LOCKER_VERSION,
            true
        );

        wp_localize_script( 'sf-locker-selector', 'sfLockerData', array(
            'ajax_url'  => WC_AJAX::get_endpoint( '%%endpoint%%' ),
        ) );
    }

    public static function render_locker_selector() {
        $chosen_method = isset( WC()->session->chosen_shipping_methods )
            ? WC()->session->chosen_shipping_methods[0]
            : '';

        if ( strpos( $chosen_method, 'sf_express_locker' ) === false ) {
            return;
        }

        $selected_code = WC()->session->get( 'sf_locker_code', '' );
        $selected_locker = null;
        if ( $selected_code ) {
            $selected_locker = SF_Locker_Data::get_by_code( $selected_code );
        }

        $regions = SF_Locker_Data::get_regions();

        include SF_LOCKER_PATH . 'templates/locker-selector.php';
    }

    public static function render_shipping_row() {
        if ( ! WC()->cart->needs_shipping() ) {
            return;
        }

        $shipping_total = WC()->cart->get_shipping_total();
        $shipping_tax   = WC()->cart->get_shipping_tax();

        if ( $shipping_total <= 0 && $shipping_tax <= 0 ) {
            return;
        }

        $total = $shipping_total + $shipping_tax;

        echo '<tr class="woocommerce-shipping-total"><th>運送費用</th><td>' . wc_price( $total ) . '</td></tr>';
    }

    public static function validate_locker_selection( $data, $errors ) {
        $shipping_method = isset( $_POST['shipping_method'] ) ? (array) $_POST['shipping_method'] : array();

        $is_locker_method = false;
        foreach ( $shipping_method as $method ) {
            if ( strpos( $method, 'sf_express_locker' ) !== false ) {
                $is_locker_method = true;
                break;
            }
        }

        if ( ! $is_locker_method ) {
            return;
        }

        $locker_code = isset( $_POST['sf_locker_code'] ) ? sanitize_text_field( $_POST['sf_locker_code'] ) : '';
        if ( empty( $locker_code ) ) {
            $errors->add( 'sf_locker_required', '請選擇一個順豐智能櫃。' );
            return;
        }

        $locker = SF_Locker_Data::get_by_code( $locker_code );
        if ( ! $locker ) {
            $errors->add( 'sf_locker_invalid', '所選的智能櫃無效，請重新選擇。' );
        }
    }

    public static function save_locker_meta( $order, $data ) {
        $shipping_methods = $order->get_shipping_methods();
        $is_locker = false;

        foreach ( $shipping_methods as $method ) {
            if ( strpos( $method->get_method_id(), 'sf_express_locker' ) !== false ) {
                $is_locker = true;
                break;
            }
        }

        if ( ! $is_locker ) {
            return;
        }

        $locker_code = isset( $_POST['sf_locker_code'] ) ? sanitize_text_field( $_POST['sf_locker_code'] ) : '';

        if ( ! empty( $locker_code ) ) {
            $locker = SF_Locker_Data::get_by_code( $locker_code );
            if ( $locker ) {
                $order->update_meta_data( '_sf_locker_code', $locker['code'] );
                $order->update_meta_data( '_sf_locker_address', $locker['address_zh'] );
                $order->update_meta_data( '_sf_locker_district', $locker['district'] );
            }
        }
    }

    public static function ajax_get_districts() {
        check_ajax_referer( 'sf-locker-search', 'security' );

        $districts = SF_Locker_Data::get_districts();
        wp_send_json_success( $districts );
    }

    public static function ajax_get_districts_by_region() {
        check_ajax_referer( 'sf-locker-search', 'security' );

        $region = isset( $_POST['region'] ) ? sanitize_text_field( $_POST['region'] ) : '';
        $districts = SF_Locker_Data::get_districts_by_region( $region );
        wp_send_json_success( $districts );
    }

    public static function ajax_get_locker() {
        check_ajax_referer( 'sf-locker-search', 'security' );

        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
        if ( empty( $code ) ) {
            wp_send_json_error( array( 'message' => '缺少智能櫃編碼' ) );
        }

        $locker = SF_Locker_Data::get_by_code( $code );
        if ( ! $locker ) {
            wp_send_json_error( array( 'message' => '找不到智能櫃' ) );
        }

        wp_send_json_success( $locker );
    }

    public static function ajax_search_lockers() {
        check_ajax_referer( 'sf-locker-search', 'security' );

        $district = isset( $_POST['district'] ) ? sanitize_text_field( $_POST['district'] ) : '';

        $lockers = SF_Locker_Data::search( '', $district, 200 );

        $html = '';
        foreach ( $lockers as $locker ) {
            $hours_display = ! empty( $locker['opening_hours'] ) ? ' (' . $locker['opening_hours'] . ')' : '';
            $html .= '<li class="sf-locker-item" data-code="' . esc_attr( $locker['code'] ) . '"
                           data-address="' . esc_attr( $locker['address_zh'] ) . '"
                           data-district="' . esc_attr( $locker['district'] ) . '">
                         <span class="sf-locker-name">' . esc_html( $locker['district'] ) . ' — ' . esc_html( $locker['address_zh'] ) . $hours_display . '</span>
                         <span class="sf-locker-code">' . esc_html( $locker['code'] ) . '</span>
                       </li>';
        }

        if ( empty( $lockers ) ) {
            $html .= '<li class="sf-locker-no-results">沒有符合的智能櫃</li>';
        }

        wp_send_json_success( array(
            'html'  => $html,
            'count' => count( $lockers ),
        ) );
    }
}
