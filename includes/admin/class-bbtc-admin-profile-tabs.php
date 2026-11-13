<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin UI and action handling for profile tabs.
 */
class BBTC_Admin_Profile_Tabs {

	/**
	 * Dispatch to the correct render method based on the current action.
	 */
	public function render( string $action ): void {
		switch ( $action ) {
			case 'list':
			default:
				$this->render_list();
				break;

			case 'add':
				$this->render_add_edit_screen( 0 );
				break;

			case 'edit':
				$id = absint( $_GET['id'] ?? 0 );
				$this->render_add_edit_screen( $id );
				break;

			case 'add-subtab':
				$parent_id = absint( $_GET['parent_id'] ?? 0 );
				$this->render_subtab_screen( $parent_id );
				break;

			case 'edit-subtab':
				$subtab_id = absint( $_GET['id'] ?? 0 );
				$parent_id = absint( $_GET['parent_id'] ?? 0 );
				$this->render_subtab_screen( $parent_id, $subtab_id );
				break;

			case 'edit-existing':
				$slug = sanitize_key( $_GET['slug'] ?? '' );
				$this->render_existing_tab_screen( $slug );
				break;

		}
	}

	/**
	 * AJAX handler: saves a new tab order after drag-and-drop reordering.
	 * Expects POST: ordered_ids[] (tab IDs in new order), _ajax_nonce.
	 */
	public static function ajax_reorder_tabs(): void {
		check_ajax_referer( 'bbtc_reorder_profile_tabs' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$ordered_ids = array_map( 'absint', (array) ( $_POST['ordered_ids'] ?? [] ) );

		if ( empty( $ordered_ids ) ) {
			wp_send_json_error( [ 'message' => 'No IDs provided.' ] );
		}

		foreach ( $ordered_ids as $index => $tab_id ) {
			if ( $tab_id > 0 ) {
				BBTC_DB::update_tab( $tab_id, [ 'position' => ( $index + 1 ) * 10 ] );
			}
		}

		BBTC_DB::clear_cache();
		wp_send_json_success();
	}

	/**
	 * AJAX handler: saves the display order of existing (built-in) profile tabs.
	 * Expects POST: ordered_slugs[] (slugs in new order), _ajax_nonce.
	 */
	public static function ajax_reorder_existing_tabs(): void {
		check_ajax_referer( 'bbtc_reorder_existing_profile_tabs' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$known_slugs   = self::get_known_profile_nav_slugs();
		$ordered_slugs = array_map( 'sanitize_key', (array) ( $_POST['ordered_slugs'] ?? [] ) );

		// Only keep slugs that are actually known — discard any injected values.
		$ordered_slugs = array_values( array_intersect( $ordered_slugs, $known_slugs ) );

		update_option( 'bbtc_existing_profile_tab_order', $ordered_slugs, false );
		wp_send_json_success();
	}

	/**
	 * Handle row-level actions (delete, toggle, save) before the page renders.
	 * Called by BBTC_Admin::render_page() before render().
	 */
	public function process_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle POST save.
		if ( ! empty( $_POST ) && isset( $_POST['_wpnonce'] ) ) {
			$nonce = sanitize_key( $_POST['_wpnonce'] );
			if ( wp_verify_nonce( $nonce, 'bbtc_save_profile_tab' ) ) {
				$this->process_save();
				return;
			}
			if ( wp_verify_nonce( $nonce, 'bbtc_save_existing_profile_tab' ) ) {
				$this->process_save_existing_profile_tab();
				return;
			}
			if ( wp_verify_nonce( $nonce, 'bbtc_save_profile_subtab' ) ) {
				$this->process_save_standalone_subtab();
				return;
			}
		}

		$action = sanitize_key( $_GET['action'] ?? '' );
		$id     = absint( $_GET['id'] ?? 0 );

		if ( ! $id || ! in_array( $action, [ 'delete', 'toggle' ], true ) ) {
			return;
		}

		if ( 'delete' === $action ) {
			$nonce = sanitize_key( $_GET['_wpnonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'bbtc_delete_profile_tab_' . $id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'buddyboss-tab-creator' ) );
			}

			BBTC_DB::delete_tab( $id );
			BBTC_Admin::set_notice( 'success', __( 'Tab deleted.', 'buddyboss-tab-creator' ) );

			// If parent_id is present (deleting a sub-tab from the accordion),
			// redirect back to the parent tab's edit screen.
			$parent_id = absint( $_GET['parent_id'] ?? 0 );
			$redirect  = $parent_id > 0
				? admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $parent_id )
				: admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' );

			wp_safe_redirect( $redirect );
			exit;
		}

		if ( 'toggle' === $action ) {
			$nonce = sanitize_key( $_GET['_wpnonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'bbtc_toggle_profile_tab_' . $id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'buddyboss-tab-creator' ) );
			}

			$tab = BBTC_DB::get_tab( $id );
			if ( $tab ) {
				$new_status = ( 'active' === $tab->status ) ? 'inactive' : 'active';
				BBTC_DB::update_tab( $id, [ 'status' => $new_status ] );

				$notice = ( 'active' === $new_status )
					? __( 'Tab activated.', 'buddyboss-tab-creator' )
					: __( 'Tab deactivated.', 'buddyboss-tab-creator' );
				BBTC_Admin::set_notice( 'success', $notice );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' ) );
			exit;
		}
	}

	/**
	 * Handle bulk actions. Called before render().
	 */
	public function process_bulk_action( BBTC_List_Table_Profile $table ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $table->current_action();

		if ( ! in_array( $action, [ 'activate', 'deactivate', 'delete' ], true ) ) {
			return;
		}

		check_admin_referer( 'bulk-profile-tabs' );

		$ids = array_map( 'absint', (array) ( $_POST['profile-tab'] ?? [] ) );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return;
		}

		$count = 0;

		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'activate':
					BBTC_DB::update_tab( $id, [ 'status' => 'active' ] );
					$count++;
					break;

				case 'deactivate':
					BBTC_DB::update_tab( $id, [ 'status' => 'inactive' ] );
					$count++;
					break;

				case 'delete':
					if ( BBTC_DB::delete_tab( $id ) ) {
						$count++;
					}
					break;
			}
		}

		$notice = match ( $action ) {
			'activate'   => sprintf(
				/* translators: %d: number of tabs */
				_n( '%d tab activated.', '%d tabs activated.', $count, 'buddyboss-tab-creator' ),
				$count
			),
			'deactivate' => sprintf(
				/* translators: %d: number of tabs */
				_n( '%d tab deactivated.', '%d tabs deactivated.', $count, 'buddyboss-tab-creator' ),
				$count
			),
			'delete'     => sprintf(
				/* translators: %d: number of tabs */
				_n( '%d tab deleted.', '%d tabs deleted.', $count, 'buddyboss-tab-creator' ),
				$count
			),
			default      => '',
		};

		if ( $notice ) {
			BBTC_Admin::set_notice( 'success', $notice );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' ) );
		exit;
	}

	/**
	 * Render the tabs list screen.
	 */
	public function render_list(): void {
		$add_url = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=add' );
		$table   = new BBTC_List_Table_Profile();
		$table->prepare_items();
		?>
		<div class="tablenav top">
			<a href="<?php echo esc_url( $add_url ); ?>" class="add-new-btn">
				<?php esc_html_e( '+ Add New Tab', 'buddyboss-tab-creator' ); ?>
			</a>
		</div>
		<form method="post">
			<?php $table->display(); ?>
		</form>

		<?php
		// Existing BuddyBoss Profile Tabs section.
		$known_slugs  = self::get_known_profile_nav_slugs();
		$nav_labels   = self::get_builtin_nav_labels();
		$custom_count = BBTC_DB::get_tabs_count( 'profile', [ 'parent_id' => 0 ] );

		// Apply saved drag-and-drop order, appending any new slugs at the end.
		$saved_order = get_option( 'bbtc_existing_profile_tab_order', [] );
		if ( ! empty( $saved_order ) ) {
			$known_slugs = array_merge(
				array_intersect( $saved_order, $known_slugs ),
				array_diff( $known_slugs, $saved_order )
			);
		}
		?>
		<div class="bbtc-section-heading">
			<h3><?php esc_html_e( 'Existing BuddyBoss Profile Tabs', 'buddyboss-tab-creator' ); ?></h3>
			<p><?php esc_html_e( 'Modify built-in profile tabs registered by BuddyBoss Platform. You can rename, reorder, hide, set as default, or restrict who can access them.', 'buddyboss-tab-creator' ); ?></p>
		</div>
		<table class="bbtc-existing-table">
			<thead>
				<tr>
					<th class="bbtc-col-handle"></th>
					<th><?php esc_html_e( 'Tab', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Override Label', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Hidden', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Default', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Visibility', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Availability', 'buddyboss-tab-creator' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'buddyboss-tab-creator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $known_slugs as $slug ) :
					$override      = BBTC_DB::get_override( 'profile', $slug );
					$override_id   = $override ? absint( $override->id ) : 0;
					$edit_url      = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit-existing&slug=' . rawurlencode( $slug ) );

					// Tab display name: use real BuddyBoss label if available, fall back to slug.
					$tab_name = ! empty( $nav_labels[ $slug ] )
						? $nav_labels[ $slug ]
						: ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );

					// Override label.
					$label_override = ( $override && ! empty( $override->label_override ) )
						? esc_html( $override->label_override )
						: '<span class="bbtc-override-none">—</span>';

					// Hidden.
					$is_hidden = ( $override && absint( $override->is_hidden ) );

					// Default.
					$is_default = ( $override && absint( $override->is_default ) );

					// Visibility / availability summary from override rules.
					$vis_summary   = '<span class="bbtc-override-none">—</span>';
					$avail_summary = '<span class="bbtc-override-none">—</span>';
					if ( $override_id ) {
						$rules = BBTC_DB::get_rules_for_override( $override_id );
						$vis_vals  = [];
						$avail_vals = [];
						foreach ( $rules as $rule ) {
							if ( 'visibility' === $rule->rule_type ) {
								$vis_vals[] = esc_html( $rule->rule_value );
							} elseif ( 'availability' === $rule->rule_type ) {
								$avail_vals[] = esc_html( $rule->rule_value );
							}
						}
						if ( ! empty( $vis_vals ) ) {
							$vis_summary = implode( ', ', $vis_vals );
						}
						if ( ! empty( $avail_vals ) ) {
							$avail_summary = implode( ', ', $avail_vals );
						}
					}

					?>
					<tr data-slug="<?php echo esc_attr( $slug ); ?>">
						<td class="bbtc-col-handle"><span class="bbtc-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'buddyboss-tab-creator' ); ?>">⠿</span></td>
						<td><strong><?php echo esc_html( $tab_name ); ?></strong></td>
						<td><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo wp_kses( $label_override, [ 'span' => [ 'class' => [] ] ] ); ?></td>
						<td>
							<?php if ( $is_hidden ) : ?>
								<span class="bbtc-hidden-badge"><?php esc_html_e( 'Hidden', 'buddyboss-tab-creator' ); ?></span>
							<?php else : ?>
								<?php esc_html_e( 'No', 'buddyboss-tab-creator' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_default ) : ?>
								<span class="bbtc-default-badge"><?php esc_html_e( 'Default', 'buddyboss-tab-creator' ); ?></span>
							<?php else : ?>
								<?php esc_html_e( 'No', 'buddyboss-tab-creator' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo wp_kses( $vis_summary, [ 'span' => [ 'class' => [] ] ] ); ?></td>
						<td><?php echo wp_kses( $avail_summary, [ 'span' => [ 'class' => [] ] ] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'buddyboss-tab-creator' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="tablenav bottom" style="justify-content:flex-end;padding:8px 12px;">
			<span class="tablenav-pages">
				<?php
				printf(
					/* translators: 1: custom tab count, 2: built-in tab count */
					esc_html__( '%1$d custom tabs | %2$d built-in tabs', 'buddyboss-tab-creator' ),
					absint( $custom_count ),
					count( $known_slugs )
				);
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render the profile tab add / edit form.
	 */
	public function render_add_edit_screen( int $tab_id = 0 ): void {
		$tab        = null;
		$rules      = [];
		$is_new     = true;
		$avail_checked = [];
		$vis_checked   = [];
		$assoc_users   = [];

		if ( $tab_id > 0 ) {
			$tab = BBTC_DB::get_tab( $tab_id );
			if ( ! $tab ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Tab not found.', 'buddyboss-tab-creator' ) . '</p></div>';
				return;
			}
			$rules  = BBTC_DB::get_rules_for_tab( $tab_id );
			$is_new = false;

			foreach ( $rules as $rule ) {
				if ( 'availability' === $rule->rule_type ) {
					if ( 'scope' === $rule->rule_key ) {
						$avail_checked[] = ( 'none' === $rule->rule_value ) ? 'none_wp' : $rule->rule_value;
					} elseif ( 'role' === $rule->rule_key ) {
						$avail_checked[] = $rule->rule_value;
					} elseif ( 'user_id' === $rule->rule_key ) {
						$assoc_users[] = absint( $rule->rule_value );
					}
				} elseif ( 'visibility' === $rule->rule_type ) {
					if ( 'audience' === $rule->rule_key ) {
						$vis_checked[] = $rule->rule_value;
					} elseif ( 'role' === $rule->rule_key ) {
						$vis_checked[] = $rule->rule_value;
					}
				}
			}
		}

		$list_url  = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' );
		$wp_roles  = wp_roles()->roles;
		$page_title = $is_new
			? esc_html__( 'Add New Profile Tab', 'buddyboss-tab-creator' )
			: sprintf(
				/* translators: %s: tab label */
				esc_html__( 'Edit Profile Tab: %s', 'buddyboss-tab-creator' ),
				'<em>' . esc_html( $tab->label ) . '</em>'
			);

		// Field defaults.
		$f_is_enabled      = $is_new ? 1 : absint( $tab->is_enabled );
		$f_label           = $is_new ? '' : $tab->label;
		$f_slug            = $is_new ? '' : $tab->slug;
		$f_is_default      = $is_new ? 0 : absint( $tab->is_default );
		$f_is_predefined   = $is_new ? 0 : absint( $tab->is_predefined );
		$f_site_admin_only = $is_new ? 0 : absint( $tab->site_admin_only );
		$f_position        = $is_new ? 10 : absint( $tab->position );
		$f_tab_link        = $is_new ? '' : $tab->tab_link;
		$f_default_subnav  = $is_new ? '' : $tab->default_subnav_slug;
		$f_item_css_id     = $is_new ? '' : $tab->item_css_id;
		$f_add_to_admin_bar    = $is_new ? 0 : absint( $tab->add_to_admin_bar );
		$f_administer_item_id  = $is_new ? '' : $tab->administer_item_id;
		$f_content_source  = $is_new ? 'content' : $tab->content_source;
		$f_content         = $is_new ? '' : $tab->content;
		$f_external_url    = $is_new ? '' : $tab->external_url;
		$f_url_new_tab     = $is_new ? 0 : absint( $tab->url_new_tab );
		$f_status          = $is_new ? 'active' : $tab->status;

		// Sub-tabs (edit mode only).
		$subtabs      = [];
		$subtab_rules = [];
		if ( ! $is_new && $tab_id > 0 ) {
			$subtabs = BBTC_DB::get_tabs( 'profile', [ 'parent_id' => $tab_id ] );
			foreach ( $subtabs as $subtab ) {
				$subtab_rules[ $subtab->id ] = BBTC_DB::get_rules_for_tab( $subtab->id );
			}
		}
		?>
		<div class="bbtc-breadcrumb">
			<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( '← Back to Profile Tabs', 'buddyboss-tab-creator' ); ?></a>
		</div>
		<h1 class="wp-heading-inline"><?php echo wp_kses( $page_title, [ 'em' => [] ] ); ?></h1>

		<form method="post" id="bbtc-tab-form" data-is-new="<?php echo esc_attr( $is_new ? '1' : '0' ); ?>">
			<?php wp_nonce_field( 'bbtc_save_profile_tab' ); ?>
			<input type="hidden" name="tab_id" value="<?php echo esc_attr( $tab_id ); ?>">
			<input type="hidden" name="is_new" value="<?php echo esc_attr( $is_new ? '1' : '0' ); ?>">

			<div class="bbtc-form-wrap">
				<div class="bbtc-form-main">

					<!-- Tab Settings postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Tab Settings', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_enabled" value="1" <?php checked( $f_is_enabled, 1 ); ?>>
									<?php esc_html_e( 'Only enabled tabs will be visible', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-label"><?php esc_html_e( 'Tab Label', 'buddyboss-tab-creator' ); ?> <span style="color:#d63638;">*</span></label>
								<input type="text" id="bbtc-label" name="label" value="<?php echo esc_attr( $f_label ); ?>" placeholder="<?php esc_attr_e( 'e.g. Portfolio', 'buddyboss-tab-creator' ); ?>" required>
								<p class="description"><?php esc_html_e( 'Tab label displayed in the navigation.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-slug"><?php esc_html_e( 'Tab Slug', 'buddyboss-tab-creator' ); ?></label>
								<input type="text" id="bbtc-slug" name="slug" value="<?php echo esc_attr( $f_slug ); ?>" placeholder="<?php esc_attr_e( 'auto-generated from label', 'buddyboss-tab-creator' ); ?>" <?php echo ! $is_new ? 'readonly' : ''; ?>>
								<?php if ( ! $is_new ) : ?>
									<p class="description"><?php esc_html_e( 'Slug cannot be changed after first save.', 'buddyboss-tab-creator' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Preferably unique tab slug. Auto-generated from label.', 'buddyboss-tab-creator' ); ?></p>
								<?php endif; ?>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_default" value="1" <?php checked( $f_is_default, 1 ); ?>>
									<?php esc_html_e( 'Set it as default component?', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_predefined" id="bbtc-is-predefined" value="1" <?php checked( $f_is_predefined, 1 ); ?>>
									<?php esc_html_e( 'Are we modifying predefined tab?', 'buddyboss-tab-creator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Are we modifying a tab added by BuddyPress or some other plugin?', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="site_admin_only" id="bbtc-site-admin-only" value="1" <?php checked( $f_site_admin_only, 1 ); ?>>
									<?php esc_html_e( 'Site admins only?', 'buddyboss-tab-creator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Is this tab visible to site admins only?', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label><?php esc_html_e( 'Add this tab for?', 'buddyboss-tab-creator' ); ?> <span style="color:#646970;font-weight:400;"><?php esc_html_e( '(Availability — who has this tab on their profile)', 'buddyboss-tab-creator' ); ?></span></label>
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-avail-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-avail-group">
									<label>
										<input type="checkbox" name="availability[]" value="all_members" <?php checked( in_array( 'all_members', $avail_checked, true ) || $is_new ); ?>>
										<?php esc_html_e( 'All Members', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="availability[]" value="none_wp" <?php checked( in_array( 'none_wp', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'None/WP', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(removes it for everyone. Only applies for existing tabs)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
										<label>
											<input type="checkbox" name="availability[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $avail_checked, true ) ); ?>>
											<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be available to these users on their profile.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label><?php esc_html_e( 'Tab Visibility', 'buddyboss-tab-creator' ); ?> <span style="color:#646970;font-weight:400;"><?php esc_html_e( '(who can see this tab when visiting a profile)', 'buddyboss-tab-creator' ); ?></span></label>
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-vis-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-vis-group">
									<label>
										<input type="checkbox" name="visibility[]" value="do_not_modify" <?php checked( in_array( 'do_not_modify', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Do Not Modify', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies when modifying existing tab or subnav)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) || $is_new ); ?>>
										<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="none_remove" <?php checked( in_array( 'none_remove', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'None/Remove it for everyone', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies for existing tabs)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Logged In Members', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="owner_only" <?php checked( in_array( 'owner_only', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Profile Owner', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="friends" <?php checked( in_array( 'friends', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Friends', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
									</label>
									<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
										<label>
											<input type="checkbox" name="visibility[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $vis_checked, true ) ); ?>>
											<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be visible to these users when they visit the profile.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<input type="hidden" name="position" value="<?php echo esc_attr( $f_position ); ?>">

							<div class="bbtc-form-field">
								<label for="bbtc-tab-link"><?php esc_html_e( 'Tab Link', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="url" id="bbtc-tab-link" name="tab_link" value="<?php echo esc_attr( $f_tab_link ); ?>" placeholder="https://example.com/dynamic-link">
								<p class="description"><?php esc_html_e( 'Only use if you need a dynamic url or link to absolute url.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-default-subnav"><?php esc_html_e( 'Default Subnav Slug', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-default-subnav" name="default_subnav_slug" value="<?php echo esc_attr( $f_default_subnav ); ?>" placeholder="<?php esc_attr_e( 'e.g. my-subnav-slug', 'buddyboss-tab-creator' ); ?>">
								<p class="description"><?php esc_html_e( 'Specify the slug of the subnav to be used. If not specified, the first sub nav will be default.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-item-css-id"><?php esc_html_e( 'Item CSS ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-item-css-id" name="item_css_id" value="<?php echo esc_attr( $f_item_css_id ); ?>" placeholder="<?php esc_attr_e( 'e.g. my-custom-id', 'buddyboss-tab-creator' ); ?>">
								<p class="description"><?php esc_html_e( 'Optional CSS ID for this nav item.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="add_to_admin_bar" value="1" <?php checked( $f_add_to_admin_bar, 1 ); ?>>
									<?php esc_html_e( 'Add to admin bar?', 'buddyboss-tab-creator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Add this tab to the admin account menu for user.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-administer-item-id"><?php esc_html_e( 'Administer Item ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-administer-item-id" name="administer_item_id" value="<?php echo esc_attr( $f_administer_item_id ); ?>" placeholder="<?php esc_attr_e( 'e.g. my-admin-item', 'buddyboss-tab-creator' ); ?>">
								<p class="description"><?php esc_html_e( 'Required if you want to remove item from administer account resource add admin menu.', 'buddyboss-tab-creator' ); ?></p>
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Tab Settings -->

					<!-- Content Settings postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Content Settings', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<div class="bbtc-radio-group">
									<label class="bbtc-inline">
										<input type="radio" name="content_source" value="content" <?php checked( $f_content_source, 'content' ); ?>>
										<?php esc_html_e( 'Custom Content', 'buddyboss-tab-creator' ); ?>
									</label>
									<label class="bbtc-inline">
										<input type="radio" name="content_source" value="url" <?php checked( $f_content_source, 'url' ); ?>>
										<?php esc_html_e( 'External URL', 'buddyboss-tab-creator' ); ?>
									</label>
								</div>
							</div>

							<div class="bbtc-content-editor-wrap">
								<?php
								wp_editor(
									$f_content,
									'bbtc_content',
									[
										'textarea_name' => 'content',
										'media_buttons' => true,
										'teeny'         => false,
										'textarea_rows' => 10,
									]
								);
								?>
							</div>

							<div class="bbtc-url-wrap">
								<div class="bbtc-form-field">
									<label for="bbtc-external-url"><?php esc_html_e( 'External URL', 'buddyboss-tab-creator' ); ?></label>
									<input type="url" id="bbtc-external-url" name="external_url" value="<?php echo esc_attr( $f_external_url ); ?>" placeholder="https://example.com">
								</div>
								<div class="bbtc-form-field">
									<label class="bbtc-inline">
										<input type="checkbox" name="url_new_tab" value="1" <?php checked( $f_url_new_tab, 1 ); ?>>
										<?php esc_html_e( 'Open in new tab', 'buddyboss-tab-creator' ); ?>
									</label>
								</div>
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Content Settings -->

					<!-- Subnav postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3>
								<?php esc_html_e( 'Subnav', 'buddyboss-tab-creator' ); ?>
								<span style="font-weight:400;font-size:13px;color:#8892a0;"><?php esc_html_e( '— Add sub-tabs (child nav items) for this profile tab.', 'buddyboss-tab-creator' ); ?></span>
							</h3>
						</div>
						<div class="inside">
							<?php if ( $is_new ) : ?>
								<p class="description"><?php esc_html_e( 'Save this tab first to add sub-navs.', 'buddyboss-tab-creator' ); ?></p>
							<?php else : ?>
								<p class="description" style="margin-bottom:14px;"><?php esc_html_e( 'Add sub-tabs (child nav items) for this profile tab. Each sub-tab can have its own content, visibility, and availability settings.', 'buddyboss-tab-creator' ); ?></p>

								<div id="bbtc-subtabs-accordion" data-next-idx="<?php echo absint( count( $subtabs ) ); ?>">
									<?php if ( empty( $subtabs ) ) : ?>
										<p id="bbtc-no-subtabs-msg" class="description"><?php esc_html_e( 'No sub-navs added yet.', 'buddyboss-tab-creator' ); ?></p>
									<?php else : ?>
										<?php foreach ( $subtabs as $idx => $subtab ) :
											$st_rules = $subtab_rules[ $subtab->id ] ?? [];
											$st_avail = [];
											$st_vis   = [];
											foreach ( $st_rules as $r ) {
												if ( 'availability' === $r->rule_type ) {
													if ( 'scope' === $r->rule_key ) {
														$st_avail[] = ( 'none' === $r->rule_value ) ? 'none_wp' : $r->rule_value;
													} elseif ( 'role' === $r->rule_key ) {
														$st_avail[] = $r->rule_value;
													}
												} elseif ( 'visibility' === $r->rule_type ) {
													if ( 'audience' === $r->rule_key ) {
														$st_vis[] = $r->rule_value;
													} elseif ( 'role' === $r->rule_key ) {
														$st_vis[] = $r->rule_value;
													}
												}
											}
											$this->render_subtab_accordion_item( $idx, $subtab, $wp_roles, $st_avail, $st_vis, $tab_id );
										endforeach; ?>
									<?php endif; ?>
								</div>

								<!-- Template for new sub-tab items added via JS -->
								<template id="bbtc-subtab-template">
									<?php $this->render_subtab_accordion_item( '__IDX__', null, $wp_roles, [], [], 0 ); ?>
								</template>

								<div style="margin-top:10px;">
									<button type="button" id="bbtc-add-subtab-btn" class="bbtc-add-subtab-btn">
										<?php esc_html_e( '+ Add New Subnav', 'buddyboss-tab-creator' ); ?>
									</button>
								</div>
							<?php endif; ?>
						</div><!-- /.inside -->
					</div><!-- /.postbox Subnav -->

				</div><!-- /.bbtc-form-main -->

				<div class="bbtc-form-sidebar">

					<!-- Publish sidebar box -->
					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Publish', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-body">
							<div class="bbtc-publish-actions">
								<div class="bbtc-publish-row">
									<span>
										<?php esc_html_e( 'Status:', 'buddyboss-tab-creator' ); ?>
										<strong>
											<?php echo ( 'active' === $f_status ) ? esc_html__( 'Active', 'buddyboss-tab-creator' ) : esc_html__( 'Draft', 'buddyboss-tab-creator' ); ?>
										</strong>
									</span>
								</div>
								<div class="bbtc-publish-row">
									<span><?php esc_html_e( 'Visibility:', 'buddyboss-tab-creator' ); ?> <strong><?php esc_html_e( 'Public', 'buddyboss-tab-creator' ); ?></strong></span>
								</div>
								<div class="bbtc-publish-row">
									<?php if ( $is_new ) : ?>
										<span><?php esc_html_e( 'Publish:', 'buddyboss-tab-creator' ); ?> <strong><?php esc_html_e( 'Immediately', 'buddyboss-tab-creator' ); ?></strong></span>
									<?php else : ?>
										<span><?php esc_html_e( 'Published:', 'buddyboss-tab-creator' ); ?> <strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tab->created_at ) ) ); ?></strong></span>
									<?php endif; ?>
								</div>
							</div>
							<?php if ( ! $is_new ) : ?>
								<p class="bbtc-publish-meta">
									<?php
									printf(
										/* translators: %s: date modified */
										esc_html__( 'Last modified: %s', 'buddyboss-tab-creator' ),
										esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tab->updated_at ) ) )
									);
									?>
								</p>
							<?php endif; ?>
						</div>
						<div class="bbtc-sidebar-box-footer">
							<?php if ( ! $is_new ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=delete&id=' . $tab_id ), 'bbtc_delete_profile_tab_' . $tab_id ) ); ?>" class="button-link-delete bbtc-delete-link">
									<?php esc_html_e( 'Move to Trash', 'buddyboss-tab-creator' ); ?>
								</a>
							<?php endif; ?>
							<button type="submit" name="status" value="inactive" class="button-secondary">
								<?php esc_html_e( 'Save Draft', 'buddyboss-tab-creator' ); ?>
							</button>
							<button type="submit" name="status" value="active" class="button-primary" style="margin-left:auto;">
								<?php echo $is_new ? esc_html__( 'Publish', 'buddyboss-tab-creator' ) : esc_html__( 'Update', 'buddyboss-tab-creator' ); ?>
							</button>
						</div>
					</div><!-- /.bbtc-sidebar-box Publish -->

					<!-- Tab Scope sidebar box -->
					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Tab Scope', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-body">
							<p style="font-size:12px;color:#646970;margin-bottom:8px;">
								<?php esc_html_e( 'You can limit the scope of this tab/modification to selected users only. If you select some users, the tab settings will only apply to those users.', 'buddyboss-tab-creator' ); ?>
							</p>
							<div class="bbtc-form-field">
								<label style="font-weight:600;font-size:12px;margin-bottom:5px;display:block;">
									<?php esc_html_e( 'Associated Users', 'buddyboss-tab-creator' ); ?>
								</label>
								<?php if ( ! empty( $assoc_users ) ) : ?>
									<div id="bbtc-assoc-users-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
										<?php foreach ( $assoc_users as $user_id ) :
											$user = get_userdata( $user_id );
											if ( ! $user ) continue;
											?>
											<span class="bbtc-group-tag">
												<?php echo esc_html( $user->display_name ); ?>
												<button type="button" data-user-id="<?php echo esc_attr( $user_id ); ?>" class="bbtc-remove-user" aria-label="<?php esc_attr_e( 'Remove', 'buddyboss-tab-creator' ); ?>">×</button>
												<input type="hidden" name="associated_users[]" value="<?php echo esc_attr( $user_id ); ?>">
											</span>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<div id="bbtc-assoc-users-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;"></div>
								<?php endif; ?>
								<div style="border:1px solid #8c8f94;border-radius:3px;padding:6px;min-height:36px;background:#fff;">
									<input type="text" id="bbtc-user-search" placeholder="<?php esc_attr_e( 'Type user name...', 'buddyboss-tab-creator' ); ?>" style="border:none;outline:none;width:100%;font-size:12px;padding:2px;font-family:inherit;">
								</div>
								<p class="description"><?php esc_html_e( 'You can select one or more user by typing the user name.', 'buddyboss-tab-creator' ); ?></p>
							</div>
						</div>
					</div><!-- /.bbtc-sidebar-box Tab Scope -->

				</div><!-- /.bbtc-form-sidebar -->

			</div><!-- /.bbtc-form-wrap -->
		</form>
		<?php
	}

	/**
	 * Validate, sanitize, and save a profile tab (insert or update).
	 */
	public function process_save(): void {
		check_admin_referer( 'bbtc_save_profile_tab' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buddyboss-tab-creator' ) );
		}

		$tab_id     = absint( $_POST['tab_id'] ?? 0 );
		$is_new     = ( 0 === $tab_id );
		$wp_role_slugs = array_keys( wp_roles()->roles );

		// --- Sanitize core fields ---
		$label           = sanitize_text_field( $_POST['label'] ?? '' );

		// Position: auto-assign for new tabs (place after last tab), preserve for edits.
		if ( $is_new ) {
			$all_tabs = BBTC_DB::get_tabs( 'profile', [ 'orderby' => 'position', 'order' => 'DESC', 'limit' => 1 ] );
			$position = ! empty( $all_tabs ) ? ( (int) $all_tabs[0]->position + 10 ) : 10;
		} else {
			$position = absint( $_POST['position'] ?? 10 );
		}
		$is_enabled      = absint( $_POST['is_enabled'] ?? 0 );
		$is_predefined   = absint( $_POST['is_predefined'] ?? 0 );
		$site_admin_only = absint( $_POST['site_admin_only'] ?? 0 );
		$is_default      = absint( $_POST['is_default'] ?? 0 );
		$tab_link        = esc_url_raw( $_POST['tab_link'] ?? '' );
		$default_subnav  = sanitize_key( $_POST['default_subnav_slug'] ?? '' );
		$item_css_id     = sanitize_html_class( $_POST['item_css_id'] ?? '' );
		$add_to_admin_bar    = absint( $_POST['add_to_admin_bar'] ?? 0 );
		$administer_item_id  = sanitize_text_field( $_POST['administer_item_id'] ?? '' );

		$allowed_sources = [ 'content', 'url' ];
		$content_source  = in_array( $_POST['content_source'] ?? 'content', $allowed_sources, true )
			? $_POST['content_source']
			: 'content';

		$content      = wp_kses_post( $_POST['content'] ?? '' );
		$external_url = esc_url_raw( $_POST['external_url'] ?? '' );
		$url_new_tab  = absint( $_POST['url_new_tab'] ?? 0 );

		// Status comes from whichever submit button was clicked.
		$allowed_statuses = [ 'active', 'inactive' ];
		$status = in_array( $_POST['status'] ?? 'active', $allowed_statuses, true )
			? $_POST['status']
			: 'active';

		// --- Validate required fields ---
		if ( '' === $label ) {
			BBTC_Admin::set_notice( 'error', __( 'Tab label is required.', 'buddyboss-tab-creator' ) );
			$redirect = $is_new
				? admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=add' )
				: admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $tab_id );
			wp_safe_redirect( $redirect );
			exit;
		}

		// Slug: only set on add; locked on edit.
		$slug = '';
		if ( $is_new ) {
			$raw_slug = sanitize_title( $_POST['slug'] ?? '' );
			$slug     = $raw_slug ?: sanitize_title( $label );
		}

		// --- Availability checkboxes ---
		$allowed_avail    = array_merge( [ 'all_members', 'none_wp' ], $wp_role_slugs );
		$availability_raw = array_map( 'sanitize_key', (array) ( $_POST['availability'] ?? [] ) );
		$availability     = array_filter(
			$availability_raw,
			fn( $v ) => in_array( $v, $allowed_avail, true )
		);

		// --- Visibility checkboxes ---
		$allowed_vis    = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];
		$visibility_raw = array_map( 'sanitize_key', (array) ( $_POST['visibility'] ?? [] ) );
		$visibility     = array_filter(
			$visibility_raw,
			fn( $v ) => in_array( $v, $allowed_vis, true ) || in_array( $v, $wp_role_slugs, true )
		);

		// --- Tab Scope: associated users ---
		$associated_users = array_values( array_filter( array_map( 'absint', (array) ( $_POST['associated_users'] ?? [] ) ) ) );

		// --- Build tab data array ---
		$tab_data = [
			'is_enabled'         => $is_enabled,
			'is_predefined'      => $is_predefined,
			'site_admin_only'    => $site_admin_only,
			'is_default'         => $is_default,
			'label'              => $label,
			'position'           => $position,
			'tab_link'           => $tab_link,
			'default_subnav_slug' => $default_subnav,
			'item_css_id'        => $item_css_id,
			'add_to_admin_bar'   => $add_to_admin_bar,
			'administer_item_id' => $administer_item_id,
			'content_source'     => $content_source,
			'content'            => $content,
			'external_url'       => $external_url,
			'url_new_tab'        => $url_new_tab,
			'status'             => $status,
			'updated_at'         => current_time( 'mysql' ),
		];

		if ( $is_new ) {
			$tab_data['tab_type']   = 'profile';
			$tab_data['parent_id']  = 0;
			$tab_data['slug']       = $slug;
			$tab_data['created_at'] = current_time( 'mysql' );

			$tab_id = BBTC_DB::insert_tab( $tab_data );

			if ( ! $tab_id ) {
				BBTC_Admin::set_notice( 'error', __( 'Failed to save tab. Please try again.', 'buddyboss-tab-creator' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=add' ) );
				exit;
			}
		} else {
			// Do not overwrite slug on edit.
			BBTC_DB::update_tab( $tab_id, $tab_data );
		}

		// --- Rebuild rules ---
		BBTC_DB::delete_rules_for_tab( $tab_id );

		// Availability rules.
		foreach ( $availability as $avail_val ) {
			if ( 'all_members' === $avail_val ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'scope',
					'rule_value' => 'all_members',
				] );
			} elseif ( 'none_wp' === $avail_val ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'scope',
					'rule_value' => 'none',
				] );
			} elseif ( in_array( $avail_val, $wp_role_slugs, true ) ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'role',
					'rule_value' => $avail_val,
				] );
			}
		}

		// Tab Scope: associated users as availability user_id rows.
		foreach ( $associated_users as $user_id ) {
			if ( $user_id > 0 ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'user_id',
					'rule_value' => (string) $user_id,
				] );
			}
		}

		// Visibility rules.
		$known_audiences = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];
		foreach ( $visibility as $vis_val ) {
			if ( in_array( $vis_val, $known_audiences, true ) ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'visibility',
					'rule_key'   => 'audience',
					'rule_value' => $vis_val,
				] );
			} elseif ( in_array( $vis_val, $wp_role_slugs, true ) ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'visibility',
					'rule_key'   => 'role',
					'rule_value' => $vis_val,
				] );
			}
		}

		// Save inline sub-tabs submitted with this form.
		$subtabs_raw = (array) ( $_POST['subtabs'] ?? [] );
		foreach ( $subtabs_raw as $subtab_raw ) {
			$this->process_save_subtab( (array) $subtab_raw, $tab_id );
		}

		BBTC_DB::clear_cache();

		do_action( 'bbtc_after_save_tab', $tab_id, $tab_data );

		BBTC_Admin::set_notice( 'success', __( 'Tab saved.', 'buddyboss-tab-creator' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $tab_id ) );
		exit;
	}

	/**
	 * Render one accordion item for the inline sub-tab editor.
	 *
	 * @param string|int $idx          Array index (or '__IDX__' for JS template).
	 * @param object|null $subtab      Existing sub-tab row object, or null for new/template.
	 * @param array       $wp_roles    wp_roles()->roles array.
	 * @param array       $avail_checked  Pre-checked availability values.
	 * @param array       $vis_checked    Pre-checked visibility values.
	 * @param int         $parent_tab_id  Parent tab ID (for delete link; 0 in JS template).
	 */
	private function render_subtab_accordion_item(
		string|int $idx,
		?object $subtab,
		array $wp_roles,
		array $avail_checked,
		array $vis_checked,
		int $parent_tab_id
	): void {
		$is_existing = ( null !== $subtab );
		$subtab_id   = $is_existing ? absint( $subtab->id ) : 0;
		$idx_str     = (string) $idx;

		$f_is_enabled         = $is_existing ? absint( $subtab->is_enabled )        : 1;
		$f_label              = $is_existing ? $subtab->label                        : '';
		$f_slug               = $is_existing ? $subtab->slug                         : '';
		$f_site_admin_only    = $is_existing ? absint( $subtab->site_admin_only )    : 0;
		$f_position           = $is_existing ? absint( $subtab->position )           : ( ( (int) $idx + 1 ) * 10 );
		$f_tab_link           = $is_existing ? (string) $subtab->tab_link           : '';
		$f_item_css_id        = $is_existing ? (string) $subtab->item_css_id        : '';
		$f_add_to_admin_bar   = $is_existing ? absint( $subtab->add_to_admin_bar )  : 0;
		$f_administer_item_id = $is_existing ? (string) $subtab->administer_item_id : '';
		$f_content            = $is_existing ? (string) $subtab->content            : '';

		$header_label = ( $is_existing && '' !== $f_label )
			? $f_label
			: __( 'New Subnav', 'buddyboss-tab-creator' );

		$delete_url = $is_existing
			? wp_nonce_url(
				admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=delete&id=' . $subtab_id . '&parent_id=' . $parent_tab_id ),
				'bbtc_delete_profile_tab_' . $subtab_id
			)
			: '';

		$avail_id = 'bbtc-st-avail-' . esc_attr( $idx_str );
		$vis_id   = 'bbtc-st-vis-'   . esc_attr( $idx_str );
		?>
		<div class="bbtc-accordion-item" data-subtab-id="<?php echo esc_attr( $subtab_id ); ?>">
			<div class="bbtc-accordion-header">
				<span class="bbtc-accordion-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'buddyboss-tab-creator' ); ?>">⠿</span>
				<span class="bbtc-accordion-title"><?php echo esc_html( $header_label ); ?></span>
				<span class="bbtc-accordion-chevron" aria-hidden="true">▾</span>
			</div>
			<div class="bbtc-accordion-body bbtc-collapsed">
				<input type="hidden" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][subtab_id]" value="<?php echo esc_attr( $subtab_id ); ?>">
				<input type="hidden" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][position]" data-position value="<?php echo esc_attr( $f_position ); ?>">

				<div class="bbtc-form-field">
					<label class="bbtc-inline">
						<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][is_enabled]" value="1" <?php checked( $f_is_enabled, 1 ); ?>>
						<?php esc_html_e( 'Only enabled sub-navs will be visible', 'buddyboss-tab-creator' ); ?>
					</label>
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Label', 'buddyboss-tab-creator' ); ?> <span style="color:#d63638;">*</span></label>
					<input type="text" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][label]" value="<?php echo esc_attr( $f_label ); ?>" placeholder="<?php esc_attr_e( 'Required: Label for this sub nav item', 'buddyboss-tab-creator' ); ?>" class="bbtc-subtab-label-input">
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Slug', 'buddyboss-tab-creator' ); ?></label>
					<input type="text" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][slug]" value="<?php echo esc_attr( $f_slug ); ?>" placeholder="<?php esc_attr_e( 'auto-generated from label', 'buddyboss-tab-creator' ); ?>" class="bbtc-subtab-slug-input" <?php echo $is_existing ? 'readonly' : ''; ?>>
					<p class="description">
						<?php
						if ( $is_existing ) {
							esc_html_e( 'Slug cannot be changed after first save.', 'buddyboss-tab-creator' );
						} else {
							esc_html_e( 'Preferably unique slug for this sub nav.', 'buddyboss-tab-creator' );
						}
						?>
					</p>
				</div>

				<div class="bbtc-form-field">
					<label class="bbtc-inline">
						<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][site_admin_only]" value="1" <?php checked( $f_site_admin_only, 1 ); ?>>
						<?php esc_html_e( 'Site Admin Only', 'buddyboss-tab-creator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Is this sub tab visible to site admins only?', 'buddyboss-tab-creator' ); ?></p>
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Add this tab for?', 'buddyboss-tab-creator' ); ?></label>
					<div style="margin-bottom:6px;">
						<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="<?php echo esc_attr( $avail_id ); ?>"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
					</div>
					<div class="bbtc-checkbox-group" id="<?php echo esc_attr( $avail_id ); ?>">
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][availability][]" value="all_members" <?php checked( in_array( 'all_members', $avail_checked, true ) || ! $is_existing ); ?>>
							<?php esc_html_e( 'All Members', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][availability][]" value="none_wp" <?php checked( in_array( 'none_wp', $avail_checked, true ) ); ?>>
							<?php esc_html_e( 'None/WP', 'buddyboss-tab-creator' ); ?>
							<span class="bbtc-field-hint"><?php esc_html_e( '(removes it for everyone. Only applies for existing tabs)', 'buddyboss-tab-creator' ); ?></span>
						</label>
						<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
							<label>
								<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][availability][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $avail_checked, true ) ); ?>>
								<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Tab will be available to these users on their profile.', 'buddyboss-tab-creator' ); ?></p>
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Visible for?', 'buddyboss-tab-creator' ); ?></label>
					<div style="margin-bottom:6px;">
						<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="<?php echo esc_attr( $vis_id ); ?>"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
					</div>
					<div class="bbtc-checkbox-group" id="<?php echo esc_attr( $vis_id ); ?>">
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="do_not_modify" <?php checked( in_array( 'do_not_modify', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Do Not Modify', 'buddyboss-tab-creator' ); ?>
							<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies when modifying existing tab or subnav)', 'buddyboss-tab-creator' ); ?></span>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) || ! $is_existing ); ?>>
							<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="none_remove" <?php checked( in_array( 'none_remove', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'None/Remove it for everyone', 'buddyboss-tab-creator' ); ?>
							<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies for existing tabs)', 'buddyboss-tab-creator' ); ?></span>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Logged In Members', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="owner_only" <?php checked( in_array( 'owner_only', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Profile Owner', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="friends" <?php checked( in_array( 'friends', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Friends', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
						</label>
						<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
							<label>
								<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $vis_checked, true ) ); ?>>
								<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Tab will be visible to these users when they visit the profile.', 'buddyboss-tab-creator' ); ?></p>
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'URL', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
					<input type="url" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][tab_link]" value="<?php echo esc_attr( $f_tab_link ); ?>" placeholder="<?php esc_attr_e( 'Optional: If you want to link to some other page', 'buddyboss-tab-creator' ); ?>">
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'CSS ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
					<input type="text" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][item_css_id]" value="<?php echo esc_attr( $f_item_css_id ); ?>" placeholder="<?php esc_attr_e( 'Optional: CSS id for this sub nav item', 'buddyboss-tab-creator' ); ?>">
				</div>

				<div class="bbtc-form-field">
					<label class="bbtc-inline">
						<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][add_to_admin_bar]" value="1" <?php checked( $f_add_to_admin_bar, 1 ); ?>>
						<?php esc_html_e( 'Add to admin bar?', 'buddyboss-tab-creator' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Optional: Add this tab to admin account menu for user.', 'buddyboss-tab-creator' ); ?></p>
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Administer Item ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
					<input type="text" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][administer_item_id]" value="<?php echo esc_attr( $f_administer_item_id ); ?>" placeholder="<?php esc_attr_e( 'Optional: admin item id. Required if you want to remove item from account menu.', 'buddyboss-tab-creator' ); ?>">
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Content', 'buddyboss-tab-creator' ); ?></label>
					<?php if ( $is_existing ) : ?>
						<?php
						wp_editor(
							$f_content,
							'bbtc_subtab_' . $subtab_id,
							[
								'textarea_name' => 'subtabs[' . $idx_str . '][content]',
								'media_buttons' => false,
								'teeny'         => true,
								'textarea_rows' => 6,
							]
						);
						?>
					<?php else : ?>
						<textarea name="subtabs[<?php echo esc_attr( $idx_str ); ?>][content]" rows="6" class="bbtc-subtab-content-new" placeholder="<?php esc_attr_e( 'Any type of content: shortcodes, html, text, etc.', 'buddyboss-tab-creator' ); ?>"></textarea>
					<?php endif; ?>
				</div>

				<div class="bbtc-accordion-footer">
					<div class="bbtc-accordion-reorder">
						<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-accordion-reorder="up" aria-label="<?php esc_attr_e( 'Move up', 'buddyboss-tab-creator' ); ?>">▲</button>
						<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-accordion-reorder="down" aria-label="<?php esc_attr_e( 'Move down', 'buddyboss-tab-creator' ); ?>">▼</button>
					</div>
					<?php if ( $is_existing ) : ?>
						<a href="<?php echo esc_url( $delete_url ); ?>" class="bbtc-btn-sm bbtc-btn-sm-danger bbtc-delete-link">
							<?php esc_html_e( 'Remove', 'buddyboss-tab-creator' ); ?>
						</a>
					<?php else : ?>
						<button type="button" class="bbtc-btn-sm bbtc-btn-sm-danger bbtc-subtab-remove-new">
							<?php esc_html_e( 'Remove', 'buddyboss-tab-creator' ); ?>
						</button>
					<?php endif; ?>
				</div>

			</div><!-- /.bbtc-accordion-body -->
		</div><!-- /.bbtc-accordion-item -->
		<?php
	}

	/**
	 * Save a single sub-tab from inline form POST data.
	 * Called in a loop from process_save() for each subtab[] entry.
	 *
	 * @param array $raw            Raw POST data for this sub-tab.
	 * @param int   $parent_tab_id  Parent custom tab ID.
	 */
	public function process_save_subtab( array $raw, int $parent_tab_id ): void {
		$wp_role_slugs = array_keys( wp_roles()->roles );
		$subtab_id     = absint( $raw['subtab_id'] ?? 0 );

		$label = sanitize_text_field( $raw['label'] ?? '' );
		if ( '' === $label ) {
			return; // Skip accordion items with no label (user may have added but not filled in).
		}

		$is_enabled         = absint( $raw['is_enabled'] ?? 0 );
		$site_admin_only    = absint( $raw['site_admin_only'] ?? 0 );
		$position           = absint( $raw['position'] ?? 10 );
		$tab_link           = esc_url_raw( $raw['tab_link'] ?? '' );
		$item_css_id        = sanitize_html_class( $raw['item_css_id'] ?? '' );
		$add_to_admin_bar   = absint( $raw['add_to_admin_bar'] ?? 0 );
		$administer_item_id = sanitize_text_field( $raw['administer_item_id'] ?? '' );
		$content            = wp_kses_post( $raw['content'] ?? '' );

		// Availability checkboxes.
		$allowed_avail = array_merge( [ 'all_members', 'none_wp' ], $wp_role_slugs );
		$availability  = array_filter(
			array_map( 'sanitize_key', (array) ( $raw['availability'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_avail, true )
		);

		// Visibility checkboxes.
		$allowed_vis = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];
		$visibility  = array_filter(
			array_map( 'sanitize_key', (array) ( $raw['visibility'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_vis, true ) || in_array( $v, $wp_role_slugs, true )
		);

		$subtab_data = [
			'tab_type'           => 'profile',
			'parent_id'          => $parent_tab_id,
			'label'              => $label,
			'is_enabled'         => $is_enabled,
			'is_predefined'      => 0,
			'site_admin_only'    => $site_admin_only,
			'position'           => $position,
			'tab_link'           => $tab_link,
			'item_css_id'        => $item_css_id,
			'add_to_admin_bar'   => $add_to_admin_bar,
			'administer_item_id' => $administer_item_id,
			'content'            => $content,
			'content_source'     => 'content',
			'status'             => 'active',
			'is_default'         => 0,
			'updated_at'         => current_time( 'mysql' ),
		];

		if ( $subtab_id > 0 ) {
			BBTC_DB::update_tab( $subtab_id, $subtab_data );
		} else {
			$slug = sanitize_title( $raw['slug'] ?? '' );
			if ( '' === $slug ) {
				$slug = sanitize_title( $label );
			}
			$subtab_data['slug']       = $slug;
			$subtab_data['created_at'] = current_time( 'mysql' );

			$subtab_id = BBTC_DB::insert_tab( $subtab_data );
			if ( ! $subtab_id ) {
				return;
			}
		}

		// Rebuild rules.
		BBTC_DB::delete_rules_for_tab( $subtab_id );

		$known_audiences = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];

		foreach ( $availability as $avail_val ) {
			if ( 'all_members' === $avail_val ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'all_members' ] );
			} elseif ( 'none_wp' === $avail_val ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'none' ] );
			} elseif ( in_array( $avail_val, $wp_role_slugs, true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'role', 'rule_value' => $avail_val ] );
			}
		}

		foreach ( $visibility as $vis_val ) {
			if ( in_array( $vis_val, $known_audiences, true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'visibility', 'rule_key' => 'audience', 'rule_value' => $vis_val ] );
			} elseif ( in_array( $vis_val, $wp_role_slugs, true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'visibility', 'rule_key' => 'role', 'rule_value' => $vis_val ] );
			}
		}

		do_action( 'bbtc_after_save_tab', $subtab_id, $subtab_data );
	}

	/**
	 * Render standalone sub-tab add / edit screen.
	 * Used for sub-tabs on existing built-in profile tabs (not the inline accordion).
	 *
	 * @param int    $parent_id            Parent custom tab ID (0 when editing sub-tab of existing built-in tab).
	 * @param int    $subtab_id            Sub-tab ID for editing (0 = add new).
	 * @param string $parent_existing_slug Slug of the built-in parent tab (empty when parent is a custom tab).
	 */
	public function render_subtab_screen( int $parent_id = 0, int $subtab_id = 0, string $parent_existing_slug = '' ): void {
		$subtab   = null;
		$is_new   = true;
		$wp_roles = wp_roles()->roles;

		$avail_checked = [];
		$vis_checked   = [];

		if ( $subtab_id > 0 ) {
			$subtab = BBTC_DB::get_tab( $subtab_id );
			if ( ! $subtab ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Sub-tab not found.', 'buddyboss-tab-creator' ) . '</p></div>';
				return;
			}
			$is_new = false;

			$rules = BBTC_DB::get_rules_for_tab( $subtab_id );
			foreach ( $rules as $rule ) {
				if ( 'availability' === $rule->rule_type ) {
					$avail_checked[] = ( 'scope' === $rule->rule_key && 'none' === $rule->rule_value ) ? 'none_wp' : $rule->rule_value;
				} elseif ( 'visibility' === $rule->rule_type ) {
					$vis_checked[] = $rule->rule_value;
				}
			}

			// Determine context from saved subtab if not passed.
			if ( 0 === $parent_id && empty( $parent_existing_slug ) ) {
				$parent_id            = absint( $subtab->parent_id );
				$parent_existing_slug = $subtab->parent_existing_slug ?? '';
			}
		}

		// Determine back link and parent context label.
		if ( ! empty( $parent_existing_slug ) ) {
			$back_url    = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit-existing&slug=' . rawurlencode( $parent_existing_slug ) );
			$parent_name = ucwords( str_replace( [ '-', '_' ], ' ', $parent_existing_slug ) );
		} elseif ( $parent_id > 0 ) {
			$back_url   = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $parent_id );
			$parent_tab = BBTC_DB::get_tab( $parent_id );
			$parent_name = $parent_tab ? $parent_tab->label : __( 'Parent Tab', 'buddyboss-tab-creator' );
		} else {
			$back_url    = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' );
			$parent_name = __( 'Profile Tabs', 'buddyboss-tab-creator' );
		}

		$page_title = $is_new
			? sprintf(
				/* translators: %s: parent tab name */
				esc_html__( 'Add Sub-Tab to: %s', 'buddyboss-tab-creator' ),
				'<em>' . esc_html( $parent_name ) . '</em>'
			)
			: sprintf(
				/* translators: %s: sub-tab label */
				esc_html__( 'Edit Sub-Tab: %s', 'buddyboss-tab-creator' ),
				'<em>' . esc_html( $subtab->label ) . '</em>'
			);

		// Field defaults.
		$f_is_enabled         = $is_new ? 1 : absint( $subtab->is_enabled );
		$f_label              = $is_new ? '' : $subtab->label;
		$f_slug               = $is_new ? '' : $subtab->slug;
		$f_site_admin_only    = $is_new ? 0 : absint( $subtab->site_admin_only );
		$f_position           = $is_new ? 10 : absint( $subtab->position );
		$f_tab_link           = $is_new ? '' : (string) $subtab->tab_link;
		$f_item_css_id        = $is_new ? '' : (string) $subtab->item_css_id;
		$f_add_to_admin_bar   = $is_new ? 0 : absint( $subtab->add_to_admin_bar );
		$f_administer_item_id = $is_new ? '' : (string) $subtab->administer_item_id;
		$f_content            = $is_new ? '' : (string) $subtab->content;
		$f_is_default         = $is_new ? 0 : absint( $subtab->is_default );
		$f_status             = $is_new ? 'active' : $subtab->status;
		?>
		<div class="bbtc-breadcrumb">
			<a href="<?php echo esc_url( $back_url ); ?>">← <?php echo esc_html( $parent_name ); ?></a>
		</div>
		<h1 class="wp-heading-inline"><?php echo wp_kses( $page_title, [ 'em' => [] ] ); ?></h1>

		<form method="post" id="bbtc-subtab-form" data-is-new="<?php echo esc_attr( $is_new ? '1' : '0' ); ?>">
			<?php wp_nonce_field( 'bbtc_save_profile_subtab' ); ?>
			<input type="hidden" name="subtab_id" value="<?php echo esc_attr( $subtab_id ); ?>">
			<input type="hidden" name="parent_id" value="<?php echo esc_attr( $parent_id ); ?>">
			<input type="hidden" name="parent_existing_slug" value="<?php echo esc_attr( $parent_existing_slug ); ?>">

			<div class="bbtc-form-wrap">
				<div class="bbtc-form-main">

					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Sub-Tab Settings', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_enabled" value="1" <?php checked( $f_is_enabled, 1 ); ?>>
									<?php esc_html_e( 'Only enabled sub-navs will be visible', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-subtab-label"><?php esc_html_e( 'Label', 'buddyboss-tab-creator' ); ?> <span style="color:#d63638;">*</span></label>
								<input type="text" id="bbtc-subtab-label" name="label" value="<?php echo esc_attr( $f_label ); ?>" placeholder="<?php esc_attr_e( 'Required: Label for this sub nav item', 'buddyboss-tab-creator' ); ?>" class="bbtc-standalone-subtab-label" required>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-subtab-slug"><?php esc_html_e( 'Slug', 'buddyboss-tab-creator' ); ?></label>
								<input type="text" id="bbtc-subtab-slug" name="slug" value="<?php echo esc_attr( $f_slug ); ?>" placeholder="<?php esc_attr_e( 'auto-generated from label', 'buddyboss-tab-creator' ); ?>" class="bbtc-standalone-subtab-slug" <?php echo ! $is_new ? 'readonly' : ''; ?>>
								<p class="description">
									<?php
									if ( ! $is_new ) {
										esc_html_e( 'Slug cannot be changed after first save.', 'buddyboss-tab-creator' );
									} else {
										esc_html_e( 'Preferably unique slug for this sub nav.', 'buddyboss-tab-creator' );
									}
									?>
								</p>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="site_admin_only" value="1" <?php checked( $f_site_admin_only, 1 ); ?>>
									<?php esc_html_e( 'Site Admin Only', 'buddyboss-tab-creator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Is this sub tab visible to site admins only?', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label><?php esc_html_e( 'Add this tab for?', 'buddyboss-tab-creator' ); ?></label>
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-st-avail-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-st-avail-group">
									<label>
										<input type="checkbox" name="availability[]" value="all_members" <?php checked( in_array( 'all_members', $avail_checked, true ) || $is_new ); ?>>
										<?php esc_html_e( 'All Members', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="availability[]" value="none_wp" <?php checked( in_array( 'none_wp', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'None/WP', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(removes it for everyone. Only applies for existing tabs)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
										<label>
											<input type="checkbox" name="availability[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $avail_checked, true ) ); ?>>
											<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be available to these users on their profile.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label><?php esc_html_e( 'Visible for?', 'buddyboss-tab-creator' ); ?></label>
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-st-vis-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-st-vis-group">
									<label>
										<input type="checkbox" name="visibility[]" value="do_not_modify" <?php checked( in_array( 'do_not_modify', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Do Not Modify', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies when modifying existing tab or subnav)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) || $is_new ); ?>>
										<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="none_remove" <?php checked( in_array( 'none_remove', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'None/Remove it for everyone', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies for existing tabs)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Logged In Members', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="owner_only" <?php checked( in_array( 'owner_only', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Profile Owner', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="friends" <?php checked( in_array( 'friends', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Friends', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
									</label>
									<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
										<label>
											<input type="checkbox" name="visibility[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $vis_checked, true ) ); ?>>
											<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be visible to these users when they visit the profile.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label for="bbtc-st-tab-link"><?php esc_html_e( 'URL', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="url" id="bbtc-st-tab-link" name="tab_link" value="<?php echo esc_attr( $f_tab_link ); ?>" placeholder="<?php esc_attr_e( 'Optional: If you want to link to some other page', 'buddyboss-tab-creator' ); ?>">
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-st-item-css-id"><?php esc_html_e( 'CSS ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-st-item-css-id" name="item_css_id" value="<?php echo esc_attr( $f_item_css_id ); ?>" placeholder="<?php esc_attr_e( 'Optional: CSS id for this sub nav item', 'buddyboss-tab-creator' ); ?>">
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="add_to_admin_bar" value="1" <?php checked( $f_add_to_admin_bar, 1 ); ?>>
									<?php esc_html_e( 'Add to admin bar?', 'buddyboss-tab-creator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Optional: Add this tab to admin account menu for user.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-st-administer-item-id"><?php esc_html_e( 'Administer Item ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-st-administer-item-id" name="administer_item_id" value="<?php echo esc_attr( $f_administer_item_id ); ?>" placeholder="<?php esc_attr_e( 'Optional: admin item id. Required if you want to remove item from account menu.', 'buddyboss-tab-creator' ); ?>">
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label><?php esc_html_e( 'Content', 'buddyboss-tab-creator' ); ?></label>
								<?php
								wp_editor(
									$f_content,
									'bbtc_standalone_subtab_content',
									[
										'textarea_name' => 'content',
										'media_buttons' => true,
										'teeny'         => false,
										'textarea_rows' => 10,
									]
								);
								?>
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Sub-Tab Settings -->

				</div><!-- /.bbtc-form-main -->

				<div class="bbtc-form-sidebar">

					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Publish', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-body">
							<div class="bbtc-publish-actions">
								<div class="bbtc-publish-row">
									<span>
										<?php esc_html_e( 'Status:', 'buddyboss-tab-creator' ); ?>
										<strong>
											<?php echo ( 'active' === $f_status ) ? esc_html__( 'Active', 'buddyboss-tab-creator' ) : esc_html__( 'Draft', 'buddyboss-tab-creator' ); ?>
										</strong>
									</span>
								</div>
							</div>
						</div>
						<div class="bbtc-sidebar-box-footer">
							<?php if ( ! $is_new ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=delete&id=' . $subtab_id . ( $parent_id > 0 ? '&parent_id=' . $parent_id : '' ) ), 'bbtc_delete_profile_tab_' . $subtab_id ) ); ?>" class="button-link-delete bbtc-delete-link">
									<?php esc_html_e( 'Move to Trash', 'buddyboss-tab-creator' ); ?>
								</a>
							<?php endif; ?>
							<a href="<?php echo esc_url( $back_url ); ?>" class="button-secondary">
								<?php esc_html_e( 'Cancel', 'buddyboss-tab-creator' ); ?>
							</a>
							<button type="submit" name="status" value="active" class="button-primary" style="margin-left:auto;">
								<?php echo $is_new ? esc_html__( 'Publish', 'buddyboss-tab-creator' ) : esc_html__( 'Update', 'buddyboss-tab-creator' ); ?>
							</button>
						</div>
					</div><!-- /.bbtc-sidebar-box Publish -->

					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Advanced', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-body">
							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_default" value="1" <?php checked( $f_is_default, 1 ); ?>>
									<?php
									printf(
										/* translators: %s: parent tab name */
										esc_html__( 'Set as default sub-tab for %s', 'buddyboss-tab-creator' ),
										esc_html( $parent_name )
									);
									?>
								</label>
							</div>
						</div>
					</div><!-- /.bbtc-sidebar-box Advanced -->

				</div><!-- /.bbtc-form-sidebar -->

			</div><!-- /.bbtc-form-wrap -->
		</form>
		<?php
	}

	/**
	 * Save a standalone sub-tab (used for sub-tabs on existing built-in profile tabs).
	 */
	public function process_save_standalone_subtab(): void {
		check_admin_referer( 'bbtc_save_profile_subtab' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buddyboss-tab-creator' ) );
		}

		$subtab_id            = absint( $_POST['subtab_id'] ?? 0 );
		$parent_id            = absint( $_POST['parent_id'] ?? 0 );
		$parent_existing_slug = sanitize_key( $_POST['parent_existing_slug'] ?? '' );
		$wp_role_slugs        = array_keys( wp_roles()->roles );

		$label = sanitize_text_field( $_POST['label'] ?? '' );
		if ( '' === $label ) {
			BBTC_Admin::set_notice( 'error', __( 'Sub-tab label is required.', 'buddyboss-tab-creator' ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' ) );
			exit;
		}

		$is_enabled         = absint( $_POST['is_enabled'] ?? 0 );
		$site_admin_only    = absint( $_POST['site_admin_only'] ?? 0 );
		$position           = absint( $_POST['position'] ?? 10 );
		$tab_link           = esc_url_raw( $_POST['tab_link'] ?? '' );
		$item_css_id        = sanitize_html_class( $_POST['item_css_id'] ?? '' );
		$add_to_admin_bar   = absint( $_POST['add_to_admin_bar'] ?? 0 );
		$administer_item_id = sanitize_text_field( $_POST['administer_item_id'] ?? '' );
		$content            = wp_kses_post( $_POST['content'] ?? '' );
		$is_default         = absint( $_POST['is_default'] ?? 0 );

		$allowed_statuses = [ 'active', 'inactive' ];
		$status = in_array( $_POST['status'] ?? 'active', $allowed_statuses, true )
			? $_POST['status']
			: 'active';

		// Availability checkboxes.
		$allowed_avail = array_merge( [ 'all_members', 'none_wp' ], $wp_role_slugs );
		$availability  = array_filter(
			array_map( 'sanitize_key', (array) ( $_POST['availability'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_avail, true )
		);

		// Visibility checkboxes.
		$allowed_vis = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];
		$visibility  = array_filter(
			array_map( 'sanitize_key', (array) ( $_POST['visibility'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_vis, true ) || in_array( $v, $wp_role_slugs, true )
		);

		$subtab_data = [
			'tab_type'            => 'profile',
			'parent_id'           => $parent_id,
			'parent_existing_slug' => ! empty( $parent_existing_slug ) ? $parent_existing_slug : null,
			'label'               => $label,
			'is_enabled'          => $is_enabled,
			'is_predefined'       => 0,
			'site_admin_only'     => $site_admin_only,
			'position'            => $position,
			'tab_link'            => $tab_link,
			'item_css_id'         => $item_css_id,
			'add_to_admin_bar'    => $add_to_admin_bar,
			'administer_item_id'  => $administer_item_id,
			'content'             => $content,
			'content_source'      => 'content',
			'status'              => $status,
			'is_default'          => $is_default,
			'updated_at'          => current_time( 'mysql' ),
		];

		$is_new = ( 0 === $subtab_id );

		if ( $is_new ) {
			$slug = sanitize_title( $_POST['slug'] ?? '' );
			if ( '' === $slug ) {
				$slug = sanitize_title( $label );
			}
			$subtab_data['slug']       = $slug;
			$subtab_data['created_at'] = current_time( 'mysql' );

			$subtab_id = BBTC_DB::insert_tab( $subtab_data );

			if ( ! $subtab_id ) {
				BBTC_Admin::set_notice( 'error', __( 'Failed to save sub-tab. Please try again.', 'buddyboss-tab-creator' ) );
				wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' ) );
				exit;
			}

			// Show one-time experimental notice when adding sub-tab to an existing built-in tab.
			if ( ! empty( $parent_existing_slug ) ) {
				$user_id = get_current_user_id();
				if ( ! get_user_meta( $user_id, 'bbtc_experimental_notice_shown', true ) ) {
					update_user_meta( $user_id, 'bbtc_experimental_notice_shown', '1' );
					BBTC_Admin::set_notice( 'warning', __( 'Sub-tabs on existing BuddyBoss tabs use an experimental injection method. Please test on your BuddyBoss Platform version.', 'buddyboss-tab-creator' ) );
				}
			}
		} else {
			BBTC_DB::update_tab( $subtab_id, $subtab_data );
		}

		// Rebuild rules.
		BBTC_DB::delete_rules_for_tab( $subtab_id );

		$known_audiences = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];

		foreach ( $availability as $avail_val ) {
			if ( 'all_members' === $avail_val ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'all_members' ] );
			} elseif ( 'none_wp' === $avail_val ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'none' ] );
			} elseif ( in_array( $avail_val, $wp_role_slugs, true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'role', 'rule_value' => $avail_val ] );
			}
		}

		foreach ( $visibility as $vis_val ) {
			if ( in_array( $vis_val, $known_audiences, true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'visibility', 'rule_key' => 'audience', 'rule_value' => $vis_val ] );
			} elseif ( in_array( $vis_val, $wp_role_slugs, true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'visibility', 'rule_key' => 'role', 'rule_value' => $vis_val ] );
			}
		}

		BBTC_DB::clear_cache();
		do_action( 'bbtc_after_save_tab', $subtab_id, $subtab_data );

		if ( $is_new && empty( $parent_existing_slug ) ) {
			BBTC_Admin::set_notice( 'success', __( 'Sub-tab saved.', 'buddyboss-tab-creator' ) );
		} elseif ( ! $is_new ) {
			BBTC_Admin::set_notice( 'success', __( 'Sub-tab updated.', 'buddyboss-tab-creator' ) );
		}

		// Redirect: back to parent existing tab screen, or parent custom tab edit screen.
		if ( ! empty( $parent_existing_slug ) ) {
			$redirect = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit-existing&slug=' . rawurlencode( $parent_existing_slug ) );
		} elseif ( $parent_id > 0 ) {
			$redirect = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $parent_id );
		} else {
			$redirect = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the edit-existing screen for a built-in BuddyBoss profile tab.
	 *
	 * @param string $slug The known profile nav slug.
	 */
	public function render_existing_tab_screen( string $slug ): void {
		if ( '' === $slug || ! in_array( $slug, self::get_known_profile_nav_slugs(), true ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid tab slug.', 'buddyboss-tab-creator' ) . '</p></div>';
			return;
		}

		$override    = BBTC_DB::get_override( 'profile', $slug );
		$override_id = $override ? absint( $override->id ) : 0;
		$rules       = $override_id ? BBTC_DB::get_rules_for_override( $override_id ) : [];

		$wp_roles = wp_roles()->roles;

		// Parse saved rules.
		$avail_override_active = false;
		$avail_checked         = [];
		$vis_override_active   = false;
		$vis_checked           = [];

		foreach ( $rules as $rule ) {
			if ( 'availability' === $rule->rule_type ) {
				$avail_override_active = true;
				if ( 'scope' === $rule->rule_key ) {
					$avail_checked[] = ( 'none' === $rule->rule_value ) ? 'none_wp' : $rule->rule_value;
				} elseif ( 'role' === $rule->rule_key ) {
					$avail_checked[] = $rule->rule_value;
				}
			} elseif ( 'visibility' === $rule->rule_type ) {
				$vis_override_active = true;
				if ( 'audience' === $rule->rule_key ) {
					$vis_checked[] = $rule->rule_value;
				} elseif ( 'role' === $rule->rule_key ) {
					$vis_checked[] = $rule->rule_value;
				}
			}
		}

		$nav_labels = self::get_builtin_nav_labels();
		$tab_name   = ! empty( $nav_labels[ $slug ] )
			? $nav_labels[ $slug ]
			: ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		$list_url   = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile' );

		// Field defaults.
		$f_is_enabled      = $override ? absint( $override->is_enabled )      : 1;
		$f_label_override  = $override ? (string) $override->label_override   : '';
		$f_is_default      = $override ? absint( $override->is_default )      : 0;
		$f_is_predefined   = $override ? absint( $override->is_predefined )   : 1;
		$f_is_hidden       = $override ? absint( $override->is_hidden )       : 0;
		$f_site_admin_only = $override ? absint( $override->site_admin_only ) : 0;
		$f_tab_link        = $override ? (string) $override->tab_link         : '';
		$f_item_css_id     = $override ? (string) $override->item_css_id      : '';

		?>
		<div class="bbtc-breadcrumb">
			<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( '← Back to Profile Tabs', 'buddyboss-tab-creator' ); ?></a>
		</div>
		<h1 class="wp-heading-inline">
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %1$s: tab name */
					__( 'Edit Existing Tab: %1$s', 'buddyboss-tab-creator' ),
					'<em>' . esc_html( $tab_name ) . '</em>'
				),
				[ 'em' => [] ]
			);
			?>
			<code style="font-size:14px;"><?php echo esc_html( $slug ); ?></code>
		</h1>

		<form method="post">
			<?php wp_nonce_field( 'bbtc_save_existing_profile_tab' ); ?>
			<input type="hidden" name="original_slug" value="<?php echo esc_attr( $slug ); ?>">
			<input type="hidden" name="override_id" value="<?php echo esc_attr( $override_id ); ?>">

			<div class="bbtc-form-wrap">
				<div class="bbtc-form-main">

					<!-- Override Settings postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Override Settings', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_enabled" value="1" <?php checked( $f_is_enabled, 1 ); ?>>
									<?php esc_html_e( 'Only enabled tabs will be visible', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-label-override"><?php esc_html_e( 'Override Label', 'buddyboss-tab-creator' ); ?></label>
								<input type="text" id="bbtc-label-override" name="label_override" value="<?php echo esc_attr( $f_label_override ); ?>" placeholder="<?php esc_attr_e( 'Leave blank to keep original label', 'buddyboss-tab-creator' ); ?>">
								<p class="description">
									<?php
									printf(
										/* translators: %s: original tab name */
										esc_html__( 'Current label shown by BuddyBoss is "%s". Enter a new label to rename it, or leave blank to keep the original.', 'buddyboss-tab-creator' ),
										esc_html( $tab_name )
									);
									?>
								</p>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_default" value="1" <?php checked( $f_is_default, 1 ); ?>>
									<?php esc_html_e( 'Set it as default component?', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_predefined" value="1" <?php checked( $f_is_predefined, 1 ); ?>>
									<?php esc_html_e( 'Are we modifying predefined tab?', 'buddyboss-tab-creator' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Are we modifying a tab added by BuddyPress or some other plugin?', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_hidden" value="1" <?php checked( $f_is_hidden, 1 ); ?>>
									<?php esc_html_e( 'Hide this tab (removes it from all profiles)', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="site_admin_only" value="1" <?php checked( $f_site_admin_only, 1 ); ?>>
									<?php esc_html_e( 'Site admins only?', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Override Settings -->

					<!-- Availability Override postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Availability Override', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="apply_availability_override" value="1" class="bbtc-override-toggle" data-target="bbtc-avail-override-section" <?php checked( $avail_override_active ); ?>>
									<?php esc_html_e( 'Apply availability restrictions to this tab', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-conditional-section <?php echo esc_attr( $avail_override_active ? 'bbtc-visible' : '' ); ?>" id="bbtc-avail-override-section">
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-avail-override-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-avail-override-group">
									<label>
										<input type="checkbox" name="availability[]" value="all_members" <?php checked( in_array( 'all_members', $avail_checked, true ) || ( $avail_override_active && empty( $avail_checked ) ) ); ?>>
										<?php esc_html_e( 'All Members', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="availability[]" value="none_wp" <?php checked( in_array( 'none_wp', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'None/WP', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(removes it for everyone)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
										<label>
											<input type="checkbox" name="availability[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $avail_checked, true ) ); ?>>
											<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Availability Override -->

					<!-- Visibility Override postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Visibility Override', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="apply_visibility_override" value="1" class="bbtc-override-toggle" data-target="bbtc-vis-override-section" <?php checked( $vis_override_active ); ?>>
									<?php esc_html_e( 'Apply visibility restrictions to this tab', 'buddyboss-tab-creator' ); ?>
								</label>
							</div>

							<div class="bbtc-conditional-section <?php echo esc_attr( $vis_override_active ? 'bbtc-visible' : '' ); ?>" id="bbtc-vis-override-section">
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-vis-override-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-vis-override-group">
									<label>
										<input type="checkbox" name="visibility[]" value="do_not_modify" <?php checked( in_array( 'do_not_modify', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Do Not Modify', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) || ( $vis_override_active && empty( $vis_checked ) ) ); ?>>
										<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="none_remove" <?php checked( in_array( 'none_remove', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'None/Remove for everyone', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Logged In Members', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="owner_only" <?php checked( in_array( 'owner_only', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Profile Owner', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="friends" <?php checked( in_array( 'friends', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Friends', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
									</label>
									<?php foreach ( $wp_roles as $role_slug => $role_data ) : ?>
										<label>
											<input type="checkbox" name="visibility[]" value="<?php echo esc_attr( $role_slug ); ?>" <?php checked( in_array( $role_slug, $vis_checked, true ) ); ?>>
											<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Visibility Override -->

					<!-- Additional Settings postbox -->
					<div class="postbox">
						<div class="postbox-header">
							<h3><?php esc_html_e( 'Additional Settings', 'buddyboss-tab-creator' ); ?></h3>
						</div>
						<div class="inside">

							<div class="bbtc-form-field">
								<label for="bbtc-ov-tab-link"><?php esc_html_e( 'Tab Link', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="url" id="bbtc-ov-tab-link" name="tab_link" value="<?php echo esc_attr( $f_tab_link ); ?>" placeholder="https://example.com/dynamic-link">
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-ov-item-css-id"><?php esc_html_e( 'Item CSS ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-ov-item-css-id" name="item_css_id" value="<?php echo esc_attr( $f_item_css_id ); ?>" placeholder="<?php esc_attr_e( 'e.g. my-custom-id', 'buddyboss-tab-creator' ); ?>">
							</div>

						</div><!-- /.inside -->
					</div><!-- /.postbox Additional Settings -->

				</div><!-- /.bbtc-form-main -->

				<div class="bbtc-form-sidebar">

					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Save Changes', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-footer">
							<a href="<?php echo esc_url( $list_url ); ?>" class="button-secondary">
								<?php esc_html_e( 'Cancel', 'buddyboss-tab-creator' ); ?>
							</a>
							<button type="submit" class="button-primary" style="margin-left:auto;">
								<?php esc_html_e( 'Save Changes', 'buddyboss-tab-creator' ); ?>
							</button>
						</div>
					</div><!-- /.bbtc-sidebar-box Save Changes -->

					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header" style="font-size:13px;font-weight:400;color:#646970;padding:14px 16px;">
							<strong style="color:#1d2327;"><?php esc_html_e( 'Note:', 'buddyboss-tab-creator' ); ?></strong>
							<?php
							printf(
								/* translators: %s: tab slug wrapped in <code> */
								esc_html__( 'The slug %s is set by BuddyBoss and cannot be changed. This is a platform limitation.', 'buddyboss-tab-creator' ),
								'<code>' . esc_html( $slug ) . '</code>'
							);
							?>
						</div>
					</div><!-- /.bbtc-sidebar-box Note -->

				</div><!-- /.bbtc-form-sidebar -->

			</div><!-- /.bbtc-form-wrap -->
		</form>
		<?php
	}

	/**
	 * Save override for an existing built-in profile tab.
	 */
	public function process_save_existing_profile_tab(): void {
		check_admin_referer( 'bbtc_save_existing_profile_tab' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buddyboss-tab-creator' ) );
		}

		$slug        = sanitize_key( $_POST['original_slug'] ?? '' );
		$override_id = absint( $_POST['override_id'] ?? 0 );

		if ( '' === $slug || ! in_array( $slug, self::get_known_profile_nav_slugs(), true ) ) {
			wp_die( esc_html__( 'Invalid tab slug.', 'buddyboss-tab-creator' ) );
		}

		$wp_role_slugs = array_keys( wp_roles()->roles );

		$is_enabled      = absint( $_POST['is_enabled'] ?? 0 );
		$label_override  = sanitize_text_field( $_POST['label_override'] ?? '' );
		$is_default      = absint( $_POST['is_default'] ?? 0 );
		$is_predefined   = absint( $_POST['is_predefined'] ?? 0 );
		$is_hidden       = absint( $_POST['is_hidden'] ?? 0 );
		$site_admin_only = absint( $_POST['site_admin_only'] ?? 0 );
		$tab_link        = esc_url_raw( $_POST['tab_link'] ?? '' );
		$item_css_id     = sanitize_html_class( $_POST['item_css_id'] ?? '' );

		$data = [
			'is_enabled'        => $is_enabled,
			'label_override'    => $label_override,
			'is_default'        => $is_default,
			'is_predefined'     => $is_predefined,
			'is_hidden'         => $is_hidden,
			'site_admin_only'   => $site_admin_only,
			'tab_link'          => $tab_link,
			'item_css_id'       => $item_css_id,
			'updated_at'        => current_time( 'mysql' ),
		];

		$new_override_id = BBTC_DB::insert_or_update_override( 'profile', $slug, $data );

		if ( ! $new_override_id ) {
			BBTC_Admin::set_notice( 'error', __( 'Failed to save override. Please try again.', 'buddyboss-tab-creator' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit-existing&slug=' . rawurlencode( $slug ) ) );
			exit;
		}

		// Rebuild override rules only if the override checkboxes were checked.
		BBTC_DB::delete_rules_for_override( $new_override_id );

		$apply_availability = absint( $_POST['apply_availability_override'] ?? 0 );
		$apply_visibility   = absint( $_POST['apply_visibility_override'] ?? 0 );

		if ( $apply_availability ) {
			$allowed_avail = array_merge( [ 'all_members', 'none_wp' ], $wp_role_slugs );
			$availability  = array_filter(
				array_map( 'sanitize_key', (array) ( $_POST['availability'] ?? [] ) ),
				fn( $v ) => in_array( $v, $allowed_avail, true )
			);

			foreach ( $availability as $avail_val ) {
				if ( 'all_members' === $avail_val ) {
					BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'all_members' ] );
				} elseif ( 'none_wp' === $avail_val ) {
					BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'none' ] );
				} elseif ( in_array( $avail_val, $wp_role_slugs, true ) ) {
					BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'role', 'rule_value' => $avail_val ] );
				}
			}
		}

		if ( $apply_visibility ) {
			$allowed_vis = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];
			$visibility  = array_filter(
				array_map( 'sanitize_key', (array) ( $_POST['visibility'] ?? [] ) ),
				fn( $v ) => in_array( $v, $allowed_vis, true ) || in_array( $v, $wp_role_slugs, true )
			);

			$known_audiences = [ 'do_not_modify', 'anyone', 'none_remove', 'logged_in', 'owner_only', 'friends', 'site_admin' ];
			foreach ( $visibility as $vis_val ) {
				if ( in_array( $vis_val, $known_audiences, true ) ) {
					BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'visibility', 'rule_key' => 'audience', 'rule_value' => $vis_val ] );
				} elseif ( in_array( $vis_val, $wp_role_slugs, true ) ) {
					BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'visibility', 'rule_key' => 'role', 'rule_value' => $vis_val ] );
				}
			}
		}

		BBTC_DB::clear_cache();

		BBTC_Admin::set_notice( 'success', __( 'Override saved.', 'buddyboss-tab-creator' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit-existing&slug=' . rawurlencode( $slug ) ) );
		exit;
	}

	/**
	 * Returns a slug => label map for built-in BuddyBoss profile nav items.
	 *
	 * Primary source: transient written by BBTC_Profile_Nav::cache_builtin_nav_slugs()
	 * on the first frontend profile page visit (contains the exact rendered nav names).
	 *
	 * Fallback: BuddyBoss component ->name properties, which are set during
	 * bp_setup_globals on every page load (including admin) and reflect any
	 * BuddyBoss label customisations (e.g. "Timeline", "Connections", "Photos").
	 *
	 * @return array<string,string>
	 */
	public static function get_builtin_nav_labels(): array {
		// Primary: transient from a real frontend nav build.
		$cached = get_transient( 'bbtc_builtin_profile_nav_labels' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}

		// Fallback: BuddyBoss Platform default profile nav tab labels.
		// These are the nav-level labels (e.g. "Timeline", "Groups") as
		// BuddyBoss registers them via bp_core_new_nav_item() in bp_setup_nav.
		// Component ->name properties return component names ("Activity Feeds",
		// "Social Groups") which differ from the actual profile nav tab labels.
		return [
			'activity' => __( 'Timeline',    'buddyboss' ),
			'profile'  => __( 'Profile',     'buddyboss' ),
			'friends'  => __( 'Connections', 'buddyboss' ),
			'groups'   => __( 'Groups',      'buddyboss' ),
			'forums'   => __( 'Forums',      'buddyboss' ),
			'media'    => __( 'Photos',      'buddyboss' ),
			'video'    => __( 'Videos',      'buddyboss' ),
			'document' => __( 'Documents',   'buddyboss' ),
		];
	}

	/**
	 * Returns built-in BuddyBoss profile nav slugs that are truly active and
	 * visible to any visitor (not just the profile owner).
	 *
	 * Primary source: transient `bbtc_builtin_profile_nav_v2`, populated on
	 * the front end at bp_setup_nav priority 15. That caching step already
	 * filters out tabs with show_for_displayed_user = false (Notifications,
	 * Messages, Settings) so what we get here matches what any visitor sees.
	 *
	 * Fallback (before the first frontend profile visit): bp_is_active() checks
	 * plus BuddyBoss media feature checks. Owner-only tabs are intentionally
	 * excluded from the fallback too.
	 */
	public static function get_known_profile_nav_slugs(): array {
		// Primary: use the transient populated from the front end.
		$cached = get_transient( 'bbtc_builtin_profile_nav_v2' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return (array) apply_filters( 'bbtc_known_profile_nav_slugs', $cached );
		}

		// Fallback: derive from active components.
		// Notifications, Messages, Settings deliberately omitted — they are
		// owner-only tabs and will never appear when viewing someone else's profile.
		$slugs = [];

		if ( bp_is_active( 'activity' ) ) {
			$slugs[] = 'activity';
		}
		if ( bp_is_active( 'xprofile' ) ) {
			$slugs[] = 'profile';
		}
		if ( bp_is_active( 'friends' ) ) {
			$slugs[] = 'friends';
		}
		if ( bp_is_active( 'groups' ) ) {
			$slugs[] = 'groups';
		}
		if ( bp_is_active( 'forums' ) ) {
			$slugs[] = 'forums';
		}

		// Media (photos): check BuddyBoss profile media setting when available.
		if ( bp_is_active( 'media' ) ) {
			$media_on = ! function_exists( 'bp_is_profile_media_support_enabled' )
				|| bp_is_profile_media_support_enabled();
			if ( $media_on ) {
				$slugs[] = 'media';
			}
		}

		// Video: active component + BuddyBoss profile video setting.
		if ( bp_is_active( 'video' ) ) {
			$video_on = ! function_exists( 'bp_is_profile_video_support_enabled' )
				|| bp_is_profile_video_support_enabled();
			if ( $video_on ) {
				$slugs[] = 'video';
			}
		}

		// Document: active component + BuddyBoss profile document setting.
		if ( bp_is_active( 'document' ) ) {
			$doc_on = ! function_exists( 'bp_is_profile_document_support_enabled' )
				|| bp_is_profile_document_support_enabled();
			if ( $doc_on ) {
				$slugs[] = 'document';
			}
		}

		return (array) apply_filters( 'bbtc_known_profile_nav_slugs', $slugs );
	}
}
