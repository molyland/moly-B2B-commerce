<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Moly_B2B_Commerce_Discount_Rules {

    public function get_discount_rules() {
        $rules = get_option( 'moly_b2b_commerce_discount_rules', array() );
        if ( ! is_array( $rules ) ) {
            $rules = maybe_unserialize( $rules );
        }

        $normalized = array();
        foreach ( (array) $rules as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }
            $normalized[] = wp_parse_args( $rule, array(
                'id'              => '',
                'name'            => '',
                'user_id'         => '',
                'group_slug'      => '',
                'scope'           => 'global',
                'scope_target'    => '',
                'discount_type'   => 'percentage',
                'discount_value'  => 0,
                'min_order_total' => 0,
                'priority'        => 0,
                'stack'           => 0,
                'active'          => 0,
            ) );
        }
        return $normalized;
    }

    public function sanitize_discount_rules( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }

        $out = array();
        foreach ( $input as $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $user_id        = ! empty( $rule['user_id'] ) ? absint( $rule['user_id'] ) : '';
            $group_slug     = $user_id ? '' : sanitize_key( $rule['group_slug'] ?? '' );
            $scope          = in_array( $rule['scope'] ?? '', array( 'global', 'category', 'product' ), true ) ? $rule['scope'] : 'global';
            $discount_type  = in_array( $rule['discount_type'] ?? '', array( 'percentage', 'fixed' ), true ) ? $rule['discount_type'] : 'percentage';
            $discount_value = max( 0, (float) ( $rule['discount_value'] ?? 0 ) );

            if ( $discount_type === 'percentage' ) {
                $discount_value = min( 100, $discount_value );
            } else {
                $scope = 'global';
            }

            $out[] = array(
                'id'              => ! empty( $rule['id'] ) ? sanitize_text_field( $rule['id'] ) : wp_generate_uuid4(),
                'name'            => sanitize_text_field( $rule['name'] ?? '' ),
                'user_id'         => $user_id,
                'group_slug'      => $group_slug,
                'scope'           => $scope,
                'scope_target'    => sanitize_text_field( $rule['scope_target'] ?? '' ),
                'discount_type'   => $discount_type,
                'discount_value'  => $discount_value,
                'min_order_total' => max( 0, (float) ( $rule['min_order_total'] ?? 0 ) ),
                'priority'        => (int) ( $rule['priority'] ?? 0 ),
                'stack'           => ! empty( $rule['stack'] ) ? 1 : 0,
                'active'          => ! empty( $rule['active'] ) ? 1 : 0,
            );
        }
        return $out;
    }

    public function is_discount_rule_applicable_to_user( $rule, $user, $user_groups ) {
        if ( ! empty( $rule['user_id'] ) ) {
            return absint( $rule['user_id'] ) === absint( $user->ID );
        }
        return ! empty( $rule['group_slug'] ) && in_array( $rule['group_slug'], (array) $user_groups, true );
    }

    public function get_discount_rule_scope_weight( $rule ) {
        $weights = array( 'global' => 10, 'category' => 20, 'product' => 30 );
        return $weights[ $rule['scope'] ] ?? 0;
    }

    public function get_discount_rule_target_weight( $rule ) {
        return ! empty( $rule['user_id'] ) ? 20 : 10;
    }

    public function discount_rule_matches_product( $rule, $product_id ) {
        if ( $rule['scope'] === 'global' ) {
            return true;
        }

        $product = wc_get_product( $product_id );
        $parent_id = $product && $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;

        if ( $rule['scope'] === 'product' ) {
            return absint( $rule['scope_target'] ) === absint( $product_id ) || absint( $rule['scope_target'] ) === absint( $parent_id );
        }
        return $rule['scope'] === 'category' && has_term( $rule['scope_target'], 'product_cat', $parent_id );
    }

    public function resolve_discount_rules( $rules ) {
        usort( $rules, function( $a, $b ) {
            $a_key = array( $this->get_discount_rule_scope_weight( $a ), $this->get_discount_rule_target_weight( $a ), (int) $a['priority'] );
            $b_key = array( $this->get_discount_rule_scope_weight( $b ), $this->get_discount_rule_target_weight( $b ), (int) $b['priority'] );
            return $a_key <=> $b_key;
        } );

        $resolved = array();
        foreach ( $rules as $rule ) {
            if ( empty( $resolved ) || ! empty( $rule['stack'] ) ) {
                $resolved[] = $rule;
            } else {
                $resolved = array( $rule );
            }
        }
        return $resolved;
    }

    public function get_applicable_product_discount_rules( $product_id ) {
        if ( ! is_user_logged_in() ) {
            return array();
        }

        $user        = wp_get_current_user();
        $user_groups = $this->get_user_groups( $user->ID );
        $matches     = array();
        foreach ( $this->get_discount_rules() as $rule ) {
            if ( empty( $rule['active'] ) || $rule['discount_type'] !== 'percentage' ) {
                continue;
            }
            if ( ! $this->is_discount_rule_applicable_to_user( $rule, $user, $user_groups ) ) {
                continue;
            }
            if ( $this->discount_rule_matches_product( $rule, $product_id ) ) {
                $matches[] = $rule;
            }
        }
        return $this->resolve_discount_rules( $matches );
    }

    public function get_applicable_fixed_discount_rules( $subtotal ) {
        if ( ! is_user_logged_in() ) {
            return array();
        }

        $user        = wp_get_current_user();
        $user_groups = $this->get_user_groups( $user->ID );
        $matches     = array();
        foreach ( $this->get_discount_rules() as $rule ) {
            if ( empty( $rule['active'] ) || $rule['discount_type'] !== 'fixed' ) {
                continue;
            }
            if ( (float) $rule['min_order_total'] > 0 && (float) $subtotal < (float) $rule['min_order_total'] ) {
                continue;
            }
            if ( $this->is_discount_rule_applicable_to_user( $rule, $user, $user_groups ) ) {
                $matches[] = $rule;
            }
        }
        return $this->resolve_discount_rules( $matches );
    }
}