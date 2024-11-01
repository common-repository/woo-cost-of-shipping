<?php
/**
 * WooCommerce Cost of Shipping Admin Orders
 * 
 * This file adds the cost of shipping functionality to the Admin Orders page.
 * 
 * Currently, it allows for importing the cost of shipping from the WooCommerce Shipstation plugin.
 * It utilizes the safedom standards introduced by the plugin itself in the class-wc-cos-safe-domdocument.php file
 * 
 * Additionally, this file enqueues some jQuery, and uses a woocommerce action to provide an editable
 * field in the order page to allow an end user to apply a cost of shipping manually.
 * 
 * Future expansions of this plugin may include:
 * - Stamps integration
 * - ShippingEasy integration
 * - <<< WooCommerce Services "Shipping" plugin >>> done as of 1.2.0
 * - Freightview for WooCommerce
 * - Orodoro
 * 
 * This plugin can be expanded into net profit reporting by getting the WooCommerce Net Profit plugin
 * developed by TheRiteSites and found at https://www.theritesites.com/plugins/woocommerce-net-profit
 * 
 * 
 * @since 1.0.0
 * @package WC_COS
 * @subpackage WC_COS/includes/admin
 * 
 */
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WC_COS_Admin_Orders {

	private $log = null;
	
	/**
	 * Parent plugin class.
	 *
	 * @var    WooCommerce_Net_Profit
	 * @since  1.0.0
	 */
	protected $plugin = null;

	/**
	 * Bootstrap class
	 *
	 * @since 1.0.0
	 */
	public function __construct( $plugin ) {
		
		$this->plugin = $plugin;

		$this->init_hooks();


		if ( ! function_exists( 'is_woocommerce_services_active' ) ) {
			require_once( $this->plugin->__get('path') . 'woo-includes/woo-functions.php' );
		}
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	protected function init_hooks() {

		// Adds style sheets for the order edit page
		add_action( 'admin_print_styles', array( $this, 'enqueue_styles' ) );

		// Adds javascript files for the order edit page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Display the order total cost of shipping on the order admin page
		add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'show_order_shipping_cost' ) );

		// When shipstation comes back with the shipment information, catch the incoming data
		add_action( 'woocommerce_shipstation_shipnotify', array($this, 'maybe_save_order_shipping_cost_shipstation'), 10, 2 );
	
		// Adds order note when cost of shipping is changed
		add_action( 'wc_cos_after_cost_of_shipping_stored', array( $this, 'add_order_shipping_cost_to_order_note' ), 10, 3 );

		// Sets the cost of shipping when it is manually entered via an AJAX request
		add_action( 'wp_ajax_set_shipping_cost', array( $this, 'set_shipping_cost_callback') );
	
		// If WooCommerce Services is active when the order is marked complete, attempts to save Cost of Shipping
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_save_order_shipping_cost_woocommerce_services' ) );
		
		// Adds meta box to individual orders screen to display manual import button for Cost of Shipping when using WooCommerce Services
		add_action( 'add_meta_boxes', array( $this, 'add_wcs_cos_meta_box' ), 10, 2 );
	
		// Callback for when the import Cost of Shipping button is pressed, when using WooCommerce Services for shipping labels.
		add_action( 'wp_ajax_set_label_cost_wcs', array( $this, 'set_label_cost_wcs_callback' ) );
	}

	/**
	 * Function to register and enqueue javascript files for the order admin page
	 * 
	 * @since 1.1.0
	 */
	public function enqueue_scripts() {
		global $pagenow;
		global $post_type;
		$admin_page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( ( $pagenow == 'post.php' && $post_type == 'shop_order' ) || ( $admin_page == 'wc-orders' && $pagenow == 'admin.php' ) ) {

			$js_file = '';
			$path = realpath( dirname(__FILE__) . '/../' );

			if ( file_exists( $path .  '/assets/js/woo-cost-of-shipping.min.js' ) && ! ( $this->plugin->__get( 'debug' ) || WP_DEBUG ) ) {
				$js_file = plugins_url( '/assets/js/woo-cost-of-shipping.min.js', plugin_dir_path(__DIR__) );
			}
			else
				$js_file = plugins_url( '/assets/js/woocommerce-cost-of-shipping.js', plugin_dir_path(__DIR__) );

			if ( ! empty( $js_file ) ) {
				wp_register_script( $this->plugin->basename , $js_file, array( 'jquery' ), $this->plugin->version, false );

				wp_localize_script( $this->plugin->basename, 'WCCOSApi', array(
					'nonce' => wp_create_nonce('wp_rest'),
					'currency' => get_woocommerce_currency_symbol(),
					'debug' => $this->plugin->__get( 'debug' )
				));
				wp_enqueue_script( $this->plugin->basename );
			}
		}
	}

	/**
	 * Function to register and enqueue CSS files for the order admin page
	 * 
	 * @since 1.0.0
	 */
	public function enqueue_styles() {
		global $pagenow;
		global $post_type;
		$admin_page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( ( $pagenow == 'post.php' && $post_type == 'shop_order' ) || ( $pagenow == 'admin.php' && $admin_page == 'wc-orders' ) ) {

			$cs_file = '';
			$path = realpath(dirname(__FILE__) . '/../');
			
			if ( file_exists( $path . '/assets/css/woocommerce-cost-of-shipping.min.css' ) && !( $this->plugin->__get( 'debug' ) || WP_DEBUG ) ) {
				$cs_file = plugins_url( '/assets/css/woocommerce-cost-of-shipping.min.css', plugin_dir_path(__DIR__) );
			}
			else
				$cs_file = plugins_url( '/assets/css/woocommerce-cost-of-shipping.css', plugin_dir_path(__DIR__) );
			wp_enqueue_style( $this->plugin->basename, $cs_file, array(), $this->plugin->version );
		}
	}

	/**
	 * Helper function to add a node to a WooCommerce order when the cost of shipping is changed
	 * 
	 * @since 1.1.0
	 *
	 * @param int	$order_id	the id of the order to store to
	 * @param float $cos		cost of shipping
	 * @param string $method	method that cost of shipping was calulcated from
	 */
	public function add_order_shipping_cost_to_order_note( $order_id, $cos, $method ) {
		$order = wc_get_order( $order_id );

		$fcos = wc_price( $cos );
		$by = $method == 'manual' ? __('manually', 'woocommerce-cost-of-shipping') : sprintf(__('automatically by %s', 'woocommerce-cost-of-shipping'), $method);
		// If automatically changed by an integration component, there is a change in verbage.
		// Careful on translation here!
		$note = sprintf( __('Cost of Shipping was changed to %s %s', 'woocommerce-cost-of-shipping'), $fcos, $by );

		$order->add_order_note( $note );
		$order->save();
	}

	/**
	 * Callback function for AJAX request to handle the manual shipping cost entry.
	 * This function will return success on successful $order->update_meta_data for the new shipping cost and method.
	 * 
	 * @since 1.0.0
	 * 
	 * @return json error|success
	 */
	function set_shipping_cost_callback() {
			
		if ( ! check_ajax_referer( 'wp_rest', 'security' ) || ! is_user_logged_in() || ! current_user_can('manage_woocommerce') ) {
			wp_send_json_error();
			wp_die();
		}

		$post_id = intval( sanitize_text_field( $_POST['post_id'] ) );
		$cos     = floatval( sanitize_text_field( $_POST['shipping_cost'] ) );

		do_action( 'wc_cos_before_ajax_set_shipping_cost', $post_id, $cos, 'manual' );
		
		// Returns true on success, false on anything else
		if ( $this->store_cost_of_shipping( $post_id, $cos, 'manual' ) ){
			
			do_action( 'wc_cos_after_ajax_set_shipping_cost', $post_id, $cos, 'manual' );
			wp_send_json_success();
			wp_die();
		}
		wp_send_json_error();
		wp_die();
		
	}


	/**
	 * Helper function to store cost of shipping and method to postmeta table with added filter
	 * 
	 * @since 1.0.0
	 *
	 * @param int	$order_id the id of the order to store to
	 * @param float	$cos	cost of shipping
	 * @param string $method	method that cost of shipping was calculated from
	 * 
	 * @return bool
	 */
	public function store_cost_of_shipping( $order_id, $cos, $method ) {
		
		$flag = true;
		if ( is_null( $this->log ) ) {
			$this->log = new WC_Logger();
		}
		if ( true === $this->plugin->__get( 'debug' ) ) {
			$this->log->add( 'the-rite-sites-profit-plugins', __( 'Function: store_cost_of_shipping', 'woocommerce-cost-of-shipping' ) );
			$this->log->add( 'the-rite-sites-profit-plugins', sprintf( __( '   Order cost of shipping being stored to order id %d', 'woocommerce-cost-of-shipping' ), $order_id ) );
			$this->log->add( 'the-rite-sites-profit-plugins', sprintf( __( '       Cost of shipping: %.2f via method: %s', 'woocommerce-cost-of-shipping' ), $cos, $method ) );
		}
		$cos = apply_filters( 'wc_cos_store_cost_of_shipping', $cos, $order_id );
		$method = apply_filters( 'wc_cos_store_cost_of_shipping_method', $method, $order_id );
		
		do_action( 'wc_cos_before_cost_of_shipping_stored', $order_id, $cos, $method );

		// Query wc_order
		$order = wc_get_order( $order_id );

		// Store for possible history update before the new store overwrites.
		$old_cos = $order->get_meta( '_wc_cost_of_shipping', true );
		$old_method = $order->get_meta( '_wc_cos_method', true );

		/**
		 * We only need to go through the rest of the function if the values need
		 *   to be updated. If nothing is updated in the database for the actual
		 *   cost of shipping, then we do not need to update which method its being
		 *   saved by at this point. Might see some functionality wanted later on,
		 *   but this will be the simplest way forward to not corrupt data.
		 * 
		 *  Updated in 1.5.0 for HPOS
		 */
		if ( ( float ) $old_cos == ( float ) $cos ) {
			if ( true === $this->plugin->__get( 'debug' ) ) {
				$this->log->add( 'the-rite-sites-profit-plugins', __( '       No need to update Cost of Shipping, values are equivalent.', 'woocommerce-cost-of-shipping' ) );
			}
			return false;
		}
		$order->update_meta_data( '_wc_cost_of_shipping', ( float ) $cos );

		$order->update_meta_data( $order_id, '_wc_cos_method', $method );

		if ( true === $this->plugin->__get( 'debug' ) ) {
			$this->log->add( 'the-rite-sites-profit-plugins', __( '     Cost of shipping value and method updated.', 'woocommerce-cost-of-shipping' ) );
		}

		if ( ! empty( $old_cos ) && ! empty( $old_method ) ) {
			$dirty_cos_history = $order->get_meta( '_wc_cos_history' );
			$cos_history = maybe_unserialize( $dirty_cos_history );
			if ( empty( $cos_history ) ) {
				$cos_history = array();
			}

			$cos_history[] = array( 'method' => $old_method, 'cos' => $old_cos );
			$order->update_meta_data( '_wc_cos_history', $cos_history );

			if ( true === $this->plugin->__get( 'debug' ) ) {
				$this->log->add( 'the-rite-sites-profit-plugins', __( '     Cost of shipping history updated with value and method.', 'woocommerce-cost-of-shipping' ) );
			}
		}

		$order->save();
		do_action( 'wc_cos_after_cost_of_shipping_stored', $order_id, $cos, $method );
		return true;
	}
	
	/**
	 * Attempts to save the Cost of Shipping when a user is using
	 *   WooCommerce ShipStation as a means to purchase shipping labels.
	 * 
	 * @since 1.0.0
	 * 
	 * @param WC_Order $order	The current order received from ShipStation
	 */
	public function maybe_save_order_shipping_cost_shipstation( $order, $args ) {
		
		$xml = $this->get_parsed_xml( $args['xml'] );
		
		if ( is_null( $this->log ) ) {
			$this->log = new WC_Logger();
		}
		
		if ( ! isset( $xml ) ) {
			$this->log->add( 'shipstation', __( 'shipstation integration xml not set', 'woocommerce-cost-of-shipping' ) );
			$this->log->add( 'the-rite-sites-profit-plugins', __( 'shipstation integration xml not set', 'woocommerce-cost-of-shipping' ) );
		}
		elseif ( empty( $xml ) ) {
			$this->log->add( 'shipstation', __( 'shipstation integration xml is empty', 'woocommerce-cost-of-shipping' ) );
			$this->log->add( 'the-rite-sites-profit-plugins', __( 'shipstation integration xml is empty', 'woocommerce-cost-of-shipping' ) );
		}
		elseif ( ! isset( $xml->ShippingCost ) ) {
			$this->log->add( 'shipstation', __( 'shipstation integration xml does not have shipping amount set', 'woocommerce-cost-of-shipping' ) );
			$this->log->add( 'the-rite-sites-profit-plugins', __( 'shipstation integration xml does not have shipping amount set', 'woocommerce-cost-of-shipping' ) );
		}
		else {
			$shipping_cost = wc_clean( $xml->ShippingCost );
			$this->store_cost_of_shipping( $order->get_id(), $shipping_cost, 'shipstation' );
			$this->log->add( 'shipstation', 'After shipstation automatically stored cost of shipping.');
		}
	
	}

	/**
	 * Attempts to save the Cost of Shipping when a user is using
	 *   WooCommerce Services as a means to purchase USPS shipping labels.
	 * 
	 * @since 1.2.0
	 * 
	 * @param int $order_id		The current order which should have USPS labels
	 * 
	 * @return bool
	 */
	public function maybe_save_order_shipping_cost_woocommerce_services( $order_id = 0 ) {
		if ( is_null( $this->log ) ) {
			$this->log = new WC_Logger();
		}

		if ( true === is_woocommerce_services_active() ) {
			if ( true === $this->plugin->__get( 'debug' ) )
				$this->log->add( 'the-rite-sites-profit-plugins', __( 'WooCommerce services is active, retrieving labels.', 'woocommerce-cost-of-shipping' ) );
			$labels = $this->get_wcs_label_order_meta_data( $order_id );
			$flag = false;
			$cost_of_shipping = 0.00;
			foreach ( $labels as $label ) {
				if ( isset( $label['rate'] ) ) {
					$cost_of_shipping += floatval( $label['rate'] );
					$flag = true;
				}
			}

			$order = wc_get_order( $order_id );
			$previous_cos_method = $order->get_meta( '_wc_cos_method', true );
			if ( false === $flag && ! ( 'wc_services' === $previous_cos_method ) ) return false;
			if ( true === $this->store_cost_of_shipping( $order_id, $cost_of_shipping, 'wc_services' ) ) {
				return $cost_of_shipping;
			}
		}
		else {
			if ( true === $this->plugin->__get( 'debug' ) )
				$this->log->add( 'the-rite-sites-profit-plugins', __( '    WooCommerce services is not active, ignoring trying to save WooCommerce services labels as cost of shipping.', 'woocommerce-cost-of-shipping' ) );
		}
		return false;
	}

	/**
	 * Does validation and verification if the WooCommerce Services
	 *   Cost of Shipping import meta box should be displayed
	 * 
	 * @since 1.2.0
	 * 
	 * @return bool
	 */
	public function should_show_wcs_cos_meta_box() {
		global $post_type, $pagenow;
		$admin_page = isset( $_GET['page'] ) ? $_GET['page'] : '';

		if ( true === is_woocommerce_services_active() ) {
			$order = wc_get_order();
			if ( $order && ( ( 'shop_order' === $post_type && ! ( 'post-new.php' === $pagenow ) || ( $pagenow == 'admin.php' && $admin_page == 'wc-orders' ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handles displaying the internals of the meta box to import the
	 *   cost of shipping while the user is using WooCommerce Services
	 *   to purchase shipping labels.
	 * 
	 * @since 1.2.0
	 * 
	 * @param int $post_id	Current order ID passed to meta box via add_meta_box function
	 */
	public function display_wcs_cos_meta_box( $post_id ) {
		$order = ( $post_id instanceof WP_Post ) ? wc_get_order( $post_id->ID ) : $post_id;
		// $order = wc_get_order( $post_id );

		$cos = floatval( $order->get_meta( '_wc_cost_of_shipping' ) );

		if ( empty( $cos ) ) {
			?>
			<div class="wc-cos-wrapper">
				<button class="wc-cos-wcs-import button-primary" data-orderid="<?php echo $order->get_id() ?>" id="wcs-shipping-import">Import</button>
			</div>
			<?php
		}
		else {
			?>
			<div class="wc-cos-wrapper">
				<button class="wc-cos-wcs-import button-secondary" data-orderid="<?php echo $order->get_id() ?>" id="wcs-shipping-re-import">Re-Import</button>
			</div>
			<?php
		}
	}

	/**
	 * The callback for the AJAX call when either the import or re-import
	 *   buttons are pressed. This reads in the order ID and calls the
	 *   function in charge of attempting the reading and saving of the
	 *   Cost of Shipping as stored by WooCommerce Services
	 * 
	 * @since 1.2.0
	 * 
	 * @return json success|error
	 */
	public function set_label_cost_wcs_callback() {
		if ( ! check_ajax_referer( 'wp_rest', 'security' ) || ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
			wp_die();
		}

		$post_id = intval( sanitize_text_field( $_POST['post_id'] ) );

		do_action( 'wc_cos_before_ajax_set_label_cost_wcs', $post_id );
		
		// Returns true on success, false on anything else
		if ( $cost = $this->maybe_save_order_shipping_cost_woocommerce_services( $post_id ) ) {
			do_action( 'wc_cos_after_ajax_set_label_cost_wcs', $post_id );
			wp_send_json_success( array( 'cost' => $cost, 'currency' => get_woocommerce_currency_symbol() ) );
			wp_die();
		}
		elseif ( false === $cost ) {
			do_action( 'wc_cos_after_ajax_no_labels_wcs', $post_id );
			wp_send_json_success( array( 'message' => 'No labels exist.' ) );
			wp_die();
		}
		wp_send_json_error();
		wp_die();
	}

	/**
	 * Registers the meta box if and only if the user is using WooCommerce
	 *   Services.
	 * 
	 * @since 1.2.0
	 * 
	 * @param string $post_type		The current pages post type, passed by the add_meta_boxes action
	 * @param int	 $post			The current post ID.
	 */
	public function add_wcs_cos_meta_box( $post_type, $post ) {
		if ( $this->should_show_wcs_cos_meta_box() ) {

			$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
					? wc_get_page_screen_id( 'shop-order' )
					: 'shop_order';

			add_meta_box(
				'wc-cos-services-cost-of-shipping',
				__( 'Import Cost of Shipping', 'woocommerce-cost-of-shipping' ),
				array( $this, 'display_wcs_cos_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Attempts to recover faulty json string array fields that might contain strings with unescaped quotes
	 *
	 * @since 1.2.0
	 * 
	 * @param string $field_name
	 * @param string $json
	 *
	 * @return string
	 */
	public function try_recover_invalid_json_array( $field_name, $json ) {
		$regex = '/"' . $field_name . '":\["(.+?)"\]/';
		preg_match_all( $regex, $json, $match_groups );
		if ( 2 === count( $match_groups ) ) {
			foreach ( $match_groups[ 0 ] as $idx => $match ) {
				$array = $match_groups[ 1 ][ $idx ];
				$escaped_array = preg_replace( '/(?<![,\\\])"(?!,)/', '\\"', $array );
				$json = str_replace( '["' . $array . '"]', '["' . $escaped_array. '"]', $json );
			}
		}
		return $json;
	}

	/**
	 * Attempts to deserialize USPS labels from json stored in database.
	 * 
	 * @since 1.2.0
	 * 
	 * @param serialized array	$label_data		Raw data from database
	 * 
	 * @return array
	 */
	public function try_deserialize_labels_json( $label_data ) {
		//attempt to decode the JSON (legacy way of storing the labels data)
		$decoded_labels = json_decode( $label_data, true );
		if ( $decoded_labels ) {
			return $decoded_labels;
		}
		$label_data = $this->try_recover_invalid_json_string( 'package_name', $label_data );
		$decoded_labels = json_decode( $label_data, true );
		if ( $decoded_labels ) {
			return $decoded_labels;
		}
		$label_data = $this->try_recover_invalid_json_array( 'product_names', $label_data );
		$decoded_labels = json_decode( $label_data, true );
		if ( $decoded_labels ) {
			return $decoded_labels;
		}
		return array();
	}

	/**
	 * Returns labels for the specific order ID
	 *
	 * @since 1.2.0
	 * 
	 * @param $order_id
	 *
	 * @return array
	 */
	public function get_wcs_label_order_meta_data( $order_id ) {
		$order = wc_get_order( ( int ) $order_id );
		$label_data = $order->get_meta( 'wc_connect_labels', true );
		//return an empty array if the data doesn't exist
		if ( ! $label_data ) {
			return array();
		}
		//labels stored as an array, return
		if ( is_array( $label_data ) ) {
			return $label_data;
		}
		return $this->try_deserialize_labels_json( $label_data );
	}
	
	/**
	 * Get Parsed XML response for shipstation API response
	 *
	 * @since 1.0.0
	 * 
	 * @param  string $xml XML.
	 * 
	 * @return string|bool
	 */
	private function get_parsed_xml( $xml ) {
		if ( ! class_exists( 'WC_COS_Safe_DOMDocument' ) ) {
			include_once( 'class-wc-cos-safe-domdocument.php' );
		}

		if ( is_null( $this->log ) ) {
			$this->log = new WC_Logger();
		}

		libxml_use_internal_errors( true );

		$dom     = new WC_COS_Safe_DOMDocument;
		$success = $dom->loadXML( $xml );

		if ( ! $success ) {
			$this->log->add( 'the-rite-sites-profit-plugins', __( 'wpcom_safe_simplexml_load_string(): Error loading XML string in shipstation xml retrieve', 'woocommerce-cost-of-shipping' ) );
			return false;
		}

		if ( isset( $dom->doctype ) ) {
			$this->log->add( 'the-rite-sites-profit-plugins', __( 'wpcom_safe_simplexml_import_dom(): Unsafe DOCTYPE Detected in shipstation xml retrieve', 'woocommerce-cost-of-shipping' ) );
			return false;
		}

		return simplexml_import_dom( $dom, 'SimpleXMLElement' );
	}

	/**
	 * Render a read-only input box with the order shipping cost with an edit link below it
	 * 
	 * The edit link will spawn a javascript modal that will have an editable field and save button.
	 *
	 * @since 1.0.0
	 * 
	 * @param int $post_id post (order) ID
	 */
	public function show_order_shipping_cost( $post_id ) {
		
		$ajax_nonce = wp_create_nonce( 'edit-shipping-meta-nonce' );
		$order = wc_get_order( $post_id );
		$cost_of_shipping = floatval( $order->get_meta( '_wc_cost_of_shipping', true ) );
		$formatted_total = wc_price( $cost_of_shipping );
		?>
		<tr>

			<td class="total shipping-total"><?php esc_html_e( 'Shipping Cost', 'woocommerce-cost-of-shipping' ); ?>:</td>

			<td width="1%"></td>

			<td class="total shipping-total">
				<span id="shipping-cost">
					<?php echo $formatted_total; ?>
				
					<div class="edit-shipping-tooltip" style="display:none;">
						<input id="shipping-cost-input" type="number" step="0.01" min="0" value="<?php echo esc_attr($cost_of_shipping); ?>" />
						<div class="edit-shipping-buttons">
							<button id="edit-shipping-cancel" class="button" type="button">Cancel</button>
							<button id="edit-shipping-save" class="button button-primary" type="button">Save</button>
						</div>
					</div>
				</span>
			</td>

			<td width="1%"><a data-orderid="<?php echo $post_id; ?>" id="edit-shipping-cost">edit</a></td>

		</tr>
		<?php
	}

	
	
	/**
	 * Return the formatted order shipping cost, which includes the refunded order
	 * total cost if refunds have been processed
	 *
	 * @since 1.0.0
	 * 
	 * @param int $order_id
	 * 
	 * @return string formatted total
	 */
	protected function get_formatted_order_shipping_cost( $order_id ) {

		$order = wc_get_order( $order_id );
		$order_shipping_cost = $order->get_meta( '_wc_cost_of_shipping', true );
		$formatted_total = wc_price( $order_shipping_cost );

		return $formatted_total;
	}
}
