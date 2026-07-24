<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Pricing {

    public function calculate_runtime_product_price( $product, $base_price = null ) {
        if ( ! $product instanceof WC_Product ) {
            return array( 'base_price' => 0.0, 'price' => 0.0, 'rules' => array() );
        }

        if ( $base_price === null || $base_price === '' ) {
            $base_price = $product->get_price( 'edit' );
        }
        if ( $base_price === '' ) {
            return array( 'base_price' => '', 'price' => '', 'rules' => array() );
        }

        $base_price = (float) $base_price;
        $price      = $base_price;
        $rules      = $this->get_applicable_product_discount_rules( $product->get_id() );
        foreach ( $rules as $rule ) {
            $price *= 1 - ( (float) $rule['discount_value'] / 100 );
        }

        return array(
            'base_price' => $base_price,
            'price'      => max( 0, round( $price, wc_get_price_decimals() ) ),
            'rules'      => $rules,
        );
    }

    public function filter_runtime_product_price( $price, $product ) {
        if ( ( is_admin() && ! wp_doing_ajax() ) || ! is_user_logged_in() || $price === '' ) {
            return $price;
        }
        $details = $this->calculate_runtime_product_price( $product );
        return $details['price'];
    }

    public function filter_runtime_variation_price( $price, $variation, $product ) {
        if ( ! is_user_logged_in() || $price === '' ) {
            return $price;
        }
        $details = $this->calculate_runtime_product_price( $variation, $price );
        return $details['price'];
    }

    public function add_runtime_price_hash( $hash, $product, $for_display ) {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $hash['moly_b2b_commerce_user']   = $user->ID;
            $hash['moly_b2b_commerce_groups'] = $this->get_user_groups( $user->ID );
            $hash['moly_b2b_commerce_rules']  = md5( wp_json_encode( $this->get_discount_rules() ) );
        }
        return $hash;
    }

    public function format_runtime_price_range( $prices ) {
        $min = min( $prices );
        $max = max( $prices );
        return $min !== $max ? wc_format_price_range( $min, $max ) : wc_price( $min );
    }

    public function filter_discounted_price_html( $price_html, $product ) {
        if ( $this->is_guest_catalog_mode() || ! is_user_logged_in() ) {
            return $price_html;
        }

        if ( $product->is_type( 'variable' ) ) {
            $base_prices       = array();
            $discounted_prices = array();
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) {
                    continue;
                }
                $details = $this->calculate_runtime_product_price( $variation );
                if ( $details['base_price'] === '' ) {
                    continue;
                }
                $base_prices[]       = wc_get_price_to_display( $variation, array( 'price' => $details['base_price'] ) );
                $discounted_prices[] = wc_get_price_to_display( $variation, array( 'price' => $details['price'] ) );
            }
            if ( empty( $base_prices ) || $base_prices === $discounted_prices ) {
                return $price_html;
            }
            return '<del>' . wp_kses_post( $this->format_runtime_price_range( $base_prices ) ) . '</del> <ins>' . wp_kses_post( $this->format_runtime_price_range( $discounted_prices ) ) . '</ins>';
        }

        $details = $this->calculate_runtime_product_price( $product );
        if ( $details['base_price'] === '' || $details['price'] >= $details['base_price'] ) {
            return $price_html;
        }

        $base_display       = wc_get_price_to_display( $product, array( 'price' => $details['base_price'] ) );
        $discounted_display = wc_get_price_to_display( $product, array( 'price' => $details['price'] ) );
        return wc_format_sale_price( $base_display, $discounted_display ) . $product->get_price_suffix( $details['price'] );
    }

    public function apply_b2b_order_discounts( $cart ) {
        if ( ( is_admin() && ! wp_doing_ajax() ) || ! is_user_logged_in() ) {
            return;
        }

        $subtotal = (float) $cart->get_subtotal();
        if ( $subtotal <= 0 ) {
            return;
        }

        $remaining = $subtotal;
        foreach ( $this->get_applicable_fixed_discount_rules( $subtotal ) as $rule ) {
            $amount = min( max( 0, (float) $rule['discount_value'] ), $remaining );
            if ( $amount <= 0 ) {
                continue;
            }
            $cart->add_fee( sprintf( '%s: %s', __( 'B2B discount', 'moly-b2b-commerce' ), $rule['name'] ), -round( $amount, wc_get_price_decimals() ), false );
            $remaining -= $amount;
        }
    }

    public function get_applicable_discount_details( $cart = null ) {
        if ( $cart === null ) {
            $cart = function_exists( 'WC' ) ? WC()->cart : null;
        }
        if ( ! $cart || ! is_user_logged_in() ) {
            return array();
        }

        $details = array();
        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'] ?? null;
            if ( ! $product ) {
                continue;
            }

            $base_price = $product->get_price( 'edit' );
            if ( $base_price === '' ) {
                continue;
            }
            $current_price = (float) $base_price;
            foreach ( $this->get_applicable_product_discount_rules( $product->get_id() ) as $rule ) {
                $next_price = $current_price * ( 1 - ( (float) $rule['discount_value'] / 100 ) );
                $details[] = array(
                    'rule'          => $rule,
                    'amount'        => round( max( 0, $current_price - $next_price ) * (int) $cart_item['quantity'], wc_get_price_decimals() ),
                    'cart_item_key' => $cart_item['key'],
                    'product_id'    => $product->get_id(),
                    'product_name'  => $product->get_name(),
                    'quantity'      => (int) $cart_item['quantity'],
                );
                $current_price = $next_price;
            }
        }

        $subtotal = (float) $cart->get_subtotal();
        $remaining = $subtotal;
        foreach ( $this->get_applicable_fixed_discount_rules( $subtotal ) as $rule ) {
            $amount = min( max( 0, (float) $rule['discount_value'] ), $remaining );
            if ( $amount > 0 ) {
                $details[] = array( 'rule' => $rule, 'amount' => round( $amount, wc_get_price_decimals() ) );
                $remaining -= $amount;
            }
        }
        return $details;
    }
}