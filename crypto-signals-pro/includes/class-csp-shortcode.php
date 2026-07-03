<?php
/**
 * Front-end [crypto_signals] shortcode: the subscriber-only market dashboard.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Shortcode {

	/**
	 * Registers the shortcode.
	 */
	public function init() {
		add_shortcode( 'crypto_signals', array( $this, 'render' ) );
	}

	/**
	 * Renders the shortcode output.
	 *
	 * @param array $atts Shortcode attributes (currently unused, reserved for future filtering).
	 */
	public function render( $atts ) {
		if ( ! is_user_logged_in() ) {
			return $this->render_locked( __( 'برای مشاهده این گزارش ابتدا وارد حساب کاربری خود شوید.', 'crypto-signals-pro' ) );
		}

		$user_id = get_current_user_id();

		if ( ! CSP_Access_Control::user_has_access( $user_id ) ) {
			return $this->render_locked( CSP_Settings::get( 'locked_message' ) );
		}

		return $this->render_dashboard( $user_id );
	}

	/**
	 * Renders the paywall/teaser screen shown to users without an active subscription.
	 *
	 * @param string $message Message to display.
	 */
	private function render_locked( $message ) {
		$purchase_url = CSP_Settings::get( 'purchase_url' );

		ob_start();
		?>
		<div class="csp-dashboard csp-locked">
			<div class="csp-locked-box">
				<span class="csp-lock-icon" aria-hidden="true">&#128274;</span>
				<p><?php echo esc_html( $message ); ?></p>
				<?php if ( ! empty( $purchase_url ) ) : ?>
					<a class="csp-cta-button" href="<?php echo esc_url( $purchase_url ); ?>">
						<?php esc_html_e( 'مشاهده پلان‌های اشتراک', 'crypto-signals-pro' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the full signals dashboard for a user with active access.
	 *
	 * @param int $user_id Current user ID.
	 */
	private function render_dashboard( $user_id ) {
		global $wpdb;

		$coins_table   = CSP_DB::coins_table();
		$signals_table = CSP_DB::signals_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			"SELECT c.*, s.signal, s.confidence, s.technical_score, s.whale_score, s.fundamental_score,
					s.price, s.entry_price, s.stop_loss, s.take_profit, s.whale_long_ratio,
					s.retail_long_ratio, s.details, s.updated_at
			 FROM {$coins_table} c
			 LEFT JOIN {$signals_table} s ON s.coin_id = c.id
			 WHERE c.is_active = 1
			 ORDER BY c.sort_order ASC"
		);

		$subscription = CSP_Access_Control::get_active_subscription( $user_id );
		$is_custom    = $subscription && 'custom' === $subscription->coin_access;
		$allowed_ids  = array();
		if ( $is_custom ) {
			$allowed_ids = array_map( 'absint', array_filter( explode( ',', (string) $subscription->coin_ids ) ) );
		}

		$win_stats = $this->get_win_rate_stats();

		ob_start();
		?>
		<div class="csp-dashboard">
			<div class="csp-header">
				<h2><?php esc_html_e( 'رادار سیگنال ارزهای دیجیتال', 'crypto-signals-pro' ); ?></h2>
				<div class="csp-winrate-badge" title="<?php esc_attr_e( 'نرخ موفقیت واقعی بر اساس تاریخچه سیگنال‌های همین سایت', 'crypto-signals-pro' ); ?>">
					<?php
					printf(
						/* translators: 1: win rate percent, 2: number of closed signals */
						esc_html__( 'نرخ موفقیت تاریخی: %1$s%% (بر اساس %2$d سیگنال بسته‌شده)', 'crypto-signals-pro' ),
						esc_html( $win_stats['win_rate'] ),
						absint( $win_stats['closed_count'] )
					);
					?>
				</div>
			</div>

			<p class="csp-disclaimer"><?php echo esc_html( CSP_Settings::get( 'disclaimer' ) ); ?></p>

			<div class="csp-toolbar">
				<input type="text" class="csp-filter-input" placeholder="<?php esc_attr_e( 'جستجوی ارز...', 'crypto-signals-pro' ); ?>" />
				<select class="csp-filter-signal">
					<option value=""><?php esc_html_e( 'همه سیگنال‌ها', 'crypto-signals-pro' ); ?></option>
					<option value="strong_buy"><?php esc_html_e( 'خرید قوی', 'crypto-signals-pro' ); ?></option>
					<option value="buy"><?php esc_html_e( 'خرید', 'crypto-signals-pro' ); ?></option>
					<option value="hold"><?php esc_html_e( 'خنثی', 'crypto-signals-pro' ); ?></option>
					<option value="sell"><?php esc_html_e( 'فروش', 'crypto-signals-pro' ); ?></option>
					<option value="strong_sell"><?php esc_html_e( 'فروش قوی', 'crypto-signals-pro' ); ?></option>
				</select>
			</div>

			<div class="csp-cards">
				<?php foreach ( $rows as $row ) : ?>
					<?php
					if ( $is_custom && ! in_array( (int) $row->id, $allowed_ids, true ) ) {
						continue;
					}
					echo $this->render_coin_card( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders a single coin's signal card.
	 *
	 * @param object $row Joined coin+signal row.
	 */
	private function render_coin_card( $row ) {
		$signal      = $row->signal ? $row->signal : 'hold';
		$label       = CSP_Signal_Engine::label_fa( $signal );
		$details     = $row->details ? json_decode( $row->details, true ) : array();
		$divergence  = isset( $details['whale']['divergence'] ) ? $details['whale']['divergence'] : 'unknown';

		$divergence_labels = array(
			'smart_money_bullish' => __( 'نهنگ‌ها خریدار، خرده‌فروش‌ها فروشنده (واگرایی صعودی)', 'crypto-signals-pro' ),
			'smart_money_bearish' => __( 'نهنگ‌ها فروشنده، خرده‌فروش‌ها خریدار (واگرایی نزولی)', 'crypto-signals-pro' ),
			'crowded'             => __( 'معامله شلوغ؛ نهنگ‌ها و خرده‌فروش‌ها هم‌جهت (احتیاط)', 'crypto-signals-pro' ),
			'aligned'             => __( 'همسو بدون واگرایی خاص', 'crypto-signals-pro' ),
			'unknown'             => __( 'داده کافی نیست', 'crypto-signals-pro' ),
		);

		ob_start();
		?>
		<div class="csp-card csp-signal-<?php echo esc_attr( $signal ); ?>" data-symbol="<?php echo esc_attr( strtolower( $row->symbol ) ); ?>" data-signal="<?php echo esc_attr( $signal ); ?>">
			<div class="csp-card-head">
				<span class="csp-symbol"><?php echo esc_html( $row->symbol ); ?></span>
				<span class="csp-name"><?php echo esc_html( $row->name ); ?></span>
				<span class="csp-badge csp-badge-<?php echo esc_attr( $signal ); ?>"><?php echo esc_html( $label ); ?></span>
			</div>

			<div class="csp-card-body">
				<div class="csp-row"><span><?php esc_html_e( 'قیمت فعلی', 'crypto-signals-pro' ); ?></span><strong><?php echo esc_html( $row->price ? number_format_i18n( (float) $row->price, 4 ) : '-' ); ?></strong></div>
				<div class="csp-row"><span><?php esc_html_e( 'سطح اطمینان', 'crypto-signals-pro' ); ?></span><strong><?php echo esc_html( $row->confidence ? $row->confidence . '%' : '-' ); ?></strong></div>

				<div class="csp-levels">
					<div><span><?php esc_html_e( 'نقطه ورود', 'crypto-signals-pro' ); ?></span><strong><?php echo esc_html( $row->entry_price ? number_format_i18n( (float) $row->entry_price, 4 ) : '-' ); ?></strong></div>
					<div><span><?php esc_html_e( 'حد ضرر', 'crypto-signals-pro' ); ?></span><strong><?php echo esc_html( $row->stop_loss ? number_format_i18n( (float) $row->stop_loss, 4 ) : '-' ); ?></strong></div>
					<div><span><?php esc_html_e( 'حد سود', 'crypto-signals-pro' ); ?></span><strong><?php echo esc_html( $row->take_profit ? number_format_i18n( (float) $row->take_profit, 4 ) : '-' ); ?></strong></div>
				</div>

				<div class="csp-scores">
					<div class="csp-score-bar">
						<span><?php esc_html_e( 'تکنیکال', 'crypto-signals-pro' ); ?></span>
						<?php echo $this->render_score_bar( $row->technical_score ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="csp-score-bar">
						<span><?php esc_html_e( 'نهنگ‌ها/بزرگان', 'crypto-signals-pro' ); ?></span>
						<?php echo $this->render_score_bar( $row->whale_score ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="csp-score-bar">
						<span><?php esc_html_e( 'فاندامنتال', 'crypto-signals-pro' ); ?></span>
						<?php echo $this->render_score_bar( $row->fundamental_score ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>

				<?php if ( null !== $row->whale_long_ratio ) : ?>
				<div class="csp-whale-retail">
					<div>
						<span><?php esc_html_e( 'پوزیشن نهنگ‌ها (لانگ)', 'crypto-signals-pro' ); ?></span>
						<strong><?php echo esc_html( $row->whale_long_ratio ); ?>%</strong>
					</div>
					<div>
						<span><?php esc_html_e( 'پوزیشن خرده‌فروش‌ها (لانگ)', 'crypto-signals-pro' ); ?></span>
						<strong><?php echo esc_html( $row->retail_long_ratio ); ?>%</strong>
					</div>
				</div>
				<p class="csp-divergence"><?php echo esc_html( $divergence_labels[ $divergence ] ?? $divergence_labels['unknown'] ); ?></p>
				<?php endif; ?>

				<p class="csp-updated">
					<?php
					if ( $row->updated_at ) {
						printf(
							/* translators: %s: human time diff */
							esc_html__( 'به‌روزرسانی: %s پیش', 'crypto-signals-pro' ),
							esc_html( human_time_diff( strtotime( $row->updated_at ), current_time( 'timestamp' ) ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
						);
					} else {
						esc_html_e( 'در انتظار اولین تحلیل...', 'crypto-signals-pro' );
					}
					?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders a -100..100 score as a small visual bar.
	 *
	 * @param float|null $score Score value.
	 */
	private function render_score_bar( $score ) {
		$score   = null === $score ? 0 : (float) $score;
		$percent = ( $score + 100 ) / 2; // 0..100
		$class   = $score >= 20 ? 'positive' : ( $score <= -20 ? 'negative' : 'neutral' );

		ob_start();
		?>
		<div class="csp-bar-track">
			<div class="csp-bar-fill csp-bar-<?php echo esc_attr( $class ); ?>" style="width: <?php echo esc_attr( round( $percent, 2 ) ); ?>%;"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Computes the site-wide win rate from closed history rows.
	 */
	private function get_win_rate_stats() {
		global $wpdb;

		$table = CSP_DB::signal_history_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wins  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE result = 'win'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE result IN ('win','loss')" );

		$win_rate = $total > 0 ? round( ( $wins / $total ) * 100, 1 ) : 0;

		return array(
			'win_rate'     => $win_rate,
			'closed_count' => $total,
		);
	}
}
