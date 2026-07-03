<?php
/**
 * Combines technical, whale/retail positioning, and fundamental analysis into
 * a single weighted buy/sell/hold signal with a transparent confidence score.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Signal_Engine {

	/**
	 * Builds the technical analysis score (-100..100) and breakdown for a coin from its candles.
	 *
	 * @param array $candles OHLCV candle list, oldest first.
	 */
	public static function analyze_technical( array $candles ) {
		$result = array(
			'rsi'       => null,
			'macd'      => null,
			'bollinger' => null,
			'ema_fast'  => null,
			'ema_slow'  => null,
			'atr'       => null,
			'pivots'    => null,
			'volume'    => null,
			'score'     => 0.0,
			'available' => false,
		);

		if ( count( $candles ) < 30 ) {
			return $result;
		}

		$closes = CSP_Indicators::closes( $candles );
		$price  = end( $closes );

		$rsi       = CSP_Indicators::rsi( $closes, 14 );
		$macd      = CSP_Indicators::macd( $closes, 12, 26, 9 );
		$bollinger = CSP_Indicators::bollinger_bands( $closes, 20, 2.0 );
		$ema_fast  = CSP_Indicators::ema( $closes, 50 );
		$ema_slow  = CSP_Indicators::ema( $closes, 200 );
		$atr       = CSP_Indicators::atr( $candles, 14 );
		$pivots    = CSP_Indicators::pivot_points( $candles );
		$volume    = CSP_Indicators::volume_trend( $candles, 20 );

		$result['rsi']       = null !== $rsi ? round( $rsi, 2 ) : null;
		$result['macd']      = $macd;
		$result['bollinger'] = $bollinger;
		$result['ema_fast']  = $ema_fast;
		$result['ema_slow']  = $ema_slow;
		$result['atr']       = $atr;
		$result['pivots']    = $pivots;
		$result['volume']    = $volume;

		$score        = 0.0;
		$weight_total = 0.0;

		if ( null !== $rsi ) {
			// RSI below 30 is oversold (bullish), above 70 is overbought (bearish).
			$rsi_component = ( 50 - $rsi ) * 2.5;
			$rsi_component = max( -100, min( 100, $rsi_component ) );
			$score        += $rsi_component * 0.25;
			$weight_total += 0.25;
			$result['available'] = true;
		}

		if ( null !== $macd ) {
			$macd_component = $price > 0 ? ( $macd['histogram'] / $price ) * 100 * 20 : 0;
			$macd_component = max( -100, min( 100, $macd_component ) );
			$score         += $macd_component * 0.25;
			$weight_total  += 0.25;
			$result['available'] = true;
		}

		if ( null !== $bollinger ) {
			$band_width = $bollinger['upper'] - $bollinger['lower'];
			if ( $band_width > 0 ) {
				// Price near lower band => bullish (mean reversion), near upper band => bearish.
				$position       = ( $price - $bollinger['lower'] ) / $band_width; // 0..1
				$bb_component   = ( 0.5 - $position ) * 200;
				$bb_component   = max( -100, min( 100, $bb_component ) );
				$score         += $bb_component * 0.2;
				$weight_total  += 0.2;
				$result['available'] = true;
			}
		}

		if ( null !== $ema_fast && null !== $ema_slow && $ema_slow > 0 ) {
			// Golden-cross style trend bias: price and fast EMA above slow EMA => bullish trend.
			$trend_component = ( ( $ema_fast - $ema_slow ) / $ema_slow ) * 100 * 10;
			$trend_component = max( -100, min( 100, $trend_component ) );
			$score          += $trend_component * 0.2;
			$weight_total   += 0.2;
			$result['available'] = true;
		}

		if ( null !== $volume && $volume['ratio'] > 1.2 ) {
			// High relative volume confirms the direction already implied by the score so far.
			$direction        = $score >= 0 ? 1 : -1;
			$volume_component = $direction * min( 100, ( $volume['ratio'] - 1 ) * 50 );
			$score           += $volume_component * 0.1;
			$weight_total    += 0.1;
		}

		if ( $weight_total > 0 ) {
			$score = $score / $weight_total;
		}

		$result['score'] = round( max( -100, min( 100, $score ) ), 2 );

		return $result;
	}

	/**
	 * Runs the full multi-factor analysis for a single coin row and returns a signal payload
	 * ready to be persisted via CSP_DB::signals_table().
	 *
	 * @param object $coin Coin row from the coins table.
	 */
	public static function generate_signal( $coin ) {
		$settings = CSP_Settings::get_all();
		$timeframe = $settings['signal_timeframe'];

		$candles = CSP_Data_Provider::get_ohlcv( $coin->binance_symbol, $timeframe, 200 );
		if ( is_wp_error( $candles ) || count( $candles ) < 30 ) {
			return new WP_Error( 'csp_insufficient_data', 'Not enough candle data for ' . $coin->symbol );
		}

		$technical    = self::analyze_technical( $candles );
		$whale        = CSP_Whale_Tracker::analyze( $coin->binance_symbol, $coin->binance_symbol, $timeframe );
		$market_data  = CSP_Data_Provider::get_coin_market_data( $coin->coingecko_id );
		$fundamental  = CSP_Fundamental::analyze( is_wp_error( $market_data ) ? null : $market_data );

		$price = end( $candles )['close'];

		$w_technical   = (float) $settings['weight_technical'] / 100;
		$w_whale       = (float) $settings['weight_whale'] / 100;
		$w_fundamental = (float) $settings['weight_fundamental'] / 100;

		// Re-normalize weights across only the pillars that actually produced data,
		// so a temporarily unavailable data source doesn't silently bias the result to zero.
		$active_weight = 0.0;
		$total_score   = 0.0;

		if ( $technical['available'] ) {
			$total_score  += $technical['score'] * $w_technical;
			$active_weight += $w_technical;
		}
		if ( $whale['available'] ) {
			$total_score  += $whale['score'] * $w_whale;
			$active_weight += $w_whale;
		}
		if ( $fundamental['available'] ) {
			$total_score  += $fundamental['score'] * $w_fundamental;
			$active_weight += $w_fundamental;
		}

		if ( $active_weight <= 0 ) {
			return new WP_Error( 'csp_no_signal_data', 'No analysis data available for ' . $coin->symbol );
		}

		$final_score = $total_score / $active_weight;

		$signal_label = self::score_to_label( $final_score );

		// Confidence is a transparent function of how strong/aligned the score is, capped
		// below 100 so the plugin never implies a guaranteed outcome.
		$confidence = min( 92, 50 + ( abs( $final_score ) * 0.42 ) );

		$atr = $technical['atr'];
		if ( ! $atr || $atr <= 0 ) {
			$atr = $price * 0.02; // Fallback ~2% volatility assumption when ATR is unavailable.
		}

		$stop_multiplier   = (float) $settings['atr_stop_multiplier'];
		$target_multiplier = (float) $settings['atr_target_multiplier'];

		if ( in_array( $signal_label, array( 'strong_buy', 'buy' ), true ) ) {
			$entry       = $price;
			$stop_loss   = $price - ( $atr * $stop_multiplier );
			$take_profit = $price + ( $atr * $target_multiplier );
		} elseif ( in_array( $signal_label, array( 'strong_sell', 'sell' ), true ) ) {
			$entry       = $price;
			$stop_loss   = $price + ( $atr * $stop_multiplier );
			$take_profit = $price - ( $atr * $target_multiplier );
		} else {
			$entry       = $price;
			$stop_loss   = $price - ( $atr * $stop_multiplier );
			$take_profit = $price + ( $atr * $target_multiplier );
		}

		return array(
			'signal'             => $signal_label,
			'confidence'         => round( $confidence, 2 ),
			'technical_score'    => $technical['score'],
			'whale_score'        => $whale['score'],
			'fundamental_score'  => $fundamental['score'],
			'price'              => $price,
			'entry_price'        => $entry,
			'stop_loss'          => $stop_loss,
			'take_profit'        => $take_profit,
			'whale_long_ratio'   => $whale['whale_long_ratio'],
			'retail_long_ratio'  => $whale['retail_long_ratio'],
			'details'            => wp_json_encode(
				array(
					'technical'   => $technical,
					'whale'       => $whale,
					'fundamental' => $fundamental,
					'timeframe'   => $timeframe,
				)
			),
		);
	}

	/**
	 * Maps a -100..100 combined score to a signal label.
	 *
	 * @param float $score Combined weighted score.
	 */
	public static function score_to_label( $score ) {
		if ( $score >= 60 ) {
			return 'strong_buy';
		}
		if ( $score >= 20 ) {
			return 'buy';
		}
		if ( $score <= -60 ) {
			return 'strong_sell';
		}
		if ( $score <= -20 ) {
			return 'sell';
		}
		return 'hold';
	}

	/**
	 * Returns a human-readable Persian label for a signal key.
	 *
	 * @param string $signal Signal key.
	 */
	public static function label_fa( $signal ) {
		$map = array(
			'strong_buy'  => 'خرید قوی',
			'buy'         => 'خرید',
			'hold'        => 'نگهداری/خنثی',
			'sell'        => 'فروش',
			'strong_sell' => 'فروش قوی',
		);

		return isset( $map[ $signal ] ) ? $map[ $signal ] : $signal;
	}
}
