<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Groups {

        public function register_groups_submenu() {
            add_submenu_page(
                'moly-b2b-commerce',
                __( 'Moly B2B Groups', 'moly-b2b-commerce' ),
                __( 'Groups', 'moly-b2b-commerce' ),
                'manage_options',
                'moly-b2b-commerce-groups',
                array( $this, 'render_groups_page' )
            );
        }

        public function get_allowed_groups() {
            $groups = $this->get_groups();
            $allowed = array();
            foreach ( $groups as $group ) {
                if ( ! empty( $group['allowed'] ) ) {
                    $allowed[] = $group['slug'];
                }
            }
            return array_filter( $allowed );
        }

        public function get_groups() {
            $groups = get_option( 'moly_b2b_commerce_groups', array() );
            if ( ! is_array( $groups ) ) {
                $groups = maybe_unserialize( $groups );
            }
            return array_values( (array) $groups );
        }

        public function get_user_groups( $user_id = 0 ) {
            if ( empty( $user_id ) ) {
                $user_id = get_current_user_id();
            }
            $groups = get_user_meta( $user_id, 'moly_b2b_commerce_user_groups', true );
            if ( ! is_array( $groups ) ) {
                $groups = maybe_unserialize( $groups );
            }
            return array_filter( (array) $groups );
        }

        public function sanitize_allowed_groups( $input ) {
            if ( ! is_array( $input ) ) {
                return array();
            }
            $groups = $this->get_groups();
            $valid = array();
            $allowed_slugs = array_map( function( $g ) { return $g['slug']; }, $groups );
            foreach ( $input as $slug ) {
                if ( in_array( $slug, $allowed_slugs, true ) ) {
                    $valid[] = $slug;
                }
            }
            return $valid;
        }

        public function render_user_groups_field( $user ) {
            if ( ! current_user_can( 'edit_user', $user->ID ) ) {
                return;
            }

            $groups = $this->get_groups();
            $user_groups = $this->get_user_groups( $user->ID );

            echo '<h2>' . esc_html__( 'B2B Groups', 'moly-b2b-commerce' ) . '</h2>';
            if ( empty( $groups ) ) {
                echo '<p>' . esc_html__( 'No groups available. Add groups under Moly B2B > Groups.', 'moly-b2b-commerce' ) . '</p>';
                return;
            }

            echo '<table class="form-table"><tr><th>' . esc_html__( 'Assign groups', 'moly-b2b-commerce' ) . '</th><td>';
            foreach ( $groups as $g ) {
                $slug = $g['slug'];
                $checked = in_array( $slug, $user_groups, true ) ? 'checked' : '';
                printf( '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="moly_b2b_commerce_user_groups[]" value="%s" %s /> %s</label>', esc_attr( $slug ), $checked, esc_html( $g['name'] ) );
            }
            echo '<p class="description">' . esc_html__( 'Select groups for this user.', 'moly-b2b-commerce' ) . '</p>';
            echo '</td></tr></table>';
        }

        public function save_user_groups( $user_id ) {
            if ( ! current_user_can( 'edit_user', $user_id ) ) {
                return false;
            }

            $groups = array();
            if ( isset( $_POST['moly_b2b_commerce_user_groups'] ) && is_array( $_POST['moly_b2b_commerce_user_groups'] ) ) {
                foreach ( $_POST['moly_b2b_commerce_user_groups'] as $g ) {
                    $groups[] = sanitize_text_field( wp_unslash( $g ) );
                }
            }

            // validate against existing groups
            $existing = array_map( function( $g ) { return $g['slug']; }, $this->get_groups() );
            $out = array();
            foreach ( $groups as $s ) {
                if ( in_array( $s, $existing, true ) ) {
                    $out[] = $s;
                }
            }
            update_user_meta( $user_id, 'moly_b2b_commerce_user_groups', array_values( $out ) );
            return true;
        }

        public function render_groups_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            if ( isset( $_POST['moly_b2b_commerce_save_group'] ) && check_admin_referer( 'moly_b2b_commerce_save_group', 'moly_b2b_commerce_nonce_group' ) ) {
                $this->save_group_settings();
                wp_safe_redirect( remove_query_arg( 'edit_group' ) );
                exit;
            }

            if ( isset( $_GET['delete_group'] ) && isset( $_GET['_wpnonce'] ) ) {
                $slug  = sanitize_title( wp_unslash( $_GET['delete_group'] ) );
                $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
                if ( wp_verify_nonce( $nonce, 'moly_b2b_commerce_delete_group_' . $slug ) ) {
                    $this->delete_group( $slug );
                    wp_safe_redirect( remove_query_arg( array( 'delete_group', '_wpnonce' ) ) );
                    exit;
                }
            }

            $groups     = $this->get_groups();
            $edit_group = null;
            if ( isset( $_GET['edit_group'] ) ) {
                $edit_slug = sanitize_title( wp_unslash( $_GET['edit_group'] ) );
                foreach ( $groups as $group ) {
                    if ( isset( $group['slug'] ) && $group['slug'] === $edit_slug ) {
                        $edit_group = $group;
                        break;
                    }
                }
            }

            echo '<div class="wrap">';
            echo '<span></span>';
            echo '<h2>' . esc_html__( 'Existing groups', 'moly-b2b-commerce' ) . '</h2>';

            echo '<table class="widefat fixed"><thead><tr><th>' . esc_html__( 'Name', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Slug', 'moly-b2b-commerce' ) . '</th><th>' . esc_html__( 'Allowed to view prices', 'moly-b2b-commerce' ) . '</th><th></th></tr></thead><tbody>';
            if ( empty( $groups ) ) {
                echo '<tr><td colspan="4">' . esc_html__( 'No groups created.', 'moly-b2b-commerce' ) . '</td></tr>';
            } else {
                foreach ( $groups as $group ) {
                    $edit_url   = add_query_arg( 'edit_group', $group['slug'] );
                    $delete_url = wp_nonce_url( add_query_arg( 'delete_group', $group['slug'] ), 'moly_b2b_commerce_delete_group_' . $group['slug'] );
                    $status_icon = ! empty( $group['allowed'] ) ? '&#10004;' : '&#10006;';
                    $status_label = ! empty( $group['allowed'] ) ? __( 'Active', 'moly-b2b-commerce' ) : __( 'Inactive', 'moly-b2b-commerce' );

                    echo '<tr>';
                    echo '<td>' . esc_html( $group['name'] ) . '</td>';
                    echo '<td>' . esc_html( $group['slug'] ) . '</td>';
                    echo '<td><span aria-label="' . esc_attr( $status_label ) . '">' . $status_icon . '</span></td>';
                    echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-secondary">' . esc_html__( 'Edit', 'moly-b2b-commerce' ) . '</a> <a href="' . esc_url( $delete_url ) . '" class="button button-secondary">' . esc_html__( 'Delete', 'moly-b2b-commerce' ) . '</a></td>';
                    echo '</tr>';
                }
            }
            echo '</tbody></table>';

            $original_slug = $edit_group['slug'] ?? '';
            $name           = $edit_group['name'] ?? '';
            $slug           = $edit_group['slug'] ?? '';
            $allowed        = ! empty( $edit_group['allowed'] );
            $form_title     = $original_slug ? __( 'Edit group', 'moly-b2b-commerce' ) : __( 'Add group', 'moly-b2b-commerce' );
            $button_label   = $original_slug ? __( 'Update group', 'moly-b2b-commerce' ) : __( 'Add group', 'moly-b2b-commerce' );

            echo '<h2 style="margin-top:24px;">' . esc_html( $form_title ) . '</h2>';
            echo '<form method="post">';
            wp_nonce_field( 'moly_b2b_commerce_save_group', 'moly_b2b_commerce_nonce_group' );
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="group_name">' . esc_html__( 'Name', 'moly-b2b-commerce' ) . '</label></th><td><input type="text" id="group_name" name="group_name" class="regular-text" value="' . esc_attr( $name ) . '" required /></td></tr>';
            echo '<tr><th><label for="group_slug">' . esc_html__( 'Slug', 'moly-b2b-commerce' ) . '</label></th><td><input type="text" id="group_slug" name="group_slug" class="regular-text" value="' . esc_attr( $slug ) . '" /></td></tr>';
            echo '<tr><th><label for="group_allowed">' . esc_html__( 'Allowed to view prices', 'moly-b2b-commerce' ) . '</label></th><td><label><input type="checkbox" id="group_allowed" name="group_allowed" value="1" ' . checked( $allowed, true, false ) . ' /> ' . esc_html__( 'Yes', 'moly-b2b-commerce' ) . '</label></td></tr>';
            echo '</tbody></table>';
            if ( $original_slug ) {
                echo '<input type="hidden" name="group_original_slug" value="' . esc_attr( $original_slug ) . '" />';
            }
            echo '<p class="submit">';
            submit_button( $button_label, 'primary', 'moly_b2b_commerce_save_group', false );
            echo ' <a href="' . esc_url( remove_query_arg( 'edit_group' ) ) . '" class="button button-secondary">' . esc_html__( 'Cancel', 'moly-b2b-commerce' ) . '</a>';
            echo '</p>';
            echo '</form>';
            echo '<p>' . esc_html__( 'Users are assigned to groups from their user profile page under Moly B2B Groups.', 'moly-b2b-commerce' ) . '</p>';
            echo '</div>';
        }

        public function save_group_settings() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return false;
            }

            $name          = isset( $_POST['group_name'] ) ? sanitize_text_field( wp_unslash( $_POST['group_name'] ) ) : '';
            $slug          = isset( $_POST['group_slug'] ) ? sanitize_title( wp_unslash( $_POST['group_slug'] ) ) : '';
            $original_slug = isset( $_POST['group_original_slug'] ) ? sanitize_title( wp_unslash( $_POST['group_original_slug'] ) ) : '';
            $allowed       = isset( $_POST['group_allowed'] );

            if ( $name === '' ) {
                return false;
            }
            if ( $slug === '' ) {
                $slug = sanitize_title( $name );
            }
            if ( $slug === '' ) {
                return false;
            }

            $groups = $this->get_groups();
            foreach ( $groups as $group ) {
                if ( $group['slug'] === $slug && $group['slug'] !== $original_slug ) {
                    return false;
                }
            }

            if ( $original_slug === '' ) {
                $groups[] = array(
                    'name'    => $name,
                    'slug'    => $slug,
                    'allowed' => $allowed,
                );
            } else {
                $found = false;
                foreach ( $groups as &$group ) {
                    if ( $group['slug'] === $original_slug ) {
                        $group['name']    = $name;
                        $group['slug']    = $slug;
                        $group['allowed'] = $allowed;
                        $found = true;
                        break;
                    }
                }
                unset( $group );
                if ( ! $found ) {
                    return false;
                }
            }

            update_option( 'moly_b2b_commerce_groups', $groups );
            if ( $original_slug !== '' && $original_slug !== $slug ) {
                $this->migrate_group_slug( $original_slug, $slug );
            }
            return true;
        }

        public function migrate_group_slug( $old_slug, $new_slug ) {
            $users = get_users( array( 'meta_key' => 'moly_b2b_commerce_user_groups', 'fields' => 'ID' ) );
            foreach ( $users as $user_id ) {
                $user_groups = $this->get_user_groups( $user_id );
                if ( in_array( $old_slug, $user_groups, true ) ) {
                    $user_groups = array_map( function( $slug ) use ( $old_slug, $new_slug ) {
                        return $slug === $old_slug ? $new_slug : $slug;
                    }, $user_groups );
                    update_user_meta( $user_id, 'moly_b2b_commerce_user_groups', array_values( array_unique( $user_groups ) ) );
                }
            }

            $rules   = $this->get_discount_rules();
            $changed = false;
            foreach ( $rules as &$rule ) {
                if ( isset( $rule['group_slug'] ) && $rule['group_slug'] === $old_slug ) {
                    $rule['group_slug'] = $new_slug;
                    $changed = true;
                }
            }
            unset( $rule );
            if ( $changed ) {
                update_option( 'moly_b2b_commerce_discount_rules', $rules );
            }
        }

        public function add_group( $name ) {
            $slug = sanitize_title( $name );
            if ( $name === '' || $slug === '' ) {
                return false;
            }
            $groups = $this->get_groups();
            foreach ( $groups as $group ) {
                if ( isset( $group['slug'] ) && $group['slug'] === $slug ) {
                    return false;
                }
            }
            $groups[] = array(
                'name'    => $name,
                'slug'    => $slug,
                'allowed' => false,
            );
            update_option( 'moly_b2b_commerce_groups', $groups );
            return true;
        }

        public function delete_group( $slug ) {
            $groups = $this->get_groups();
            $out = array();
            foreach ( $groups as $g ) {
                if ( isset( $g['slug'] ) && $g['slug'] === $slug ) {
                    continue; // drop
                }
                $out[] = $g;
            }
            update_option( 'moly_b2b_commerce_groups', $out );

            // remove group from users
            $users = get_users( array( 'meta_key' => 'moly_b2b_commerce_user_groups', 'fields' => 'ID' ) );
            foreach ( $users as $uid ) {
                $ugs = $this->get_user_groups( $uid );
                if ( in_array( $slug, $ugs, true ) ) {
                    $ugs = array_diff( $ugs, array( $slug ) );
                    update_user_meta( $uid, 'moly_b2b_commerce_user_groups', array_values( $ugs ) );
                }
            }
            return true;
        }
}
