<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin menu registration, page routing, asset enqueueing, and admin notices.
 */
class BBTC_Admin {

	private string $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'add_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 10 );
		add_action( 'admin_notices',         [ $this, 'show_notices' ], 10 );
		add_action( 'admin_init',            [ $this, 'maybe_redirect_after_activation' ] );
		add_action( 'admin_init',            [ $this, 'handle_early_actions' ], 20 );

		// AJAX: drag-and-drop reorder for custom profile tabs.
		add_action( 'wp_ajax_bbtc_reorder_profile_tabs', function() {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-profile-tabs.php';
			BBTC_Admin_Profile_Tabs::ajax_reorder_tabs();
		} );

		// AJAX: drag-and-drop reorder for existing (built-in) profile tabs.
		add_action( 'wp_ajax_bbtc_reorder_existing_profile_tabs', function() {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-profile-tabs.php';
			BBTC_Admin_Profile_Tabs::ajax_reorder_existing_tabs();
		} );

		// AJAX: drag-and-drop reorder for custom group tabs.
		add_action( 'wp_ajax_bbtc_reorder_group_tabs', function() {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-group-tabs.php';
			BBTC_Admin_Group_Tabs::ajax_reorder_group_tabs();
		} );

		// AJAX: drag-and-drop reorder for existing (built-in) group tabs.
		add_action( 'wp_ajax_bbtc_reorder_existing_group_tabs', function() {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-group-tabs.php';
			BBTC_Admin_Group_Tabs::ajax_reorder_existing_group_tabs();
		} );

		// AJAX: group search for Tab Scope sidebar.
		add_action( 'wp_ajax_bbtc_search_groups', function() {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-group-tabs.php';
			BBTC_Admin_Group_Tabs::ajax_search_groups();
		} );
	}

	/**
	 * Process saves, deletes, and toggles before any output is produced.
	 * Hooked to admin_init so wp_safe_redirect() can still send headers.
	 */
	public function handle_early_actions(): void {
		// Only fire on our plugin's admin page.
		$page = sanitize_key( $_GET['page'] ?? '' );
		if ( 'buddyboss-tab-creator' !== $page ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = sanitize_key( $_GET['tab'] ?? 'profile' );
		if ( ! in_array( $active_tab, [ 'profile', 'group' ], true ) ) {
			$active_tab = 'profile';
		}

		if ( 'profile' === $active_tab ) {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-list-table-profile.php';
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-profile-tabs.php';

			$profile_tabs = new BBTC_Admin_Profile_Tabs();
			$profile_tabs->process_actions();

			if ( isset( $_POST['action'] ) || isset( $_POST['action2'] ) ) {
				$list_table = new BBTC_List_Table_Profile();
				$profile_tabs->process_bulk_action( $list_table );
			}
		} elseif ( 'group' === $active_tab ) {
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-list-table-group.php';
			require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-group-tabs.php';

			$group_tabs = new BBTC_Admin_Group_Tabs();
			$group_tabs->process_actions();

			if ( isset( $_POST['action'] ) || isset( $_POST['action2'] ) ) {
				$list_table = new BBTC_List_Table_Group();
				$group_tabs->process_bulk_action( $list_table );
			}
		}
	}

	public function maybe_redirect_after_activation(): void {
		if ( ! get_transient( 'bbtc_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'bbtc_activation_redirect' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator' ) );
		exit;
	}

	public function add_submenu(): void {
		$this->hook_suffix = add_submenu_page(
			'buddyboss-platform',
			__( 'Tab Creator', 'buddyboss-tab-creator' ),
			__( 'Tab Creator', 'buddyboss-tab-creator' ),
			'manage_options',
			'buddyboss-tab-creator',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'bbtc-admin',
			BBTC_PLUGIN_URL . 'assets/css/admin.css',
			[],
			defined( 'WP_DEBUG' ) && WP_DEBUG ? filemtime( BBTC_PLUGIN_DIR . 'assets/css/admin.css' ) : BBTC_VERSION
		);

		wp_enqueue_script(
			'bbtc-admin-js',
			BBTC_PLUGIN_URL . 'assets/js/admin.js',
			[],
			BBTC_VERSION,
			true
		);

		wp_localize_script(
			'bbtc-admin-js',
			'bbtcData',
			[
				'ajaxurl'           => admin_url( 'admin-ajax.php' ),
				'groupSearchNonce'  => wp_create_nonce( 'bbtc_group_search' ),
				'reorderNonce'         => wp_create_nonce( 'bbtc_reorder_profile_tabs' ),
				'reorderExistingNonce' => wp_create_nonce( 'bbtc_reorder_existing_profile_tabs' ),
				'reorderGroupNonce'         => wp_create_nonce( 'bbtc_reorder_group_tabs' ),
				'reorderExistingGroupNonce' => wp_create_nonce( 'bbtc_reorder_existing_group_tabs' ),
				'confirmDelete'     => __( 'Are you sure you want to delete this tab? This action cannot be undone.', 'buddyboss-tab-creator' ),
				'confirmBulkDelete' => __( 'Delete the selected tabs? This action cannot be undone.', 'buddyboss-tab-creator' ),
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = sanitize_key( $_GET['tab'] ?? 'profile' );
		if ( ! in_array( $active_tab, [ 'profile', 'group' ], true ) ) {
			$active_tab = 'profile';
		}

		$action = sanitize_key( $_GET['action'] ?? 'list' );

		require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-list-table-profile.php';
		require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-profile-tabs.php';
		$profile_tabs = new BBTC_Admin_Profile_Tabs();

		require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-list-table-group.php';
		require_once BBTC_PLUGIN_DIR . 'includes/admin/class-bbtc-admin-group-tabs.php';
		$group_tabs = new BBTC_Admin_Group_Tabs();

		$profile_url = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' );
		$group_url   = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Tab Creator', 'buddyboss-tab-creator' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $profile_url ); ?>"
				   class="nav-tab<?php echo 'profile' === $active_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Profile Tabs', 'buddyboss-tab-creator' ); ?>
				</a>
				<a href="<?php echo esc_url( $group_url ); ?>"
				   class="nav-tab<?php echo 'group' === $active_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Group Tabs', 'buddyboss-tab-creator' ); ?>
				</a>
			</nav>

			<div class="bbtc-tab-content">
				<?php if ( 'profile' === $active_tab ) : ?>
					<?php $profile_tabs->render( $action ); ?>
				<?php else : ?>
					<?php $group_tabs->render( $action ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function show_notices(): void {
		$notice = get_transient( 'bbtc_notice_' . get_current_user_id() );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'bbtc_notice_' . get_current_user_id() );
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);
	}

	public static function set_notice( string $type, string $message ): void {
		set_transient( 'bbtc_notice_' . get_current_user_id(), [ 'type' => $type, 'message' => $message ], 60 );
	}
}
