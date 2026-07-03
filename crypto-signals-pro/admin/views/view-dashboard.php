<?php
/**
 * Admin view: dashboard overview.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$coins_table    = CSP_DB::coins_table();
$signals_table  = CSP_DB::signals_table();
$history_table  = CSP_DB::signal_history_table();
$subs_table     = CSP_DB::subscriptions_table();

$total_coins       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$coins_table} WHERE is_active = 1" );
$total_signals     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$signals_table}" );
$wins              = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$history_table} WHERE result = 'win'" );
$closed            = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$history_table} WHERE result IN ('win','loss')" );
$win_rate          = $closed > 0 ? round( ( $wins / $closed ) * 100, 1 ) : 0;
$now               = current_time( 'mysql' );
$active_subs       = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$subs_table} WHERE status = 'active' AND starts_at <= %s AND (expires_at IS NULL OR expires_at >= %s)",
		$now,
		$now
	)
);

$latest_signals = $wpdb->get_results(
	"SELECT c.symbol, c.name, s.signal, s.confidence, s.updated_at
	 FROM {$signals_table} s
	 INNER JOIN {$coins_table} c ON c.id = s.coin_id
	 ORDER BY s.updated_at DESC
	 LIMIT 10"
);
?>
<div class="wrap csp-admin-wrap">
	<h1><?php esc_html_e( 'داشبورد سیگنال کریپتو', 'crypto-signals-pro' ); ?></h1>
	<?php $this->render_notice(); ?>

	<div class="csp-stat-grid">
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'ارزهای تحت پوشش', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $total_coins ); ?></span>
		</div>
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'اشتراک‌های فعال', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $active_subs ); ?></span>
		</div>
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'سیگنال‌های ثبت‌شده', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $total_signals ); ?></span>
		</div>
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'نرخ موفقیت تاریخی', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $win_rate ); ?>%</span>
			<span class="csp-stat-sub">
				<?php
				printf(
					/* translators: %d: number of closed signals */
					esc_html__( 'بر اساس %d سیگنال بسته‌شده', 'crypto-signals-pro' ),
					absint( $closed )
				);
				?>
			</span>
		</div>
	</div>

	<p class="description">
		<?php esc_html_e( 'توجه: نرخ موفقیت بالا صرفاً بر اساس عملکرد واقعی و تاریخی سیگنال‌های ثبت‌شده در همین سایت محاسبه می‌شود و تضمینی برای آینده نیست. از این عدد به‌جای شعارهای غیرواقعی مثل «۸۵٪ تضمینی» در تبلیغات استفاده کنید.', 'crypto-signals-pro' ); ?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="csp-refresh-all"><?php esc_html_e( 'به‌روزرسانی فوری همه سیگنال‌ها', 'crypto-signals-pro' ); ?></button>
		<span class="csp-refresh-status"></span>
	</p>

	<h2><?php esc_html_e( 'آخرین سیگنال‌های به‌روزرسانی‌شده', 'crypto-signals-pro' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ارز', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'سیگنال', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'اطمینان', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'آخرین به‌روزرسانی', 'crypto-signals-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $latest_signals ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'هنوز سیگنالی ثبت نشده است. از دکمه بالا برای اولین به‌روزرسانی استفاده کنید.', 'crypto-signals-pro' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $latest_signals as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row->symbol ); ?></strong> — <?php echo esc_html( $row->name ); ?></td>
						<td><?php echo esc_html( CSP_Signal_Engine::label_fa( $row->signal ) ); ?></td>
						<td><?php echo esc_html( $row->confidence ); ?>%</td>
						<td><?php echo esc_html( human_time_diff( strtotime( $row->updated_at ), current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested ?> <?php esc_html_e( 'پیش', 'crypto-signals-pro' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<p class="csp-shortcode-hint">
		<?php esc_html_e( 'برای نمایش این گزارش‌ها به مشتریان، کد کوتاه زیر را در هر صفحه یا برگه‌ای از سایت قرار دهید:', 'crypto-signals-pro' ); ?>
		<code>[crypto_signals]</code>
	</p>
</div>
