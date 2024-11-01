<?php
/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WC_COS_Dependencies' ) )
	require_once 'class-wc-cos-dependencies.php';

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	function is_woocommerce_active() {
		return WC_COS_Dependencies::woocommerce_active_check();
	}
}

/**
 * WC Services Detection
 */
if ( ! function_exists( 'is_woocommerce_services_active' ) ) {
	function is_woocommerce_services_active() {
		return WC_COS_Dependencies::woocommerce_services_active_check();
	}
}