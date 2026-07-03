<?php
/**
 * Whale / large-trader vs. retail positioning analysis.
 *
 * Uses Binance Futures public "top trader" ratio endpoints (large accounts, i.e. the
 * closest free proxy to whale positioning) compared against the "global account" ratio
 * (broad retail base), plus live order-book imbalance for short-term pressure.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Whale_Tracker {

	/**
	 * Builds a whale-vs-retail positioning snapshot for a coin.
	 *
	 * @param string $spot_symbol    Binance spot symbol, e.g. BTCUSDT.
	 * @param string $futures_symbol Binance futures symbol (usually same as spot for major pairs).
	 * @param string $period         Futures ratio aggregation period.
	 */
	public static function analyze( $spot_symbol, $futures_symbol, $period = '4h' ) {
		$result = array(
			'whale_long_ratio'   => null,
			'whale_short_ratio'  => null,
			'retail_long_ratio'  => null,
			'retail_short_ratio' => null,
			'order_book_bias'    => null, // -1..1, positive = more bids (buy pressure).
			'divergence'         => 'unknown', // 'smart_money_bullish', 'smart_money_bearish', 'aligned', 'crowded'.
			'score'              => 0.0, // -100..100 whale/positioning score.
			'available'          => false,
		);

		$top_ratio = CSP_Data_Provider::get_top_trader_long_short_ratio( $futures_symbol, $period );
		$global_ratio = CSP_Data_Provider::get_global_long_short_ratio( $futures_symbol, $period );
		$order_book = CSP_Data_Provider::get_order_book( $spot_symbol, 100 );

		$has_whale  = ! is_wp_error( $top_ratio ) && ! empty( $top_ratio[0]['longAccount'] );
		$has_retail = ! is_wp_error( $global_ratio ) && ! empty( $global_ratio[0]['longAccount'] );

		if ( $has_whale ) {
			$result['whale_long_ratio']  = round( (float) $top_ratio[0]['longAccount'] * 100, 2 );
			$result['whale_short_ratio']  = round( (float) $top_ratio[0]['shortAccount'] * 100, 2 );
		}

		if ( $has_retail ) {
			$result['retail_long_ratio']  = round( (float) $global_ratio[0]['longAccount'] * 100, 2 );
			$result['retail_short_ratio'] = round( (float) $global_ratio[0]['shortAccount'] * 100, 2 );
		}

		if ( ! is_wp_error( $order_book ) && ! empty( $order_book['bids'] ) && ! empty( $order_book['asks'] ) ) {
			$bid_volume = 0.0;
			foreach ( $order_book['bids'] as $level ) {
				$bid_volume += (float) $level[1];
			}
			$ask_volume = 0.0;
			foreach ( $order_book['asks'] as $level ) {
				$ask_volume += (float) $level[1];
			}
			$total = $bid_volume + $ask_volume;
			if ( $total > 0 ) {
				$result['order_book_bias'] = ( $bid_volume - $ask_volume ) / $total;
			}
		}

		if ( ! $has_whale && ! $has_retail && null === $result['order_book_bias'] ) {
			return $result;
		}

		$result['available'] = true;

		$score = 0.0;
		$weight_total = 0.0;

		if ( $has_whale ) {
			// Whale long ratio above 50% => bullish weight, scaled around the midpoint.
			$whale_component = ( $result['whale_long_ratio'] - 50 ) * 2; // -100..100
			$score          += $whale_component * 0.6;
			$weight_total    += 0.6;
		}

		if ( $has_retail ) {
			// Retail is treated as a mild contrarian signal: when retail is heavily one-sided
			// while whales are not aligned, it nudges the score the opposite way (crowd risk).
			$retail_component = ( $result['retail_long_ratio'] - 50 ) * 2;
			$score           += ( -0.2 * $retail_component );
			$weight_total    += 0.2;
		}

		if ( null !== $result['order_book_bias'] ) {
			$score       += $result['order_book_bias'] * 100 * 0.2;
			$weight_total += 0.2;
		}

		if ( $weight_total > 0 ) {
			$score = $score / $weight_total;
		}

		$result['score'] = round( max( -100, min( 100, $score ) ), 2 );

		// Determine a human-readable divergence label between whales and retail.
		if ( $has_whale && $has_retail ) {
			$whale_bullish  = $result['whale_long_ratio'] > 55;
			$whale_bearish  = $result['whale_long_ratio'] < 45;
			$retail_bullish = $result['retail_long_ratio'] > 55;
			$retail_bearish = $result['retail_long_ratio'] < 45;

			if ( $whale_bullish && $retail_bearish ) {
				$result['divergence'] = 'smart_money_bullish';
			} elseif ( $whale_bearish && $retail_bullish ) {
				$result['divergence'] = 'smart_money_bearish';
			} elseif ( ( $whale_bullish && $retail_bullish ) || ( $whale_bearish && $retail_bearish ) ) {
				$result['divergence'] = 'crowded';
			} else {
				$result['divergence'] = 'aligned';
			}
		}

		return $result;
	}
}
