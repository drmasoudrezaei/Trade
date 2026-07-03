<?php
/**
 * Admin view: live signals table with manual refresh controls.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$coins_table   = CSP_DB::coins_table();
$signals_table = CSP_DB::signals_table();

$rows = $wpdb->get_results(
	"SELECT c.id as coin_id, c.symbol, c.name, s.signal, s.confidence, s.technical_score, s.whale_score,
			s.fundamental_score, s.price, s.entry_price, s.stop_loss, s.take_profit,
			s.whale_long_ratio, s.retail_long_ratio, s.updated_at
	 FROM {$coins_table} c
	 LEFT JOIN {$signals_table} s ON s.coin_id = c.id
	 WHERE c.is_active = 1
	 ORDER BY c.sort_order ASC"
);
?>
<div class="wrap csp-admin-wrap">
	<h1><?php esc_html_e( 'سیگنال‌های زنده', 'crypto-signals-pro' ); ?></h1>
	<?php $this->render_notice(); ?>

	<p>
		<button type="button" class="button button-primary" id="csp-refresh-all"><?php esc_html_e( 'به‌روزرسانی همه', 'crypto-signals-pro' ); ?></button>
		<span class="csp-refresh-status"></span>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ارز', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'سیگنال', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'اطمینان', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'تکنیکال', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'نهنگ‌ها', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'فاندامنتال', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'قیمت', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'ورود / حد ضرر / حد سود', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'به‌روزرسانی', 'crypto-signals-pro' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'crypto-signals-pro' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row->symbol ); ?></strong><br /><small><?php echo esc_html( $row->name ); ?></small></td>
					<td><?php echo $row->signal ? esc_html( CSP_Signal_Engine::label_fa( $row->signal ) ) : '-'; ?></td>
					<td><?php echo $row->confidence ? esc_html( $row->confidence ) . '%' : '-'; ?></td>
					<td><?php echo isset( $row->technical_score ) ? esc_html( $row->technical_score ) : '-'; ?></td>
					<td><?php echo isset( $row->whale_score ) ? esc_html( $row->whale_score ) : '-'; ?></td>
					<td><?php echo isset( $row->fundamental_score ) ? esc_html( $row->fundamental_score ) : '-'; ?></td>
					<td><?php echo $row->price ? esc_html( number_format_i18n( (float) $row->price, 4 ) ) : '-'; ?></td>
					<td>
						<?php
						if ( $row->entry_price ) {
							printf(
								'%s / %s / %s',
								esc_html( number_format_i18n( (float) $row->entry_price, 4 ) ),
								esc_html( number_format_i18n( (float) $row->stop_loss, 4 ) ),
								esc_html( number_format_i18n( (float) $row->take_profit, 4 ) )
							);
						} else {
							echo '-';
						}
						?>
					</td>
					<td><?php echo $row->updated_at ? esc_html( human_time_diff( strtotime( $row->updated_at ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'پیش', 'crypto-signals-pro' ) : '-'; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested ?></td>
					<td>
						<button type="button" class="button csp-refresh-one" data-coin-id="<?php echo esc_attr( $row->coin_id ); ?>"><?php esc_html_e( 'به‌روزرسانی', 'crypto-signals-pro' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
