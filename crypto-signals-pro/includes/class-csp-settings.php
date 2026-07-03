<?php
/**
 * Plugin settings (indicator weights, refresh interval, API options, CTA text).
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Settings {

	const OPTION_KEY = 'csp_settings';

	/**
	 * Returns default settings values.
	 */
	public static function get_defaults() {
		return array(
			// Weighting of the three analysis pillars, must sum to 100.
			'weight_technical'      => 40,
			'weight_whale'          => 35,
			'weight_fundamental'    => 25,

			// Risk model.
			'atr_stop_multiplier'   => 1.5,
			'atr_target_multiplier' => 3.0,
			'signal_timeframe'      => '4h',

			// Fear & Greed interpretation: 'contrarian' (extreme fear = bullish) or 'trend' (follow momentum).
			'fear_greed_mode'       => 'contrarian',

			// How many minutes of cache before re-fetching external data.
			'cache_minutes'         => 15,

			// How many days an open tracked signal is allowed to run before being marked "expired" for win-rate stats.
			'history_expiry_days'   => 10,

			// Optional premium API keys for future/advanced integrations (not required for core functionality).
			'api_key_whale_alert'   => '',
			'api_key_glassnode'     => '',
			'api_key_cryptoquant'   => '',

			// Access / paywall.
			'locked_message'        => 'برای مشاهده سیگنال‌های زنده و گزارش کامل بازار، لطفاً اشتراک تهیه کنید.',
			'purchase_url'          => '',

			// Disclaimer shown next to every signal — keep this on, it is not legal boilerplate but an honest framing.
			'disclaimer'            => 'این گزارش صرفاً یک تحلیل خودکار بر اساس داده‌های عمومی بازار است و مشاوره مالی/سرمایه‌گذاری محسوب نمی‌شود. نرخ موفقیت نمایش داده‌شده بر اساس عملکرد تاریخی همین سیستم محاسبه می‌شود و هیچ نتیجه‌ای برای آینده تضمین نمی‌شود.',
		);
	}

	/**
	 * Returns all settings merged with defaults.
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Returns a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if not found.
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Updates settings (merges with existing).
	 *
	 * @param array $new_values New values to persist.
	 */
	public static function update( array $new_values ) {
		$current = self::get_all();
		$merged  = array_merge( $current, $new_values );
		update_option( self::OPTION_KEY, $merged );
		return $merged;
	}

	/**
	 * Returns list of coin data refresh intervals allowed for the cron/signal engine.
	 */
	public static function get_timeframe_choices() {
		return array(
			'1h'  => '۱ ساعته',
			'4h'  => '۴ ساعته',
			'1d'  => 'روزانه',
		);
	}
}
