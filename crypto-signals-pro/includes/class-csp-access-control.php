<?php
/**
 * Subscription-based access control. Admins manually (or via a future WooCommerce hook)
 * grant a user an active subscription row; this class checks whether a given user
 * currently has access to the live signals page/shortcode.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Access_Control {

	/**
	 * Checks whether a user currently has active access to the signals dashboard.
	 * Site administrators (manage_options) always have access so they can preview/manage content.
	 *
	 * @param int $user_id User ID; defaults to the current user.
	 */
	public static function user_has_access( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return apply_filters( 'csp_user_has_access', false, 0 );
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return apply_filters( 'csp_user_has_access', true, $user_id );
		}

		$subscription = self::get_active_subscription( $user_id );

		return apply_filters( 'csp_user_has_access', null !== $subscription, $user_id );
	}

	/**
	 * Returns the current active subscription row for a user, or null.
	 *
	 * @param int $user_id User ID.
	 */
	public static function get_active_subscription( $user_id ) {
		global $wpdb;

		$table = CSP_DB::subscriptions_table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d
				AND status = 'active'
				AND starts_at <= %s
				AND (expires_at IS NULL OR expires_at >= %s)
				ORDER BY id DESC
				LIMIT 1",
				$user_id,
				$now,
				$now
			)
		);

		return $row;
	}

	/**
	 * Returns whether a user's subscription grants access to a specific coin.
	 *
	 * @param int $user_id User ID.
	 * @param int $coin_id Coin ID.
	 */
	public static function user_has_coin_access( $user_id, $coin_id ) {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$subscription = self::get_active_subscription( $user_id );
		if ( ! $subscription ) {
			return false;
		}

		if ( 'all' === $subscription->coin_access ) {
			return true;
		}

		$allowed_ids = array_map( 'absint', array_filter( explode( ',', (string) $subscription->coin_ids ) ) );
		return in_array( (int) $coin_id, $allowed_ids, true );
	}

	/**
	 * Creates or updates a subscription row for a user.
	 *
	 * @param array $data Subscription fields: user_id, status, plan_name, coin_access, coin_ids, starts_at, expires_at, notes.
	 * @param int   $id   Existing subscription ID to update, or 0 to insert.
	 */
	public static function save_subscription( array $data, $id = 0 ) {
		global $wpdb;

		$table = CSP_DB::subscriptions_table();

		$fields = array(
			'user_id'     => absint( $data['user_id'] ),
			'status'      => sanitize_key( $data['status'] ),
			'plan_name'   => sanitize_text_field( $data['plan_name'] ),
			'coin_access' => 'custom' === $data['coin_access'] ? 'custom' : 'all',
			'coin_ids'    => isset( $data['coin_ids'] ) ? sanitize_text_field( $data['coin_ids'] ) : '',
			'starts_at'   => ! empty( $data['starts_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['starts_at'] ) ) : current_time( 'mysql' ),
			'expires_at'  => ! empty( $data['expires_at'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $data['expires_at'] ) ) : null,
			'notes'       => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ) );
			return $id;
		}

		$wpdb->insert( $table, $fields );
		return $wpdb->insert_id;
	}

	/**
	 * Deletes a subscription row.
	 *
	 * @param int $id Subscription ID.
	 */
	public static function delete_subscription( $id ) {
		global $wpdb;
		$table = CSP_DB::subscriptions_table();
		return $wpdb->delete( $table, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Returns all subscriptions, most recent first.
	 */
	public static function get_all_subscriptions() {
		global $wpdb;
		$table = CSP_DB::subscriptions_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}
}
