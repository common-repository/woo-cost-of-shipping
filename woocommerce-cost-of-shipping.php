<?php
/**
 * Plugin Name: WooCommerce Cost of Shipping
 * Plugin URI:  https://www.theritesites.com/plugins/woocommerce-cost-of-shipping
 * Description: Allows the association of the cost of shipping to WooCommerce orders
 * Version:     1.5.4
 * Author:      TheRiteSites
 * Author URI:  https://www.theritesites.com
 * Donate link: https://www.theritesites.com/plugins/woocommerce-cost-of-shipping
 * License:     GPL-2.0+
 * Text Domain: woocommerce-cost-of-shipping
 * Domain Path: /languages 
 * WC tested up to: 8.9
 * WC requires at least: 3.0
 * Requires at least: 5.2
 *
 * @link    https://www.theritesites.com/plugins/woocommerce-cost-of-shipping
 *
 * @package WC_COS
 * @version 1.5.4
 */

/**
 * 
 * This plugin can be expanded into net profit reporting by getting the WooCommerce Net Profit plugin
 * developed by TheRiteSites and found at https://www.theritesites.com/plugins/woocommerce-net-profit
 * 
 * Copyright (c) 2020,2024 TheRiteSites (email : contact@theritesites.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */



if ( ! function_exists( 'is_woocommerce_active' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Main initiation class.
 *
 * @since  1.0.0
 */
final class WC_COS {

	/**
	 * Current version.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	const VERSION = '1.5.3';

	/**
	 * Debug flag
	 * 
	 * @var		boolean
	 * @since 	1.1.0
	 */
	const DEBUG = false;

	/**
	 * URL of plugin directory.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $path = '';

	/**
	 * Plugin basename.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $basename = '';

	/**
	 * Detailed activation error messages.
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	protected $activation_errors = array();

	/**
	 * Singleton instance of plugin.
	 *
	 * @var    WC_COS
	 * @since  1.0.0
	 */
	protected static $single_instance = null;
	
	/**
	 * instance of WC_COS_Admin
	 * 
	 * @var WC_COS_Admin
	 * @since 1.0.0
	 */
	protected $WC_COS_Admin;
	

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since   1.0.0
	 * @return  WC_COS A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin.
	 *
	 * @since  1.0.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since  1.0.0
	 */
	public function plugin_classes() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-wc-cos-admin.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-wc-cos-admin-orders.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-wc-cos-safe-domdocument.php';
		// $this->plugin_class = new WCCOS_Plugin_Class( $this );
		$this->WC_COS_Admin = new WC_COS_Admin( $this );

	} // END OF PLUGIN CLASSES FUNCTION

	/**
	 * Add hooks and filters.
	 * Priority needs to be
	 * < 10 for CPT_Core,
	 * < 5 for Taxonomy_Core,
	 * and 0 for Widgets because widgets_init runs at init priority 1.
	 *
	 * @since  1.0.0
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Activate the plugin.
	 *
	 * @since  1.0.0
	 */
	public function _activate() {
		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Make sure any rewrite functionality has been loaded.
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin.
	 * Uninstall routines should be in uninstall.php.
	 *
	 * @since  1.0.0
	 */
	public function _deactivate() {
		// Add deactivation cleanup functionality here.
	}

	/**
	 * Init hooks
	 *
	 * @since  1.0.0
	 */
	public function init() {

		// Bail early if requirements aren't met.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Load translated strings for plugin.
		load_plugin_textdomain( 'woocommerce-cost-of-shipping', false, dirname( $this->basename ) . '/languages/' );

		// Initialize plugin classes.
		$this->plugin_classes();
	}

	/**
	 * Check if the plugin meets requirements and
	 * disable it if they are not present.
	 *
	 * @since  1.0.0
	 *
	 * @return boolean True if requirements met, false if not.
	 */
	public function check_requirements() {

		// Bail early if plugin meets requirements.
		if ( $this->meets_requirements() ) {
			return true;
		}

		// Add a dashboard notice.
		add_action( 'all_admin_notices', array( $this, 'requirements_not_met_notice' ) );

		// Deactivate our plugin.
		add_action( 'admin_init', array( $this, 'deactivate_me' ) );

		// Didn't meet the requirements.
		return false;
	}

	/**
	 * Deactivates this plugin, hook this function on admin_init.
	 *
	 * @since  1.0.0
	 */
	public function deactivate_me() {

		// We do a check for deactivate_plugins before calling it, to protect
		// any developers from accidentally calling it too early and breaking things.
		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( $this->basename );
		}
	}

	/**
	 * Check that all plugin requirements are met.
	 *
	 * @since  1.0.0
	 *
	 * @return boolean True if requirements are met.
	 */
	public function meets_requirements() {

		// Do checks for required classes / functions or similar.
		// Add detailed messages to $this->activation_errors array.
		
		$flag = true;
		
		if ( ! is_woocommerce_active() ) {
			array_push( $this->activation_errors, 'WooCommerce is a required plugin for WooCommerce Cost of Shipping!' );
			$flag = false;
		} 
		
		
		return $flag;
	}

	/**
	 * Adds a notice to the dashboard if the plugin requirements are not met.
	 *
	 * @since  1.0.0
	 */
	public function requirements_not_met_notice() {

		// Compile default message.
		$default_message = sprintf( __( 'WooCommerce Cost of Shipping is missing requirements and has been <a href="%s">deactivated</a>. Please make sure all requirements are available.', 'woocommerce-cost-of-shipping' ), admin_url( 'plugins.php' ) );

		// Default details to null.
		$details = null;

		// Add details if any exist.
		if ( $this->activation_errors && is_array( $this->activation_errors ) ) {
			$details = '<small>' . implode( '</small><br /><small>', $this->activation_errors ) . '</small>';
		}

		// Output errors.
		?>
		<div id="message" class="error">
			<p><?php echo wp_kses_post( $default_message ); ?></p>
			<?php echo wp_kses_post( $details ); ?>
		</div>
		<?php
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  1.0.0
	 *
	 * @param  string $field Field to get.
	 * @throws Exception     Throws an exception if the field is invalid.
	 * @return mixed         Value of the field.
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'debug':
				return self::DEBUG || WP_DEBUG;
			case 'basename':
			case 'url':
			case 'path':
				return $this->$field;
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}
}

/**
 * Grab the WC_COS object and return it.
 * Wrapper for WC_COS::get_instance().
 *
 * @since  1.0.0
 * @return WC_COS  Singleton instance of plugin class.
 */
function wc_cos() {
	return WC_COS::get_instance();
}

// Kick it off.
add_action( 'plugins_loaded', array( wc_cos(), 'hooks' ) );

// Activation and deactivation.
register_activation_hook( __FILE__, array( wc_cos(), '_activate' ) );
register_deactivation_hook( __FILE__, array( wc_cos(), '_deactivate' ) );

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
