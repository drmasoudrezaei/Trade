<?php
/**
 * Core plugin bootstrap: wires up cron, ajax, shortcode and public assets.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Plugin {

	/**
	 * Registers all front-facing hooks.
	 */
	public function run() {
		load_plugin_textdomain( CSP_TEXT_DOMAIN, false, dirname( plugin_basename( CSP_PLUGIN_FILE ) ) . '/languages' );

		$cron = new CSP_Cron();
		$cron->init();

		$ajax = new CSP_Ajax();
		$ajax->init();

		$shortcode = new CSP_Shortcode();
		$shortcode->init();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
	}

	/**
	 * Enqueues front-end CSS/JS only on pages that actually contain the shortcode.
	 *
	 * @param string $hook Current admin/front hook (unused on front-end but kept for signature consistency).
	 */
	public function enqueue_public_assets() {
		global $post;

		$has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'crypto_signals' );

		if ( ! $has_shortcode ) {
			return;
		}

		wp_enqueue_style( 'csp-public', CSP_PLUGIN_URL . 'public/css/public.css', array(), CSP_VERSION );
		wp_enqueue_script( 'csp-public', CSP_PLUGIN_URL . 'public/js/public.js', array( 'jquery' ), CSP_VERSION, true );

		wp_localize_script(
			'csp-public',
			'cspPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
}
