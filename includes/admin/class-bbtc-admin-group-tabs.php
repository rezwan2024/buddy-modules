<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin UI and action handling for group tabs.
 */
class BBTC_Admin_Group_Tabs {

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

			case 'edit-existing':
				$slug = sanitize_key( $_GET['slug'] ?? '' );
				$this->render_existing_tab_screen( $slug );
				break;

		}
	}

	/**
	 * AJAX handler: saves the display order of existing (built-in) group tabs.
	 * Expects POST: ordered_slugs[] (slugs in new order), _ajax_nonce.
	 */
	public static function ajax_reorder_existing_group_tabs(): void {
		check_ajax_referer( 'bbtc_reorder_existing_group_tabs' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$known_slugs   = self::get_known_group_nav_slugs();
		$ordered_slugs = array_map( 'sanitize_key', (array) ( $_POST['ordered_slugs'] ?? [] ) );

		// Only keep slugs that are actually known — discard any injected values.
		$ordered_slugs = array_values( array_intersect( $ordered_slugs, $known_slugs ) );

		update_option( 'bbtc_existing_group_tab_order', $ordered_slugs, false );
		wp_send_json_success();
	}

	/**
	 * AJAX handler: saves a new tab order after drag-and-drop reordering.
	 * Expects POST: ordered_ids[] (tab IDs in new order), _ajax_nonce.
	 */
	public static function ajax_reorder_group_tabs(): void {
		check_ajax_referer( 'bbtc_reorder_group_tabs' );

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
	 * AJAX handler: search BuddyBoss groups by name.
	 * Hooked to wp_ajax_bbtc_search_groups.
	 */
	public static function ajax_search_groups(): void {
		check_ajax_referer( 'bbtc_group_search' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$term = sanitize_text_field( $_GET['term'] ?? '' );

		if ( ! function_exists( 'groups_get_groups' ) ) {
			wp_send_json_success( [] );
		}

		$result = groups_get_groups( [
			'search_terms'    => $term,
			'per_page'        => 20,
			'populate_extras' => false,
			'show_hidden'     => true,
		] );

		$groups  = $result['groups'] ?? [];
		$results = [];

		foreach ( $groups as $group ) {
			$results[] = [
				'id'   => absint( $group->id ),
				'name' => esc_html( $group->name ),
			];
		}

		wp_send_json_success( $results );
	}

	/**
	 * Handle row-level actions (delete, toggle, save) before the page renders.
	 * Called from BBTC_Admin::handle_early_actions().
	 */
	public function process_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle POST save.
		if ( ! empty( $_POST ) && isset( $_POST['_wpnonce'] ) ) {
			$nonce = sanitize_key( $_POST['_wpnonce'] );
			if ( wp_verify_nonce( $nonce, 'bbtc_save_group_tab' ) ) {
				$this->process_save();
				return;
			}
			if ( wp_verify_nonce( $nonce, 'bbtc_save_existing_group_tab' ) ) {
				$this->process_save_existing_group_tab();
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
			if ( ! wp_verify_nonce( $nonce, 'bbtc_delete_group_tab_' . $id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'buddyboss-tab-creator' ) );
			}

			BBTC_DB::delete_tab( $id );
			BBTC_Admin::set_notice( 'success', __( 'Tab deleted.', 'buddyboss-tab-creator' ) );

			$parent_id = absint( $_GET['parent_id'] ?? 0 );
			$redirect  = $parent_id > 0
				? admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit&id=' . $parent_id )
				: admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group' );

			wp_safe_redirect( $redirect );
			exit;
		}

		if ( 'toggle' === $action ) {
			$nonce = sanitize_key( $_GET['_wpnonce'] ?? '' );
			if ( ! wp_verify_nonce( $nonce, 'bbtc_toggle_group_tab_' . $id ) ) {
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

			wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group' ) );
			exit;
		}
	}

	/**
	 * Handle bulk actions. Called before render().
	 */
	public function process_bulk_action( BBTC_List_Table_Group $table ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $table->current_action();

		if ( ! in_array( $action, [ 'activate', 'deactivate', 'delete' ], true ) ) {
			return;
		}

		check_admin_referer( 'bulk-group-tabs' );

		$ids = array_map( 'absint', (array) ( $_POST['group-tab'] ?? [] ) );
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

		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group' ) );
		exit;
	}

	/**
	 * Render the group tabs list screen.
	 */
	public function render_list(): void {
		$add_url = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=add' );

		$table = new BBTC_List_Table_Group();
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
		// Existing BuddyBoss Group Tabs section.
		$known_slugs  = self::get_known_group_nav_slugs();
		$nav_labels   = self::get_builtin_group_nav_labels();
		$custom_count = BBTC_DB::get_tabs_count( 'group', [ 'parent_id' => 0 ] );

		// Apply saved drag-and-drop order only when it covers exactly the current slug set.
		// If the slug sets differ (stale save from a prior state), discard the saved order
		// and reset it to the current transient order so the admin matches the frontend.
		$saved_order = get_option( 'bbtc_existing_group_tab_order', [] );
		if ( ! empty( $saved_order ) ) {
			$saved_set = $saved_order;
			sort( $saved_set );
			$known_set = $known_slugs;
			sort( $known_set );
			if ( $saved_set === $known_set ) {
				$known_slugs = array_merge(
					array_intersect( $saved_order, $known_slugs ),
					array_diff( $known_slugs, $saved_order )
				);
			} else {
				// Stale saved order — reset it to match the current transient order.
				update_option( 'bbtc_existing_group_tab_order', $known_slugs, false );
			}
		}
		?>
		<div class="bbtc-section-heading">
			<h3><?php esc_html_e( 'Existing BuddyBoss Group Tabs', 'buddyboss-tab-creator' ); ?></h3>
			<p><?php esc_html_e( 'Modify built-in group tabs registered by BuddyBoss Platform. You can rename, reorder, hide, set as default, or restrict who can access them.', 'buddyboss-tab-creator' ); ?></p>
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
					$override      = BBTC_DB::get_override( 'group', $slug );
					$override_id   = $override ? absint( $override->id ) : 0;
					$edit_url      = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit-existing&slug=' . rawurlencode( $slug ) );
					$tab_name = ! empty( $nav_labels[ $slug ] )
							? $nav_labels[ $slug ]
							: ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );

					$label_override = ( $override && ! empty( $override->label_override ) )
						? esc_html( $override->label_override )
						: '<span class="bbtc-override-none">—</span>';

					$is_hidden  = ( $override && absint( $override->is_hidden ) );
					$is_default = ( $override && absint( $override->is_default ) );

					$vis_summary   = '<span class="bbtc-override-none">—</span>';
					$avail_summary = '<span class="bbtc-override-none">—</span>';
					if ( $override_id ) {
						$rules      = BBTC_DB::get_rules_for_override( $override_id );
						$vis_vals   = [];
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
	 * Render the group tab add / edit form.
	 */
	public function render_add_edit_screen( int $tab_id = 0 ): void {
		$tab               = null;
		$rules             = [];
		$is_new            = true;
		$enabled_for_checked = [];
		$vis_checked       = [];
		$assoc_groups      = [];
		$excl_groups       = [];

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
						// 'all' or 'none' — use as-is for the checkbox value.
						$enabled_for_checked[] = $rule->rule_value;
					} elseif ( 'group_status' === $rule->rule_key ) {
						$enabled_for_checked[] = $rule->rule_value;
					} elseif ( 'group_id' === $rule->rule_key ) {
						$group_id = absint( $rule->rule_value );
						if ( $group_id > 0 && function_exists( 'groups_get_group' ) ) {
							$group = groups_get_group( $group_id );
							if ( $group && $group->id ) {
								$assoc_groups[] = [ 'id' => $group->id, 'name' => $group->name ];
							}
						}
					} elseif ( 'excluded_group_id' === $rule->rule_key ) {
						$group_id = absint( $rule->rule_value );
						if ( $group_id > 0 && function_exists( 'groups_get_group' ) ) {
							$group = groups_get_group( $group_id );
							if ( $group && $group->id ) {
								$excl_groups[] = [ 'id' => $group->id, 'name' => $group->name ];
							}
						}
					}
				} elseif ( 'visibility' === $rule->rule_type && 'audience' === $rule->rule_key ) {
					$vis_checked[] = $rule->rule_value;
				}
			}
		}

		$list_url   = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group' );
		$page_title = $is_new
			? esc_html__( 'Add New Group Tab', 'buddyboss-tab-creator' )
			: sprintf(
				/* translators: %s: tab label */
				esc_html__( 'Edit Group Tab: %s', 'buddyboss-tab-creator' ),
				'<em>' . esc_html( $tab->label ) . '</em>'
			);

		// Field defaults.
		$f_is_enabled      = $is_new ? 1 : absint( $tab->is_enabled );
		$f_label           = $is_new ? '' : $tab->label;
		$f_slug            = $is_new ? '' : $tab->slug;
		$f_is_predefined   = $is_new ? 0 : absint( $tab->is_predefined );
		$f_site_admin_only = $is_new ? 0 : absint( $tab->site_admin_only );
		$f_is_default      = $is_new ? 0 : absint( $tab->is_default );
		$f_position        = $is_new ? 10 : absint( $tab->position );
		$f_tab_link        = $is_new ? '' : $tab->tab_link;
		$f_item_css_id     = $is_new ? '' : $tab->item_css_id;
		$f_content_source  = $is_new ? 'content' : $tab->content_source;
		$f_content         = $is_new ? '' : $tab->content;
		$f_external_url    = $is_new ? '' : $tab->external_url;
		$f_url_new_tab     = $is_new ? 0 : absint( $tab->url_new_tab );
		$f_status          = $is_new ? 'active' : $tab->status;

		// Sub-tabs (edit mode only).
		$subtabs      = [];
		$subtab_rules = [];
		if ( ! $is_new && $tab_id > 0 ) {
			$subtabs = BBTC_DB::get_tabs( 'group', [ 'parent_id' => $tab_id ] );
			foreach ( $subtabs as $subtab ) {
				$subtab_rules[ $subtab->id ] = BBTC_DB::get_rules_for_tab( $subtab->id );
			}
		}
		?>
		<div class="bbtc-breadcrumb">
			<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( '← Back to Group Tabs', 'buddyboss-tab-creator' ); ?></a>
		</div>
		<h1 class="wp-heading-inline"><?php echo wp_kses( $page_title, [ 'em' => [] ] ); ?></h1>

		<form method="post" id="bbtc-tab-form" data-is-new="<?php echo esc_attr( $is_new ? '1' : '0' ); ?>">
			<?php wp_nonce_field( 'bbtc_save_group_tab' ); ?>
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
								<input type="text" id="bbtc-label" name="label" value="<?php echo esc_attr( $f_label ); ?>" placeholder="<?php esc_attr_e( 'e.g. Resources', 'buddyboss-tab-creator' ); ?>" required>
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
								<label><?php esc_html_e( 'Enabled for?', 'buddyboss-tab-creator' ); ?> <span style="color:#646970;font-weight:400;"><?php esc_html_e( '(Availability — which groups show this tab)', 'buddyboss-tab-creator' ); ?></span></label>
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-enabled-for-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-enabled-for-group">
									<label>
										<input type="checkbox" name="enabled_for[]" value="all" <?php checked( in_array( 'all', $enabled_for_checked, true ) || $is_new ); ?>>
										<?php esc_html_e( 'All Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="public" <?php checked( in_array( 'public', $enabled_for_checked, true ) ); ?>>
										<?php esc_html_e( 'Public Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="private" <?php checked( in_array( 'private', $enabled_for_checked, true ) ); ?>>
										<?php esc_html_e( 'Private Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="hidden" <?php checked( in_array( 'hidden', $enabled_for_checked, true ) ); ?>>
										<?php esc_html_e( 'Hidden Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="none" <?php checked( in_array( 'none', $enabled_for_checked, true ) ); ?>>
										<?php esc_html_e( 'None', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(Tab will not be available. If None is selected, the existing tab will be removed)', 'buddyboss-tab-creator' ); ?></span>
									</label>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be available to these groups.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<div class="bbtc-form-field">
								<label><?php esc_html_e( 'Tab Visibility', 'buddyboss-tab-creator' ); ?> <span style="color:#646970;font-weight:400;"><?php esc_html_e( '(who can see this tab when visiting a group)', 'buddyboss-tab-creator' ); ?></span></label>
								<div style="margin-bottom:6px;">
									<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="bbtc-vis-group"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
								</div>
								<div class="bbtc-checkbox-group" id="bbtc-vis-group">
									<label>
										<input type="checkbox" name="visibility[]" value="do_not_modify" <?php checked( in_array( 'do_not_modify', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Do Not Modify', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies when modifying existing tab)', 'buddyboss-tab-creator' ); ?></span>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) || $is_new ); ?>>
										<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Logged In', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="members" <?php checked( in_array( 'members', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Members Only', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="mods" <?php checked( in_array( 'mods', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Group Moderators Only', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="admins" <?php checked( in_array( 'admins', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Group Admin Only', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
									</label>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be visible to these users when they visit the group.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<hr class="bbtc-separator">

							<input type="hidden" name="position" value="<?php echo esc_attr( $f_position ); ?>">

							<div class="bbtc-form-field">
								<label for="bbtc-tab-link"><?php esc_html_e( 'Tab Link', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="url" id="bbtc-tab-link" name="tab_link" value="<?php echo esc_attr( $f_tab_link ); ?>" placeholder="https://example.com/dynamic-link">
								<p class="description"><?php esc_html_e( 'Only use if you need a dynamic url or link to absolute url.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label for="bbtc-item-css-id"><?php esc_html_e( 'Item CSS ID', 'buddyboss-tab-creator' ); ?> <span class="bbtc-tag-optional"><?php esc_html_e( 'Optional', 'buddyboss-tab-creator' ); ?></span></label>
								<input type="text" id="bbtc-item-css-id" name="item_css_id" value="<?php echo esc_attr( $f_item_css_id ); ?>" placeholder="<?php esc_attr_e( 'e.g. my-custom-id', 'buddyboss-tab-creator' ); ?>">
								<p class="description"><?php esc_html_e( 'Optional CSS ID for this nav item.', 'buddyboss-tab-creator' ); ?></p>
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
								<span style="font-weight:400;font-size:13px;color:#8892a0;"><?php esc_html_e( '— Add sub-tabs (child nav items) for this group tab.', 'buddyboss-tab-creator' ); ?></span>
							</h3>
						</div>
						<div class="inside">
							<?php if ( $is_new ) : ?>
								<p class="description"><?php esc_html_e( 'Save this tab first to add sub-navs.', 'buddyboss-tab-creator' ); ?></p>
							<?php else : ?>
								<p class="description" style="margin-bottom:14px;"><?php esc_html_e( 'Add sub-tabs (child nav items) for this group tab. Each sub-tab can have its own content, visibility, and availability settings.', 'buddyboss-tab-creator' ); ?></p>

								<div id="bbtc-subtabs-accordion" data-next-idx="<?php echo absint( count( $subtabs ) ); ?>">
									<?php if ( empty( $subtabs ) ) : ?>
										<p id="bbtc-no-subtabs-msg" class="description"><?php esc_html_e( 'No sub-navs added yet.', 'buddyboss-tab-creator' ); ?></p>
									<?php else : ?>
										<?php foreach ( $subtabs as $idx => $subtab ) :
											$st_rules          = $subtab_rules[ $subtab->id ] ?? [];
											$st_enabled_for    = [];
											$st_vis            = [];
											foreach ( $st_rules as $r ) {
												if ( 'availability' === $r->rule_type ) {
													if ( 'scope' === $r->rule_key ) {
														$st_enabled_for[] = $r->rule_value;
													} elseif ( 'group_status' === $r->rule_key ) {
														$st_enabled_for[] = $r->rule_value;
													}
												} elseif ( 'visibility' === $r->rule_type && 'audience' === $r->rule_key ) {
													$st_vis[] = $r->rule_value;
												}
											}
											$this->render_subtab_accordion_item( $idx, $subtab, $st_enabled_for, $st_vis, $tab_id );
										endforeach; ?>
									<?php endif; ?>
								</div>

								<!-- Template for new sub-tab items added via JS -->
								<template id="bbtc-subtab-template">
									<?php $this->render_subtab_accordion_item( '__IDX__', null, [], [], 0 ); ?>
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
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=delete&id=' . $tab_id ), 'bbtc_delete_group_tab_' . $tab_id ) ); ?>" class="button-link-delete bbtc-delete-link">
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
								<?php esc_html_e( 'You can limit the scope of this tab/modification to selected groups. If you select some groups, the tab settings will only apply to those groups. You can exclude this tab from selected groups.', 'buddyboss-tab-creator' ); ?>
							</p>

							<!-- Associated Groups -->
							<div class="bbtc-form-field">
								<label style="font-weight:600;font-size:12px;margin-bottom:5px;display:block;">
									<?php esc_html_e( 'Associated Groups', 'buddyboss-tab-creator' ); ?>
								</label>
								<div id="bbtc-assoc-groups-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
									<?php foreach ( $assoc_groups as $g ) : ?>
										<span class="bbtc-group-tag">
											<?php echo esc_html( $g['name'] ); ?>
											<button type="button" data-group-id="<?php echo esc_attr( $g['id'] ); ?>" class="bbtc-remove-assoc-group" aria-label="<?php esc_attr_e( 'Remove', 'buddyboss-tab-creator' ); ?>">×</button>
											<input type="hidden" name="associated_groups[]" value="<?php echo esc_attr( $g['id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<div style="border:1px solid #8c8f94;border-radius:3px;padding:6px;min-height:36px;background:#fff;position:relative;">
									<input type="text" class="bbtc-group-search-input" id="bbtc-assoc-group-search" data-target="assoc" placeholder="<?php esc_attr_e( 'Type group name...', 'buddyboss-tab-creator' ); ?>" style="border:none;outline:none;width:100%;font-size:12px;padding:2px;font-family:inherit;" autocomplete="off">
									<div class="bbtc-group-search-results" id="bbtc-assoc-group-results" style="display:none;"></div>
								</div>
								<p class="description"><?php esc_html_e( 'Select groups where this tab applies.', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<!-- Excluded Groups -->
							<div class="bbtc-form-field" style="margin-top:12px;">
								<label style="font-weight:600;font-size:12px;margin-bottom:5px;display:block;">
									<?php esc_html_e( 'Excluded Groups', 'buddyboss-tab-creator' ); ?>
								</label>
								<div id="bbtc-excl-groups-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
									<?php foreach ( $excl_groups as $g ) : ?>
										<span class="bbtc-group-tag">
											<?php echo esc_html( $g['name'] ); ?>
											<button type="button" data-group-id="<?php echo esc_attr( $g['id'] ); ?>" class="bbtc-remove-excl-group" aria-label="<?php esc_attr_e( 'Remove', 'buddyboss-tab-creator' ); ?>">×</button>
											<input type="hidden" name="excluded_groups[]" value="<?php echo esc_attr( $g['id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<div style="border:1px solid #8c8f94;border-radius:3px;padding:6px;min-height:36px;background:#fff;position:relative;">
									<input type="text" class="bbtc-group-search-input" id="bbtc-excl-group-search" data-target="excl" placeholder="<?php esc_attr_e( 'Type group name...', 'buddyboss-tab-creator' ); ?>" style="border:none;outline:none;width:100%;font-size:12px;padding:2px;font-family:inherit;" autocomplete="off">
									<div class="bbtc-group-search-results" id="bbtc-excl-group-results" style="display:none;"></div>
								</div>
								<p class="description"><?php esc_html_e( 'Exclude this tab from specific groups.', 'buddyboss-tab-creator' ); ?></p>
							</div>

						</div>
					</div><!-- /.bbtc-sidebar-box Tab Scope -->

					<!-- Advanced sidebar box -->
					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Advanced', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-body">
							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_default" value="1" <?php checked( $f_is_default, 1 ); ?>>
									<?php esc_html_e( 'Set as default group landing tab for groups where this tab is enabled', 'buddyboss-tab-creator' ); ?>
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
	 * Render one accordion item for the inline sub-tab editor (group tab version).
	 *
	 * @param string|int  $idx              Array index (or '__IDX__' for JS template).
	 * @param object|null $subtab           Existing sub-tab row object, or null for new/template.
	 * @param array       $enabled_for_checked  Pre-checked enabled_for values.
	 * @param array       $vis_checked          Pre-checked visibility values.
	 * @param int         $parent_tab_id        Parent tab ID (for delete link; 0 in JS template).
	 */
	private function render_subtab_accordion_item(
		string|int $idx,
		?object $subtab,
		array $enabled_for_checked,
		array $vis_checked,
		int $parent_tab_id
	): void {
		$is_existing = ( null !== $subtab );
		$subtab_id   = $is_existing ? absint( $subtab->id ) : 0;
		$idx_str     = (string) $idx;

		$f_is_enabled      = $is_existing ? absint( $subtab->is_enabled )     : 1;
		$f_label           = $is_existing ? $subtab->label                     : '';
		$f_slug            = $is_existing ? $subtab->slug                      : '';
		$f_site_admin_only = $is_existing ? absint( $subtab->site_admin_only ) : 0;
		$f_position        = $is_existing ? absint( $subtab->position )        : ( ( (int) $idx + 1 ) * 10 );
		$f_tab_link        = $is_existing ? (string) $subtab->tab_link        : '';
		$f_item_css_id     = $is_existing ? (string) $subtab->item_css_id     : '';
		$f_content         = $is_existing ? (string) $subtab->content         : '';

		$header_label = ( $is_existing && '' !== $f_label )
			? $f_label
			: __( 'New Subnav', 'buddyboss-tab-creator' );

		$delete_url = $is_existing
			? wp_nonce_url(
				admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=delete&id=' . $subtab_id . '&parent_id=' . $parent_tab_id ),
				'bbtc_delete_group_tab_' . $subtab_id
			)
			: '';

		$ef_id  = 'bbtc-st-ef-'  . esc_attr( $idx_str );
		$vis_id = 'bbtc-st-vis-' . esc_attr( $idx_str );
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
				</div>

				<div class="bbtc-form-field">
					<label><?php esc_html_e( 'Enabled for?', 'buddyboss-tab-creator' ); ?></label>
					<div style="margin-bottom:6px;">
						<button type="button" class="bbtc-btn-sm bbtc-btn-sm-outline" data-select-all="<?php echo esc_attr( $ef_id ); ?>"><?php esc_html_e( 'Select / Deselect All', 'buddyboss-tab-creator' ); ?></button>
					</div>
					<div class="bbtc-checkbox-group" id="<?php echo esc_attr( $ef_id ); ?>">
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][enabled_for][]" value="all" <?php checked( in_array( 'all', $enabled_for_checked, true ) || ! $is_existing ); ?>>
							<?php esc_html_e( 'All Groups', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][enabled_for][]" value="public" <?php checked( in_array( 'public', $enabled_for_checked, true ) ); ?>>
							<?php esc_html_e( 'Public Groups', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][enabled_for][]" value="private" <?php checked( in_array( 'private', $enabled_for_checked, true ) ); ?>>
							<?php esc_html_e( 'Private Groups', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][enabled_for][]" value="hidden" <?php checked( in_array( 'hidden', $enabled_for_checked, true ) ); ?>>
							<?php esc_html_e( 'Hidden Groups', 'buddyboss-tab-creator' ); ?>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Tab will be available to these groups.', 'buddyboss-tab-creator' ); ?></p>
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
							<span class="bbtc-field-hint"><?php esc_html_e( '(Only applies when modifying existing tab)', 'buddyboss-tab-creator' ); ?></span>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) || ! $is_existing ); ?>>
							<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Logged In', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="members" <?php checked( in_array( 'members', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Members Only', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="mods" <?php checked( in_array( 'mods', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Group Moderators Only', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="admins" <?php checked( in_array( 'admins', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Group Admin Only', 'buddyboss-tab-creator' ); ?>
						</label>
						<label>
							<input type="checkbox" name="subtabs[<?php echo esc_attr( $idx_str ); ?>][visibility][]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
							<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Tab will be visible to these users when they visit the group.', 'buddyboss-tab-creator' ); ?></p>
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
	 * Validate, sanitize, and save a group tab (insert or update).
	 */
	public function process_save(): void {
		check_admin_referer( 'bbtc_save_group_tab' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buddyboss-tab-creator' ) );
		}

		$tab_id = absint( $_POST['tab_id'] ?? 0 );
		$is_new = ( 0 === $tab_id );

		// --- Sanitize core fields ---
		$label           = sanitize_text_field( $_POST['label'] ?? '' );
		$is_enabled      = absint( $_POST['is_enabled'] ?? 0 );
		$is_predefined   = absint( $_POST['is_predefined'] ?? 0 );
		$site_admin_only = absint( $_POST['site_admin_only'] ?? 0 );
		$is_default      = absint( $_POST['is_default'] ?? 0 );
		$tab_link        = esc_url_raw( $_POST['tab_link'] ?? '' );
		$item_css_id     = sanitize_html_class( $_POST['item_css_id'] ?? '' );

		// Position: auto-assign for new tabs, preserve for edits.
		if ( $is_new ) {
			$all_tabs = BBTC_DB::get_tabs( 'group', [ 'orderby' => 'position', 'order' => 'DESC', 'limit' => 1 ] );
			$position = ! empty( $all_tabs ) ? ( (int) $all_tabs[0]->position + 10 ) : 10;
		} else {
			$position = absint( $_POST['position'] ?? 10 );
		}

		$allowed_sources = [ 'content', 'url' ];
		$content_source  = in_array( $_POST['content_source'] ?? 'content', $allowed_sources, true )
			? $_POST['content_source']
			: 'content';

		$content      = wp_kses_post( $_POST['content'] ?? '' );
		$external_url = esc_url_raw( $_POST['external_url'] ?? '' );
		$url_new_tab  = absint( $_POST['url_new_tab'] ?? 0 );

		$allowed_statuses = [ 'active', 'inactive' ];
		$status = in_array( $_POST['status'] ?? 'active', $allowed_statuses, true )
			? $_POST['status']
			: 'active';

		// --- Validate required fields ---
		if ( '' === $label ) {
			BBTC_Admin::set_notice( 'error', __( 'Tab label is required.', 'buddyboss-tab-creator' ) );
			$redirect = $is_new
				? admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=add' )
				: admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit&id=' . $tab_id );
			wp_safe_redirect( $redirect );
			exit;
		}

		// Slug: only set on add; locked on edit.
		$slug = '';
		if ( $is_new ) {
			$raw_slug = sanitize_title( $_POST['slug'] ?? '' );
			$slug     = $raw_slug ?: sanitize_title( $label );
		}

		// --- Enabled For (availability) checkboxes ---
		$allowed_enabled_for = [ 'all', 'public', 'private', 'hidden', 'none' ];
		$enabled_for         = array_filter(
			array_map( 'sanitize_key', (array) ( $_POST['enabled_for'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_enabled_for, true )
		);

		// --- Visibility checkboxes ---
		$allowed_vis = [ 'do_not_modify', 'anyone', 'logged_in', 'members', 'mods', 'admins', 'site_admin' ];
		$visibility  = array_filter(
			array_map( 'sanitize_key', (array) ( $_POST['visibility'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_vis, true )
		);

		// --- Tab Scope: associated and excluded groups ---
		$associated_groups = array_values( array_filter( array_map( 'absint', (array) ( $_POST['associated_groups'] ?? [] ) ) ) );
		$excluded_groups   = array_values( array_filter( array_map( 'absint', (array) ( $_POST['excluded_groups'] ?? [] ) ) ) );

		// --- Build tab data array ---
		$tab_data = [
			'is_enabled'      => $is_enabled,
			'is_predefined'   => $is_predefined,
			'site_admin_only' => $site_admin_only,
			'is_default'      => $is_default,
			'label'           => $label,
			'position'        => $position,
			'tab_link'        => $tab_link,
			'item_css_id'     => $item_css_id,
			'content_source'  => $content_source,
			'content'         => $content,
			'external_url'    => $external_url,
			'url_new_tab'     => $url_new_tab,
			'status'          => $status,
			'updated_at'      => current_time( 'mysql' ),
		];

		if ( $is_new ) {
			$tab_data['tab_type']   = 'group';
			$tab_data['parent_id']  = 0;
			$tab_data['slug']       = $slug;
			$tab_data['created_at'] = current_time( 'mysql' );

			$tab_id = BBTC_DB::insert_tab( $tab_data );

			if ( ! $tab_id ) {
				BBTC_Admin::set_notice( 'error', __( 'Failed to save tab. Please try again.', 'buddyboss-tab-creator' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=add' ) );
				exit;
			}
		} else {
			BBTC_DB::update_tab( $tab_id, $tab_data );
		}

		// --- Rebuild rules ---
		BBTC_DB::delete_rules_for_tab( $tab_id );

		// Availability rules from enabled_for[].
		foreach ( $enabled_for as $ef_val ) {
			if ( 'all' === $ef_val ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'scope',
					'rule_value' => 'all',
				] );
			} elseif ( 'none' === $ef_val ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'scope',
					'rule_value' => 'none',
				] );
			} elseif ( in_array( $ef_val, [ 'public', 'private', 'hidden' ], true ) ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'group_status',
					'rule_value' => $ef_val,
				] );
			}
		}

		// Tab Scope: associated group IDs.
		foreach ( $associated_groups as $group_id ) {
			if ( $group_id > 0 ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'group_id',
					'rule_value' => (string) $group_id,
				] );
			}
		}

		// Tab Scope: excluded group IDs.
		foreach ( $excluded_groups as $group_id ) {
			if ( $group_id > 0 ) {
				BBTC_DB::insert_rule( [
					'tab_id'     => $tab_id,
					'rule_type'  => 'availability',
					'rule_key'   => 'excluded_group_id',
					'rule_value' => (string) $group_id,
				] );
			}
		}

		// Visibility rules.
		foreach ( $visibility as $vis_val ) {
			BBTC_DB::insert_rule( [
				'tab_id'     => $tab_id,
				'rule_type'  => 'visibility',
				'rule_key'   => 'audience',
				'rule_value' => $vis_val,
			] );
		}

		// Save inline sub-tabs submitted with this form.
		$subtabs_raw = (array) ( $_POST['subtabs'] ?? [] );
		foreach ( $subtabs_raw as $subtab_raw ) {
			$this->process_save_subtab( (array) $subtab_raw, $tab_id );
		}

		BBTC_DB::clear_cache();

		do_action( 'bbtc_after_save_tab', $tab_id, $tab_data );

		BBTC_Admin::set_notice( 'success', __( 'Tab saved.', 'buddyboss-tab-creator' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit&id=' . $tab_id ) );
		exit;
	}

	/**
	 * Save a single sub-tab from inline form POST data.
	 * Called in a loop from process_save() for each subtab[] entry.
	 *
	 * @param array $raw            Raw POST data for this sub-tab.
	 * @param int   $parent_tab_id  Parent group tab ID.
	 */
	public function process_save_subtab( array $raw, int $parent_tab_id ): void {
		$subtab_id = absint( $raw['subtab_id'] ?? 0 );

		$label = sanitize_text_field( $raw['label'] ?? '' );
		if ( '' === $label ) {
			return; // Skip items with no label.
		}

		$is_enabled      = absint( $raw['is_enabled'] ?? 0 );
		$site_admin_only = absint( $raw['site_admin_only'] ?? 0 );
		$position        = absint( $raw['position'] ?? 10 );
		$tab_link        = esc_url_raw( $raw['tab_link'] ?? '' );
		$item_css_id     = sanitize_html_class( $raw['item_css_id'] ?? '' );
		$content         = wp_kses_post( $raw['content'] ?? '' );

		// Enabled for (availability) checkboxes.
		$allowed_ef = [ 'all', 'public', 'private', 'hidden', 'none' ];
		$enabled_for = array_filter(
			array_map( 'sanitize_key', (array) ( $raw['enabled_for'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_ef, true )
		);

		// Visibility checkboxes.
		$allowed_vis = [ 'do_not_modify', 'anyone', 'logged_in', 'members', 'mods', 'admins', 'site_admin' ];
		$visibility  = array_filter(
			array_map( 'sanitize_key', (array) ( $raw['visibility'] ?? [] ) ),
			fn( $v ) => in_array( $v, $allowed_vis, true )
		);

		$subtab_data = [
			'tab_type'        => 'group',
			'parent_id'       => $parent_tab_id,
			'label'           => $label,
			'is_enabled'      => $is_enabled,
			'is_predefined'   => 0,
			'site_admin_only' => $site_admin_only,
			'position'        => $position,
			'tab_link'        => $tab_link,
			'item_css_id'     => $item_css_id,
			'content'         => $content,
			'content_source'  => 'content',
			'status'          => 'active',
			'is_default'      => 0,
			'updated_at'      => current_time( 'mysql' ),
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

		foreach ( $enabled_for as $ef_val ) {
			if ( 'all' === $ef_val ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'all' ] );
			} elseif ( 'none' === $ef_val ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'none' ] );
			} elseif ( in_array( $ef_val, [ 'public', 'private', 'hidden' ], true ) ) {
				BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'availability', 'rule_key' => 'group_status', 'rule_value' => $ef_val ] );
			}
		}

		foreach ( $visibility as $vis_val ) {
			BBTC_DB::insert_rule( [ 'tab_id' => $subtab_id, 'rule_type' => 'visibility', 'rule_key' => 'audience', 'rule_value' => $vis_val ] );
		}

		do_action( 'bbtc_after_save_tab', $subtab_id, $subtab_data );
	}

	/**
	 * Render the edit-existing screen for a built-in BuddyBoss group tab.
	 *
	 * @param string $slug Known group nav slug.
	 */
	public function render_existing_tab_screen( string $slug ): void {
		if ( '' === $slug || ! in_array( $slug, self::get_known_group_nav_slugs(), true ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid tab slug.', 'buddyboss-tab-creator' ) . '</p></div>';
			return;
		}

		$override    = BBTC_DB::get_override( 'group', $slug );
		$override_id = $override ? absint( $override->id ) : 0;
		$rules       = $override_id ? BBTC_DB::get_rules_for_override( $override_id ) : [];

		// Parse saved rules into checked arrays.
		$avail_override_active = false;
		$avail_checked         = [];
		$vis_override_active   = false;
		$vis_checked           = [];
		$assoc_groups          = [];
		$excl_groups           = [];

		foreach ( $rules as $rule ) {
			if ( 'availability' === $rule->rule_type ) {
				if ( 'group_id' === $rule->rule_key ) {
					$group_id = absint( $rule->rule_value );
					if ( $group_id > 0 && function_exists( 'groups_get_group' ) ) {
						$group = groups_get_group( $group_id );
						if ( $group && $group->id ) {
							$assoc_groups[] = [ 'id' => $group->id, 'name' => $group->name ];
						}
					}
				} elseif ( 'excluded_group_id' === $rule->rule_key ) {
					$group_id = absint( $rule->rule_value );
					if ( $group_id > 0 && function_exists( 'groups_get_group' ) ) {
						$group = groups_get_group( $group_id );
						if ( $group && $group->id ) {
							$excl_groups[] = [ 'id' => $group->id, 'name' => $group->name ];
						}
					}
				} else {
					$avail_override_active = true;
					if ( 'scope' === $rule->rule_key ) {
						$avail_checked[] = $rule->rule_value;
					} elseif ( 'group_status' === $rule->rule_key ) {
						$avail_checked[] = $rule->rule_value;
					}
				}
			} elseif ( 'visibility' === $rule->rule_type ) {
				$vis_override_active = true;
				if ( 'audience' === $rule->rule_key ) {
					$vis_checked[] = $rule->rule_value;
				}
			}
		}

		$tab_name = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		$list_url = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group' );

		// Field defaults.
		$f_is_enabled      = $override ? absint( $override->is_enabled )      : 1;
		$f_label_override  = $override ? (string) $override->label_override   : '';
		$f_position_override = $override && null !== $override->position_override ? absint( $override->position_override ) : '';
		$f_is_default      = $override ? absint( $override->is_default )      : 0;
		$f_is_predefined   = $override ? absint( $override->is_predefined )   : 1;
		$f_is_hidden       = $override ? absint( $override->is_hidden )       : 0;
		$f_site_admin_only = $override ? absint( $override->site_admin_only ) : 0;
		$f_tab_link        = $override ? (string) $override->tab_link         : '';
		$f_item_css_id     = $override ? (string) $override->item_css_id      : '';

		?>
		<div class="bbtc-breadcrumb">
			<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( '← Back to Group Tabs', 'buddyboss-tab-creator' ); ?></a>
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
			<?php wp_nonce_field( 'bbtc_save_existing_group_tab' ); ?>
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
										esc_html__( 'Current tab is "%s". Enter a new label to rename it, or leave blank to keep the original.', 'buddyboss-tab-creator' ),
										esc_html( $tab_name )
									);
									?>
								</p>
							</div>

							<input type="hidden" name="position_override" value="">

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
								<p class="description"><?php esc_html_e( 'Are we modifying a tab added by BuddyBoss or some other plugin?', 'buddyboss-tab-creator' ); ?></p>
							</div>

							<div class="bbtc-form-field">
								<label class="bbtc-inline">
									<input type="checkbox" name="is_hidden" value="1" <?php checked( $f_is_hidden, 1 ); ?>>
									<?php esc_html_e( 'Hide this tab (removes it from all groups)', 'buddyboss-tab-creator' ); ?>
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
										<input type="checkbox" name="enabled_for[]" value="all" <?php checked( in_array( 'all', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'All Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="public" <?php checked( in_array( 'public', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'Public Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="private" <?php checked( in_array( 'private', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'Private Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="hidden" <?php checked( in_array( 'hidden', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'Hidden Groups', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="enabled_for[]" value="none" <?php checked( in_array( 'none', $avail_checked, true ) ); ?>>
										<?php esc_html_e( 'None', 'buddyboss-tab-creator' ); ?>
										<span class="bbtc-field-hint"><?php esc_html_e( '(removes tab for everyone)', 'buddyboss-tab-creator' ); ?></span>
									</label>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be available to these groups.', 'buddyboss-tab-creator' ); ?></p>
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
										<input type="checkbox" name="visibility[]" value="anyone" <?php checked( in_array( 'anyone', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Anyone', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="logged_in" <?php checked( in_array( 'logged_in', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Logged In', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="members" <?php checked( in_array( 'members', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Members Only', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="mods" <?php checked( in_array( 'mods', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Group Moderators Only', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="admins" <?php checked( in_array( 'admins', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Group Admin Only', 'buddyboss-tab-creator' ); ?>
									</label>
									<label>
										<input type="checkbox" name="visibility[]" value="site_admin" <?php checked( in_array( 'site_admin', $vis_checked, true ) ); ?>>
										<?php esc_html_e( 'Site Admins', 'buddyboss-tab-creator' ); ?>
									</label>
								</div>
								<p class="description"><?php esc_html_e( 'Tab will be visible to these users when they visit the group.', 'buddyboss-tab-creator' ); ?></p>
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

					<!-- Save Changes box -->
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

					<!-- Tab Scope sidebar box -->
					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header">
							<?php esc_html_e( 'Tab Scope', 'buddyboss-tab-creator' ); ?>
						</div>
						<div class="bbtc-sidebar-box-body">
							<p style="font-size:12px;color:#646970;margin-bottom:8px;">
								<?php esc_html_e( 'Limit the scope of this modification to selected groups, or exclude specific groups.', 'buddyboss-tab-creator' ); ?>
							</p>

							<!-- Associated Groups -->
							<div class="bbtc-form-field">
								<label style="font-weight:600;font-size:12px;margin-bottom:5px;display:block;">
									<?php esc_html_e( 'Associated Groups', 'buddyboss-tab-creator' ); ?>
								</label>
								<div id="bbtc-assoc-groups-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
									<?php foreach ( $assoc_groups as $g ) : ?>
										<span class="bbtc-group-tag">
											<?php echo esc_html( $g['name'] ); ?>
											<button type="button" class="bbtc-remove-assoc-group" aria-label="<?php esc_attr_e( 'Remove', 'buddyboss-tab-creator' ); ?>">×</button>
											<input type="hidden" name="associated_groups[]" value="<?php echo esc_attr( $g['id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<div style="border:1px solid #8c8f94;border-radius:3px;padding:6px;min-height:36px;background:#fff;position:relative;">
									<input type="text" class="bbtc-group-search-input" id="bbtc-assoc-group-search" data-target="assoc" placeholder="<?php esc_attr_e( 'Type group name...', 'buddyboss-tab-creator' ); ?>" style="border:none;outline:none;width:100%;font-size:12px;padding:2px;font-family:inherit;" autocomplete="off">
									<div class="bbtc-group-search-results" id="bbtc-assoc-group-results" style="display:none;"></div>
								</div>
							</div>

							<!-- Excluded Groups -->
							<div class="bbtc-form-field" style="margin-top:12px;">
								<label style="font-weight:600;font-size:12px;margin-bottom:5px;display:block;">
									<?php esc_html_e( 'Excluded Groups', 'buddyboss-tab-creator' ); ?>
								</label>
								<div id="bbtc-excl-groups-tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
									<?php foreach ( $excl_groups as $g ) : ?>
										<span class="bbtc-group-tag">
											<?php echo esc_html( $g['name'] ); ?>
											<button type="button" class="bbtc-remove-excl-group" aria-label="<?php esc_attr_e( 'Remove', 'buddyboss-tab-creator' ); ?>">×</button>
											<input type="hidden" name="excluded_groups[]" value="<?php echo esc_attr( $g['id'] ); ?>">
										</span>
									<?php endforeach; ?>
								</div>
								<div style="border:1px solid #8c8f94;border-radius:3px;padding:6px;min-height:36px;background:#fff;position:relative;">
									<input type="text" class="bbtc-group-search-input" id="bbtc-excl-group-search" data-target="excl" placeholder="<?php esc_attr_e( 'Type group name...', 'buddyboss-tab-creator' ); ?>" style="border:none;outline:none;width:100%;font-size:12px;padding:2px;font-family:inherit;" autocomplete="off">
									<div class="bbtc-group-search-results" id="bbtc-excl-group-results" style="display:none;"></div>
								</div>
							</div>
						</div>
					</div><!-- /.bbtc-sidebar-box Tab Scope -->

					<!-- Note box -->
					<div class="bbtc-sidebar-box">
						<div class="bbtc-sidebar-box-header" style="font-size:13px;font-weight:400;color:#646970;padding:14px 16px;">
							<strong style="color:#1d2327;"><?php esc_html_e( 'Note:', 'buddyboss-tab-creator' ); ?></strong>
							<?php
							printf(
								/* translators: %s: slug wrapped in <code> */
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
	 * Save override for an existing built-in group tab.
	 */
	public function process_save_existing_group_tab(): void {
		check_admin_referer( 'bbtc_save_existing_group_tab' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buddyboss-tab-creator' ) );
		}

		$slug        = sanitize_key( $_POST['original_slug'] ?? '' );
		$override_id = absint( $_POST['override_id'] ?? 0 );

		if ( '' === $slug || ! in_array( $slug, self::get_known_group_nav_slugs(), true ) ) {
			wp_die( esc_html__( 'Invalid tab slug.', 'buddyboss-tab-creator' ) );
		}

		$is_enabled      = absint( $_POST['is_enabled'] ?? 0 );
		$label_override  = sanitize_text_field( $_POST['label_override'] ?? '' );
		$position_raw    = $_POST['position_override'] ?? '';
		$position_override = ( '' !== $position_raw ) ? absint( $position_raw ) : null;
		$is_default      = absint( $_POST['is_default'] ?? 0 );
		$is_predefined   = absint( $_POST['is_predefined'] ?? 0 );
		$is_hidden       = absint( $_POST['is_hidden'] ?? 0 );
		$site_admin_only = absint( $_POST['site_admin_only'] ?? 0 );
		$tab_link        = esc_url_raw( $_POST['tab_link'] ?? '' );
		$item_css_id     = sanitize_html_class( $_POST['item_css_id'] ?? '' );

		// Enabled For (availability override).
		$apply_avail     = ! empty( $_POST['apply_availability_override'] );
		$allowed_ef      = [ 'all', 'public', 'private', 'hidden', 'none' ];
		$enabled_for     = $apply_avail
			? array_filter(
				array_map( 'sanitize_key', (array) ( $_POST['enabled_for'] ?? [] ) ),
				fn( $v ) => in_array( $v, $allowed_ef, true )
			)
			: [];

		// Visibility override.
		$apply_vis   = ! empty( $_POST['apply_visibility_override'] );
		$allowed_vis = [ 'do_not_modify', 'anyone', 'logged_in', 'members', 'mods', 'admins', 'site_admin' ];
		$visibility  = $apply_vis
			? array_filter(
				array_map( 'sanitize_key', (array) ( $_POST['visibility'] ?? [] ) ),
				fn( $v ) => in_array( $v, $allowed_vis, true )
			)
			: [];

		// Tab Scope.
		$associated_groups = array_values( array_filter( array_map( 'absint', (array) ( $_POST['associated_groups'] ?? [] ) ) ) );
		$excluded_groups   = array_values( array_filter( array_map( 'absint', (array) ( $_POST['excluded_groups'] ?? [] ) ) ) );

		$override_data = [
			'is_enabled'         => $is_enabled,
			'is_predefined'      => $is_predefined,
			'label_override'     => '' !== $label_override ? $label_override : null,
			'position_override'  => $position_override,
			'is_default'         => $is_default,
			'is_hidden'          => $is_hidden,
			'site_admin_only'    => $site_admin_only,
			'tab_link'           => $tab_link,
			'item_css_id'        => $item_css_id,
			'updated_at'         => current_time( 'mysql' ),
		];

		$new_override_id = BBTC_DB::insert_or_update_override( 'group', $slug, $override_data );

		if ( ! $new_override_id ) {
			BBTC_Admin::set_notice( 'error', __( 'Failed to save override. Please try again.', 'buddyboss-tab-creator' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit-existing&slug=' . rawurlencode( $slug ) ) );
			exit;
		}

		// Rebuild override rules.
		BBTC_DB::delete_rules_for_override( $new_override_id );

		// Availability rules.
		foreach ( $enabled_for as $ef_val ) {
			if ( 'all' === $ef_val ) {
				BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'all' ] );
			} elseif ( 'none' === $ef_val ) {
				BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'scope', 'rule_value' => 'none' ] );
			} elseif ( in_array( $ef_val, [ 'public', 'private', 'hidden' ], true ) ) {
				BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'group_status', 'rule_value' => $ef_val ] );
			}
		}

		// Tab Scope: associated groups.
		foreach ( $associated_groups as $group_id ) {
			if ( $group_id > 0 ) {
				BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'group_id', 'rule_value' => (string) $group_id ] );
			}
		}

		// Tab Scope: excluded groups.
		foreach ( $excluded_groups as $group_id ) {
			if ( $group_id > 0 ) {
				BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'availability', 'rule_key' => 'excluded_group_id', 'rule_value' => (string) $group_id ] );
			}
		}

		// Visibility rules.
		foreach ( $visibility as $vis_val ) {
			BBTC_DB::insert_override_rule( [ 'override_id' => $new_override_id, 'rule_type' => 'visibility', 'rule_key' => 'audience', 'rule_value' => $vis_val ] );
		}

		BBTC_DB::clear_cache();

		BBTC_Admin::set_notice( 'success', __( 'Override saved.', 'buddyboss-tab-creator' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit-existing&slug=' . rawurlencode( $slug ) ) );
		exit;
	}

	/**
	 * Returns a slug => label map for built-in BuddyBoss group nav items.
	 * Uses the transient populated by BBTC_Group_Nav::cache_builtin_group_nav_slugs() on the front end.
	 *
	 * @return array<string,string>
	 */
	public static function get_builtin_group_nav_labels(): array {
		$cached = get_transient( 'bbtc_builtin_group_nav_labels' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}
		return [];
	}

	/**
	 * Returns the list of known built-in BuddyBoss group nav slugs.
	 * Primary source: transient cached from the front end by BBTC_Group_Nav.
	 * Fallback: bp_is_active() checks for each component.
	 * Filtered via bbtc_known_group_nav_slugs for extensibility.
	 */
	public static function get_known_group_nav_slugs(): array {
		// Primary: use the transient populated from the front end.
		$cached = get_transient( 'bbtc_builtin_group_nav_v5' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return (array) apply_filters( 'bbtc_known_group_nav_slugs', $cached );
		}

		// Fallback: derive from active components.
		$slugs = [ 'feed', 'members' ];

		if ( bp_is_active( 'groups' ) ) {
			// Subgroups (child groups) — always present when groups are active.
			$slugs[] = 'subgroups';
		}

		if ( bp_is_active( 'media' ) ) {
			$slugs[] = 'photos';
			$slugs[] = 'albums';
		}

		if ( bp_is_active( 'video' ) ) {
			$slugs[] = 'videos';
		}

		if ( bp_is_active( 'document' ) ) {
			$slugs[] = 'documents';
		}

		if ( bp_is_active( 'messages' ) ) {
			$slugs[] = 'messages';
		}

		// 'manage' is always present for group admins.
		$slugs[] = 'manage';

		return apply_filters( 'bbtc_known_group_nav_slugs', $slugs );
	}
}
