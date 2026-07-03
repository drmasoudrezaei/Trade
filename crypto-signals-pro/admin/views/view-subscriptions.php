<?php
/**
 * Admin view: manage per-customer subscriptions/access.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$edit_id    = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sub_edit   = null;
$edit_user  = null;
if ( $edit_id ) {
	$sub_edit = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . CSP_DB::subscriptions_table() . ' WHERE id = %d', $edit_id ) );
	if ( $sub_edit ) {
		$edit_user = get_user_by( 'id', $sub_edit->user_id );
	}
}

$coins          = $wpdb->get_results( 'SELECT id, symbol, name FROM ' . CSP_DB::coins_table() . ' ORDER BY sort_order ASC' );
$selected_coins = $sub_edit ? array_map( 'absint', array_filter( explode( ',', (string) $sub_edit->coin_ids ) ) ) : array();

$subscriptions = CSP_Access_Control::get_all_subscriptions();
?>
<div class="wrap csp-admin-wrap">
	<h1><?php esc_html_e( 'اشتراک کاربران', 'crypto-signals-pro' ); ?></h1>
	<?php $this->render_notice(); ?>

	<div class="csp-two-col">
		<div class="csp-col">
			<h2><?php echo $sub_edit ? esc_html__( 'ویرایش اشتراک', 'crypto-signals-pro' ) : esc_html__( 'فعال‌سازی اشتراک جدید', 'crypto-signals-pro' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'csp_admin_action', 'csp_nonce' ); ?>
				<input type="hidden" name="csp_action" value="save_subscription" />
				<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub_edit->id ?? 0 ); ?>" />

				<table class="form-table">
					<tr>
						<th><label for="csp-user-search"><?php esc_html_e( 'کاربر', 'crypto-signals-pro' ); ?></label></th>
						<td>
							<input type="text" id="csp-user-search" class="regular-text" placeholder="<?php esc_attr_e( 'جستجوی نام کاربری یا ایمیل...', 'crypto-signals-pro' ); ?>" value="<?php echo esc_attr( $edit_user ? $edit_user->display_name . ' (' . $edit_user->user_email . ')' : '' ); ?>" />
							<input type="hidden" name="user_id" id="csp-user-id" value="<?php echo esc_attr( $sub_edit->user_id ?? '' ); ?>" />
							<div id="csp-user-results" class="csp-user-results"></div>
						</td>
					</tr>
					<tr>
						<th><label for="plan_name"><?php esc_html_e( 'نام پلان', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="text" id="plan_name" name="plan_name" class="regular-text" value="<?php echo esc_attr( $sub_edit->plan_name ?? '' ); ?>" placeholder="<?php esc_attr_e( 'مثلاً اشتراک یک‌ماهه', 'crypto-signals-pro' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="status"><?php esc_html_e( 'وضعیت', 'crypto-signals-pro' ); ?></label></th>
						<td>
							<select id="status" name="status">
								<?php $status = $sub_edit->status ?? 'active'; ?>
								<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'فعال', 'crypto-signals-pro' ); ?></option>
								<option value="suspended" <?php selected( $status, 'suspended' ); ?>><?php esc_html_e( 'معلق', 'crypto-signals-pro' ); ?></option>
								<option value="expired" <?php selected( $status, 'expired' ); ?>><?php esc_html_e( 'منقضی', 'crypto-signals-pro' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="starts_at"><?php esc_html_e( 'تاریخ شروع', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="datetime-local" id="starts_at" name="starts_at" value="<?php echo esc_attr( $sub_edit ? gmdate( 'Y-m-d\TH:i', strtotime( $sub_edit->starts_at ) ) : gmdate( 'Y-m-d\TH:i' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="expires_at"><?php esc_html_e( 'تاریخ انقضا (خالی = بدون انقضا)', 'crypto-signals-pro' ); ?></label></th>
						<td><input type="datetime-local" id="expires_at" name="expires_at" value="<?php echo esc_attr( ( $sub_edit && $sub_edit->expires_at ) ? gmdate( 'Y-m-d\TH:i', strtotime( $sub_edit->expires_at ) ) : '' ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'دسترسی به ارزها', 'crypto-signals-pro' ); ?></th>
						<td>
							<?php $coin_access = $sub_edit->coin_access ?? 'all'; ?>
							<label><input type="radio" name="coin_access" value="all" <?php checked( $coin_access, 'all' ); ?> /> <?php esc_html_e( 'همه ارزها', 'crypto-signals-pro' ); ?></label><br />
							<label><input type="radio" name="coin_access" value="custom" <?php checked( $coin_access, 'custom' ); ?> /> <?php esc_html_e( 'فقط ارزهای انتخابی', 'crypto-signals-pro' ); ?></label>
							<div class="csp-coin-checklist">
								<?php foreach ( $coins as $coin ) : ?>
									<label>
										<input type="checkbox" name="coin_ids[]" value="<?php echo esc_attr( $coin->id ); ?>" <?php checked( in_array( (int) $coin->id, $selected_coins, true ) ); ?> />
										<?php echo esc_html( $coin->symbol ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="notes"><?php esc_html_e( 'یادداشت داخلی', 'crypto-signals-pro' ); ?></label></th>
						<td><textarea id="notes" name="notes" class="large-text" rows="3"><?php echo esc_textarea( $sub_edit->notes ?? '' ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( $sub_edit ? __( 'به‌روزرسانی اشتراک', 'crypto-signals-pro' ) : __( 'فعال‌سازی اشتراک', 'crypto-signals-pro' ) ); ?>
			</form>
		</div>

		<div class="csp-col csp-col-wide">
			<h2><?php esc_html_e( 'فهرست اشتراک‌ها', 'crypto-signals-pro' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'کاربر', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'پلان', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'دسترسی', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'انقضا', 'crypto-signals-pro' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'crypto-signals-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $subscriptions as $sub ) : ?>
						<?php $user = get_user_by( 'id', $sub->user_id ); ?>
						<tr>
							<td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : esc_html__( 'کاربر حذف شده', 'crypto-signals-pro' ); ?></td>
							<td><?php echo esc_html( $sub->plan_name ); ?></td>
							<td><?php echo esc_html( $sub->status ); ?></td>
							<td><?php echo 'all' === $sub->coin_access ? esc_html__( 'همه', 'crypto-signals-pro' ) : esc_html__( 'محدود', 'crypto-signals-pro' ); ?></td>
							<td><?php echo $sub->expires_at ? esc_html( mysql2date( 'Y-m-d H:i', $sub->expires_at ) ) : esc_html__( 'بدون انقضا', 'crypto-signals-pro' ); ?></td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'csp-subscriptions', 'edit' => $sub->id ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'ویرایش', 'crypto-signals-pro' ); ?></a>
								|
								<form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'حذف این اشتراک مطمئن هستید؟', 'crypto-signals-pro' ) ); ?>');">
									<?php wp_nonce_field( 'csp_admin_action', 'csp_nonce' ); ?>
									<input type="hidden" name="csp_action" value="delete_subscription" />
									<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub->id ); ?>" />
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
