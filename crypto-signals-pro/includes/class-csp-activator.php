<?php
/**
 * Fired during plugin activation.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Activator {

	/**
	 * Runs on plugin activation: creates tables, seeds defaults, schedules cron.
	 */
	public static function activate() {
		CSP_DB::create_tables();
		CSP_DB::maybe_seed_default_coins();

		if ( ! wp_next_scheduled( 'csp_refresh_signals_event' ) ) {
			wp_schedule_event( time() + 60, 'csp_fifteen_minutes', 'csp_refresh_signals_event' );
		}

		if ( ! wp_next_scheduled( 'csp_evaluate_history_event' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'csp_evaluate_history_event' );
		}

		if ( false === get_option( 'csp_settings' ) ) {
			add_option( 'csp_settings', CSP_Settings::get_defaults() );
		}
	}
}
