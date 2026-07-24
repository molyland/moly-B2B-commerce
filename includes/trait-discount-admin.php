<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Discount_Admin {

        public function render_discount_rules_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $rules = $this->get_discount_rules();
            $groups = $this->get_groups();
            $users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login' ) ) );

            if ( isset( $_POST['moly_b2b_commerce_save_discount_rules'] ) && check_admin_referer( 'moly_b2b_commerce_save_discount_rules', 'moly_b2b_commerce_nonce_discounts' ) ) {
                $this->save_discount_rules_settings();
                $rules = $this->get_discount_rules();
                wp_redirect( remove_query_arg( 'edit_discount_rule' ) );
                exit;
            }

            if ( isset( $_POST['moly_b2b_commerce_save_discount_rules_bulk'] ) && check_admin_referer( 'moly_b2b_commerce_save_discount_rules_bulk', 'moly_b2b_commerce_nonce_discounts_bulk' ) ) {
                $this->save_discount_rules_bulk_settings();
                $rules = $this->get_discount_rules();
            }

            if ( isset( $_GET['delete_discount_rule'] ) && isset( $_GET['_wpnonce'] ) ) {
                $nonce = $_GET['_wpnonce'];
                $rule_id = sanitize_text_field( wp_unslash( $_GET['delete_discount_rule'] ) );
                if ( wp_verify_nonce( $nonce, 'moly_b2b_commerce_delete_discount_rule_' . $rule_id ) ) {
                    $this->delete_discount_rule( $rule_id );
                    $rules = $this->get_discount_rules();
                }
            }

            echo '<div class="wrap">';
            echo '<h2>' . esc_html__( 'Existing discount rules', 'moly-b2b-commerce' ) . '</h2>';
            echo '<table class="widefat fixed"><thead><tr><th>' . esc_html__( 'Name', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Target', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Scope', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Discount', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Min order', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Priority', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Stack', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Active', 'moly-b2b-commerce' ) . '</th><th></th></tr></thead><tbody>';
            if ( empty( $rules ) ) {
                echo '<tr><td colspan="9">' . esc_html__( 'No discount rules configured.', 'moly-b2b-commerce' ) . '</td></tr>';
            } else {
                foreach ( $rules as $rule ) {
                    $target_label = '';
                    if ( ! empty( $rule['user_id'] ) ) {
                        $user = get_userdata( $rule['user_id'] );
                        $target_label = $user ? esc_html( $user->display_name . ' (' . $user->user_login . ')' ) : esc_html__( 'Unknown user', 'moly-b2b-commerce' );
                    } elseif ( ! empty( $rule['group_slug'] ) ) {
                        $group = array_filter( $groups, function( $g ) use ( $rule ) {
                            return $g['slug'] === $rule['group_slug'];
                        } );
                        $group = reset( $group );
                        $target_label = $group ? esc_html( $group['name'] ) : esc_html__( 'Unknown group', 'moly-b2b-commerce' );
                    }
                    $discount_label = $rule['discount_type'] === 'fixed' ? wc_price( floatval( $rule['discount_value'] ) ) : esc_html( floatval( $rule['discount_value'] ) . '%' );
                    $scope_label = esc_html( ucfirst( $rule['scope'] ) );
                    $rule_id = esc_attr( $rule['id'] );
                    echo '<tr>';
                    echo '<td>' . esc_html( $rule['name'] ) . '</td>';
                    echo '<td>' . $target_label . '</td>';
                    echo '<td>' . $scope_label . '</td>';
                    echo '<td>' . wp_kses_post( $discount_label ) . '</td>';
                    echo '<td>' . wp_kses_post( wc_price( floatval( $rule['min_order_total'] ) ) ) . '</td>';
                    echo '<td>' . esc_html( (int) $rule['priority'] ) . '</td>';
                    echo '<td>' . ( ! empty( $rule['stack'] ) ? '&#10004;' : '&#10006;' ) . '</td>';
                    $status_icon = ! empty( $rule['active'] ) ? '&#10004;' : '&#10006;';
                    echo '<td>' . '<span aria-label="' . esc_attr( ! empty( $rule['active'] ) ? esc_html__( 'Active', 'moly-b2b-commerce' ) : esc_html__( 'Inactive', 'moly-b2b-commerce' ) ) . '">' . $status_icon . '</span>' . '</td>';
                    echo '<td><a href="' . esc_url( add_query_arg( array( 'edit_discount_rule' => $rule['id'] ) ) ) . '" class="button button-secondary">' . esc_html__( 'Edit', 'moly-b2b-commerce' ) . '</a> <a href="' . esc_url( wp_nonce_url( add_query_arg( 'delete_discount_rule', $rule['id'] ), 'moly_b2b_commerce_delete_discount_rule_' . $rule['id'] ) ) . '" class="button button-secondary">' . esc_html__( 'Delete', 'moly-b2b-commerce' ) . '</a></td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';

            $edit_rule = null;
            if ( isset( $_GET['edit_discount_rule'] ) ) {
                $rule_id = sanitize_text_field( wp_unslash( $_GET['edit_discount_rule'] ) );
                foreach ( $rules as $rule ) {
                    if ( $rule['id'] === $rule_id ) {
                        $edit_rule = $rule;
                        break;
                    }
                }
            }

            echo '<h2 style="margin-top:24px;">' . esc_html__( 'Add / Edit discount rule', 'moly-b2b-commerce' ) . '</h2>';
            echo '<p>' . esc_html__( 'Configure a rule that applies a percentage or fixed discount. Fixed discounts require a minimum order total.', 'moly-b2b-commerce' ) . '</p>';
            echo '<form method="post">';
            wp_nonce_field( 'moly_b2b_commerce_save_discount_rules', 'moly_b2b_commerce_nonce_discounts' );
            echo '<table class="form-table"><tbody>';

            $rule_id = $edit_rule['id'] ?? '';
            $name = $edit_rule['name'] ?? '';
            $user_id = $edit_rule['user_id'] ?? '';
            $group_slug = $edit_rule['group_slug'] ?? '';
            $scope = $edit_rule['scope'] ?? 'global';
            $scope_target = $edit_rule['scope_target'] ?? '';
            $discount_type = $edit_rule['discount_type'] ?? 'percentage';
            $discount_value = $edit_rule['discount_value'] ?? '';
            $min_order_total = $edit_rule['min_order_total'] ?? '';
            $priority = isset( $edit_rule['priority'] ) ? (int) $edit_rule['priority'] : 0;
            $stack = ! empty( $edit_rule['stack'] );
            $active = ! empty( $edit_rule['active'] );

            echo '<tr><th><label for="discount_name">' . esc_html__( 'Name', 'moly-b2b-commerce' ) . '</label></th><td><input type="text" id="discount_name" name="discount_rule[name]" class="regular-text" value="' . esc_attr( $name ) . '" /></td></tr>';
            echo '<tr><th>' . esc_html__( 'Target user', 'moly-b2b-commerce' ) . '</th><td><select name="discount_rule[user_id]">';
            echo '<option value="">' . esc_html__( 'None', 'moly-b2b-commerce' ) . '</option>';
            foreach ( $users as $user ) {
                $checked = selected( $user_id, $user->ID, false );
                printf( '<option value="%s" %s>%s (%s)</option>', esc_attr( $user->ID ), $checked, esc_html( $user->display_name ), esc_html( $user->user_login ) );
            }
            echo '</select></td></tr>';
            echo '<tr><th>' . esc_html__( 'Target group', 'moly-b2b-commerce' ) . '</th><td><select name="discount_rule[group_slug]">';
            echo '<option value="">' . esc_html__( 'None', 'moly-b2b-commerce' ) . '</option>';
            foreach ( $groups as $group ) {
                $checked = selected( $group_slug, $group['slug'], false );
                printf( '<option value="%s" %s>%s</option>', esc_attr( $group['slug'] ), $checked, esc_html( $group['name'] ) );
            }
            echo '</select></td></tr>';
            echo '<tr><th><label for="discount_scope">' . esc_html__( 'Scope', 'moly-b2b-commerce' ) . '</label></th><td><select id="discount_scope" name="discount_rule[scope]">';
            $scopes = array( 'global' => __( 'All products', 'moly-b2b-commerce' ), 'category' => __( 'Category', 'moly-b2b-commerce' ), 'product' => __( 'Product', 'moly-b2b-commerce' ) );
            foreach ( $scopes as $value => $label ) {
                $selected = selected( $scope, $value, false );
                printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $label ) );
            }
            echo '</select></td></tr>';
            echo '<tr><th><label for="discount_type">' . esc_html__( 'Discount type', 'moly-b2b-commerce' ) . '</label></th><td><select id="discount_type" name="discount_rule[discount_type]">';
            $types = array( 'percentage' => __( 'Percentage', 'moly-b2b-commerce' ), 'fixed' => __( 'Fixed amount', 'moly-b2b-commerce' ) );
            foreach ( $types as $value => $label ) {
                $selected = selected( $discount_type, $value, false );
                printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), $selected, esc_html( $label ) );
            }
            echo '</select></td></tr>';
            echo '<tr><th><label for="discount_scope_target">' . esc_html__( 'Scope target', 'moly-b2b-commerce' ) . '</label></th><td><input type="text" id="discount_scope_target" name="discount_rule[scope_target]" class="regular-text" value="' . esc_attr( $scope_target ) . '" placeholder="' . esc_attr__( 'Category slug or product ID', 'moly-b2b-commerce' ) . '" /></td></tr>';
            echo '<tr><th><label for="discount_value">' . esc_html__( 'Discount value', 'moly-b2b-commerce' ) . '</label></th><td><input type="number" step="0.01" id="discount_value" name="discount_rule[discount_value]" class="small-text" value="' . esc_attr( $discount_value ) . '" /></td></tr>';
            echo '<tr><th><label for="discount_min_order_total">' . esc_html__( 'Minimum order total', 'moly-b2b-commerce' ) . '</label></th><td><input type="number" step="0.01" id="discount_min_order_total" name="discount_rule[min_order_total]" class="small-text" value="' . esc_attr( $min_order_total ) . '" /></td></tr>';
            echo '<tr><th><label for="discount_priority">' . esc_html__( 'Priority', 'moly-b2b-commerce' ) . '</label></th><td><input type="number" id="discount_priority" name="discount_rule[priority]" class="small-text" value="' . esc_attr( $priority ) . '" /></td></tr>';
            echo '<tr><th><label for="discount_stack">' . esc_html__( 'Stack with previous discounts', 'moly-b2b-commerce' ) . '</label></th><td><input type="checkbox" id="discount_stack" name="discount_rule[stack]" value="1" ' . checked( $stack, true, false ) . ' /></td></tr>';
            echo '<tr><th><label for="discount_active">' . esc_html__( 'Active', 'moly-b2b-commerce' ) . '</label></th><td><input type="checkbox" id="discount_active" name="discount_rule[active]" value="1" ' . ( $active ? 'checked' : '' ) . ' /></td></tr>';
            echo '</tbody></table>';
            $button_label = $rule_id ? __( 'Update discount rule', 'moly-b2b-commerce' ) : __( 'Add discount rule', 'moly-b2b-commerce' );
            echo '<p class="submit">' . ( $rule_id ? '<input type="hidden" name="discount_rule[id]" value="' . esc_attr( $rule_id ) . '" />' : '' );
            submit_button( $button_label, 'primary', 'moly_b2b_commerce_save_discount_rules', false, array( 'id' => false ) );
            echo ' <a href="' . esc_url( remove_query_arg( 'edit_discount_rule' ) ) . '" class="button button-secondary">' . esc_html__( 'Cancel', 'moly-b2b-commerce' ) . '</a>';
            echo '</p>';
            echo '</form>';
            echo '</div>';
        }

        public function save_discount_rules_settings() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( ! isset( $_POST['discount_rule'] ) || ! is_array( $_POST['discount_rule'] ) ) {
                return;
            }

            $rule_input = wp_unslash( $_POST['discount_rule'] );
            $rules = $this->get_discount_rules();
            $rule_id = isset( $rule_input['id'] ) ? sanitize_text_field( $rule_input['id'] ) : '';
            $sanitized = $this->sanitize_discount_rules( array( $rule_input ) );
            if ( empty( $sanitized ) ) {
                return;
            }
            $rule_data = $sanitized[0];
            if ( $rule_id ) {
                $rule_data['id'] = $rule_id;
            }

            $found = false;
            foreach ( $rules as &$rule ) {
                if ( $rule['id'] === $rule_data['id'] ) {
                    $rule = $rule_data;
                    $found = true;
                    break;
                }
            }
            unset( $rule );

            if ( ! $found ) {
                $rules[] = $rule_data;
            }

            update_option( 'moly_b2b_commerce_discount_rules', $rules );
        }

        public function save_discount_rules_bulk_settings() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( ! isset( $_POST['discount_rules'] ) || ! is_array( $_POST['discount_rules'] ) ) {
                return;
            }

            $rules_input = wp_unslash( $_POST['discount_rules'] );
            $existing_rules = $this->get_discount_rules();
            $updated_rules = array();

            foreach ( $existing_rules as $rule ) {
                $rule_id = $rule['id'];
                if ( isset( $rules_input[ $rule_id ] ) && is_array( $rules_input[ $rule_id ] ) ) {
                    $rule['active'] = ! empty( $rules_input[ $rule_id ]['active'] ) ? 1 : 0;
                } else {
                    $rule['active'] = 0;
                }
                $updated_rules[] = $rule;
            }

            update_option( 'moly_b2b_commerce_discount_rules', $updated_rules );
        }

        public function delete_discount_rule( $rule_id ) {
            $rules = $this->get_discount_rules();
            $out = array();
            foreach ( $rules as $rule ) {
                if ( isset( $rule['id'] ) && $rule['id'] === $rule_id ) {
                    continue;
                }
                $out[] = $rule;
            }
            update_option( 'moly_b2b_commerce_discount_rules', $out );
        }
}
