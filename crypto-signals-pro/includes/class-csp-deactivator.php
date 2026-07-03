<?php
/**
 * Fired during plugin deactivation.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Deactivator {

	/**
	 * Runs on plugin deactivation: clears scheduled cron events.
	 * Tables and settings are intentionally kept so data is not lost on deactivate.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'csp_refresh_signals_event' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'csp_refresh_signals_event' );
		}

		$timestamp_history = wp_next_scheduled( 'csp_evaluate_history_event' );
		if ( $timestamp_history ) {
			wp_unschedule_event( $timestamp_history, 'csp_evaluate_history_event' );
		}
	}
}
