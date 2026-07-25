<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Catalog_Mode {

        public function is_guest_catalog_mode() {
            if ( ! $this->is_catalog_mode_enabled() ) {
                return false;
            }

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

        public function is_catalog_mode_enabled() {
            return (bool) get_option( 'moly_b2b_commerce_catalog_mode_enabled', true );
        }

        public function get_default_allowed_roles() {
            $roles = wp_roles();
            return $roles ? array_keys( $roles->roles ) : array();
        }

        public function get_access_mode() {
            $mode = get_option( 'moly_b2b_commerce_access_mode', 'authenticated' );
            return in_array( $mode, array( 'authenticated', 'roles_or_groups', 'roles_only', 'groups_only' ), true ) ? $mode : 'authenticated';
        }

        public function get_allowed_roles() {
            $roles = get_option( 'moly_b2b_commerce_allowed_roles', null );
            if ( null === $roles ) {
                return $this->get_default_allowed_roles();
            }
            if ( ! is_array( $roles ) ) {
                $roles = maybe_unserialize( $roles );
            }
            return array_filter( (array) $roles );
        }

        public function filter_price_html( $price, $product ) {
            if ( ! $this->is_guest_catalog_mode() ) {
                return $price;
            }

            $label = is_user_logged_in()
                ? __( 'Price unavailable for this account', 'moly-b2b-commerce' )
                : trim( $this->get_price_label() );
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

        public function get_header_message() {
            $default = __( 'To see prices, variants and proceed to purchase you must log in.', 'moly-b2b-commerce' );
            return trim( (string) get_option( 'moly_b2b_commerce_header_message', $default ) );
        }

        public function get_header_button_url() {
            return trim( (string) get_option( 'moly_b2b_commerce_header_button_url', wp_login_url() ) );
        }

        public function filter_catalog_product_price( $price, $product ) {
            return $this->is_guest_catalog_mode() ? '' : $price;
        }

        public function filter_catalog_variation_price( $price, $variation, $product ) {
            return $this->is_guest_catalog_mode() ? '' : $price;
        }

        public function filter_catalog_structured_product_data( $markup, $product ) {
            if ( $this->is_guest_catalog_mode() && is_array( $markup ) ) {
                unset( $markup['offers'] );
            }
            return $markup;
        }

        public function protect_catalog_store_api_request( $result, $server, $request ) {
            if ( null !== $result || ! $this->is_guest_catalog_mode() || ! $request instanceof WP_REST_Request ) {
                return $result;
            }

            $route = $request->get_route();
            if ( ! preg_match( '#^/wc/store/v[0-9]+/#', $route ) ) {
                return $result;
            }

            if ( preg_match( '#^/wc/store/v[0-9]+/products(?:/|$)#', $route ) ) {
                foreach ( array( 'min_price', 'max_price' ) as $parameter ) {
                    $request->set_param( $parameter, null );
                }
                if ( 'price' === $request->get_param( 'orderby' ) ) {
                    $request->set_param( 'orderby', 'date' );
                }
                $request->set_param( 'calculate_price_range', false );
                $request->set_param( 'return_price_range', false );
            }

            $method = strtoupper( $request->get_method() );
            if ( ! in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) && preg_match( '#^/wc/store/v[0-9]+/(?:cart|checkout)(?:/|$)#', $route ) ) {
                return new WP_Error(
                    'moly_b2b_commerce_catalog_mode',
                    $this->get_catalog_purchase_blocked_message(),
                    array( 'status' => 403 )
                );
            }

            return $result;
        }

        public function filter_catalog_store_api_response( $response, $server, $request ) {
            if ( ! $this->is_guest_catalog_mode() || ! $request instanceof WP_REST_Request ) {
                return $response;
            }

            $route = $request->get_route();
            if ( ! preg_match( '#^/wc/store/v[0-9]+/(?:products|cart|checkout)(?:/|$)#', $route ) || is_wp_error( $response ) ) {
                return $response;
            }

            $response = rest_ensure_response( $response );
            $response->set_data( $this->redact_catalog_store_api_data( $response->get_data() ) );
            return $response;
        }

        public function redact_catalog_store_api_data( $data, $context = '' ) {
            $was_object = is_object( $data );
            if ( $was_object ) {
                $data = get_object_vars( $data );
            }
            if ( ! is_array( $data ) ) {
                return $data;
            }

            $currency_keys = array(
                'currency_code',
                'currency_symbol',
                'currency_minor_unit',
                'currency_decimal_separator',
                'currency_thousand_separator',
                'currency_prefix',
                'currency_suffix',
            );

            foreach ( $data as $key => $value ) {
                if ( 'is_purchasable' === $key ) {
                    $data[ $key ] = false;
                    continue;
                }
                if ( 'price_range' === $key || 'raw_prices' === $key ) {
                    $data[ $key ] = null;
                    continue;
                }
                if ( 'prices' === $key || 'totals' === $key ) {
                    $data[ $key ] = $this->redact_catalog_store_api_data( $value, $key );
                    continue;
                }
                if ( in_array( $context, array( 'prices', 'totals' ), true ) && ! in_array( $key, $currency_keys, true ) ) {
                    $data[ $key ] = is_array( $value ) ? array() : '';
                    continue;
                }
                $data[ $key ] = $this->redact_catalog_store_api_data( $value, $context );
            }

            return $was_object ? (object) $data : $data;
        }

        public function get_catalog_purchase_blocked_message() {
            if ( is_user_logged_in() ) {
                return __( 'Your account is not enabled to view prices or make purchases.', 'moly-b2b-commerce' );
            }
            return __( 'You must be logged in to add products to the cart.', 'moly-b2b-commerce' );
        }

        public function validate_catalog_checkout( $data, $errors ) {
            if ( $this->is_guest_catalog_mode() && $errors instanceof WP_Error ) {
                $errors->add( 'moly_b2b_commerce_catalog_mode', $this->get_catalog_purchase_blocked_message() );
            }
        }

        public function disable_purchasable( $purchasable, $product ) {
            if ( $this->is_guest_catalog_mode() ) {
                return false;
            }

            return $purchasable;
        }

        public function prevent_add_to_cart( $passed, $product_id, $quantity ) {
            if ( $this->is_guest_catalog_mode() ) {
                wc_add_notice( esc_html( $this->get_catalog_purchase_blocked_message() ), 'error' );
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

            if ( is_user_logged_in() ) {
                $message = esc_html__( 'Your account is not enabled to view prices, variants or make purchases.', 'moly-b2b-commerce' );
            } else {
                $header_message    = $this->get_header_message();
                $header_button_url = $this->get_header_button_url();
                if ( '' === $header_message ) {
                    return;
                }

                $message = esc_html( $header_message );
                if ( '' !== $header_button_url ) {
                    $message .= sprintf(
                        ' <a href="%s">%s</a>',
                        esc_url( $header_button_url ),
                        esc_html__( 'Log in', 'moly-b2b-commerce' )
                    );
                }
            }

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
                $redirect_url = is_user_logged_in()
                    ? wc_get_page_permalink( 'myaccount' )
                    : wp_login_url( wc_get_cart_url() );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
}
