<?php
/**
 * Technical indicator calculations operating on OHLCV candle arrays.
 * Each candle is expected as: array( 'open', 'high', 'low', 'close', 'volume', 'open_time' ).
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Indicators {

	/**
	 * Extracts the closing price series from candles.
	 *
	 * @param array $candles Candle list.
	 */
	public static function closes( array $candles ) {
		return array_map(
			function ( $c ) {
				return $c['close'];
			},
			$candles
		);
	}

	/**
	 * Simple Moving Average of the last $period closes.
	 *
	 * @param array $values Numeric series.
	 * @param int   $period Lookback period.
	 */
	public static function sma( array $values, $period ) {
		$count = count( $values );
		if ( $count < $period || $period <= 0 ) {
			return null;
		}
		$slice = array_slice( $values, -$period );
		return array_sum( $slice ) / $period;
	}

	/**
	 * Exponential Moving Average series; returns the last EMA value.
	 *
	 * @param array $values Numeric series.
	 * @param int   $period Lookback period.
	 */
	public static function ema( array $values, $period ) {
		$count = count( $values );
		if ( $count < $period || $period <= 0 ) {
			return null;
		}

		$k   = 2 / ( $period + 1 );
		$ema = array_sum( array_slice( $values, 0, $period ) ) / $period;

		for ( $i = $period; $i < $count; $i++ ) {
			$ema = ( $values[ $i ] - $ema ) * $k + $ema;
		}

		return $ema;
	}

	/**
	 * Returns the full EMA series (same length semantics as ema(), but keeps every step).
	 *
	 * @param array $values Numeric series.
	 * @param int   $period Lookback period.
	 */
	public static function ema_series( array $values, $period ) {
		$count = count( $values );
		if ( $count < $period || $period <= 0 ) {
			return array();
		}

		$k      = 2 / ( $period + 1 );
		$ema    = array_sum( array_slice( $values, 0, $period ) ) / $period;
		$series = array_fill( 0, $period - 1, null );
		$series[] = $ema;

		for ( $i = $period; $i < $count; $i++ ) {
			$ema      = ( $values[ $i ] - $ema ) * $k + $ema;
			$series[] = $ema;
		}

		return $series;
	}

	/**
	 * Relative Strength Index (Wilder's smoothing).
	 *
	 * @param array $values Numeric closing price series.
	 * @param int   $period Lookback period (typically 14).
	 */
	public static function rsi( array $values, $period = 14 ) {
		$count = count( $values );
		if ( $count <= $period ) {
			return null;
		}

		$gains  = array();
		$losses = array();

		for ( $i = 1; $i < $count; $i++ ) {
			$change   = $values[ $i ] - $values[ $i - 1 ];
			$gains[]  = $change > 0 ? $change : 0;
			$losses[] = $change < 0 ? abs( $change ) : 0;
		}

		$avg_gain = array_sum( array_slice( $gains, 0, $period ) ) / $period;
		$avg_loss = array_sum( array_slice( $losses, 0, $period ) ) / $period;

		for ( $i = $period; $i < count( $gains ); $i++ ) {
			$avg_gain = ( $avg_gain * ( $period - 1 ) + $gains[ $i ] ) / $period;
			$avg_loss = ( $avg_loss * ( $period - 1 ) + $losses[ $i ] ) / $period;
		}

		if ( 0.0 === $avg_loss ) {
			return 100.0;
		}

		$rs = $avg_gain / $avg_loss;
		return 100 - ( 100 / ( 1 + $rs ) );
	}

	/**
	 * MACD (12,26,9): returns array with macd line, signal line and histogram (last values).
	 *
	 * @param array $values      Numeric closing price series.
	 * @param int   $fast_period Fast EMA period.
	 * @param int   $slow_period Slow EMA period.
	 * @param int   $signal_period Signal EMA period.
	 */
	public static function macd( array $values, $fast_period = 12, $slow_period = 26, $signal_period = 9 ) {
		$count = count( $values );
		if ( $count < ( $slow_period + $signal_period ) ) {
			return null;
		}

		$fast_series = self::ema_series( $values, $fast_period );
		$slow_series = self::ema_series( $values, $slow_period );

		$macd_series = array();
		for ( $i = 0; $i < $count; $i++ ) {
			if ( isset( $fast_series[ $i ], $slow_series[ $i ] ) && null !== $fast_series[ $i ] && null !== $slow_series[ $i ] ) {
				$macd_series[] = $fast_series[ $i ] - $slow_series[ $i ];
			}
		}

		if ( count( $macd_series ) < $signal_period ) {
			return null;
		}

		$signal_series = self::ema_series( $macd_series, $signal_period );

		$macd_line   = end( $macd_series );
		$signal_line = end( $signal_series );

		if ( null === $signal_line ) {
			return null;
		}

		return array(
			'macd'      => $macd_line,
			'signal'    => $signal_line,
			'histogram' => $macd_line - $signal_line,
		);
	}

	/**
	 * Bollinger Bands (SMA middle band + N standard deviations).
	 *
	 * @param array $values      Numeric closing price series.
	 * @param int   $period      Lookback period, typically 20.
	 * @param float $stddev_mult Standard deviation multiplier, typically 2.
	 */
	public static function bollinger_bands( array $values, $period = 20, $stddev_mult = 2.0 ) {
		$count = count( $values );
		if ( $count < $period ) {
			return null;
		}

		$slice  = array_slice( $values, -$period );
		$middle = array_sum( $slice ) / $period;

		$variance = 0.0;
		foreach ( $slice as $v ) {
			$variance += pow( $v - $middle, 2 );
		}
		$stddev = sqrt( $variance / $period );

		return array(
			'middle' => $middle,
			'upper'  => $middle + ( $stddev_mult * $stddev ),
			'lower'  => $middle - ( $stddev_mult * $stddev ),
		);
	}

	/**
	 * Average True Range — used to size stop-loss/take-profit distances relative to volatility.
	 *
	 * @param array $candles Candle list.
	 * @param int   $period  Lookback period, typically 14.
	 */
	public static function atr( array $candles, $period = 14 ) {
		$count = count( $candles );
		if ( $count <= $period ) {
			return null;
		}

		$trs = array();
		for ( $i = 1; $i < $count; $i++ ) {
			$high       = $candles[ $i ]['high'];
			$low        = $candles[ $i ]['low'];
			$prev_close = $candles[ $i - 1 ]['close'];

			$trs[] = max(
				$high - $low,
				abs( $high - $prev_close ),
				abs( $low - $prev_close )
			);
		}

		$atr = array_sum( array_slice( $trs, 0, $period ) ) / $period;
		for ( $i = $period; $i < count( $trs ); $i++ ) {
			$atr = ( ( $atr * ( $period - 1 ) ) + $trs[ $i ] ) / $period;
		}

		return $atr;
	}

	/**
	 * Simple pivot-point support/resistance based on the most recently closed candle.
	 *
	 * @param array $candles Candle list.
	 */
	public static function pivot_points( array $candles ) {
		if ( empty( $candles ) ) {
			return null;
		}

		$last = end( $candles );

		$pivot = ( $last['high'] + $last['low'] + $last['close'] ) / 3;

		return array(
			'pivot'        => $pivot,
			'resistance_1' => ( 2 * $pivot ) - $last['low'],
			'support_1'    => ( 2 * $pivot ) - $last['high'],
			'resistance_2' => $pivot + ( $last['high'] - $last['low'] ),
			'support_2'    => $pivot - ( $last['high'] - $last['low'] ),
		);
	}

	/**
	 * Compares the most recent volume against its moving average to detect confirming volume.
	 *
	 * @param array $candles Candle list.
	 * @param int   $period  Lookback period for the volume average.
	 */
	public static function volume_trend( array $candles, $period = 20 ) {
		$count = count( $candles );
		if ( $count < $period + 1 ) {
			return null;
		}

		$volumes     = array_map(
			function ( $c ) {
				return $c['volume'];
			},
			$candles
		);
		$recent      = end( $volumes );
		$avg_without = array_slice( $volumes, -( $period + 1 ), $period );
		$avg         = array_sum( $avg_without ) / $period;

		if ( 0.0 === $avg ) {
			return null;
		}

		return array(
			'current_volume' => $recent,
			'average_volume' => $avg,
			'ratio'          => $recent / $avg,
		);
	}
}
