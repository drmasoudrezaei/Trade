<?php
/**
 * Admin view: signal weighting, risk model, and paywall settings.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = CSP_Settings::get_all();
?>
<div class="wrap csp-admin-wrap">
	<h1><?php esc_html_e( 'تنظیمات سیگنال کریپتو', 'crypto-signals-pro' ); ?></h1>
	<?php $this->render_notice(); ?>

	<form method="post">
		<?php wp_nonce_field( 'csp_admin_action', 'csp_nonce' ); ?>
		<input type="hidden" name="csp_action" value="save_settings" />

		<h2><?php esc_html_e( 'وزن‌دهی موتور سیگنال', 'crypto-signals-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'مجموع سه وزن زیر همیشه به‌صورت خودکار به ۱۰۰ نرمال‌سازی می‌شود.', 'crypto-signals-pro' ); ?></p>
		<table class="form-table">
			<tr>
				<th><label for="weight_technical"><?php esc_html_e( 'وزن تحلیل تکنیکال', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" step="1" min="0" max="100" id="weight_technical" name="weight_technical" value="<?php echo esc_attr( $settings['weight_technical'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="weight_whale"><?php esc_html_e( 'وزن پوزیشن نهنگ‌ها/معامله‌گران بزرگ', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" step="1" min="0" max="100" id="weight_whale" name="weight_whale" value="<?php echo esc_attr( $settings['weight_whale'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="weight_fundamental"><?php esc_html_e( 'وزن تحلیل فاندامنتال', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" step="1" min="0" max="100" id="weight_fundamental" name="weight_fundamental" value="<?php echo esc_attr( $settings['weight_fundamental'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'مدل ریسک و تایم‌فریم', 'crypto-signals-pro' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="signal_timeframe"><?php esc_html_e( 'تایم‌فریم تحلیل', 'crypto-signals-pro' ); ?></label></th>
				<td>
					<select id="signal_timeframe" name="signal_timeframe">
						<?php foreach ( CSP_Settings::get_timeframe_choices() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['signal_timeframe'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="atr_stop_multiplier"><?php esc_html_e( 'ضریب حد ضرر (ATR×)', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" step="0.1" min="0.1" id="atr_stop_multiplier" name="atr_stop_multiplier" value="<?php echo esc_attr( $settings['atr_stop_multiplier'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="atr_target_multiplier"><?php esc_html_e( 'ضریب حد سود (ATR×)', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" step="0.1" min="0.1" id="atr_target_multiplier" name="atr_target_multiplier" value="<?php echo esc_attr( $settings['atr_target_multiplier'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="fear_greed_mode"><?php esc_html_e( 'تفسیر شاخص ترس و طمع', 'crypto-signals-pro' ); ?></label></th>
				<td>
					<select id="fear_greed_mode" name="fear_greed_mode">
						<option value="contrarian" <?php selected( $settings['fear_greed_mode'], 'contrarian' ); ?>><?php esc_html_e( 'خلاف جهت جمع (ترس شدید = فرصت خرید)', 'crypto-signals-pro' ); ?></option>
						<option value="trend" <?php selected( $settings['fear_greed_mode'], 'trend' ); ?>><?php esc_html_e( 'هم‌جهت با روند غالب', 'crypto-signals-pro' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="cache_minutes"><?php esc_html_e( 'مدت کش داده‌ها (دقیقه)', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" min="1" id="cache_minutes" name="cache_minutes" value="<?php echo esc_attr( $settings['cache_minutes'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="history_expiry_days"><?php esc_html_e( 'انقضای سیگنال باز در تاریخچه (روز)', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="number" min="1" id="history_expiry_days" name="history_expiry_days" value="<?php echo esc_attr( $settings['history_expiry_days'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'کلیدهای API پیشرفته (اختیاری)', 'crypto-signals-pro' ); ?></h2>
		<p class="description"><?php esc_html_e( 'افزونه بدون این کلیدها با API های رایگان بایننس و CoinGecko کاملاً کار می‌کند. این فیلدها برای اتصال آتی به سرویس‌های حرفه‌ای آن-چین در نظر گرفته شده‌اند.', 'crypto-signals-pro' ); ?></p>
		<table class="form-table">
			<tr>
				<th><label for="api_key_whale_alert"><?php esc_html_e( 'کلید Whale Alert', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="text" id="api_key_whale_alert" name="api_key_whale_alert" class="regular-text" value="<?php echo esc_attr( $settings['api_key_whale_alert'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="api_key_glassnode"><?php esc_html_e( 'کلید Glassnode', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="text" id="api_key_glassnode" name="api_key_glassnode" class="regular-text" value="<?php echo esc_attr( $settings['api_key_glassnode'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="api_key_cryptoquant"><?php esc_html_e( 'کلید CryptoQuant', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="text" id="api_key_cryptoquant" name="api_key_cryptoquant" class="regular-text" value="<?php echo esc_attr( $settings['api_key_cryptoquant'] ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'پیام قفل و لینک فروش اشتراک', 'crypto-signals-pro' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="locked_message"><?php esc_html_e( 'پیام برای کاربران بدون اشتراک', 'crypto-signals-pro' ); ?></label></th>
				<td><textarea id="locked_message" name="locked_message" class="large-text" rows="3"><?php echo esc_textarea( $settings['locked_message'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="purchase_url"><?php esc_html_e( 'لینک صفحه خرید/تماس', 'crypto-signals-pro' ); ?></label></th>
				<td><input type="url" id="purchase_url" name="purchase_url" class="regular-text" value="<?php echo esc_attr( $settings['purchase_url'] ); ?>" placeholder="https://" /></td>
			</tr>
			<tr>
				<th><label for="disclaimer"><?php esc_html_e( 'متن سلب مسئولیت (روی برگه سیگنال نمایش داده می‌شود)', 'crypto-signals-pro' ); ?></label></th>
				<td><textarea id="disclaimer" name="disclaimer" class="large-text" rows="3"><?php echo esc_textarea( $settings['disclaimer'] ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( __( 'ذخیره تنظیمات', 'crypto-signals-pro' ) ); ?>
	</form>
</div>
