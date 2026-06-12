<?php
defined( 'ABSPATH' ) || exit;

class SF_Locker_Order {

    public static function init() {
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'admin_order_display_locker' ) );
        add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_display_locker' ), 10, 4 );
        add_filter( 'woocommerce_order_shipping_to_display', array( __CLASS__, 'shipping_to_display' ), 10, 2 );
    }

    public static function admin_order_display_locker( $order ) {
        $locker_code = $order->get_meta( '_sf_locker_code' );
        $locker_address = $order->get_meta( '_sf_locker_address' );

        if ( empty( $locker_code ) ) {
            return;
        }

        echo '<div class="sf-locker-admin-info" style="margin-top: 12px; padding: 10px; background: #f0f8ff; border-left: 4px solid #007cba;">';
        echo '<strong>順豐智能櫃取件</strong>';
        echo '<p style="margin: 4px 0 0; font-size: 13px;">';
        echo '櫃機編號：' . esc_html( $locker_code ) . '<br>';
        echo '地址：' . esc_html( $locker_address );
        echo '</p>';
        echo '</div>';
    }

    public static function email_display_locker( $order, $sent_to_admin, $plain_text, $email ) {
        $locker_code = $order->get_meta( '_sf_locker_code' );
        $locker_address = $order->get_meta( '_sf_locker_address' );
        $locker_district = $order->get_meta( '_sf_locker_district' );

        if ( empty( $locker_code ) ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n---\n";
            echo "順豐智能櫃取件\n";
            echo "櫃機編號：{$locker_code}\n";
            echo "地址：{$locker_address}\n";
            if ( ! empty( $locker_district ) ) {
                echo "地區：{$locker_district}\n";
            }
            echo "---\n";
        } else {
            echo '<div style="margin-top: 20px; padding: 12px; background: #f0f8ff; border-left: 4px solid #007cba;">';
            echo '<h4 style="margin: 0 0 8px;">順豐智能櫃取件</h4>';
            echo '<p style="margin: 0; font-size: 13px;">';
            echo '櫃機編號：<strong>' . esc_html( $locker_code ) . '</strong><br>';
            echo '地址：' . esc_html( $locker_address );
            if ( ! empty( $locker_district ) ) {
                echo '<br>地區：' . esc_html( $locker_district );
            }
            echo '</p>';
            echo '</div>';
        }
    }

    public static function shipping_to_display( $shipping_display, $order ) {
        $locker_code    = $order->get_meta( '_sf_locker_code' );
        $locker_address = $order->get_meta( '_sf_locker_address' );

        if ( ! empty( $locker_address ) && ! empty( $locker_code ) ) {
            return $locker_address . ' (' . $locker_code . ')';
        }

        if ( ! empty( $locker_address ) ) {
            return $locker_address;
        }

        return $shipping_display;
    }
}
