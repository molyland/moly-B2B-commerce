<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Admin {

        public function register_admin_menu() {
            // The top-level Moly B2B menu opens the Settings page.
            add_menu_page(
                __( 'Moly B2B', 'moly-b2b-commerce' ),
                __( 'Moly B2B', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce',
                array( $this, 'render_settings_page' ),
                'dashicons-groups',
                56
            );

            // Settings submenu (only price label)
            add_submenu_page(
                'moly-b2b-commerce',
                __( 'Settings', 'moly-b2b-commerce' ),
                __( 'Settings', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce',
                array( $this, 'render_settings_page' )
            );

            // Roles submenu (roles and allowed groups configuration)
            add_submenu_page(
                'moly-b2b-commerce',
                __( 'Roles', 'moly-b2b-commerce' ),
                __( 'Roles', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce-roles',
                array( $this, 'render_roles_page' )
            );

            add_submenu_page(
                'moly-b2b-commerce',
                __( 'Moly B2B Groups', 'moly-b2b-commerce' ),
                __( 'Groups', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce-groups',
                array( $this, 'render_groups_page' )
            );

            add_submenu_page(
                'moly-b2b-commerce',
                __( 'B2B Fields', 'moly-b2b-commerce' ),
                __( 'B2B Fields', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce-b2b-fields',
                array( $this, 'render_b2b_fields_page' )
            );

            add_submenu_page(
                'moly-b2b-commerce',
                __( 'Discount Rules', 'moly-b2b-commerce' ),
                __( 'Discounts', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce-discounts',
                array( $this, 'render_discount_rules_page' )
            );
        }

        public function register_settings() {
            register_setting( 'moly-b2b-commerce-settings', 'moly_b2b_commerce_catalog_mode_enabled', array(
                'type'              => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_catalog_mode_enabled' ),
                'default'           => true,
            ) );

            register_setting( 'moly-b2b-commerce-settings', 'moly_b2b_commerce_price_label', array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => __( 'Log in to see the price', 'moly-b2b-commerce' ),
            ) );

            register_setting( 'moly-b2b-commerce-settings', 'moly_b2b_commerce_header_message', array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => __( 'To see prices, variants and proceed to purchase you must log in.', 'moly-b2b-commerce' ),
            ) );

            register_setting( 'moly-b2b-commerce-settings', 'moly_b2b_commerce_header_button_url', array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => wp_login_url(),
            ) );

            register_setting( 'moly-b2b-commerce-discounts-settings', 'moly_b2b_commerce_discount_rules', array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_discount_rules' ),
                'default'           => array(),
            ) );
            add_settings_section(
                'woocommerce_b2b_moly_section',
                __( 'Settings', 'moly-b2b-commerce' ),
                function() {
                    echo '<p>' . esc_html__( 'Configure catalog mode and the texts shown to visitors and unauthorized users.', 'moly-b2b-commerce' ) . '</p>';
                },
                'moly-b2b-commerce-settings'
            );

            add_settings_field(
                'moly_b2b_commerce_catalog_mode_enabled',
                __( 'Catalog mode', 'moly-b2b-commerce' ),
                array( $this, 'render_catalog_mode_enabled_field' ),
                'moly-b2b-commerce-settings',
                'woocommerce_b2b_moly_section'
            );

            add_settings_field(
                'moly_b2b_commerce_price_label',
                __( 'Hidden price label', 'moly-b2b-commerce' ),
                array( $this, 'render_price_label_field' ),
                'moly-b2b-commerce-settings',
                'woocommerce_b2b_moly_section'
            );

            add_settings_field(
                'moly_b2b_commerce_header_message',
                __( 'Header Message', 'moly-b2b-commerce' ),
                array( $this, 'render_header_message_field' ),
                'moly-b2b-commerce-settings',
                'woocommerce_b2b_moly_section'
            );

            add_settings_field(
                'moly_b2b_commerce_header_button_url',
                __( 'Header Button URL', 'moly-b2b-commerce' ),
                array( $this, 'render_header_button_url_field' ),
                'moly-b2b-commerce-settings',
                'woocommerce_b2b_moly_section'
            );

            // roles/groups configuration moved to the Roles page
            // note: roles/groups configuration moved to Roles page
        }

        public function register_roles_settings() {
            register_setting( 'moly-b2b-commerce-roles-settings', 'moly_b2b_commerce_allowed_roles', array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_allowed_roles' ),
                'default'           => $this->get_default_allowed_roles(),
            ) );

            register_setting( 'moly-b2b-commerce-roles-settings', 'moly_b2b_commerce_access_mode', array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_access_mode' ),
                'default'           => 'authenticated',
            ) );

            add_settings_section(
                'woocommerce_b2b_moly_roles_section',
                __( 'Permissions configuration', 'moly-b2b-commerce' ),
                function() {
                    echo '<p>' . esc_html__( 'Select roles allowed to view prices.', 'moly-b2b-commerce' ) . '</p>';
                },
                'moly-b2b-commerce-roles-settings'
            );

            add_settings_field(
                'moly_b2b_commerce_access_mode',
                __( 'Price access mode', 'moly-b2b-commerce' ),
                array( $this, 'render_access_mode_field' ),
                'moly-b2b-commerce-roles-settings',
                'woocommerce_b2b_moly_roles_section'
            );

            add_settings_field(
                'moly_b2b_commerce_allowed_roles',
                __( 'Roles allowed to view prices', 'moly-b2b-commerce' ),
                array( $this, 'render_allowed_roles_field' ),
                'moly-b2b-commerce-roles-settings',
                'woocommerce_b2b_moly_roles_section'
            );
        }

        public function render_catalog_mode_enabled_field() {
            printf(
                '<label><input type="checkbox" name="moly_b2b_commerce_catalog_mode_enabled" value="1" %s /> %s</label>',
                checked( $this->is_catalog_mode_enabled(), true, false ),
                esc_html__( 'Hide prices and purchasing functions from visitors and unauthorized users.', 'moly-b2b-commerce' )
            );
        }

        public function sanitize_catalog_mode_enabled( $value ) {
            return ! empty( $value );
        }

        public function render_price_label_field() {
            $value = $this->get_price_label();
            printf(
                '<input type="text" name="moly_b2b_commerce_price_label" value="%s" class="regular-text" />',
                esc_attr( $value )
            );
        }

        public function render_header_message_field() {
            printf(
                '<input type="text" name="moly_b2b_commerce_header_message" value="%s" class="large-text" /><p class="description">%s</p>',
                esc_attr( $this->get_header_message() ),
                esc_html__( 'The notice is hidden when this field is empty. The access link is optional.', 'moly-b2b-commerce' )
            );
        }

        public function render_header_button_url_field() {
            printf(
                '<input type="url" name="moly_b2b_commerce_header_button_url" value="%s" class="large-text" placeholder="https://" />',
                esc_attr( $this->get_header_button_url() )
            );
        }

        public function render_access_mode_field() {
            $selected = $this->get_access_mode();
            $modes = array(
                'authenticated'   => __( 'All authenticated users', 'moly-b2b-commerce' ),
                'roles_or_groups' => __( 'Allowed roles or groups', 'moly-b2b-commerce' ),
                'roles_only'      => __( 'Allowed roles only', 'moly-b2b-commerce' ),
                'groups_only'     => __( 'Allowed groups only', 'moly-b2b-commerce' ),
            );
            echo '<select name="moly_b2b_commerce_access_mode">';
            foreach ( $modes as $value => $label ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
            }
            echo '</select>';
        }

        public function sanitize_access_mode( $value ) {
            $allowed = array( 'authenticated', 'roles_or_groups', 'roles_only', 'groups_only' );
            return in_array( $value, $allowed, true ) ? $value : 'authenticated';
        }

        public function render_allowed_roles_field() {
            if ( ! function_exists( 'get_editable_roles' ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }

            $roles = get_editable_roles();
            $selected = $this->get_allowed_roles();

            foreach ( $roles as $role_key => $role ) {
                $checked = in_array( $role_key, $selected, true ) ? 'checked' : '';
                printf(
                    '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="moly_b2b_commerce_allowed_roles[]" value="%s" %s /> %s</label>',
                    esc_attr( $role_key ),
                    $checked,
                    esc_html( $role['name'] )
                );
            }
            echo '<p class="description">' . esc_html__( 'Used by access modes that include roles.', 'moly-b2b-commerce' ) . '</p>';
        }

        public function sanitize_allowed_roles( $input ) {
            if ( ! is_array( $input ) ) {
                return array();
            }

            if ( ! function_exists( 'get_editable_roles' ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }

            $all_roles = array_keys( get_editable_roles() );
            $out = array();
            foreach ( $input as $role ) {
                if ( in_array( $role, $all_roles, true ) ) {
                    $out[] = $role;
                }
            }
            return $out;
        }

        public function render_settings_page() {
            echo '<div class="wrap">';
            echo '<form method="post" action="options.php">';
            settings_fields( 'moly-b2b-commerce-settings' );
            do_settings_sections( 'moly-b2b-commerce-settings' );
            submit_button();
            echo '</form>';
            echo '</div>';
        }

        public function render_admin_page_header() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
            $plugin_pages = array(
                'moly-b2b-commerce',
                'moly-b2b-commerce-roles',
                'moly-b2b-commerce-groups',
                'moly-b2b-commerce-b2b-fields',
                'moly-b2b-commerce-discounts',
            );
            if ( ! in_array( $page, $plugin_pages, true ) ) {
                return;
            }
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $data = get_plugin_data( MOLY_B2B_COMMERCE_PLUGIN_FILE );

            echo '<div class="wrap moly-b2b-commerce-info-header">';
            echo '<h1>' . esc_html( $data['Name'] ) . '</h1>';
            printf( '<p><strong>%s:</strong> %s</p>', esc_html__( 'Version', 'moly-b2b-commerce' ), esc_html( $data['Version'] ) );
            echo '<p>' . esc_html__( 'Catalog mode for WooCommerce: hides prices, quantities, variations and blocks purchases for non-logged users.', 'moly-b2b-commerce' ) . '</p>';
            echo '</div>';
        }

        public function render_roles_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            echo '<div class="wrap">';
            echo '<form method="post" action="options.php">';
            settings_fields( 'moly-b2b-commerce-roles-settings' );
            do_settings_sections( 'moly-b2b-commerce-roles-settings' );
            submit_button();
            echo '</form>';
            echo '</div>';
        }

        public function add_action_links( $links ) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url( admin_url( 'admin.php?page=moly-b2b-commerce' ) ),
                esc_html__( 'Settings', 'moly-b2b-commerce' )
            );

            array_unshift( $links, $settings_link );
            return $links;
        }
}
