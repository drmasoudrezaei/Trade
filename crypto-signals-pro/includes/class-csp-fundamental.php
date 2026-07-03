<?php
/**
 * Fundamental / macro market analysis: Fear & Greed Index, BTC dominance trend,
 * and market cap momentum.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Fundamental {

	/**
	 * Builds a fundamental snapshot and score for a coin.
	 *
	 * @param array $coin_market_data CoinGecko market data row for the coin.
	 */
	public static function analyze( $coin_market_data ) {
		$result = array(
			'fear_greed_value'    => null,
			'fear_greed_label'    => '',
			'btc_dominance'       => null,
			'market_cap_change_24h' => null,
			'price_change_24h'    => null,
			'price_change_7d'     => null,
			'score'               => 0.0,
			'available'           => false,
		);

		$fng    = CSP_Data_Provider::get_fear_greed_index();
		$global = CSP_Data_Provider::get_global_market_data();

		$score        = 0.0;
		$weight_total = 0.0;
		$mode         = CSP_Settings::get( 'fear_greed_mode', 'contrarian' );

		if ( ! is_wp_error( $fng ) && isset( $fng['value'] ) ) {
			$value = (float) $fng['value'];
			$result['fear_greed_value'] = $value;
			$result['fear_greed_label'] = isset( $fng['value_classification'] ) ? $fng['value_classification'] : '';

			// Index runs 0 (extreme fear) .. 100 (extreme greed).
			if ( 'contrarian' === $mode ) {
				// Extreme fear => bullish contrarian opportunity, extreme greed => bearish risk.
				$fng_component = ( 50 - $value ) * 2;
			} else {
				// Trend-following: greed supports upward momentum, fear supports downward momentum.
				$fng_component = ( $value - 50 ) * 2;
			}

			$score        += $fng_component * 0.5;
			$weight_total += 0.5;
			$result['available'] = true;
		}

		if ( ! is_wp_error( $global ) ) {
			if ( isset( $global['market_cap_percentage']['btc'] ) ) {
				$result['btc_dominance'] = round( (float) $global['market_cap_percentage']['btc'], 2 );
			}
			if ( isset( $global['market_cap_change_percentage_24h_usd'] ) ) {
				$mcap_change = (float) $global['market_cap_change_percentage_24h_usd'];
				$result['market_cap_change_24h'] = round( $mcap_change, 2 );

				$mcap_component = max( -100, min( 100, $mcap_change * 10 ) );
				$score          += $mcap_component * 0.3;
				$weight_total   += 0.3;
				$result['available'] = true;
			}
		}

		if ( is_array( $coin_market_data ) ) {
			if ( isset( $coin_market_data['price_change_percentage_24h'] ) ) {
				$result['price_change_24h'] = round( (float) $coin_market_data['price_change_percentage_24h'], 2 );
			}
			if ( isset( $coin_market_data['price_change_percentage_7d_in_currency'] ) ) {
				$result['price_change_7d'] = round( (float) $coin_market_data['price_change_percentage_7d_in_currency'], 2 );
			}

			if ( null !== $result['price_change_7d'] ) {
				$momentum_component = max( -100, min( 100, $result['price_change_7d'] * 5 ) );
				$score             += $momentum_component * 0.2;
				$weight_total      += 0.2;
				$result['available'] = true;
			}
		}

		if ( $weight_total > 0 ) {
			$score = $score / $weight_total;
		}

		$result['score'] = round( max( -100, min( 100, $score ) ), 2 );

		return $result;
	}
}
