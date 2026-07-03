<?php
/**
 * admin-ajax.php handlers used by both the admin panel and the front-end shortcode.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Ajax {

	/**
	 * Registers AJAX hooks.
	 */
	public function init() {
		add_action( 'wp_ajax_csp_refresh_now', array( $this, 'handle_refresh_now' ) );
		add_action( 'wp_ajax_csp_search_users', array( $this, 'handle_search_users' ) );
	}

	/**
	 * Admin-only: force an immediate refresh of all (or a single) coin's signal.
	 */
	public function handle_refresh_now() {
		check_ajax_referer( 'csp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'crypto-signals-pro' ) ), 403 );
		}

		$coin_id = isset( $_POST['coin_id'] ) ? absint( $_POST['coin_id'] ) : 0;
		$cron    = new CSP_Cron();

		if ( $coin_id > 0 ) {
			global $wpdb;
			$coin = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . CSP_DB::coins_table() . ' WHERE id = %d', $coin_id ) );
			if ( ! $coin ) {
				wp_send_json_error( array( 'message' => __( 'ارز پیدا نشد.', 'crypto-signals-pro' ) ), 404 );
			}
			$result = $cron->refresh_coin_signal( $coin );
		} else {
			$cron->refresh_all_signals();
			$result = true;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'به‌روزرسانی انجام شد.', 'crypto-signals-pro' ) ) );
	}

	/**
	 * Admin-only: lightweight user search used by the subscriptions screen.
	 */
	public function handle_search_users() {
		check_ajax_referer( 'csp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'crypto-signals-pro' ) ), 403 );
		}

		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

		$users = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'number'         => 20,
			)
		);

		$data = array_map(
			function ( $user ) {
				return array(
					'id'   => $user->ID,
					'text' => sprintf( '%s (%s)', $user->display_name, $user->user_email ),
				);
			},
			$users
		);

		wp_send_json_success( array( 'users' => $data ) );
	}
}
