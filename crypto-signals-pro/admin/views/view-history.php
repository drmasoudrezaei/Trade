<?php
/**
 * Admin view: signal history and transparent win-rate reporting.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$coins_table   = CSP_DB::coins_table();
$history_table = CSP_DB::signal_history_table();

$overall_wins   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$history_table} WHERE result = 'win'" );
$overall_losses = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$history_table} WHERE result = 'loss'" );
$overall_closed = $overall_wins + $overall_losses;
$overall_rate   = $overall_closed > 0 ? round( ( $overall_wins / $overall_closed ) * 100, 1 ) : 0;

$per_coin = $wpdb->get_results(
	"SELECT c.symbol, c.name,
			SUM(CASE WHEN h.result = 'win' THEN 1 ELSE 0 END) as wins,
			SUM(CASE WHEN h.result = 'loss' THEN 1 ELSE 0 END) as losses,
			AVG(CASE WHEN h.result IN ('win','loss') THEN h.pnl_percent ELSE NULL END) as avg_pnl
	 FROM {$history_table} h
	 INNER JOIN {$coins_table} c ON c.id = h.coin_id
	 GROUP BY h.coin_id
	 ORDER BY wins DESC"
);

$recent = $wpdb->get_results(
	"SELECT h.*, c.symbol
	 FROM {$history_table} h
	 INNER JOIN {$coins_table} c ON c.id = h.coin_id
	 ORDER BY h.opened_at DESC
	 LIMIT 50"
);
?>
<div class="wrap csp-admin-wrap">
	<h1><?php esc_html_e( 'تاریخچه و نرخ موفقیت', 'crypto-signals-pro' ); ?></h1>
	<?php $this->render_notice(); ?>

	<div class="csp-stat-grid">
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'نرخ موفقیت کلی', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $overall_rate ); ?>%</span>
		</div>
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'سیگنال‌های برنده', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $overall_wins ); ?></span>
		</div>
		<div class="csp-stat-card">
			<span class="csp-stat-label"><?php esc_html_e( 'سیگنال‌های بازنده', 'crypto-signals-pro' ); ?></span>
			<span class="csp-stat-value"><?php echo esc_html( $overall_losses ); ?></span>
		</div>
	</div>

	<h2><?php esc_html_e( 'عملکرد به تفکیک ارز', 'crypto-signals-pro' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ارز', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'برد', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'باخت', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'نرخ موفقیت', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'میانگین سود/زیان', 'crypto-signals-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $per_coin ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'هنوز داده‌ای برای گزارش وجود ندارد.', 'crypto-signals-pro' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $per_coin as $row ) : ?>
					<?php
					$closed = $row->wins + $row->losses;
					$rate   = $closed > 0 ? round( ( $row->wins / $closed ) * 100, 1 ) : 0;
					?>
					<tr>
						<td><strong><?php echo esc_html( $row->symbol ); ?></strong> — <?php echo esc_html( $row->name ); ?></td>
						<td><?php echo esc_html( $row->wins ); ?></td>
						<td><?php echo esc_html( $row->losses ); ?></td>
						<td><?php echo esc_html( $rate ); ?>%</td>
						<td><?php echo null !== $row->avg_pnl ? esc_html( round( $row->avg_pnl, 2 ) ) . '%' : '-'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'آخرین سیگنال‌های ثبت‌شده', 'crypto-signals-pro' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ارز', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'سیگنال', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'ورود', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'نتیجه', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'سود/زیان', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'تاریخ شروع', 'crypto-signals-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $recent as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row->symbol ); ?></strong></td>
					<td><?php echo esc_html( CSP_Signal_Engine::label_fa( $row->signal ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $row->entry_price, 4 ) ); ?></td>
					<td><?php echo esc_html( $row->result ); ?></td>
					<td><?php echo null !== $row->pnl_percent ? esc_html( $row->pnl_percent ) . '%' : '-'; ?></td>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row->opened_at ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
