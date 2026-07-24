<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Order_Discounts {

    public function save_b2b_discount_order_meta( $order_id, $data = array() ) {
        $this->store_b2b_discount_snapshot( wc_get_order( $order_id ) );
    }

    public function save_b2b_discount_order_meta_store_api( $order ) {
        $this->store_b2b_discount_snapshot( $order );
    }

    private function store_b2b_discount_snapshot( $order ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart || ! $order instanceof WC_Order ) {
            return;
        }

        $applied = $this->get_applicable_discount_details( WC()->cart );
        if ( empty( $applied ) ) {
            $order->delete_meta_data( '_moly_b2b_commerce_discount_snapshot' );
            $order->delete_meta_data( '_moly_b2b_commerce_discount_rule_ids' );
            $order->delete_meta_data( '_moly_b2b_commerce_discount_total' );
            $order->save();
            return;
        }

        $rule_ids = array();
        $snapshot = array();
        $total    = 0.0;
        foreach ( $applied as $detail ) {
            $rule      = $detail['rule'];
            $amount       = round( (float) $detail['amount'], wc_get_price_decimals() );
            $is_user_rule = ! empty( $rule['user_id'] );
            $rule_ids[]   = $rule['id'];
            $total     += $amount;
            $snapshot[] = array(
                'rule_id'        => sanitize_key( $rule['id'] ),
                'rule_name'      => sanitize_text_field( $rule['name'] ),
                'discount_type'  => sanitize_key( $rule['discount_type'] ),
                'discount_value' => (float) $rule['discount_value'],
                'scope'          => sanitize_key( $rule['scope'] ),
                'scope_target'   => sanitize_text_field( $rule['scope_target'] ),
                'target_type'    => $is_user_rule ? 'user' : 'group',
                'target_id'      => $is_user_rule ? absint( $rule['user_id'] ) : sanitize_key( $rule['group_slug'] ),
                'priority'       => (int) $rule['priority'],
                'stack'          => ! empty( $rule['stack'] ),
                'amount'         => $amount,
                'product_id'     => isset( $detail['product_id'] ) ? absint( $detail['product_id'] ) : 0,
                'product_name'   => isset( $detail['product_name'] ) ? sanitize_text_field( $detail['product_name'] ) : '',
                'quantity'       => isset( $detail['quantity'] ) ? absint( $detail['quantity'] ) : 0,
            );
        }

        $order->update_meta_data( '_moly_b2b_commerce_discount_snapshot', $snapshot );
        $order->update_meta_data( '_moly_b2b_commerce_discount_rule_ids', array_values( array_unique( $rule_ids ) ) );
        $order->update_meta_data( '_moly_b2b_commerce_discount_total', wc_format_decimal( $total, wc_get_price_decimals() ) );
        $order->save();
    }

    public function display_order_b2b_discount( $order ) {
        $snapshot = $order->get_meta( '_moly_b2b_commerce_discount_snapshot', true );
        $total    = $order->get_meta( '_moly_b2b_commerce_discount_total', true );
        if ( (float) $total <= 0 ) {
            return;
        }

        $names = array();
        if ( is_array( $snapshot ) ) {
            foreach ( $snapshot as $item ) {
                if ( ! empty( $item['rule_name'] ) ) {
                    $names[] = $item['rule_name'];
                }
            }
        } else {
            $rule_ids = (array) $order->get_meta( '_moly_b2b_commerce_discount_rule_ids', true );
            foreach ( $this->get_discount_rules() as $rule ) {
                if ( in_array( $rule['id'], $rule_ids, true ) ) {
                    $names[] = $rule['name'];
                }
            }
        }

        $names         = array_values( array_unique( $names ) );
        $display_names = ! empty( $names ) ? implode( ', ', $names ) : esc_html__( 'Custom B2B discount', 'moly-b2b-commerce' );
        echo '<div class="moly-b2b-commerce-order-b2b-fields"><h3>' . esc_html__( 'B2B Discounts', 'moly-b2b-commerce' ) . '</h3><p><strong>' . esc_html( $display_names ) . ':</strong> ' . wp_kses_post( wc_price( (float) $total ) ) . '</p></div>';
    }
}
