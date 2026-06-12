<?php
defined( 'ABSPATH' ) || exit;

class SF_Locker_Shipping_Method extends WC_Shipping_Method {

    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'sf_express_locker';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = '順豐智能櫃自取 (SF Express Locker)';
        $this->method_description = '客戶可選擇順豐智能櫃地址作為送貨方式。';
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    public static function register_filters() {
        add_filter( 'woocommerce_shipping_instance_form_fields_sf_express_locker', array( __CLASS__, 'instance_form_fields' ) );
    }

    public static function instance_form_fields( $fields ) {
        $fields['base_fee'] = array(
            'title'       => '首 1kg 運費',
            'type'        => 'price',
            'description' => '首 1kg 的運費。設定為 0 即首重免費。',
            'default'     => '0',
            'desc_tip'    => true,
        );

        $fields['per_kg_rate'] = array(
            'title'       => '其後每公斤運費',
            'type'        => 'price',
            'description' => '超過首重後，每公斤（不足 1kg 亦當 1kg 計）的運費。',
            'default'     => '0',
            'desc_tip'    => true,
        );

        $fields['max_weight'] = array(
            'title'       => '最高重量限制 (kg)',
            'type'        => 'number',
            'description' => '訂單總重量超過此值時，智能櫃將不顯示為可選方式。通常智能櫃限重 20kg。',
            'default'     => '20',
            'desc_tip'    => true,
            'custom_attributes' => array(
                'min'  => '0',
                'step' => '0.1',
            ),
        );

        $fields['free_shipping_threshold'] = array(
            'title'       => '免運費門檻',
            'type'        => 'price',
            'description' => '訂單金額達到此金額時免費。留空則不使用。',
            'default'     => '',
            'desc_tip'    => true,
        );

        return $fields;
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title             = $this->get_option( 'title', '順豐智能櫃自取' );
        $this->base_fee          = (float) $this->get_option( 'base_fee', 0 );
        $this->per_kg_rate       = (float) $this->get_option( 'per_kg_rate', 0 );
        $this->max_weight        = (float) $this->get_option( 'max_weight', 20 );
        $this->free_threshold    = $this->get_option( 'free_shipping_threshold', '' );

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'title' => array(
                'title'       => '顯示名稱',
                'type'        => 'text',
                'description' => '客戶在結帳時看到的名稱。',
                'default'     => '順豐智能櫃自取',
                'desc_tip'    => true,
            ),
        );
    }

    private function get_weight_in_kg() {
        $weight = WC()->cart ? WC()->cart->get_cart_contents_weight() : 0;
        $weight = max( 0, (float) $weight );

        $unit = get_option( 'woocommerce_weight_unit', 'kg' );

        switch ( $unit ) {
            case 'g':
                $weight /= 1000;
                break;
            case 'lbs':
                $weight *= 0.45359237;
                break;
            case 'oz':
                $weight *= 0.0283495;
                break;
        }

        return $weight;
    }

    public function calculate_shipping( $package = array() ) {
        $weight = $this->get_weight_in_kg();

        $rate_cost = $this->base_fee;

        if ( $weight > 1 ) {
            $rate_cost += ceil( $weight - 1 ) * $this->per_kg_rate;
        }

        $rate_cost = max( 0, $rate_cost );

        if ( ! empty( $this->free_threshold ) ) {
            $cart_total = WC()->cart->get_subtotal();
            if ( $cart_total >= (float) $this->free_threshold ) {
                $rate_cost = 0;
            }
        }

        $this->add_rate( array(
            'id'        => $this->get_rate_id(),
            'label'     => $this->title,
            'cost'      => $rate_cost,
            'package'   => $package,
        ) );
    }

    public function is_available( $package ) {
        $is_available = parent::is_available( $package );

        if ( $is_available ) {
            $has_lockers = SF_Locker_Data::get_count() > 0;
            if ( ! $has_lockers ) {
                $is_available = false;
            }
        }

        if ( $is_available && $this->max_weight > 0 ) {
            $weight = $this->get_weight_in_kg();
            if ( $weight > $this->max_weight ) {
                $is_available = false;
            }
        }

        return $is_available;
    }
}
