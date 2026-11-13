<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for group tabs.
 */
class BBTC_List_Table_Group extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'group-tab',
			'plural'   => 'group-tabs',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'handle'      => '',
			'cb'          => '<input type="checkbox" />',
			'label'       => esc_html__( 'Tab Label', 'buddyboss-tab-creator' ),
			'slug'        => esc_html__( 'Slug', 'buddyboss-tab-creator' ),
			'type'        => esc_html__( 'Type', 'buddyboss-tab-creator' ),
'enabled_for' => esc_html__( 'Enabled For', 'buddyboss-tab-creator' ),
			'visible_to'  => esc_html__( 'Visible To', 'buddyboss-tab-creator' ),
			'status'      => esc_html__( 'Active?', 'buddyboss-tab-creator' ),
			'is_default'  => esc_html__( 'Is Default?', 'buddyboss-tab-creator' ),
			'subtabs'     => esc_html__( 'Sub-tabs', 'buddyboss-tab-creator' ),
			'date'        => esc_html__( 'Date', 'buddyboss-tab-creator' ),
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

	/**
	 * Outputs a drag-handle cell.
	 */
	protected function column_handle( $item ): string {
		return '<span class="bbtc-drag-handle" title="' . esc_attr__( 'Drag to reorder', 'buddyboss-tab-creator' ) . '">⠿</span>';
	}

	/**
	 * Override single_row() to inject data-tab-id for drag-and-drop.
	 */
	public function single_row( $item ): void {
		echo '<tr data-tab-id="' . absint( $item->id ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	protected function get_bulk_actions(): array {
		return [
			'activate'   => esc_html__( 'Activate', 'buddyboss-tab-creator' ),
			'deactivate' => esc_html__( 'Deactivate', 'buddyboss-tab-creator' ),
			'delete'     => esc_html__( 'Delete', 'buddyboss-tab-creator' ),
		];
	}

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="group-tab[]" value="%d" />',
			absint( $item->id )
		);
	}

	protected function column_label( $item ): string {
		$tab_id     = absint( $item->id );
		$edit_url   = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit&id=' . $tab_id );
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=delete&id=' . $tab_id ),
			'bbtc_delete_group_tab_' . $tab_id
		);
		$toggle_label = ( 'active' === $item->status )
			? esc_html__( 'Deactivate', 'buddyboss-tab-creator' )
			: esc_html__( 'Activate', 'buddyboss-tab-creator' );
		$toggle_url = wp_nonce_url(
			admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=toggle&id=' . $tab_id ),
			'bbtc_toggle_group_tab_' . $tab_id
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

			case 'enabled_for':
				return $this->render_availability_summary( (int) $item->id );

			case 'visible_to':
				return $this->render_visibility_summary( (int) $item->id );

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

			case 'date':
				return esc_html( date_i18n( 'Y-m-d', strtotime( $item->created_at ) ) );
		}

		return '';
	}

	public function no_items(): void {
		esc_html_e( 'No group tabs found. Add your first tab.', 'buddyboss-tab-creator' );
	}

	public function prepare_items(): void {
		$per_page = 20;
		$orderby  = sanitize_key( $_GET['orderby'] ?? 'position' );
		$order    = sanitize_key( $_GET['order'] ?? 'asc' );
		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$allowed_orderby = [ 'label', 'slug', 'position', 'status', 'date', 'created_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'position';
		}
		if ( 'date' === $orderby ) {
			$orderby = 'created_at';
		}

		$args = [
			'orderby'   => $orderby,
			'order'     => 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC',
			'limit'     => $per_page,
			'offset'    => $offset,
			'parent_id' => 0,
		];

		$this->items = BBTC_DB::get_tabs( 'group', $args );
		$total       = BBTC_DB::get_tabs_count( 'group', [ 'parent_id' => 0 ] );

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
		$count = BBTC_DB::get_tabs_count( 'group', [ 'parent_id' => $tab_id ] );
		if ( $count > 0 ) {
			$url = admin_url( 'admin.php?page=buddyboss-tab-creator&tab=group&action=edit&id=' . $tab_id . '#subtabs' );
			return sprintf(
				'<a href="%s" class="bbtc-subtab-count has-subtabs">%d</a>',
				esc_url( $url ),
				$count
			);
		}
		return '<span class="bbtc-subtab-count">0</span>';
	}

	private function render_availability_summary( int $tab_id ): string {
		$rules  = BBTC_DB::get_rules_for_tab( $tab_id );
		$parts  = [];
		$statuses = [];

		foreach ( $rules as $rule ) {
			if ( 'availability' !== $rule->rule_type ) {
				continue;
			}

			if ( 'scope' === $rule->rule_key ) {
				$parts[] = match ( $rule->rule_value ) {
					'all'  => __( 'All Groups', 'buddyboss-tab-creator' ),
					'none' => __( 'None', 'buddyboss-tab-creator' ),
					default => esc_html( $rule->rule_value ),
				};
			} elseif ( 'group_status' === $rule->rule_key ) {
				$statuses[] = match ( $rule->rule_value ) {
					'public'  => __( 'Public', 'buddyboss-tab-creator' ),
					'private' => __( 'Private', 'buddyboss-tab-creator' ),
					'hidden'  => __( 'Hidden', 'buddyboss-tab-creator' ),
					default   => esc_html( $rule->rule_value ),
				};
			} elseif ( 'group_id' === $rule->rule_key ) {
				$parts[] = sprintf( __( 'Group #%s', 'buddyboss-tab-creator' ), $rule->rule_value );
			} elseif ( 'excluded_group_id' === $rule->rule_key ) {
				$parts[] = sprintf( __( 'Excl. Group #%s', 'buddyboss-tab-creator' ), $rule->rule_value );
			} elseif ( 'inherit' === $rule->rule_key ) {
				$parts[] = __( 'Inherited', 'buddyboss-tab-creator' );
			}
		}

		if ( ! empty( $statuses ) ) {
			$parts[] = implode( ' / ', $statuses );
		}

		$parts = array_filter( $parts );

		if ( empty( $parts ) ) {
			return '<span style="color:#8892a0;">' . esc_html__( 'All', 'buddyboss-tab-creator' ) . '</span>';
		}

		return esc_html( implode( ', ', $parts ) );
	}

	private function render_visibility_summary( int $tab_id ): string {
		$rules = BBTC_DB::get_rules_for_tab( $tab_id );
		$parts = [];

		foreach ( $rules as $rule ) {
			if ( 'visibility' !== $rule->rule_type ) {
				continue;
			}

			if ( 'audience' === $rule->rule_key ) {
				$parts[] = match ( $rule->rule_value ) {
					'do_not_modify' => __( 'Do Not Modify', 'buddyboss-tab-creator' ),
					'anyone'        => __( 'Anyone', 'buddyboss-tab-creator' ),
					'logged_in'     => __( 'Logged In', 'buddyboss-tab-creator' ),
					'members'       => __( 'Members Only', 'buddyboss-tab-creator' ),
					'mods'          => __( 'Moderators+', 'buddyboss-tab-creator' ),
					'admins'        => __( 'Admins Only', 'buddyboss-tab-creator' ),
					'site_admin'    => __( 'Site Admin', 'buddyboss-tab-creator' ),
					default         => esc_html( $rule->rule_value ),
				};
			} elseif ( 'inherit' === $rule->rule_key ) {
				$parts[] = __( 'Inherited', 'buddyboss-tab-creator' );
			}
		}

		$parts = array_filter( $parts );

		if ( empty( $parts ) ) {
			return '<span style="color:#8892a0;">' . esc_html__( 'All', 'buddyboss-tab-creator' ) . '</span>';
		}

		return esc_html( implode( ', ', $parts ) );
	}
}
