<?php
/**
 * Admin view: manage the tracked coin watchlist.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$edit_id   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$coin_edit = null;
if ( $edit_id ) {
	$coin_edit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . CSP_DB::coins_table() . ' WHERE id = %d', $edit_id ) );
}

$coins = $wpdb->get_results( 'SELECT * FROM ' . CSP_DB::coins_table() . ' ORDER BY sort_order ASC, symbol ASC' );
?>
<div class="wrap csp-admin-wrap">
	<h1><?php esc_html_e( 'ارزهای تحت پوشش', 'crypto-signals-pro' ); ?></h1>
	<?php $this->render_notice(); ?>

	<div class="csp-two-col">
		<div class="csp-col">
			<h2><?php echo $coin_edit ? esc_html__( 'ویرایش ارز', 'crypto-signals-pro' ) : esc_html__( 'افزودن ارز جدید', 'crypto-signals-pro' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'csp_admin_action', 'csp_nonce' ); ?>
				<input type="hidden" name="csp_action" value="save_coin" />
				<input type="hidden" name="coin_id" value="<?php echo esc_attr( $coin_edit->id ?? 0 ); ?>" />

				<table class="form-table">
					<tr>
						<th><label for="symbol"><?php esc_html_e( 'نماد (مثلاً BTC)', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="text" id="symbol" name="symbol" class="regular-text" required value="<?php echo esc_attr( $coin_edit->symbol ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="name"><?php esc_html_e( 'نام کامل', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr( $coin_edit->name ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="binance_symbol"><?php esc_html_e( 'نماد بایننس (مثلاً BTCUSDT)', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="text" id="binance_symbol" name="binance_symbol" class="regular-text" required value="<?php echo esc_attr( $coin_edit->binance_symbol ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="coingecko_id"><?php esc_html_e( 'شناسه CoinGecko (مثلاً bitcoin)', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="text" id="coingecko_id" name="coingecko_id" class="regular-text" value="<?php echo esc_attr( $coin_edit->coingecko_id ?? '' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="sort_order"><?php esc_html_e( 'ترتیب نمایش', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr( $coin_edit->sort_order ?? 0 ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'فعال', 'crypto-signals-pro' ); ?></th>
						<td><label><input type="checkbox" name="is_active" <?php checked( $coin_edit ? (int) $coin_edit->is_active : 1, 1 ); ?> /> <?php esc_html_e( 'در تحلیل و نمایش لحاظ شود', 'crypto-signals-pro' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( $coin_edit ? __( 'به‌روزرسانی ارز', 'crypto-signals-pro' ) : __( 'افزودن ارز', 'crypto-signals-pro' ) ); ?>
			</form>
		</div>

		<div class="csp-col csp-col-wide">
			<h2><?php esc_html_e( 'فهرست ارزها', 'crypto-signals-pro' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نماد', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'نام', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'نماد بایننس', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'crypto-signals-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $coins as $coin ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $coin->symbol ); ?></strong></td>
							<td><?php echo esc_html( $coin->name ); ?></td>
							<td><?php echo esc_html( $coin->binance_symbol ); ?></td>
							<td><?php echo $coin->is_active ? esc_html__( 'فعال', 'crypto-signals-pro' ) : esc_html__( 'غیرفعال', 'crypto-signals-pro' ); ?></td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'csp-coins', 'edit' => $coin->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'ویرایش', 'crypto-signals-pro' ); ?></a>
								|
								<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'حذف این ارز مطمئن هستید؟', 'crypto-signals-pro' ) ); ?>');">
									<?php wp_nonce_field( 'csp_admin_action', 'csp_nonce' ); ?>
									<input type="hidden" name="csp_action" value="delete_coin" />
									<input type="hidden" name="coin_id" value="<?php echo esc_attr( $coin->id ); ?>" />
									<button type="submit" class="button-link-delete"><?php esc_html_e( 'حذف', 'crypto-signals-pro' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
