<?php
/**
 * Scheduled background jobs: refreshing live signals and evaluating open
 * tracked signals against real price action to build an honest win-rate history.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Cron {

	/**
	 * Registers custom cron schedules and hooks.
	 */
	public function init() {
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) );
		add_action( 'csp_refresh_signals_event', array( $this, 'refresh_all_signals' ) );
		add_action( 'csp_evaluate_history_event', array( $this, 'evaluate_open_history' ) );
	}

	/**
	 * Adds a 15-minute cron schedule used for market data refresh.
	 *
	 * @param array $schedules Existing WP cron schedules.
	 */
	public function register_schedules( $schedules ) {
		$schedules['csp_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'هر ۱۵ دقیقه (سیگنال‌های کریپتو)', 'crypto-signals-pro' ),
		);
		return $schedules;
	}

	/**
	 * Recomputes signals for every active coin and stores them, appending an
	 * open row to the history table so it can later be scored win/loss.
	 */
	public function refresh_all_signals() {
		global $wpdb;

		$coins_table = CSP_DB::coins_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$coins = $wpdb->get_results( "SELECT * FROM {$coins_table} WHERE is_active = 1 ORDER BY sort_order ASC" );

		if ( empty( $coins ) ) {
			return;
		}

		foreach ( $coins as $coin ) {
			$this->refresh_coin_signal( $coin );
		}
	}

	/**
	 * Refreshes the signal for a single coin and persists it.
	 *
	 * @param object $coin Coin row.
	 */
	public function refresh_coin_signal( $coin ) {
		global $wpdb;

		$signal = CSP_Signal_Engine::generate_signal( $coin );

		if ( is_wp_error( $signal ) ) {
			return $signal;
		}

		$signals_table = CSP_DB::signals_table();
		$now           = current_time( 'mysql' );

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$signals_table} WHERE coin_id = %d", $coin->id )
		);

		$row = array(
			'coin_id'            => $coin->id,
			'signal'             => $signal['signal'],
			'confidence'         => $signal['confidence'],
			'technical_score'    => $signal['technical_score'],
			'whale_score'        => $signal['whale_score'],
			'fundamental_score'  => $signal['fundamental_score'],
			'price'              => $signal['price'],
			'entry_price'        => $signal['entry_price'],
			'stop_loss'          => $signal['stop_loss'],
			'take_profit'        => $signal['take_profit'],
			'whale_long_ratio'   => $signal['whale_long_ratio'],
			'retail_long_ratio'  => $signal['retail_long_ratio'],
			'details'            => $signal['details'],
			'updated_at'         => $now,
		);

		$previous_signal = null;
		if ( $existing_id ) {
			$previous_signal = $wpdb->get_var(
				$wpdb->prepare( "SELECT signal FROM {$signals_table} WHERE id = %d", $existing_id )
			);
			$wpdb->update( $signals_table, $row, array( 'id' => $existing_id ) );
		} else {
			$wpdb->insert( $signals_table, $row );
		}

		// Track every *new* actionable signal (buy/sell, not hold) in history for win-rate stats.
		// Avoid duplicate history rows when the signal hasn't changed since last refresh.
		if ( in_array( $signal['signal'], array( 'strong_buy', 'buy', 'sell', 'strong_sell' ), true )
			&& $signal['signal'] !== $previous_signal ) {
			$wpdb->insert(
				CSP_DB::signal_history_table(),
				array(
					'coin_id'     => $coin->id,
					'signal'      => $signal['signal'],
					'confidence'  => $signal['confidence'],
					'entry_price' => $signal['entry_price'],
					'stop_loss'   => $signal['stop_loss'],
					'take_profit' => $signal['take_profit'],
					'opened_at'   => $now,
					'result'      => 'open',
				)
			);
		}

		return $signal;
	}

	/**
	 * Checks every open history row against the coin's current price: if take-profit or
	 * stop-loss has been hit, closes the row as a win/loss; expires stale rows.
	 */
	public function evaluate_open_history() {
		global $wpdb;

		$history_table = CSP_DB::signal_history_table();
		$coins_table   = CSP_DB::coins_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$open_rows = $wpdb->get_results( "SELECT * FROM {$history_table} WHERE result = 'open'" );

		if ( empty( $open_rows ) ) {
			return;
		}

		$expiry_days = (int) CSP_Settings::get( 'history_expiry_days', 10 );

		$coin_cache = array();

		foreach ( $open_rows as $row ) {
			if ( ! isset( $coin_cache[ $row->coin_id ] ) ) {
				$coin_cache[ $row->coin_id ] = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$coins_table} WHERE id = %d", $row->coin_id )
				);
			}
			$coin = $coin_cache[ $row->coin_id ];
			if ( ! $coin ) {
				continue;
			}

			$ticker = CSP_Data_Provider::get_ticker_24h( $coin->binance_symbol );
			if ( is_wp_error( $ticker ) || empty( $ticker['lastPrice'] ) ) {
				continue;
			}

			$current_price = (float) $ticker['lastPrice'];
			$is_long       = in_array( $row->signal, array( 'strong_buy', 'buy' ), true );

			$hit_target = $is_long ? $current_price >= (float) $row->take_profit : $current_price <= (float) $row->take_profit;
			$hit_stop   = $is_long ? $current_price <= (float) $row->stop_loss : $current_price >= (float) $row->stop_loss;

			$result      = null;
			$close_price = null;

			if ( $hit_target ) {
				$result      = 'win';
				$close_price = (float) $row->take_profit;
			} elseif ( $hit_stop ) {
				$result      = 'loss';
				$close_price = (float) $row->stop_loss;
			} elseif ( strtotime( $row->opened_at ) < strtotime( '-' . $expiry_days . ' days' ) ) {
				$result      = 'expired';
				$close_price = $current_price;
			}

			if ( null === $result ) {
				continue;
			}

			$entry_price = (float) $row->entry_price;
			$pnl_percent = 0.0;
			if ( $entry_price > 0 ) {
				$pnl_percent = $is_long
					? ( ( $close_price - $entry_price ) / $entry_price ) * 100
					: ( ( $entry_price - $close_price ) / $entry_price ) * 100;
			}

			$wpdb->update(
				$history_table,
				array(
					'result'      => $result,
					'close_price' => $close_price,
					'pnl_percent' => round( $pnl_percent, 2 ),
					'closed_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $row->id )
			);
		}
	}
}
