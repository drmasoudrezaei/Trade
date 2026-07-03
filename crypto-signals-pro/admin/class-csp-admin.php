<?php
/**
 * WordPress admin panel: menu registration, form processing, and asset loading.
 *
 * @package CryptoSignalsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSP_Admin {

	const CAPABILITY = 'manage_options';

	/**
	 * Registers admin hooks.
	 */
	public function run() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
	}

	/**
	 * Registers the plugin's admin menu and submenus.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'سیگنال کریپتو', 'crypto-signals-pro' ),
			__( 'سیگنال کریپتو', 'crypto-signals-pro' ),
			self::CAPABILITY,
			'csp-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-line',
			58
		);

		add_submenu_page( 'csp-dashboard', __( 'داشبورد', 'crypto-signals-pro' ), __( 'داشبورد', 'crypto-signals-pro' ), self::CAPABILITY, 'csp-dashboard', array( $this, 'render_dashboard_page' ) );
		add_submenu_page( 'csp-dashboard', __( 'ارزهای تحت پوشش', 'crypto-signals-pro' ), __( 'ارزهای تحت پوشش', 'crypto-signals-pro' ), self::CAPABILITY, 'csp-coins', array( $this, 'render_coins_page' ) );
		add_submenu_page( 'csp-dashboard', __( 'سیگنال‌های زنده', 'crypto-signals-pro' ), __( 'سیگنال‌های زنده', 'crypto-signals-pro' ), self::CAPABILITY, 'csp-signals', array( $this, 'render_signals_page' ) );
		add_submenu_page( 'csp-dashboard', __( 'اشتراک کاربران', 'crypto-signals-pro' ), __( 'اشتراک کاربران', 'crypto-signals-pro' ), self::CAPABILITY, 'csp-subscriptions', array( $this, 'render_subscriptions_page' ) );
		add_submenu_page( 'csp-dashboard', __( 'تاریخچه و نرخ موفقیت', 'crypto-signals-pro' ), __( 'تاریخچه و نرخ موفقیت', 'crypto-signals-pro' ), self::CAPABILITY, 'csp-history', array( $this, 'render_history_page' ) );
		add_submenu_page( 'csp-dashboard', __( 'تنظیمات', 'crypto-signals-pro' ), __( 'تنظیمات', 'crypto-signals-pro' ), self::CAPABILITY, 'csp-settings', array( $this, 'render_settings_page' ) );
	}

	/**
	 * Enqueues admin CSS/JS only on this plugin's own screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'csp-' ) === false ) {
			return;
		}

		wp_enqueue_style( 'csp-admin', CSP_PLUGIN_URL . 'admin/css/admin.css', array(), CSP_VERSION );
		wp_enqueue_script( 'csp-admin', CSP_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), CSP_VERSION, true );

		wp_localize_script(
			'csp-admin',
			'cspAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'csp_admin_nonce' ),
			)
		);
	}

	/**
	 * Processes all admin form submissions (coins, subscriptions, settings) before any output.
	 */
	public function handle_form_submissions() {
		if ( ! current_user_can( self::CAPABILITY ) || empty( $_POST['csp_action'] ) ) {
			return;
		}

		check_admin_referer( 'csp_admin_action', 'csp_nonce' );

		$action = sanitize_key( wp_unslash( $_POST['csp_action'] ) );

		switch ( $action ) {
			case 'save_coin':
				$this->process_save_coin();
				break;
			case 'delete_coin':
				$this->process_delete_coin();
				break;
			case 'save_subscription':
				$this->process_save_subscription();
				break;
			case 'delete_subscription':
				$this->process_delete_subscription();
				break;
			case 'save_settings':
				$this->process_save_settings();
				break;
		}
	}

	/**
	 * Inserts or updates a coin row from the coins admin form.
	 */
	private function process_save_coin() {
		global $wpdb;

		$id = isset( $_POST['coin_id'] ) ? absint( $_POST['coin_id'] ) : 0;

		$fields = array(
			'symbol'         => strtoupper( sanitize_text_field( wp_unslash( $_POST['symbol'] ?? '' ) ) ),
			'name'           => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'coingecko_id'   => sanitize_text_field( wp_unslash( $_POST['coingecko_id'] ?? '' ) ),
			'binance_symbol' => strtoupper( sanitize_text_field( wp_unslash( $_POST['binance_symbol'] ?? '' ) ) ),
			'is_active'      => isset( $_POST['is_active'] ) ? 1 : 0,
			'sort_order'     => isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 0,
		);

		if ( '' === $fields['symbol'] || '' === $fields['binance_symbol'] ) {
			$this->redirect_with_notice( 'csp-coins', 'error', __( 'نماد ارز و نماد بایننس الزامی است.', 'crypto-signals-pro' ) );
		}

		$table = CSP_DB::coins_table();
		if ( $id > 0 ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ) );
		} else {
			$wpdb->insert( $table, $fields );
		}

		$this->redirect_with_notice( 'csp-coins', 'success', __( 'ارز با موفقیت ذخیره شد.', 'crypto-signals-pro' ) );
	}

	/**
	 * Deletes a coin and its related signal/history rows.
	 */
	private function process_delete_coin() {
		global $wpdb;

		$id = isset( $_POST['coin_id'] ) ? absint( $_POST['coin_id'] ) : 0;
		if ( $id > 0 ) {
			$wpdb->delete( CSP_DB::coins_table(), array( 'id' => $id ) );
			$wpdb->delete( CSP_DB::signals_table(), array( 'coin_id' => $id ) );
			$wpdb->delete( CSP_DB::signal_history_table(), array( 'coin_id' => $id ) );
		}

		$this->redirect_with_notice( 'csp-coins', 'success', __( 'ارز حذف شد.', 'crypto-signals-pro' ) );
	}

	/**
	 * Saves a user subscription from the subscriptions admin form.
	 */
	private function process_save_subscription() {
		$id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
			$this->redirect_with_notice( 'csp-subscriptions', 'error', __( 'کاربر معتبر انتخاب نشده است.', 'crypto-signals-pro' ) );
		}

		$data = array(
			'user_id'     => $user_id,
			'status'      => sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ),
			'plan_name'   => sanitize_text_field( wp_unslash( $_POST['plan_name'] ?? '' ) ),
			'coin_access' => sanitize_key( wp_unslash( $_POST['coin_access'] ?? 'all' ) ),
			'coin_ids'    => isset( $_POST['coin_ids'] ) ? implode( ',', array_map( 'absint', (array) wp_unslash( $_POST['coin_ids'] ) ) ) : '',
			'starts_at'   => sanitize_text_field( wp_unslash( $_POST['starts_at'] ?? '' ) ),
			'expires_at'  => sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ),
			'notes'       => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);

		CSP_Access_Control::save_subscription( $data, $id );

		$this->redirect_with_notice( 'csp-subscriptions', 'success', __( 'اشتراک با موفقیت ذخیره شد.', 'crypto-signals-pro' ) );
	}

	/**
	 * Deletes a subscription row.
	 */
	private function process_delete_subscription() {
		$id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
		if ( $id > 0 ) {
			CSP_Access_Control::delete_subscription( $id );
		}
		$this->redirect_with_notice( 'csp-subscriptions', 'success', __( 'اشتراک حذف شد.', 'crypto-signals-pro' ) );
	}

	/**
	 * Persists the settings form (weights, risk model, API keys, paywall copy).
	 */
	private function process_save_settings() {
		$w_technical   = isset( $_POST['weight_technical'] ) ? (float) $_POST['weight_technical'] : 40;
		$w_whale       = isset( $_POST['weight_whale'] ) ? (float) $_POST['weight_whale'] : 35;
		$w_fundamental = isset( $_POST['weight_fundamental'] ) ? (float) $_POST['weight_fundamental'] : 25;

		$sum = $w_technical + $w_whale + $w_fundamental;
		if ( $sum <= 0 ) {
			$sum = 100;
			$w_technical = 40;
			$w_whale = 35;
			$w_fundamental = 25;
		}
		// Normalize so the three weights always sum to 100, regardless of admin input.
		$w_technical   = round( ( $w_technical / $sum ) * 100, 1 );
		$w_whale       = round( ( $w_whale / $sum ) * 100, 1 );
		$w_fundamental = round( 100 - $w_technical - $w_whale, 1 );

		$new_values = array(
			'weight_technical'      => $w_technical,
			'weight_whale'          => $w_whale,
			'weight_fundamental'    => $w_fundamental,
			'atr_stop_multiplier'   => isset( $_POST['atr_stop_multiplier'] ) ? (float) $_POST['atr_stop_multiplier'] : 1.5,
			'atr_target_multiplier' => isset( $_POST['atr_target_multiplier'] ) ? (float) $_POST['atr_target_multiplier'] : 3.0,
			'signal_timeframe'      => isset( $_POST['signal_timeframe'] ) ? sanitize_text_field( wp_unslash( $_POST['signal_timeframe'] ) ) : '4h',
			'fear_greed_mode'       => isset( $_POST['fear_greed_mode'] ) ? sanitize_key( wp_unslash( $_POST['fear_greed_mode'] ) ) : 'contrarian',
			'cache_minutes'         => isset( $_POST['cache_minutes'] ) ? absint( $_POST['cache_minutes'] ) : 15,
			'history_expiry_days'   => isset( $_POST['history_expiry_days'] ) ? absint( $_POST['history_expiry_days'] ) : 10,
			'api_key_whale_alert'   => isset( $_POST['api_key_whale_alert'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key_whale_alert'] ) ) : '',
			'api_key_glassnode'     => isset( $_POST['api_key_glassnode'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key_glassnode'] ) ) : '',
			'api_key_cryptoquant'   => isset( $_POST['api_key_cryptoquant'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key_cryptoquant'] ) ) : '',
			'locked_message'        => isset( $_POST['locked_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['locked_message'] ) ) : '',
			'purchase_url'          => isset( $_POST['purchase_url'] ) ? esc_url_raw( wp_unslash( $_POST['purchase_url'] ) ) : '',
			'disclaimer'            => isset( $_POST['disclaimer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['disclaimer'] ) ) : '',
		);

		CSP_Settings::update( $new_values );

		$this->redirect_with_notice( 'csp-settings', 'success', __( 'تنظیمات ذخیره شد.', 'crypto-signals-pro' ) );
	}

	/**
	 * Redirects back to an admin page with a transient notice, then stops execution.
	 *
	 * @param string $page    Admin page slug.
	 * @param string $type    'success' or 'error'.
	 * @param string $message Notice text.
	 */
	private function redirect_with_notice( $page, $type, $message ) {
		set_transient( 'csp_admin_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}

	/**
	 * Prints and clears the pending admin notice, if any.
	 */
	public function render_notice() {
		$key    = 'csp_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );

		$class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
		printf( '<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
	}

	/**
	 * Renders the dashboard overview page.
	 */
	public function render_dashboard_page() {
		require CSP_PLUGIN_DIR . 'admin/views/view-dashboard.php';
	}

	/**
	 * Renders the coins management page.
	 */
	public function render_coins_page() {
		require CSP_PLUGIN_DIR . 'admin/views/view-coins.php';
	}

	/**
	 * Renders the live signals page.
	 */
	public function render_signals_page() {
		require CSP_PLUGIN_DIR . 'admin/views/view-signals.php';
	}

	/**
	 * Renders the subscriptions management page.
	 */
	public function render_subscriptions_page() {
		require CSP_PLUGIN_DIR . 'admin/views/view-subscriptions.php';
	}

	/**
	 * Renders the signal history / win-rate report page.
	 */
	public function render_history_page() {
		require CSP_PLUGIN_DIR . 'admin/views/view-history.php';
	}

	/**
	 * Renders the settings page.
	 */
	public function render_settings_page() {
		require CSP_PLUGIN_DIR . 'admin/views/view-settings.php';
	}
}
