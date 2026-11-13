<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton. Bootstraps all other components.
 */
class BBTC_Plugin {

	private static ?self $instance = null;

	private bool $dependencies_met = true;

	private function __construct() {}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	public function init(): void {
		$this->check_dependencies();

		if ( ! $this->dependencies_met ) {
			add_action( 'admin_notices', [ $this, 'show_dependency_notice' ], 10 );
			return;
		}

		require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin.php';

		if ( is_admin() ) {
			new BBTC_Admin();
		}

		if ( ! is_admin() && bp_is_active( 'members' ) ) {
			require_once BBTC_PLUGIN_DIR . 'includes/profile/class-bbtc-profile-visibility.php';
			require_once BBTC_PLUGIN_DIR . 'includes/profile/class-bbtc-profile-nav.php';
			BBTC_Profile_Nav::init();
		}

		if ( ! is_admin() && bp_is_active( 'groups' ) ) {
			require_once BBTC_PLUGIN_DIR . 'includes/group/class-bbtc-group-visibility.php';
			require_once BBTC_PLUGIN_DIR . 'includes/group/class-bbtc-group-nav.php';
			BBTC_Group_Nav::init();
		}
	}

	public function check_dependencies(): void {
		if ( ! defined( 'BP_PLATFORM_VERSION' ) || ! version_compare( BP_PLATFORM_VERSION, '2.8', '>=' ) ) {
			$this->dependencies_met = false;
		}
	}

	public function show_dependency_notice(): void {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'BuddyBoss Tab Creator requires BuddyBoss Platform version 2.8 or higher to be installed and active.', 'buddyboss-tab-creator' ); ?></p>
		</div>
		<?php
	}
}
