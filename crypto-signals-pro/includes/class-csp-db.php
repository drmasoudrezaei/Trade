<?php
/**
 * Database table helpers and schema.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_DB {

	/**
	 * Returns coins table name.
	 */
	public static function coins_table() {
		global $wpdb;
		return $wpdb->prefix . 'csp_coins';
	}

	/**
	 * Returns live signals table name.
	 */
	public static function signals_table() {
		global $wpdb;
		return $wpdb->prefix . 'csp_signals';
	}

	/**
	 * Returns signal history table name (used for transparent win-rate tracking).
	 */
	public static function signal_history_table() {
		global $wpdb;
		return $wpdb->prefix . 'csp_signal_history';
	}

	/**
	 * Returns subscriptions table name.
	 */
	public static function subscriptions_table() {
		global $wpdb;
		return $wpdb->prefix . 'csp_subscriptions';
	}

	/**
	 * Creates/updates all plugin tables using dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$coins_table        = self::coins_table();
		$signals_table      = self::signals_table();
		$history_table      = self::signal_history_table();
		$subscriptions_table = self::subscriptions_table();

		$sql = "CREATE TABLE {$coins_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			symbol VARCHAR(20) NOT NULL,
			name VARCHAR(100) NOT NULL DEFAULT '',
			coingecko_id VARCHAR(100) NOT NULL DEFAULT '',
			binance_symbol VARCHAR(20) NOT NULL DEFAULT '',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY symbol (symbol)
		) {$charset_collate};

		CREATE TABLE {$signals_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			coin_id BIGINT UNSIGNED NOT NULL,
			signal VARCHAR(20) NOT NULL DEFAULT 'hold',
			confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
			technical_score DECIMAL(6,2) NOT NULL DEFAULT 0,
			whale_score DECIMAL(6,2) NOT NULL DEFAULT 0,
			fundamental_score DECIMAL(6,2) NOT NULL DEFAULT 0,
			price DECIMAL(24,8) NOT NULL DEFAULT 0,
			entry_price DECIMAL(24,8) NOT NULL DEFAULT 0,
			stop_loss DECIMAL(24,8) NOT NULL DEFAULT 0,
			take_profit DECIMAL(24,8) NOT NULL DEFAULT 0,
			whale_long_ratio DECIMAL(6,2) NOT NULL DEFAULT 0,
			retail_long_ratio DECIMAL(6,2) NOT NULL DEFAULT 0,
			details LONGTEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY coin_id (coin_id)
		) {$charset_collate};

		CREATE TABLE {$history_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			coin_id BIGINT UNSIGNED NOT NULL,
			signal VARCHAR(20) NOT NULL,
			confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
			entry_price DECIMAL(24,8) NOT NULL DEFAULT 0,
			stop_loss DECIMAL(24,8) NOT NULL DEFAULT 0,
			take_profit DECIMAL(24,8) NOT NULL DEFAULT 0,
			opened_at DATETIME NOT NULL,
			closed_at DATETIME NULL,
			result VARCHAR(10) NOT NULL DEFAULT 'open',
			close_price DECIMAL(24,8) NULL,
			pnl_percent DECIMAL(8,2) NULL,
			PRIMARY KEY  (id),
			KEY coin_id (coin_id),
			KEY result (result)
		) {$charset_collate};

		CREATE TABLE {$subscriptions_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			plan_name VARCHAR(100) NOT NULL DEFAULT '',
			coin_access VARCHAR(20) NOT NULL DEFAULT 'all',
			coin_ids TEXT NULL,
			starts_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) {$charset_collate};
		";

		dbDelta( $sql );
	}

	/**
	 * Seeds a sensible default coin watchlist on first install.
	 */
	public static function maybe_seed_default_coins() {
		global $wpdb;

		$coins_table = self::coins_table();
		$count       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$coins_table}" );

		if ( $count > 0 ) {
			return;
		}

		$defaults = array(
			array( 'BTC', 'Bitcoin', 'bitcoin', 'BTCUSDT' ),
			array( 'ETH', 'Ethereum', 'ethereum', 'ETHUSDT' ),
			array( 'BNB', 'BNB', 'binancecoin', 'BNBUSDT' ),
			array( 'SOL', 'Solana', 'solana', 'SOLUSDT' ),
			array( 'XRP', 'XRP', 'ripple', 'XRPUSDT' ),
			array( 'ADA', 'Cardano', 'cardano', 'ADAUSDT' ),
			array( 'DOGE', 'Dogecoin', 'dogecoin', 'DOGEUSDT' ),
			array( 'TON', 'Toncoin', 'the-open-network', 'TONUSDT' ),
			array( 'AVAX', 'Avalanche', 'avalanche-2', 'AVAXUSDT' ),
			array( 'LINK', 'Chainlink', 'chainlink', 'LINKUSDT' ),
		);

		foreach ( $defaults as $index => $coin ) {
			$wpdb->insert(
				$coins_table,
				array(
					'symbol'         => $coin[0],
					'name'           => $coin[1],
					'coingecko_id'   => $coin[2],
					'binance_symbol' => $coin[3],
					'is_active'      => 1,
					'sort_order'     => $index,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d' )
			);
		}
	}
}
