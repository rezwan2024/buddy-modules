<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for profile tabs.
 */
class BBTC_List_Table_Profile extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'profile-tab',
			'plural'   => 'profile-tabs',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'handle'       => '',
			'cb'           => '<input type="checkbox" />',
			'label'        => esc_html__( 'Tab Label', 'buddyboss-tab-creator' ),
			'slug'         => esc_html__( 'Slug', 'buddyboss-tab-creator' ),
			'type'         => esc_html__( 'Type', 'buddyboss-tab-creator' ),
			'availability' => esc_html__( 'Availability', 'buddyboss-tab-creator' ),
			'visibility'   => esc_html__( 'Visibility', 'buddyboss-tab-creator' ),
			'status'       => esc_html__( 'Active?', 'buddyboss-tab-creator' ),
			'is_default'   => esc_html__( 'Is Default?', 'buddyboss-tab-creator' ),
			'subtabs'      => esc_html__( 'Sub-tabs', 'buddyboss-tab-creator' ),
			'date'         => esc_html__( 'Date', 'buddyboss-tab-creator' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'label'  => [ 'label', false ],
			'slug'   => [ 'slug', false ],
			'status' => [ 'status', false ],
			'date'   => [ 'date', false ],
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'activate'   => esc_html__( 'Activate', 'buddyboss-tab-creator' ),
			'deactivate' => esc_html__( 'Deactivate', 'buddyboss-tab-creator' ),
			'delete'     => esc_html__( 'Delete', 'buddyboss-tab-creator' ),
		];
	}

	/**
	 * Outputs a drag-handle cell. The actual draggable is added to <tr> via JS.
	 */
	protected function column_handle( $item ): string {
		return '<span class="bbtc-drag-handle" title="' . esc_attr__( 'Drag to reorder', 'buddyboss-tab-creator' ) . '">⠿</span>';
	}

	/**
	 * Override single_row() to inject data-tab-id on each <tr> for drag-and-drop.
	 *
	 * @param object $item
	 */
	public function single_row( $item ): void {
		echo '<tr data-tab-id="' . absint( $item->id ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="profile-tab[]" value="%d" />',
			absint( $item->id )
		);
	}

	protected function column_label( $item ): string {
		$tab_id    = absint( $item->id );
		$edit_url  = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $tab_id );
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=delete&id=' . $tab_id ),
			'bbtc_delete_profile_tab_' . $tab_id
		);
		$toggle_label = ( 'active' === $item->status )
			? esc_html__( 'Deactivate', 'buddyboss-tab-creator' )
			: esc_html__( 'Activate', 'buddyboss-tab-creator' );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=toggle&id=' . $tab_id ),
			'bbtc_toggle_profile_tab_' . $tab_id
		);

		$row_actions = [
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), esc_html__( 'Edit', 'buddyboss-tab-creator' ) ),
			'toggle' => sprintf( '<a href="%s">%s</a>', esc_url( $toggle_url ), $toggle_label ),
			'trash'  => sprintf( '<a href="%s" class="bbtc-delete-link">%s</a>', esc_url( $delete_url ), esc_html__( 'Delete', 'buddyboss-tab-creator' ) ),
		];

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			esc_url( $edit_url ),
			esc_html( $item->label ),
			$this->row_actions( $row_actions )
		);
	}

	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'slug':
				return '<code>' . esc_html( $item->slug ) . '</code>';

			case 'type':
				return '<span class="bbtc-type-custom">' . esc_html__( 'Custom', 'buddyboss-tab-creator' ) . '</span>';

			case 'status':
				if ( 'active' === $item->status ) {
					return '<span class="bbtc-status-active">' . esc_html__( 'Active', 'buddyboss-tab-creator' ) . '</span>';
				}
				return '<span class="bbtc-status-inactive">' . esc_html__( 'Inactive', 'buddyboss-tab-creator' ) . '</span>';

			case 'is_default':
				if ( 1 === (int) $item->is_default ) {
					return '<span class="bbtc-default-badge">' . esc_html__( 'Default', 'buddyboss-tab-creator' ) . '</span>';
				}
				return '&mdash;';

			case 'subtabs':
				return $this->render_subtabs_column( (int) $item->id );

			case 'availability':
				return $this->render_rules_summary( (int) $item->id, 'availability' );

			case 'visibility':
				return $this->render_rules_summary( (int) $item->id, 'visibility' );

			case 'date':
				return esc_html( date_i18n( 'Y-m-d', strtotime( $item->created_at ) ) );
		}

		return '';
	}

	public function no_items(): void {
		esc_html_e( 'No profile tabs found. Add your first tab.', 'buddyboss-tab-creator' );
	}

	public function prepare_items(): void {
		$per_page     = 20;
		$orderby      = sanitize_key( $_GET['orderby'] ?? 'position' );
		$order        = sanitize_key( $_GET['order'] ?? 'asc' );
		$paged        = absint( $_GET['paged'] ?? 1 );
		$paged        = max( 1, $paged );
		$offset       = ( $paged - 1 ) * $per_page;

		$allowed_orderby = [ 'label', 'slug', 'position', 'status', 'date', 'created_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'position';
		}
		// 'date' maps to 'created_at' column
		if ( 'date' === $orderby ) {
			$orderby = 'created_at';
		}

		$args = [
			'orderby'   => $orderby,
			'order'     => strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC',
			'limit'     => $per_page,
			'offset'    => $offset,
			'parent_id' => 0,
		];

		$this->items = BBTC_DB::get_tabs( 'profile', $args );
		$total       = BBTC_DB::get_tabs_count( 'profile', [ 'parent_id' => 0 ] );

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		] );

		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function render_subtabs_column( int $tab_id ): string {
		$count = BBTC_DB::get_tabs_count( 'profile', [ 'parent_id' => $tab_id ] );
		if ( $count > 0 ) {
			$url = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=profile&action=edit&id=' . $tab_id . '#subtabs' );
			return sprintf(
				'<a href="%s" class="bbtc-subtab-count has-subtabs">%d</a>',
				esc_url( $url ),
				$count
			);
		}
		return '<span class="bbtc-subtab-count">0</span>';
	}

	private function render_rules_summary( int $tab_id, string $rule_type ): string {
		$rules = BBTC_DB::get_rules_for_tab( $tab_id );
		$parts = [];

		foreach ( $rules as $rule ) {
			if ( $rule->rule_type !== $rule_type ) {
				continue;
			}

			if ( 'availability' === $rule_type ) {
				$parts[] = $this->label_availability_rule( $rule->rule_key, $rule->rule_value );
			} else {
				$parts[] = $this->label_visibility_rule( $rule->rule_key, $rule->rule_value );
			}
		}

		$parts = array_filter( $parts );

		if ( empty( $parts ) ) {
			return '<span style="color:#8892a0;">' . esc_html__( 'All', 'buddyboss-tab-creator' ) . '</span>';
		}

		return esc_html( implode( ', ', $parts ) );
	}

	private function label_availability_rule( string $key, string $value ): string {
		if ( 'scope' === $key ) {
			return match ( $value ) {
				'all_members' => __( 'All Members', 'buddyboss-tab-creator' ),
				'none'        => __( 'None', 'buddyboss-tab-creator' ),
				default       => $value,
			};
		}
		if ( 'role' === $key ) {
			$roles = wp_roles()->roles;
			return isset( $roles[ $value ] ) ? translate_user_role( $roles[ $value ]['name'] ) : $value;
		}
		if ( 'user_id' === $key ) {
			return sprintf( __( 'User #%s', 'buddyboss-tab-creator' ), $value );
		}
		if ( 'inherit' === $key ) {
			return __( 'Inherited', 'buddyboss-tab-creator' );
		}
		return $value;
	}

	private function label_visibility_rule( string $key, string $value ): string {
		if ( 'audience' === $key ) {
			return match ( $value ) {
				'do_not_modify' => __( 'Do Not Modify', 'buddyboss-tab-creator' ),
				'anyone'        => __( 'Anyone', 'buddyboss-tab-creator' ),
				'none_remove'   => __( 'None', 'buddyboss-tab-creator' ),
				'logged_in'     => __( 'Logged In', 'buddyboss-tab-creator' ),
				'owner_only'    => __( 'Profile Owner', 'buddyboss-tab-creator' ),
				'friends'       => __( 'Friends', 'buddyboss-tab-creator' ),
				'site_admin'    => __( 'Site Admin', 'buddyboss-tab-creator' ),
				default         => $value,
			};
		}
		if ( 'role' === $key ) {
			$roles = wp_roles()->roles;
			return isset( $roles[ $value ] ) ? translate_user_role( $roles[ $value ]['name'] ) : $value;
		}
		if ( 'inherit' === $key ) {
			return __( 'Inherited', 'buddyboss-tab-creator' );
		}
		return $value;
	}
}
