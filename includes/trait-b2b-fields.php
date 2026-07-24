<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_B2B_Fields {

        public function get_b2b_custom_fields( $enabled_only = true ) {
            $fields = get_option( 'moly_b2b_commerce_b2b_fields', array() );
            if ( ! is_array( $fields ) ) {
                $fields = maybe_unserialize( $fields );
            }
            $fields = array_values( (array) $fields );

            usort( $fields, function( $a, $b ) {
                $a_order = isset( $a['order'] ) ? intval( $a['order'] ) : 0;
                $b_order = isset( $b['order'] ) ? intval( $b['order'] ) : 0;
                if ( $a_order === $b_order ) {
                    return strcmp( sanitize_text_field( $a['label'] ?? '' ), sanitize_text_field( $b['label'] ?? '' ) );
                }
                return $a_order < $b_order ? -1 : 1;
            } );

            if ( ! $enabled_only ) {
                return $fields;
            }

            return array_values( array_filter( $fields, function( $field ) {
                return ! isset( $field['enabled'] ) || ! empty( $field['enabled'] );
            } ) );
        }

        public function get_b2b_builtin_fields() {
            return array(
                array(
                    'label' => __( 'VAT ID', 'moly-b2b-commerce' ),
                    'slug'  => 'vat_id',
                    'type'  => 'text',
                ),
                array(
                    'label' => __( 'Codice fiscale', 'moly-b2b-commerce' ),
                    'slug'  => 'codice_fiscale',
                    'type'  => 'text',
                ),
                array(
                    'label' => __( 'PEC', 'moly-b2b-commerce' ),
                    'slug'  => 'pec',
                    'type'  => 'text',
                ),
                array(
                    'label' => __( 'SDI', 'moly-b2b-commerce' ),
                    'slug'  => 'sdi',
                    'type'  => 'text',
                ),
            );
        }

        public function get_b2b_builtin_field_settings() {
            $settings = get_option( 'moly_b2b_commerce_b2b_builtin_fields', array() );
            if ( ! is_array( $settings ) ) {
                $settings = maybe_unserialize( $settings );
            }
            $settings = (array) $settings;

            $formatted = array();
            foreach ( $this->get_b2b_builtin_fields() as $field ) {
                $slug = $field['slug'];
                if ( isset( $settings[ $slug ] ) && is_array( $settings[ $slug ] ) ) {
                    $formatted[ $slug ] = array(
                        'enabled'  => ! empty( $settings[ $slug ]['enabled'] ),
                        'required' => ! empty( $settings[ $slug ]['required'] ),
                    );
                } else {
                    $formatted[ $slug ] = array(
                        'enabled'  => true,
                        'required' => false,
                    );
                }
            }

            return $formatted;
        }

        public function get_enabled_b2b_builtin_fields() {
            $settings = $this->get_b2b_builtin_field_settings();
            $fields   = $this->get_b2b_builtin_fields();
            $enabled  = array();

            foreach ( $fields as $field ) {
                $slug = $field['slug'];
                if ( ! array_key_exists( $slug, $settings ) ) {
                    $field['enabled']  = true;
                    $field['required'] = false;
                } else {
                    $field['enabled']  = ! empty( $settings[ $slug ]['enabled'] );
                    $field['required'] = ! empty( $settings[ $slug ]['required'] );
                }
                if ( $field['enabled'] ) {
                    $enabled[] = $field;
                }
            }

            return $enabled;
        }

        public function get_b2b_fields() {
            return array_merge( $this->get_b2b_custom_fields(), $this->get_enabled_b2b_builtin_fields() );
        }

        public function get_b2b_field_type_options() {
            return array(
                'text'     => __( 'Text', 'moly-b2b-commerce' ),
                'textarea' => __( 'Text area', 'moly-b2b-commerce' ),
                'email'    => __( 'Email', 'moly-b2b-commerce' ),
                'tel'      => __( 'Phone', 'moly-b2b-commerce' ),
            );
        }

        public function is_b2b_field_required( $field ) {
            return ! empty( $field['required'] );
        }

        public function save_b2b_builtin_field_setting() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $slug = isset( $_POST['b2b_field_slug'] ) ? sanitize_title( wp_unslash( $_POST['b2b_field_slug'] ) ) : '';
            if ( $slug === '' ) {
                return;
            }

            $builtin_fields = $this->get_b2b_builtin_fields();
            $valid_slugs = wp_list_pluck( $builtin_fields, 'slug' );
            if ( ! in_array( $slug, $valid_slugs, true ) ) {
                return;
            }

            $settings = $this->get_b2b_builtin_field_settings();
            $settings[ $slug ] = array(
                'enabled'  => isset( $_POST['b2b_field_enabled'] ) ? 1 : 0,
                'required' => isset( $_POST['b2b_field_required'] ) ? 1 : 0,
            );
            update_option( 'moly_b2b_commerce_b2b_builtin_fields', $settings );
        }

        public function render_b2b_fields_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( isset( $_POST['moly_b2b_commerce_save_b2b_fields'] ) && check_admin_referer( 'moly_b2b_commerce_save_b2b_fields', 'moly_b2b_commerce_nonce_b2b' ) ) {
                $this->save_b2b_custom_fields_settings();
            }

            if ( isset( $_POST['moly_b2b_commerce_save_builtin_fields'] ) && check_admin_referer( 'moly_b2b_commerce_save_builtin_fields', 'moly_b2b_commerce_nonce_builtin' ) ) {
                $this->save_b2b_builtin_field_settings();
            }

            if ( isset( $_POST['moly_b2b_commerce_save_builtin_field'] ) && check_admin_referer( 'moly_b2b_commerce_save_builtin_field', 'moly_b2b_commerce_nonce_builtin_field' ) ) {
                $this->save_b2b_builtin_field_setting();
                wp_redirect( remove_query_arg( 'edit_b2b_builtin_field' ) );
                exit;
            }

            if ( isset( $_POST['moly_b2b_commerce_save_custom_field'] ) && check_admin_referer( 'moly_b2b_commerce_save_custom_field', 'moly_b2b_commerce_nonce_custom_field' ) ) {
                $this->save_b2b_custom_field_settings();
                $custom_fields = $this->get_b2b_custom_fields( false );
                wp_redirect( remove_query_arg( 'edit_b2b_field' ) );
                exit;
            }

            if ( isset( $_GET['delete_b2b_field'] ) && isset( $_GET['_wpnonce'] ) ) {
                $nonce = $_GET['_wpnonce'];
                $slug = sanitize_title( wp_unslash( $_GET['delete_b2b_field'] ) );
                if ( wp_verify_nonce( $nonce, 'moly_b2b_commerce_delete_b2b_field_' . $slug ) ) {
                    $this->delete_b2b_field( $slug );
                }
            }

            $custom_fields      = $this->get_b2b_custom_fields( false );
            $builtin_fields     = $this->get_b2b_builtin_fields();
            $builtin_field_info = $this->get_b2b_builtin_field_settings();
            $type_options       = $this->get_b2b_field_type_options();
            $edit_field         = null;
            $edit_builtin_field = null;
            if ( isset( $_GET['edit_b2b_field'] ) ) {
                $slug = sanitize_title( wp_unslash( $_GET['edit_b2b_field'] ) );
                foreach ( $custom_fields as $field ) {
                    if ( $field['slug'] === $slug ) {
                        $edit_field = $field;
                        break;
                    }
                }
            }
            if ( isset( $_GET['edit_b2b_builtin_field'] ) ) {
                $slug = sanitize_title( wp_unslash( $_GET['edit_b2b_builtin_field'] ) );
                foreach ( $builtin_fields as $field ) {
                    if ( $field['slug'] === $slug ) {
                        $edit_builtin_field = $field;
                        $edit_builtin_field['enabled'] = array_key_exists( $slug, $builtin_field_info ) ? ! empty( $builtin_field_info[ $slug ]['enabled'] ) : true;
                        $edit_builtin_field['required'] = array_key_exists( $slug, $builtin_field_info ) ? ! empty( $builtin_field_info[ $slug ]['required'] ) : false;
                        break;
                    }
                }
            }

            echo '<div class="wrap">';
            echo '<style>.moly-b2b-commerce-custom-fields-table input.regular-text, .moly-b2b-commerce-custom-fields-table select { max-width: 10rem; }
.moly-b2b-commerce-custom-fields-table input.small-text { max-width: 5rem; }
.moly-b2b-commerce-custom-fields-table th, .moly-b2b-commerce-custom-fields-table td { vertical-align: middle; white-space: nowrap; }
.moly-b2b-commerce-custom-fields-table td input[type=text], .moly-b2b-commerce-custom-fields-table td select { margin: 0; }</style>';

            echo '<h2 style="margin-top:24px;">' . esc_html__( 'Built-in B2B fields', 'moly-b2b-commerce' ) . '</h2>';
            echo '<p>' . esc_html__( 'Built-in B2B fields are enabled by default. Edit a field below to change only its enabled or required state.', 'moly-b2b-commerce' ) . '</p>';
            echo '<table class="widefat fixed"><thead><tr><th>' . esc_html__( 'Label', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Slug', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Type', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Enabled', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Required', 'moly-b2b-commerce' ) . '</th><th></th></tr></thead><tbody>';
            foreach ( $builtin_fields as $field ) {
                $slug = $field['slug'];
                if ( array_key_exists( $slug, $builtin_field_info ) ) {
                    $enabled = ! empty( $builtin_field_info[ $slug ]['enabled'] );
                    $required = ! empty( $builtin_field_info[ $slug ]['required'] );
                } else {
                    $enabled = true;
                    $required = false;
                }
                $type_label = isset( $type_options[ $field['type'] ] ) ? $type_options[ $field['type'] ] : ucfirst( $field['type'] );
                $enabled_icon  = $enabled ? '&#10004;' : '&#10006;';
                $required_icon = $required ? '&#10004;' : '&#10006;';
                $edit_url = add_query_arg( array( 'edit_b2b_builtin_field' => $slug ) );
                echo '<tr>';
                echo '<td>' . esc_html( $field['label'] ) . '</td>';
                echo '<td>' . esc_html( $slug ) . '</td>';
                echo '<td>' . esc_html( $type_label ) . '</td>';
                echo '<td><span aria-label="' . esc_attr__( 'Enabled', 'moly-b2b-commerce' ) . '">' . $enabled_icon . '</span></td>';
                echo '<td><span aria-label="' . esc_attr__( 'Required', 'moly-b2b-commerce' ) . '">' . $required_icon . '</span></td>';
                echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-secondary">' . esc_html__( 'Edit', 'moly-b2b-commerce' ) . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '<h2>' . esc_html__( 'Custom B2B fields', 'moly-b2b-commerce' ) . '</h2>';
            echo '<p>' . esc_html__( 'Use the form below to add a new custom field or edit an existing one.', 'moly-b2b-commerce' ) . '</p>';
            if ( empty( $custom_fields ) ) {
                echo '<p>' . esc_html__( 'No custom fields created.', 'moly-b2b-commerce' ) . '</p>';
            } else {
                echo '<table class="widefat fixed moly-b2b-commerce-custom-fields-table"><thead><tr><th>' . esc_html__( 'Label', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Slug', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Type', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Order', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Enabled', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Required', 'moly-b2b-commerce' ) . '</th><th></th></tr></thead><tbody>';
                foreach ( $custom_fields as $field ) {
                    $enabled_icon  = ! empty( $field['enabled'] ) ? '&#10004;' : '&#10006;';
                    $required_icon = ! empty( $field['required'] ) ? '&#10004;' : '&#10006;';
                    $order    = isset( $field['order'] ) ? intval( $field['order'] ) : 0;
                    $type_label = isset( $type_options[ $field['type'] ] ) ? $type_options[ $field['type'] ] : esc_html__( ucfirst( $field['type'] ), 'moly-b2b-commerce' );
                    $edit_url = add_query_arg( array( 'edit_b2b_field' => $field['slug'] ) );
                    $del_url  = wp_nonce_url( add_query_arg( 'delete_b2b_field', $field['slug'] ), 'moly_b2b_commerce_delete_b2b_field_' . $field['slug'] );

                    echo '<tr>';
                    echo '<td>' . esc_html( $field['label'] ) . '</td>';
                    echo '<td>' . esc_html( $field['slug'] ) . '</td>';
                    echo '<td>' . esc_html( $type_label ) . '</td>';
                    echo '<td>' . esc_html( $order ) . '</td>';
                    echo '<td><span aria-label="' . esc_attr__( 'Enabled', 'moly-b2b-commerce' ) . '">' . $enabled_icon . '</span></td>';
                    echo '<td><span aria-label="' . esc_attr__( 'Required', 'moly-b2b-commerce' ) . '">' . $required_icon . '</span></td>';
                    echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-secondary">' . esc_html__( 'Edit', 'moly-b2b-commerce' ) . '</a> <a href="' . esc_url( $del_url ) . '" class="button button-secondary">' . esc_html__( 'Delete', 'moly-b2b-commerce' ) . '</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            $is_builtin_edit = ! empty( $edit_builtin_field );
            $original_slug = $is_builtin_edit ? $edit_builtin_field['slug'] : ( $edit_field['slug'] ?? '' );
            $label = $is_builtin_edit ? $edit_builtin_field['label'] : ( $edit_field['label'] ?? '' );
            $slug = $is_builtin_edit ? $edit_builtin_field['slug'] : ( $edit_field['slug'] ?? '' );
            $type = $is_builtin_edit ? $edit_builtin_field['type'] : ( $edit_field['type'] ?? 'text' );
            $order = isset( $edit_field['order'] ) ? intval( $edit_field['order'] ) : 0;
            $enabled = $is_builtin_edit ? ! empty( $edit_builtin_field['enabled'] ) : ! empty( $edit_field['enabled'] );
            $required = $is_builtin_edit ? ! empty( $edit_builtin_field['required'] ) : ! empty( $edit_field['required'] );

            $form_title = $is_builtin_edit ? __( 'Edit built-in field', 'moly-b2b-commerce' ) : ( $original_slug ? __( 'Edit custom field', 'moly-b2b-commerce' ) : __( 'Add custom field', 'moly-b2b-commerce' ) );
            $form_description = $is_builtin_edit ? __( 'Modify built-in field visibility and requirement. Only enabled and required can be changed.', 'moly-b2b-commerce' ) : __( 'Use the form below to add a new custom field or edit an existing one.', 'moly-b2b-commerce' );
            echo '<h2 style="margin-top:24px;">' . esc_html( $form_title ) . '</h2>';
            echo '<p>' . esc_html( $form_description ) . '</p>';
            echo '<form method="post">';
            wp_nonce_field( $is_builtin_edit ? 'moly_b2b_commerce_save_builtin_field' : 'moly_b2b_commerce_save_custom_field', $is_builtin_edit ? 'moly_b2b_commerce_nonce_builtin_field' : 'moly_b2b_commerce_nonce_custom_field' );
            echo '<table class="form-table"><tbody>';

            if ( $is_builtin_edit ) {
                echo '<tr><th>' . esc_html__( 'Label', 'moly-b2b-commerce' ) . '</th><td>' . esc_html( $label ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Slug', 'moly-b2b-commerce' ) . '</th><td>' . esc_html( $slug ) . '<input type="hidden" name="b2b_field_slug" value="' . esc_attr( $slug ) . '" /></td></tr>';
                echo '<tr><th>' . esc_html__( 'Type', 'moly-b2b-commerce' ) . '</th><td>' . esc_html( isset( $type_options[ $type ] ) ? $type_options[ $type ] : ucfirst( $type ) ) . '</td></tr>';
            } else {
                echo '<tr><th><label for="b2b_field_label">' . esc_html__( 'Label', 'moly-b2b-commerce' ) . '</label></th><td><input type="text" id="b2b_field_label" name="b2b_field_label" class="regular-text" value="' . esc_attr( $label ) . '" /></td></tr>';
                echo '<tr><th><label for="b2b_field_slug">' . esc_html__( 'Slug', 'moly-b2b-commerce' ) . '</label></th><td><input type="text" id="b2b_field_slug" name="b2b_field_slug" class="regular-text" value="' . esc_attr( $slug ) . '" /></td></tr>';
                echo '<tr><th><label for="b2b_field_type">' . esc_html__( 'Type', 'moly-b2b-commerce' ) . '</label></th><td><select id="b2b_field_type" name="b2b_field_type">';
                foreach ( $type_options as $value => $type_label ) {
                    $selected = selected( $type, $value, false );
                    printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $type_label ) );
                }
                echo '</select></td></tr>';
                echo '<tr><th><label for="b2b_field_order">' . esc_html__( 'Order', 'moly-b2b-commerce' ) . '</label></th><td><input type="number" id="b2b_field_order" name="b2b_field_order" class="small-text" value="' . esc_attr( $order ) . '" /></td></tr>';
            }

            echo '<tr><th><label for="b2b_field_enabled">' . esc_html__( 'Enabled', 'moly-b2b-commerce' ) . '</label></th><td><label><input type="checkbox" id="b2b_field_enabled" name="b2b_field_enabled" value="1" ' . ( $enabled ? 'checked' : '' ) . ' /> ' . esc_html__( 'Yes', 'moly-b2b-commerce' ) . '</label></td></tr>';
            echo '<tr><th><label for="b2b_field_required">' . esc_html__( 'Required', 'moly-b2b-commerce' ) . '</label></th><td><label><input type="checkbox" id="b2b_field_required" name="b2b_field_required" value="1" ' . ( $required ? 'checked' : '' ) . ' /> ' . esc_html__( 'Yes', 'moly-b2b-commerce' ) . '</label></td></tr>';
            echo '</tbody></table>';
            $button_label = $is_builtin_edit ? __( 'Update built-in field', 'moly-b2b-commerce' ) : ( $original_slug ? __( 'Update custom field', 'moly-b2b-commerce' ) : __( 'Add custom field', 'moly-b2b-commerce' ) );
            echo '<p class="submit">' . ( $original_slug && ! $is_builtin_edit ? '<input type="hidden" name="b2b_field_original_slug" value="' . esc_attr( $original_slug ) . '" />' : '' );
            submit_button( $button_label, 'primary', $is_builtin_edit ? 'moly_b2b_commerce_save_builtin_field' : 'moly_b2b_commerce_save_custom_field', false, array( 'id' => false ) );
            echo ' <a href="' . esc_url( remove_query_arg( array( 'edit_b2b_field', 'edit_b2b_builtin_field' ) ) ) . '" class="button button-secondary">' . esc_html__( 'Cancel', 'moly-b2b-commerce' ) . '</a>';
            echo '</p>';
            echo '</form>';

            echo '</div>';
        }

        public function add_b2b_field( $label, $slug, $type = 'text', $enabled = 1, $required = 0 ) {
            $fields = $this->get_b2b_custom_fields( false );
            $builtin_slugs = wp_list_pluck( $this->get_b2b_builtin_fields(), 'slug' );
            foreach ( $fields as $field ) {
                if ( isset( $field['slug'] ) && $field['slug'] === $slug ) {
                    return false;
                }
            }
            if ( in_array( $slug, $builtin_slugs, true ) ) {
                return false;
            }

            $order = 0;
            foreach ( $fields as $field ) {
                $order = max( $order, isset( $field['order'] ) ? intval( $field['order'] ) : 0 );
            }

            $fields[] = array(
                'label'    => $label,
                'slug'     => $slug,
                'type'     => in_array( $type, array( 'text', 'textarea', 'email', 'tel' ), true ) ? $type : 'text',
                'enabled'  => $enabled ? 1 : 0,
                'required' => $required ? 1 : 0,
                'order'    => $order + 1,
            );
            update_option( 'moly_b2b_commerce_b2b_fields', $fields );
            return true;
        }

        public function save_b2b_custom_field_settings() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $label = isset( $_POST['b2b_field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['b2b_field_label'] ) ) : '';
            $slug = isset( $_POST['b2b_field_slug'] ) ? sanitize_title( wp_unslash( $_POST['b2b_field_slug'] ) ) : '';
            $type = isset( $_POST['b2b_field_type'] ) ? sanitize_text_field( wp_unslash( $_POST['b2b_field_type'] ) ) : 'text';
            $order = isset( $_POST['b2b_field_order'] ) ? intval( $_POST['b2b_field_order'] ) : 0;
            $enabled = isset( $_POST['b2b_field_enabled'] ) ? 1 : 0;
            $required = isset( $_POST['b2b_field_required'] ) ? 1 : 0;
            $original_slug = isset( $_POST['b2b_field_original_slug'] ) ? sanitize_title( wp_unslash( $_POST['b2b_field_original_slug'] ) ) : '';

            if ( $label === '' || $slug === '' ) {
                return;
            }

            $type_options = array_keys( $this->get_b2b_field_type_options() );
            if ( ! in_array( $type, $type_options, true ) ) {
                $type = 'text';
            }

            $fields = $this->get_b2b_custom_fields( false );
            $builtin_slugs = wp_list_pluck( $this->get_b2b_builtin_fields(), 'slug' );
            $existing_slugs = wp_list_pluck( $fields, 'slug' );
            $updated_fields = array();
            $slug_changed = false;

            if ( $original_slug !== '' ) {
                foreach ( $fields as $field ) {
                    if ( $field['slug'] === $original_slug ) {
                        $desired_slug = $slug;
                        if ( $desired_slug === '' ) {
                            $desired_slug = $original_slug;
                        }
                        if ( $desired_slug !== $original_slug && ( in_array( $desired_slug, $existing_slugs, true ) || in_array( $desired_slug, $builtin_slugs, true ) ) ) {
                            $desired_slug = $original_slug;
                        }

                        $field['label']    = $label;
                        $field['type']     = $type;
                        $field['slug']     = $desired_slug;
                        $field['order']    = $order;
                        $field['enabled']  = $enabled ? 1 : 0;
                        $field['required'] = $required ? 1 : 0;

                        if ( $desired_slug !== $original_slug ) {
                            $slug_changed = true;
                            $old_slug = $original_slug;
                            $new_slug = $desired_slug;
                        }
                    }
                    $updated_fields[] = $field;
                }

                update_option( 'moly_b2b_commerce_b2b_fields', $updated_fields );
                if ( $slug_changed ) {
                    $this->migrate_b2b_field_slug( $old_slug, $new_slug );
                }
                return;
            }

            if ( in_array( $slug, $existing_slugs, true ) || in_array( $slug, $builtin_slugs, true ) ) {
                return;
            }

            $fields[] = array(
                'label'    => $label,
                'slug'     => $slug,
                'type'     => $type,
                'enabled'  => $enabled ? 1 : 0,
                'required' => $required ? 1 : 0,
                'order'    => $order,
            );
            update_option( 'moly_b2b_commerce_b2b_fields', $fields );
        }

        public function save_b2b_custom_fields_settings() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( ! isset( $_POST['moly_b2b_commerce_custom_fields'] ) || ! is_array( $_POST['moly_b2b_commerce_custom_fields'] ) ) {
                return;
            }

            $fields = $this->get_b2b_custom_fields( false );
            $updates = wp_unslash( $_POST['moly_b2b_commerce_custom_fields'] );
            $type_options = array_keys( $this->get_b2b_field_type_options() );
            $builtin_slugs = wp_list_pluck( $this->get_b2b_builtin_fields(), 'slug' );
            $existing_slugs = array_map( function( $field ) { return $field['slug']; }, $fields );
            $out = array();
            $migrations = array();

            foreach ( $fields as $field ) {
                $old_slug = $field['slug'];
                $config = isset( $updates[ $old_slug ] ) && is_array( $updates[ $old_slug ] ) ? $updates[ $old_slug ] : array();
                $desired_slug = isset( $config['slug'] ) ? sanitize_title( $config['slug'] ) : $old_slug;
                if ( $desired_slug === '' ) {
                    $desired_slug = $old_slug;
                }
                if ( $desired_slug !== $old_slug && ( in_array( $desired_slug, $existing_slugs, true ) || in_array( $desired_slug, $builtin_slugs, true ) ) ) {
                    $desired_slug = $old_slug;
                }

                $field['label']    = isset( $config['label'] ) ? sanitize_text_field( $config['label'] ) : $field['label'];
                $field['type']     = isset( $config['type'] ) && in_array( $config['type'], $type_options, true ) ? sanitize_text_field( $config['type'] ) : $field['type'];
                $field['slug']     = $desired_slug;
                $field['order']    = isset( $config['order'] ) ? intval( $config['order'] ) : intval( $field['order'] ?? 0 );
                $field['enabled']  = ! empty( $config['enabled'] ) ? 1 : 0;
                $field['required'] = ! empty( $config['required'] ) ? 1 : 0;
                $out[]             = $field;

                if ( $desired_slug !== $old_slug ) {
                    $migrations[ $old_slug ] = $desired_slug;
                    $existing_slugs = array_diff( $existing_slugs, array( $old_slug, true ) );
                    $existing_slugs[] = $desired_slug;
                }
            }
            update_option( 'moly_b2b_commerce_b2b_fields', $out );

            foreach ( $migrations as $old_slug => $new_slug ) {
                $this->migrate_b2b_field_slug( $old_slug, $new_slug );
            }
        }

        public function migrate_b2b_field_slug( $old_slug, $new_slug ) {
            if ( $old_slug === $new_slug ) {
                return;
            }

            $old_user_key = 'moly_b2b_commerce_user_field_' . $old_slug;
            $new_user_key = 'moly_b2b_commerce_user_field_' . $new_slug;
            $users = get_users( array( 'meta_key' => $old_user_key, 'fields' => 'ID' ) );
            foreach ( $users as $user_id ) {
                $value = get_user_meta( $user_id, $old_user_key, true );
                if ( $value !== '' ) {
                    update_user_meta( $user_id, $new_user_key, $value );
                }
                delete_user_meta( $user_id, $old_user_key );
            }

            $old_order_key = 'billing_moly_b2b_commerce_' . $old_slug;
            $new_order_key = 'billing_moly_b2b_commerce_' . $new_slug;
            $orders = wc_get_orders( array(
                'limit'      => -1,
                'return'     => 'objects',
                'meta_query' => array(
                    array(
                        'key'     => $old_order_key,
                        'compare' => 'EXISTS',
                    ),
                ),
            ) );

            foreach ( $orders as $order ) {
                $value = $order->get_meta( $old_order_key, true );
                if ( $value !== '' ) {
                    $order->update_meta_data( $new_order_key, $value );
                }
                $order->delete_meta_data( $old_order_key );
                $order->save();
            }
        }

        public function save_b2b_builtin_field_settings() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $settings = array();
            if ( isset( $_POST['moly_b2b_commerce_builtin'] ) && is_array( $_POST['moly_b2b_commerce_builtin'] ) ) {
                $inputs = wp_unslash( $_POST['moly_b2b_commerce_builtin'] );
                foreach ( $inputs as $slug => $config ) {
                    $settings[ sanitize_key( $slug ) ] = array(
                        'enabled'  => ! empty( $config['enabled'] ),
                        'required' => ! empty( $config['required'] ),
                    );
                }
            }

            update_option( 'moly_b2b_commerce_b2b_builtin_fields', $settings );
        }

        public function sanitize_b2b_field_value( $value, $type = 'text' ) {
            $value = wp_unslash( $value );
            if ( 'email' === $type ) {
                return sanitize_email( $value );
            }
            if ( 'textarea' === $type ) {
                return sanitize_textarea_field( $value );
            }
            return sanitize_text_field( $value );
        }

        public function save_b2b_order_meta( $order_id, $data = array() ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }

            $changed = false;
            foreach ( $this->get_b2b_fields() as $field ) {
                $field_key = 'billing_moly_b2b_commerce_' . $field['slug'];
                if ( isset( $_POST[ $field_key ] ) ) {
                    $value = $this->sanitize_b2b_field_value( $_POST[ $field_key ], $field['type'] ?? 'text' );
                    $order->update_meta_data( $field_key, $value );
                    $changed = true;
                }
            }
            if ( $changed ) {
                $order->save();
            }
        }

        public function display_order_b2b_fields( $order ) {
            $fields = $this->get_b2b_fields();
            if ( empty( $fields ) ) {
                return;
            }

            $output = array();
            foreach ( $fields as $field ) {
                $key = 'billing_moly_b2b_commerce_' . $field['slug'];
                $value = $order->get_meta( $key );
                if ( $value !== '' ) {
                    $output[] = sprintf( '<strong>%s:</strong> %s', esc_html( $field['label'] ), esc_html( $value ) );
                }
            }

            if ( empty( $output ) ) {
                return;
            }

            echo '<div class="moly-b2b-commerce-order-b2b-fields"><h3>' . esc_html__( 'B2B Fields', 'moly-b2b-commerce' ) . '</h3><p>' . implode( '<br/>', $output ) . '</p></div>';
        }

        public function delete_b2b_field( $slug ) {
            $fields = $this->get_b2b_custom_fields( false );
            $out = array();
            foreach ( $fields as $field ) {
                if ( isset( $field['slug'] ) && $field['slug'] === $slug ) {
                    continue;
                }
                $out[] = $field;
            }
            update_option( 'moly_b2b_commerce_b2b_fields', $out );

            $meta_key = 'moly_b2b_commerce_user_field_' . $slug;
            $users = get_users( array( 'meta_key' => $meta_key, 'fields' => 'ID' ) );
            foreach ( $users as $uid ) {
                delete_user_meta( $uid, $meta_key );
            }
            return true;
        }

        public function register_b2b_block_checkout_fields() {
            if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
                return;
            }

            foreach ( $this->get_b2b_fields() as $field ) {
                $field_id   = 'moly-b2b-commerce/' . $field['slug'];
                $field_type = $field['type'] ?? 'text';
                woocommerce_register_additional_checkout_field( array(
                    'id'                => $field_id,
                    'label'             => $field['label'],
                    'location'          => 'contact',
                    'type'              => 'text',
                    'required'          => $this->is_b2b_field_required( $field ),
                    'sanitize_callback' => function( $value ) use ( $field_type ) {
                        return $this->sanitize_b2b_field_value( $value, $field_type );
                    },
                ) );
                add_filter( 'woocommerce_get_default_value_for_' . $field_id, function( $value ) use ( $field ) {
                    $saved = $this->get_current_user_b2b_field_value( $field['slug'] );
                    return '' !== $saved ? $saved : $value;
                } );
            }
        }

        public function sync_b2b_block_field_value( $key, $value, $group, $wc_object ) {
            $prefix = 'moly-b2b-commerce/';
            if ( 0 !== strpos( $key, $prefix ) ) {
                return;
            }

            $slug  = sanitize_key( substr( $key, strlen( $prefix ) ) );
            $field = null;
            foreach ( $this->get_b2b_fields() as $candidate ) {
                if ( $candidate['slug'] === $slug ) {
                    $field = $candidate;
                    break;
                }
            }
            if ( ! $field ) {
                return;
            }

            $value = $this->sanitize_b2b_field_value( $value, $field['type'] ?? 'text' );
            if ( $wc_object instanceof WC_Customer ) {
                $wc_object->update_meta_data( 'moly_b2b_commerce_user_field_' . $slug, $value );
            } elseif ( $wc_object instanceof WC_Order ) {
                $wc_object->update_meta_data( 'billing_moly_b2b_commerce_' . $slug, $value );
            }
        }

        public function add_b2b_checkout_fields( $fields ) {
            $b2b_fields = $this->get_b2b_fields();
            if ( empty( $b2b_fields ) ) {
                return $fields;
            }

            if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
                $fields['billing'] = array();
            }

            foreach ( $b2b_fields as $field ) {
                $key = 'billing_moly_b2b_commerce_' . $field['slug'];
                $fields['billing'][ $key ] = array(
                    'type'     => $field['type'],
                    'label'    => $field['label'],
                    'required' => $this->is_b2b_field_required( $field ),
                    'class'    => array( 'form-row-wide' ),
                    'clear'    => true,
                    'default'  => $this->get_current_user_b2b_field_value( $field['slug'] ),
                );
            }

            return $fields;
        }

        public function save_b2b_checkout_fields( $user_id, $posted ) {
            $fields = $this->get_b2b_fields();
            foreach ( $fields as $field ) {
                $field_key = 'billing_moly_b2b_commerce_' . $field['slug'];
                if ( isset( $_POST[ $field_key ] ) ) {
                    $value = $this->sanitize_b2b_field_value( $_POST[ $field_key ], $field['type'] ?? 'text' );
                    update_user_meta( $user_id, 'moly_b2b_commerce_user_field_' . $field['slug'], $value );
                }
            }
        }

        public function get_current_user_b2b_field_value( $slug ) {
            if ( ! is_user_logged_in() ) {
                return '';
            }
            return get_user_meta( get_current_user_id(), 'moly_b2b_commerce_user_field_' . $slug, true );
        }

        public function render_user_b2b_fields( $user ) {
            if ( ! current_user_can( 'edit_user', $user->ID ) ) {
                return;
            }

            $fields = $this->get_b2b_fields();
            if ( empty( $fields ) ) {
                return;
            }

            echo '<h2>' . esc_html__( 'Moly B2B Fields', 'moly-b2b-commerce' ) . '</h2>';
            echo '<p>' . esc_html__( 'These values are saved to the user profile and will prefill the checkout when the user is logged in.', 'moly-b2b-commerce' ) . '</p>';
            echo '<table class="form-table">';
            foreach ( $fields as $field ) {
                $meta_key = 'moly_b2b_commerce_user_field_' . $field['slug'];
                $value = get_user_meta( $user->ID, $meta_key, true );
                echo '<tr><th><label for="' . esc_attr( $meta_key ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
                if ( isset( $field['type'] ) && $field['type'] === 'textarea' ) {
                    printf( '<textarea name="%s" id="%s" class="large-text" rows="3">%s</textarea>', esc_attr( $meta_key ), esc_attr( $meta_key ), esc_textarea( $value ) );
                } else {
                    printf( '<input type="text" name="%s" id="%s" value="%s" class="regular-text" />', esc_attr( $meta_key ), esc_attr( $meta_key ), esc_attr( $value ) );
                }
                echo '<p class="description"><span>&nbsp;</span>' . esc_html__( 'Enter values for these B2B fields.', 'moly-b2b-commerce' ) . '</p>';
                echo '</td></tr>';
            }
            echo '</table>';
        }

        public function save_new_user_meta_fields( $user_id ) {
            if ( ! current_user_can( 'create_users' ) ) {
                return;
            }

            $this->save_user_groups( $user_id );
            $this->save_user_b2b_fields( $user_id );
            $this->save_woocommerce_billing_fields( $user_id );
        }

        public function save_user_b2b_fields( $user_id ) {
            if ( ! current_user_can( 'edit_user', $user_id ) ) {
                return false;
            }

            $fields = $this->get_b2b_fields();
            foreach ( $fields as $field ) {
                $meta_key = 'moly_b2b_commerce_user_field_' . $field['slug'];
                if ( isset( $_POST[ $meta_key ] ) ) {
                    update_user_meta( $user_id, $meta_key, $this->sanitize_b2b_field_value( $_POST[ $meta_key ], $field['type'] ?? 'text' ) );
                } else {
                    delete_user_meta( $user_id, $meta_key );
                }
            }
            return true;
        }

        public function save_woocommerce_billing_fields( $user_id ) {
            if ( ! current_user_can( 'edit_user', $user_id ) && ! current_user_can( 'create_users' ) ) {
                return false;
            }

            if ( ! function_exists( 'WC' ) ) {
                return false;
            }

            $fields = WC()->countries->get_address_fields( WC()->countries->get_base_country(), 'billing_' );
            foreach ( $fields as $key => $field ) {
                if ( isset( $_POST[ $key ] ) ) {
                    update_user_meta( $user_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
                }
            }
            return true;
        }
}
