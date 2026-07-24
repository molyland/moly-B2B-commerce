<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Catalog_Mode {

        public function is_guest_catalog_mode() {
            if ( ! is_user_logged_in() ) {
                return true;
            }

            $mode = $this->get_access_mode();
            if ( $mode === 'authenticated' ) {
                return false;
            }

            $user           = wp_get_current_user();
            $allowed_roles  = $this->get_allowed_roles();
            $allowed_groups = $this->get_allowed_groups();
            $role_match     = ! empty( array_intersect( (array) $user->roles, $allowed_roles ) );
            $group_match    = ! empty( array_intersect( $this->get_user_groups( $user->ID ), $allowed_groups ) );

            if ( $mode === 'roles_only' ) {
                return ! $role_match;
            }
            if ( $mode === 'groups_only' ) {
                return ! $group_match;
            }
            return ! ( $role_match || $group_match );
        }

        public function get_access_mode() {
            $mode = get_option( 'moly_b2b_commerce_access_mode', 'authenticated' );
            return in_array( $mode, array( 'authenticated', 'roles_or_groups', 'roles_only', 'groups_only' ), true ) ? $mode : 'authenticated';
        }

        public function get_allowed_roles() {
            $roles = get_option( 'moly_b2b_commerce_allowed_roles', array() );
            if ( ! is_array( $roles ) ) {
                $roles = maybe_unserialize( $roles );
            }
            return array_filter( (array) $roles );
        }

        public function filter_price_html( $price, $product ) {
            if ( ! $this->is_guest_catalog_mode() ) {
                return $price;
            }

            $label = trim( $this->get_price_label() );
            if ( $label === '' ) {
                return '';
            }

            return sprintf(
                '<span class="moly-b2b-commerce-price-hidden">%s</span>',
                esc_html( $label )
            );
        }

        public function get_price_label() {
            $default = __( 'Log in to see the price', 'moly-b2b-commerce' );
            return get_option( 'moly_b2b_commerce_price_label', $default );
        }

        public function disable_purchasable( $purchasable, $product ) {
            if ( $this->is_guest_catalog_mode() ) {
                return false;
            }

            return $purchasable;
        }

        public function prevent_add_to_cart( $passed, $product_id, $quantity ) {
            if ( $this->is_guest_catalog_mode() ) {
                wc_add_notice( esc_html__( 'You must be logged in to add products to the cart.', 'moly-b2b-commerce' ), 'error' );
                return false;
            }

            return $passed;
        }

        public function hide_quantity_input( $args, $product ) {
            if ( ! $this->is_guest_catalog_mode() ) {
                return $args;
            }

            $args['input_value'] = 1;
            $args['min_value']   = 1;
            $args['max_value']   = 1;
            $args['style']       = isset( $args['style'] ) ? $args['style'] . ' display:none!important;' : 'display:none!important;';
            return $args;
        }

        public function hide_variation_options( $variation, $product, $variation_object ) {
            if ( ! $this->is_guest_catalog_mode() ) {
                return $variation;
            }

            $variation['is_purchasable']      = false;
            $variation['variation_is_active'] = false;

            return $variation;
        }

        public function remove_purchase_actions() {
            if ( ! $this->is_guest_catalog_mode() ) {
                return;
            }

            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 20 );
        }

        public function register_styles() {
            if ( ! $this->is_guest_catalog_mode() ) {
                return;
            }

            wp_enqueue_style( 'moly-b2b-commerce', false );
            $css = '
                .woocommerce .woocommerce-Price-amount,
                .woocommerce div.product form.cart .button,
                .woocommerce div.product .quantity,
                .woocommerce div.product .single_variation_wrap,
                .woocommerce .single_add_to_cart_button,
                .woocommerce div.product .variations_form .single_variation_wrap,
                .woocommerce div.product .variations_form .woocommerce-variation-add-to-cart {
                    display: none !important;
                }

                .moly-b2b-commerce-login-notice {
                    margin: 0 0 1.5em;
                    padding: 1em 1.2em;
                    border: 1px solid #ccd0d4;
                    background-color: #fff8dc;
                    color: #333;
                }

                .moly-b2b-commerce-login-notice a {
                    color: #21759b;
                    text-decoration: underline;
                }
            ';
            wp_add_inline_style( 'moly-b2b-commerce', $css );
        }

        public function output_login_notice() {
            if ( ! $this->is_guest_catalog_mode() ) {
                return;
            }

            $login_url = wp_login_url( get_permalink() );
            $message = sprintf(
                esc_html__( 'To see prices, variants and proceed to purchase you must log in. %sLog in%s', 'moly-b2b-commerce' ),
                '<a href="' . esc_url( $login_url ) . '">',
                '</a>'
            );

            echo '<div class="moly-b2b-commerce-login-notice">' . wp_kses_post( $message ) . '</div>';
        }

        public function redirect_guest_checkout() {
            if ( ! $this->is_guest_catalog_mode() ) {
                return;
            }

            if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
                return;
            }

            if ( is_cart() || is_checkout() ) {
                wp_safe_redirect( wp_login_url( wc_get_cart_url() ) );
                exit;
            }
        }
}
