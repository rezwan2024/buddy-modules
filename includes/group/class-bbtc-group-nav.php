<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers custom group tabs and sub-tabs on the BuddyBoss front end.
 * All methods are static.
 */
class BBTC_Group_Nav {

	/** @var array Cached top-level group tabs keyed by tab_type. */
	private static array $tabs_cache = [];

	/**
	 * Registers all hooks.
	 */
	public static function init(): void {
		add_action( 'bp_groups_setup_nav', [ self::class, 'register_tabs' ], 20 );
		add_action( 'bp_groups_setup_nav', [ self::class, 'register_subtabs' ], 30 );

		// apply_existing_overrides and inject_existing_group_subtabs must run on
		// `groups_setup_nav`, NOT `bp_groups_setup_nav`. In BuddyBoss Platform,
		// `bp_groups_setup_nav` fires inside parent::setup_nav() BEFORE the built-in
		// group page tabs (feed, members, activity, etc.) are registered. The built-in
		// tabs are added after that, and then `groups_setup_nav` fires. Running our
		// overrides on `bp_groups_setup_nav` means edit_nav() / delete_nav() target
		// nav items that don't exist yet and silently fail.
		add_action( 'groups_setup_nav', [ self::class, 'apply_existing_overrides' ], 10 );
		// bp_actions fires at bp_init priority 11, after bp_setup_nav (priority 6).
		// Third-party plugins (e.g. buddypress-group-tabs-creator-pro) register their
		// group nav at bp_actions priority 9, so we cache at priority 10 to capture them.
		add_action( 'bp_actions', [ self::class, 'cache_builtin_group_nav_slugs' ], 10 );
		add_action( 'groups_setup_nav', [ self::class, 'inject_existing_group_subtabs' ], 20 );

		// Inject our saved group tab order into BuddyBoss's appearance settings so
		// bp_nouveau_set_nav_item_order() (called during template rendering) applies it.
		// bp_parse_args() fires `bp_after_{filter_key}_parse_args` where the filter key
		// passed to bp_parse_args() is 'nouveau_appearance_settings'.
		add_filter( 'bp_after_nouveau_appearance_settings_parse_args', [ self::class, 'inject_group_nav_order' ] );
	}

	/**
	 * Injects the drag-and-drop saved group tab order into BuddyBoss's appearance
	 * settings. bp_nouveau_set_nav_item_order() reads group_nav_order from these
	 * settings during template rendering and applies sequential positions — it runs
	 * after bp_groups_setup_nav, so this filter is the correct integration point.
	 *
	 * @param mixed $value Raw option value from the DB.
	 * @return mixed Modified value with group_nav_order injected.
	 */
	public static function inject_group_nav_order( mixed $value ): mixed {
		$saved_order = get_option( 'bbtc_existing_group_tab_order', [] );
		if ( empty( $saved_order ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			$value = [];
		}

		$value['group_nav_order'] = $saved_order;

		return $value;
	}

	/**
	 * Reads BuddyBoss's built-in group nav slugs at groups_setup_nav priority 100
	 * (after all components including bbPress forums/discussions have registered).
	 * Excludes custom tabs created by this plugin. Stores in transients for admin use.
	 */
	public static function cache_builtin_group_nav_slugs(): void {
		if ( ! bp_is_group() ) {
			return;
		}

		$nav = buddypress()->groups->nav ?? null;
		if ( ! $nav || ! method_exists( $nav, 'get_secondary' ) ) {
			return;
		}

		$group = groups_get_current_group();
		if ( ! $group ) {
			return;
		}

		// Group tabs are secondary nav items keyed under the group's own slug.
		$secondary = $nav->get_secondary( [ 'parent_slug' => $group->slug ] );
		if ( ! is_array( $secondary ) || empty( $secondary ) ) {
			return;
		}

		// Get our own custom tab slugs so we can exclude them from the built-in list.
		$custom_tabs      = BBTC_DB::get_tabs( 'group', [ 'status' => 'active', 'parent_id' => 0 ] );
		$custom_tab_slugs = array_map( fn( $t ) => $t->slug, $custom_tabs );

		$slugs  = [];
		$labels = [];
		foreach ( $secondary as $item ) {
			$data       = is_object( $item ) ? (array) $item : (array) $item;
			$clean_slug = sanitize_key( (string) ( $data['slug'] ?? '' ) );
			if ( '' === $clean_slug ) {
				continue;
			}
			// Skip tabs registered by our own plugin.
			if ( in_array( $clean_slug, $custom_tab_slugs, true ) ) {
				continue;
			}
			$slugs[]               = $clean_slug;
			$labels[ $clean_slug ] = wp_strip_all_tags( (string) ( $data['name'] ?? $clean_slug ) );
		}

		if ( ! empty( $slugs ) ) {
			set_transient( 'bbtc_builtin_group_nav_v5', $slugs, DAY_IN_SECONDS );
			set_transient( 'bbtc_builtin_group_nav_labels', $labels, DAY_IN_SECONDS );
		}
	}

	/**
	 * Returns active top-level group tabs. Caches results for the request.
	 *
	 * @param string $tab_type 'profile' or 'group'.
	 * @return array
	 */
	public static function get_tabs( string $tab_type ): array {
		if ( isset( self::$tabs_cache[ $tab_type ] ) ) {
			return self::$tabs_cache[ $tab_type ];
		}

		$tabs = BBTC_DB::get_tabs( $tab_type, [ 'status' => 'active', 'parent_id' => 0 ] );
		self::$tabs_cache[ $tab_type ] = $tabs;

		return $tabs;
	}

	/**
	 * Registers top-level custom group tabs via bp_core_new_nav_item().
	 * Hooked to bp_groups_setup_nav at priority 20.
	 */
	public static function register_tabs(): void {
		if ( ! bp_is_group() ) {
			return;
		}

		$current_group   = groups_get_current_group();
		$current_user_id = get_current_user_id();
		$group_link      = bp_get_group_permalink( $current_group );

		$tabs = self::get_tabs( 'group' );

		foreach ( $tabs as $tab ) {
			if ( ! $tab->is_enabled ) {
				continue;
			}

			if ( $tab->site_admin_only && ! current_user_can( 'manage_options' ) ) {
				continue;
			}

			if ( ! BBTC_Group_Visibility::can_show_tab( (int) $tab->id, $current_group, $current_user_id ) ) {
				continue;
			}

			if ( ! BBTC_Group_Visibility::can_view_tab( (int) $tab->id, $current_group, $current_user_id ) ) {
				continue;
			}

			$label = apply_filters( 'bbtc_tab_label', $tab->label, (int) $tab->id );

			$tab_id = (int) $tab->id;

			$nav_args = [
				'name'            => $label,
				'slug'            => $tab->slug,
				'position'        => (int) $tab->position + 100,
				'parent_url'      => $group_link,
				'parent_slug'     => $current_group->slug,
				'screen_function' => '__return_false',
				'user_has_access' => true,
			];

			if ( ! empty( $tab->item_css_id ) ) {
				$nav_args['item_css_id'] = $tab->item_css_id;
			}

			if ( 'url' === $tab->content_source ) {
				// External URL tab — link directly.
				$link = ! empty( $tab->tab_link ) ? $tab->tab_link : $tab->external_url;
				$nav_args['link'] = esc_url( $link );
			} else {
				// Content tab — register a proper screen function.
				$nav_args['screen_function'] = function() use ( $tab_id ) {
					self::render_screen( $tab_id );
				};

				if ( ! empty( $tab->tab_link ) ) {
					$nav_args['link'] = esc_url( $tab->tab_link );
				}
			}

			bp_core_new_subnav_item( $nav_args );
		}
	}

	/**
	 * Sub-tabs of custom group tabs are rendered directly inside render_screen()
	 * by detecting bp_action_variable(0). BuddyBoss Platform does not dispatch
	 * to third-level nav items registered via bp_core_new_subnav_item() for
	 * custom group tabs — the parent tab's screen_function is always called for
	 * any URL under that tab slug. render_screen() handles sub-tab routing and
	 * rendering, so no nav registration is needed here.
	 *
	 * Sub-tabs of built-in group tabs are handled separately by
	 * inject_existing_group_subtabs().
	 *
	 * Hooked to bp_groups_setup_nav at priority 30 (kept for future compatibility).
	 */
	public static function register_subtabs(): void {
		// Intentionally empty — see render_screen() for sub-tab rendering logic.
	}

	/**
	 * Applies admin overrides to built-in BuddyBoss group tabs.
	 * Hooked to bp_groups_setup_nav at priority 50.
	 *
	 * Built-in group tabs (feed, members, photos, etc.) are registered as
	 * sub-nav items under the group's own primary nav item. To modify them
	 * we call edit_nav() / delete_nav() with the group's slug as the parent.
	 */
	public static function apply_existing_overrides(): void {
		if ( ! bp_is_group() ) {
			return;
		}

		$nav = buddypress()->groups->nav ?? null;
		if ( ! $nav ) {
			return;
		}

		$current_group   = groups_get_current_group();
		$current_user_id = get_current_user_id();
		$group_slug      = $current_group->slug;

		$overrides = BBTC_DB::get_all_overrides( 'group' );

		foreach ( $overrides as $override ) {
			$slug  = $override->original_slug;
			$rules = BBTC_DB::get_rules_for_override( (int) $override->id );

			// Partition rules by type.
			$avail_rules = array_filter( $rules, fn( $r ) => 'availability' === $r->rule_type );
			$vis_rules   = array_filter( $rules, fn( $r ) => 'visibility' === $r->rule_type );

			// --- Availability check ------------------------------------------
			if ( ! empty( $avail_rules ) ) {
				// Excluded groups always override — check first.
				foreach ( $avail_rules as $rule ) {
					if ( 'excluded_group_id' === $rule->rule_key && (int) $rule->rule_value === (int) $current_group->id ) {
						$nav->delete_nav( $slug, $group_slug );
						continue 2;
					}
				}

				$passes = false;
				foreach ( $avail_rules as $rule ) {
					if ( 'scope' === $rule->rule_key && 'all' === $rule->rule_value ) {
						$passes = true;
						break;
					}
					if ( 'scope' === $rule->rule_key && 'none' === $rule->rule_value ) {
						$passes = false;
						break;
					}
					if ( 'group_status' === $rule->rule_key && $rule->rule_value === ( $current_group->status ?? '' ) ) {
						$passes = true;
					}
					if ( 'group_id' === $rule->rule_key && (int) $rule->rule_value === (int) $current_group->id ) {
						$passes = true;
					}
				}

				if ( ! $passes ) {
					$nav->delete_nav( $slug, $group_slug );
					continue;
				}
			}

			// --- Visibility check --------------------------------------------
			if ( ! empty( $vis_rules ) ) {
				$can_view = false;
				foreach ( $vis_rules as $rule ) {
					if ( 'audience' !== $rule->rule_key ) {
						continue;
					}

					$val = $rule->rule_value;

					// These meta-values mean "do not restrict".
					if ( in_array( $val, [ 'do_not_modify', 'none_remove' ], true ) ) {
						$can_view = true;
						break;
					}

					if ( 'anyone' === $val ) {
						$can_view = true;
						break;
					}
					if ( 'logged_in' === $val && is_user_logged_in() ) {
						$can_view = true;
						break;
					}
					if ( 'members' === $val && $current_user_id > 0 && function_exists( 'groups_is_user_member' ) && groups_is_user_member( (int) $current_group->id, $current_user_id ) ) {
						$can_view = true;
						break;
					}
					if ( 'mods' === $val && $current_user_id > 0 && function_exists( 'groups_is_user_mod' ) && groups_is_user_mod( (int) $current_group->id, $current_user_id ) ) {
						$can_view = true;
						break;
					}
					if ( 'admins' === $val && $current_user_id > 0 && function_exists( 'groups_is_user_admin' ) && groups_is_user_admin( (int) $current_group->id, $current_user_id ) ) {
						$can_view = true;
						break;
					}
					if ( 'site_admin' === $val && current_user_can( 'manage_options' ) ) {
						$can_view = true;
						break;
					}
				}

				if ( ! $can_view ) {
					$nav->delete_nav( $slug, $group_slug );
					continue;
				}
			}

			// site_admin_only shortcut.
			if ( ! empty( $override->site_admin_only ) && ! current_user_can( 'manage_options' ) ) {
				$nav->delete_nav( $slug, $group_slug );
				continue;
			}

			// --- Hidden ------------------------------------------------------
			if ( ! empty( $override->is_hidden ) ) {
				$nav->delete_nav( $slug, $group_slug );
				continue;
			}

			// --- is_enabled = 0: hide the tab --------------------------------
			if ( isset( $override->is_enabled ) && ! (int) $override->is_enabled ) {
				$nav->delete_nav( $slug, $group_slug );
				continue;
			}

			// --- Label override ----------------------------------------------
			if ( ! empty( $override->label_override ) ) {
				$nav->edit_nav( [ 'name' => $override->label_override ], $slug, $group_slug );
			}

			// --- Position override -------------------------------------------
			if ( null !== $override->position_override ) {
				$nav->edit_nav( [ 'position' => (int) $override->position_override ], $slug, $group_slug );
			}

			// --- Tab link override -------------------------------------------
			if ( ! empty( $override->tab_link ) ) {
				$nav->edit_nav( [ 'link' => esc_url( $override->tab_link ) ], $slug, $group_slug );
			}

			// --- Item CSS ID override ----------------------------------------
			if ( ! empty( $override->item_css_id ) ) {
				$nav->edit_nav( [ 'css_id' => $override->item_css_id ], $slug, $group_slug );
			}

			// --- Default tab -------------------------------------------------
			if ( ! empty( $override->is_default ) ) {
				// Set the group's default sub-nav by editing the primary group nav item.
				$nav->edit_nav( [ 'default_subnav_slug' => $slug ], $group_slug );
			}
		}

	}

	/**
	 * Injects custom sub-tabs into existing built-in BuddyBoss group tabs.
	 * Hooked to bp_groups_setup_nav at priority 60. Experimental — fails silently.
	 */
	public static function inject_existing_group_subtabs(): void {
		if ( ! bp_is_group() ) {
			return;
		}

		$current_group   = groups_get_current_group();
		$current_user_id = get_current_user_id();
		$group_link      = bp_get_group_permalink( $current_group );

		// All active tabs with a parent_existing_slug (sub-tabs of built-in group tabs).
		$subtabs = BBTC_DB::get_tabs( 'group', [ 'status' => 'active' ] );
		$subtabs = array_filter(
			$subtabs,
			fn( $t ) => ! empty( $t->parent_existing_slug ) && 0 === (int) $t->parent_id
		);

		if ( empty( $subtabs ) ) {
			return;
		}

		$nav = buddypress()->groups->nav ?? null;
		if ( ! $nav || ! method_exists( $nav, 'get_secondary' ) ) {
			return;
		}

		foreach ( $subtabs as $subtab ) {
			if ( ! $subtab->is_enabled ) {
				continue;
			}

			if ( $subtab->site_admin_only && ! current_user_can( 'manage_options' ) ) {
				continue;
			}

			$parent_slug = $subtab->parent_existing_slug;

			// Safety check: verify parent nav item exists in group nav.
			$parent_nav = $nav->get_secondary( [ 'parent_slug' => $current_group->slug, 'slug' => $parent_slug ] );
			if ( empty( $parent_nav ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[BBTC] Group sub-tab injection failed: parent slug "' . $parent_slug . '" not found in group nav.' );
				}
				continue;
			}

			if ( ! BBTC_Group_Visibility::can_show_tab( (int) $subtab->id, $current_group, $current_user_id ) ) {
				continue;
			}

			if ( ! BBTC_Group_Visibility::can_view_tab( (int) $subtab->id, $current_group, $current_user_id ) ) {
				continue;
			}

			$label  = apply_filters( 'bbtc_tab_label', $subtab->label, (int) $subtab->id );
			$tab_id = (int) $subtab->id;

			$screen_function = function() use ( $tab_id ) {
				self::render_screen( $tab_id );
			};

			bp_core_new_subnav_item( [
				'name'            => $label,
				'slug'            => $subtab->slug,
				'parent_slug'     => $parent_slug,
				'parent_url'      => trailingslashit( $group_link . $parent_slug ),
				'position'        => (int) $subtab->position,
				'screen_function' => $screen_function,
			] );
		}
	}

	/**
	 * Renders a custom group tab's content on the front end.
	 * Called as the screen function for content-type tabs.
	 *
	 * If the tab has visible sub-tabs, this renders a BuddyBoss-styled sub-nav
	 * and the active sub-tab's content. Active sub-tab is detected via
	 * bp_action_variable(0); defaults to the first visible sub-tab.
	 *
	 * @param int $tab_id The tab ID.
	 */
	public static function render_screen( int $tab_id ): void {
		add_action(
			'bp_template_content',
			function() use ( $tab_id ): void {
				$tab = BBTC_DB::get_tab( $tab_id );

				if ( ! $tab ) {
					return;
				}

				$current_group   = groups_get_current_group();
				$current_user_id = get_current_user_id();

				// Query active sub-tabs of this custom group tab.
				$all_subtabs = BBTC_DB::get_tabs( 'group', [
					'status'    => 'active',
					'parent_id' => $tab_id,
					'orderby'   => 'position',
					'order'     => 'ASC',
				] );

				// Filter by is_enabled and visibility rules.
				$visible_subtabs = [];
				foreach ( $all_subtabs as $st ) {
					if ( ! $st->is_enabled ) {
						continue;
					}
					if ( $st->site_admin_only && ! current_user_can( 'manage_options' ) ) {
						continue;
					}
					if ( ! BBTC_Group_Visibility::can_show_tab( (int) $st->id, $current_group, $current_user_id ) ) {
						continue;
					}
					if ( ! BBTC_Group_Visibility::can_view_tab( (int) $st->id, $current_group, $current_user_id ) ) {
						continue;
					}
					$visible_subtabs[] = $st;
				}

				if ( ! empty( $visible_subtabs ) ) {
					$group_link          = bp_get_group_permalink( $current_group );
					$parent_slug         = $tab->slug;
					$current_subtab_slug = bp_action_variable( 0 );

					// Find the active sub-tab only if a slug is present in the URL.
					$active_subtab = null;
					if ( ! empty( $current_subtab_slug ) ) {
						foreach ( $visible_subtabs as $st ) {
							if ( $st->slug === $current_subtab_slug ) {
								$active_subtab = $st;
								break;
							}
						}
					}

					// Render sub-nav using BuddyBoss nav markup.
					echo '<nav class="bp-navs group-subnav" id="bbtc-group-subnav" aria-label="' . esc_attr__( 'Group sub-navigation', 'buddyboss-tab-creator' ) . '">';
					echo '<ul>';
					foreach ( $visible_subtabs as $st ) {
						$is_current = ( $active_subtab && (int) $st->id === (int) $active_subtab->id );
						$subtab_url = trailingslashit( $group_link . $parent_slug . '/' . $st->slug );
						$label      = apply_filters( 'bbtc_tab_label', $st->label, (int) $st->id );
						printf(
							'<li id="nav-%s"%s><a href="%s">%s</a></li>',
							esc_attr( $st->slug ),
							$is_current ? ' class="current"' : '',
							esc_url( $subtab_url ),
							esc_html( $label )
						);
					}
					echo '</ul>';
					echo '</nav>';

					if ( $active_subtab ) {
						// A sub-tab slug was in the URL — render that sub-tab's content.
						$content = apply_filters( 'bbtc_tab_content', $active_subtab->content, (int) $active_subtab->id );
						echo wp_kses_post( do_shortcode( $content ) );
					} else {
						// No sub-tab slug in URL — show the main tab's own content.
						$content = apply_filters( 'bbtc_tab_content', $tab->content, $tab_id );
						echo wp_kses_post( do_shortcode( $content ) );
					}

					return;
				}

				// No sub-tabs: render the tab's own content.
				$content = apply_filters( 'bbtc_tab_content', $tab->content, $tab_id );
				echo wp_kses_post( do_shortcode( $content ) );
			}
		);

		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'groups/single/plugins' ) );
	}
}
