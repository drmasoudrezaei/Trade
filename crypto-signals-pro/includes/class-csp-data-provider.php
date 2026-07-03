<?php
/**
 * Fetches market data from public, keyless APIs (Binance Spot/Futures, CoinGecko, alternative.me)
 * with transient-based caching so the WordPress site never hammers upstream APIs.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Data_Provider {

	const BINANCE_SPOT_BASE    = 'https://api.binance.com';
	const BINANCE_FUTURES_BASE = 'https://fapi.binance.com';
	const COINGECKO_BASE       = 'https://api.coingecko.com/api/v3';
	const FEAR_GREED_URL       = 'https://api.alternative.me/fng/';

	/**
	 * Performs a cached GET request and returns decoded JSON (array) or WP_Error.
	 *
	 * @param string $url        Request URL.
	 * @param string $cache_key  Transient key.
	 * @param int    $ttl        Cache lifetime in seconds.
	 */
	private static function get_json( $url, $cache_key, $ttl = null ) {
		if ( null === $ttl ) {
			$ttl = (int) CSP_Settings::get( 'cache_minutes', 15 ) * MINUTE_IN_SECONDS;
		}

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'csp_http_error', sprintf( 'Upstream API returned HTTP %d for %s', $code, $url ) );
		}

		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'csp_json_error', 'Invalid JSON received from upstream API: ' . $url );
		}

		set_transient( $cache_key, $decoded, $ttl );

		return $decoded;
	}

	/**
	 * Fetches OHLCV candles from Binance Spot.
	 *
	 * @param string $symbol   Binance symbol, e.g. BTCUSDT.
	 * @param string $interval Kline interval, e.g. 4h.
	 * @param int    $limit    Number of candles.
	 */
	public static function get_ohlcv( $symbol, $interval = '4h', $limit = 100 ) {
		$symbol = strtoupper( sanitize_text_field( $symbol ) );
		$url    = add_query_arg(
			array(
				'symbol'   => $symbol,
				'interval' => $interval,
				'limit'    => absint( $limit ),
			),
			self::BINANCE_SPOT_BASE . '/api/v3/klines'
		);

		$cache_key = 'csp_ohlcv_' . md5( $symbol . $interval . $limit );
		$raw       = self::get_json( $url, $cache_key );

		if ( is_wp_error( $raw ) || ! is_array( $raw ) ) {
			return $raw instanceof WP_Error ? $raw : new WP_Error( 'csp_no_data', 'No OHLCV data for ' . $symbol );
		}

		$candles = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || count( $row ) < 6 ) {
				continue;
			}
			$candles[] = array(
				'open_time' => (int) $row[0],
				'open'      => (float) $row[1],
				'high'      => (float) $row[2],
				'low'       => (float) $row[3],
				'close'     => (float) $row[4],
				'volume'    => (float) $row[5],
			);
		}

		return $candles;
	}

	/**
	 * Fetches 24h ticker statistics from Binance Spot.
	 *
	 * @param string $symbol Binance symbol.
	 */
	public static function get_ticker_24h( $symbol ) {
		$symbol = strtoupper( sanitize_text_field( $symbol ) );
		$url    = add_query_arg( array( 'symbol' => $symbol ), self::BINANCE_SPOT_BASE . '/api/v3/ticker/24hr' );

		return self::get_json( $url, 'csp_ticker_' . md5( $symbol ) );
	}

	/**
	 * Fetches order book depth from Binance Spot, used to gauge short-term bid/ask pressure.
	 *
	 * @param string $symbol Binance symbol.
	 * @param int    $limit  Depth levels.
	 */
	public static function get_order_book( $symbol, $limit = 100 ) {
		$symbol = strtoupper( sanitize_text_field( $symbol ) );
		$url    = add_query_arg(
			array(
				'symbol' => $symbol,
				'limit'  => absint( $limit ),
			),
			self::BINANCE_SPOT_BASE . '/api/v3/depth'
		);

		return self::get_json( $url, 'csp_depth_' . md5( $symbol . $limit ), 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Fetches the ratio of long vs short positions held by Binance Futures "top traders"
	 * (large accounts / whales) — a real, public, keyless proxy for whale positioning.
	 *
	 * @param string $symbol Futures symbol, e.g. BTCUSDT.
	 * @param string $period Aggregation period, e.g. 4h.
	 */
	public static function get_top_trader_long_short_ratio( $symbol, $period = '4h' ) {
		$symbol = strtoupper( sanitize_text_field( $symbol ) );
		$url    = add_query_arg(
			array(
				'symbol' => $symbol,
				'period' => $period,
				'limit'  => 1,
			),
			self::BINANCE_FUTURES_BASE . '/futures/data/topLongShortPositionRatio'
		);

		return self::get_json( $url, 'csp_whale_ratio_' . md5( $symbol . $period ) );
	}

	/**
	 * Fetches the ratio of long vs short positions across all Binance Futures accounts
	 * (a proxy for "retail"/broad market positioning, to compare against whale behaviour).
	 *
	 * @param string $symbol Futures symbol.
	 * @param string $period Aggregation period.
	 */
	public static function get_global_long_short_ratio( $symbol, $period = '4h' ) {
		$symbol = strtoupper( sanitize_text_field( $symbol ) );
		$url    = add_query_arg(
			array(
				'symbol' => $symbol,
				'period' => $period,
				'limit'  => 1,
			),
			self::BINANCE_FUTURES_BASE . '/futures/data/globalLongShortAccountRatio'
		);

		return self::get_json( $url, 'csp_retail_ratio_' . md5( $symbol . $period ) );
	}

	/**
	 * Fetches CoinGecko market data (price, market cap, % changes) for a single coin.
	 *
	 * @param string $coingecko_id CoinGecko coin id, e.g. bitcoin.
	 */
	public static function get_coin_market_data( $coingecko_id ) {
		$coingecko_id = sanitize_text_field( $coingecko_id );
		$url          = add_query_arg(
			array(
				'vs_currency'           => 'usd',
				'ids'                   => $coingecko_id,
				'price_change_percentage' => '24h,7d',
			),
			self::COINGECKO_BASE . '/coins/markets'
		);

		$data = self::get_json( $url, 'csp_cg_market_' . md5( $coingecko_id ) );

		if ( is_wp_error( $data ) || empty( $data[0] ) ) {
			return is_wp_error( $data ) ? $data : new WP_Error( 'csp_no_data', 'No CoinGecko market data for ' . $coingecko_id );
		}

		return $data[0];
	}

	/**
	 * Fetches CoinGecko global market data (total market cap, BTC dominance, etc.).
	 */
	public static function get_global_market_data() {
		$data = self::get_json( self::COINGECKO_BASE . '/global', 'csp_cg_global', 15 * MINUTE_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['data'] ) ) {
			return is_wp_error( $data ) ? $data : new WP_Error( 'csp_no_data', 'No CoinGecko global data' );
		}

		return $data['data'];
	}

	/**
	 * Fetches the Fear & Greed Index (market-wide sentiment gauge).
	 */
	public static function get_fear_greed_index() {
		$data = self::get_json( self::FEAR_GREED_URL . '?limit=1', 'csp_fng', 30 * MINUTE_IN_SECONDS );

		if ( is_wp_error( $data ) || empty( $data['data'][0] ) ) {
			return is_wp_error( $data ) ? $data : new WP_Error( 'csp_no_data', 'No Fear & Greed data' );
		}

		return $data['data'][0];
	}
}
