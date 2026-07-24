<?php
/**
 * Plugin Name: Moly B2B Commerce
 * Plugin URI:  https://example.com/moly-b2b-commerce
 * Description: B2B catalog, customer groups, custom fields and discount rules for WooCommerce.
 * Version:     0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.9
 * Author:      Fabio Molinari Nicoletti
 * Author URI:
 * License:     GPL v2
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: moly-b2b-commerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'MOLY_B2B_COMMERCE_PLUGIN_FILE' ) ) {
    define( 'MOLY_B2B_COMMERCE_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/trait-admin.php';
require_once __DIR__ . '/includes/trait-catalog.php';
require_once __DIR__ . '/includes/trait-groups.php';
require_once __DIR__ . '/includes/trait-b2b-fields.php';
require_once __DIR__ . '/includes/trait-discount-admin.php';
require_once __DIR__ . '/includes/trait-discount-rules.php';
require_once __DIR__ . '/includes/trait-pricing.php';
require_once __DIR__ . '/includes/trait-order-discounts.php';

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MOLY_B2B_COMMERCE_PLUGIN_FILE, true );
    }
} );

if ( ! class_exists( 'Moly_B2B_Commerce' ) ) {

    class Moly_B2B_Commerce {

        use Moly_B2B_Commerce_Admin;
        use Moly_B2B_Commerce_Catalog_Mode;
        use Moly_B2B_Commerce_Groups;
        use Moly_B2B_Commerce_B2B_Fields;
        use Moly_B2B_Commerce_Discount_Admin;
        use Moly_B2B_Commerce_Discount_Rules;
        use Moly_B2B_Commerce_Pricing;
        use Moly_B2B_Commerce_Order_Discounts;

        public function __construct() {
            add_action( 'init', array( $this, 'init' ) );
            add_action( 'woocommerce_set_additional_field_value', array( $this, 'sync_b2b_block_field_value' ), 10, 4 );
        }

        public function init() {
            if ( ! class_exists( 'WooCommerce' ) ) {
                return;
            }

            load_plugin_textdomain( 'moly-b2b-commerce', false, dirname( plugin_basename( MOLY_B2B_COMMERCE_PLUGIN_FILE ) ) . '/languages/' );
            $this->register_b2b_block_checkout_fields();

            add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 20, 2 );
            add_filter( 'woocommerce_variation_price_html', array( $this, 'filter_price_html' ), 20, 2 );
            add_filter( 'woocommerce_product_get_price', array( $this, 'filter_runtime_product_price' ), 20, 2 );
            add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_runtime_product_price' ), 20, 2 );
            add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_runtime_variation_price' ), 20, 3 );
            add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'filter_runtime_variation_price' ), 20, 3 );
            add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'add_runtime_price_hash' ), 20, 3 );
            add_filter( 'woocommerce_get_price_html', array( $this, 'filter_discounted_price_html' ), 30, 2 );
            add_filter( 'woocommerce_is_purchasable', array( $this, 'disable_purchasable' ), 20, 2 );
            add_filter( 'woocommerce_variation_is_purchasable', array( $this, 'disable_purchasable' ), 20, 2 );
            add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'prevent_add_to_cart' ), 20, 3 );
            add_filter( 'woocommerce_quantity_input_args', array( $this, 'hide_quantity_input' ), 20, 2 );
            add_filter( 'woocommerce_available_variation', array( $this, 'hide_variation_options' ), 20, 3 );

            add_action( 'wp', array( $this, 'remove_purchase_actions' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ) );
            add_action( 'woocommerce_before_shop_loop', array( $this, 'output_login_notice' ), 5 );
            add_action( 'woocommerce_single_product_summary', array( $this, 'output_login_notice' ), 5 );
            add_action( 'template_redirect', array( $this, 'redirect_guest_checkout' ) );

            add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
            add_action( 'admin_notices', array( $this, 'render_admin_page_header' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_init', array( $this, 'register_roles_settings' ) );
            add_filter( 'woocommerce_checkout_fields', array( $this, 'add_b2b_checkout_fields' ) );
            add_action( 'woocommerce_checkout_update_user_meta', array( $this, 'save_b2b_checkout_fields' ), 10, 2 );
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_b2b_order_meta' ), 10, 2 );
            add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_b2b_order_discounts' ), 20, 1 );
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_b2b_discount_order_meta' ), 10, 2 );
            add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'save_b2b_discount_order_meta_store_api' ), 10, 1 );
            add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_order_b2b_fields' ), 10, 1 );
            add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_order_b2b_discount' ), 20, 1 );
            add_filter( 'plugin_action_links_' . plugin_basename( MOLY_B2B_COMMERCE_PLUGIN_FILE ), array( $this, 'add_action_links' ) );

            // groups and user meta hooks
            $this->register_groups_hooks();
        }

        // Groups management and user assignment hooks
        public function register_groups_hooks() {
            add_action( 'show_user_profile', array( $this, 'render_user_groups_field' ) );
            add_action( 'edit_user_profile', array( $this, 'render_user_groups_field' ) );
            add_action( 'personal_options_update', array( $this, 'save_user_groups' ) );
            add_action( 'edit_user_profile_update', array( $this, 'save_user_groups' ) );

            add_action( 'user_register', array( $this, 'save_new_user_meta_fields' ) );

            add_action( 'show_user_profile', array( $this, 'render_user_b2b_fields' ) );
            add_action( 'edit_user_profile', array( $this, 'render_user_b2b_fields' ) );
            add_action( 'personal_options_update', array( $this, 'save_user_b2b_fields' ) );
            add_action( 'edit_user_profile_update', array( $this, 'save_user_b2b_fields' ) );

            add_action( 'personal_options_update', array( $this, 'save_woocommerce_billing_fields' ) );
            add_action( 'edit_user_profile_update', array( $this, 'save_woocommerce_billing_fields' ) );
            add_action( 'user_register', array( $this, 'save_woocommerce_billing_fields' ) );
        }

    }

    new Moly_B2B_Commerce();
}
