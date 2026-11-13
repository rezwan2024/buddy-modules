<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers custom profile tabs and sub-tabs on the BuddyBoss front end.
 * All methods are static.
 */
class BBTC_Profile_Nav {

	/** @var array Cached top-level profile tabs keyed by tab_type. */
	private static array $tabs_cache = [];

	/**
	 * Registers all hooks.
	 */
	public static function init(): void {
		// Priority 15: after BuddyBoss registers its built-in tabs (priority 10),
		// but before we add our own (priority 20). Captures exactly what BB registered.
		add_action( 'bp_setup_nav', [ self::class, 'cache_builtin_nav_slugs' ], 15 );
		add_action( 'bp_setup_nav', [ self::class, 'register_tabs' ], 20 );
		add_action( 'bp_setup_nav', [ self::class, 'register_subtabs' ], 30 );
		add_action( 'bp_setup_nav', [ self::class, 'apply_existing_overrides' ], 50 );
	}

	/**
	 * Reads BuddyBoss's built-in profile nav slugs at priority 15
	 * (after BB registers them at ~10, before we add ours at 20).
	 * Only keeps tabs where show_for_displayed_user !== false so that
	 * owner-only tabs (Notifications, Messages, Settings) are excluded.
	 */
	public static function cache_builtin_nav_slugs(): void {
		if ( ! bp_is_user() ) {
			return;
		}

		$nav = buddypress()->members->nav ?? null;
		if ( ! $nav || ! method_exists( $nav, 'get_primary' ) ) {
			return;
		}

		$primary = $nav->get_primary( [ 'user_id' => bp_displayed_user_id() ] );
		if ( ! is_array( $primary ) || empty( $primary ) ) {
			return;
		}

		$slugs  = [];
		$labels = [];
		foreach ( $primary as $slug => $item ) {
			$data = is_object( $item ) ? (array) $item : (array) $item;
			// Exclude tabs that only appear on the viewer's own profile.
			$show = $data['show_for_displayed_user'] ?? true;
			if ( false === $show || '0' === (string) $show || 0 === $show ) {
				continue;
			}
			$clean_slug        = sanitize_key( (string) $slug );
			$slugs[]           = $clean_slug;
			$labels[ $clean_slug ] = wp_strip_all_tags( (string) ( $data['name'] ?? $clean_slug ) );
		}

		if ( ! empty( $slugs ) ) {
			// Key includes a version suffix — incrementing it busts stale caches.
			set_transient( 'bbtc_builtin_profile_nav_v2', $slugs, DAY_IN_SECONDS );
			set_transient( 'bbtc_builtin_profile_nav_labels', $labels, DAY_IN_SECONDS );
		}
	}

	/**
	 * Returns a slug => label map for built-in BuddyBoss profile nav items.
	 * Uses the transient populated by cache_builtin_nav_slugs() on front end.
	 * Falls back to ucwords(slug) if the transient is not yet populated.
	 *
	 * @return array<string,string>
	 */
	public static function get_builtin_profile_nav_labels(): array {
		$cached = get_transient( 'bbtc_builtin_profile_nav_labels' );
		if ( ! empty( $cached ) && is_array( $cached ) ) {
			return $cached;
		}
		return [];
	}

	/**
	 * Returns active top-level profile tabs. Caches results for the request.
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
	 * Registers top-level custom profile tabs via bp_core_new_nav_item().
	 * Hooked to bp_setup_nav at priority 20.
	 */
	public static function register_tabs(): void {
		if ( ! bp_is_user() ) {
			return;
		}

		$displayed_user_id = bp_displayed_user_id();
		$current_user_id   = get_current_user_id();

		$tabs = self::get_tabs( 'profile' );

		foreach ( $tabs as $tab ) {
			if ( ! $tab->is_enabled ) {
				continue;
			}

			if ( $tab->site_admin_only && ! current_user_can( 'manage_options' ) ) {
				continue;
			}

			if ( ! BBTC_Profile_Visibility::can_use_tab( (int) $tab->id, $displayed_user_id ) ) {
				continue;
			}

			if ( ! BBTC_Profile_Visibility::can_view_tab( (int) $tab->id, $displayed_user_id, $current_user_id ) ) {
				continue;
			}

			$label = apply_filters( 'bbtc_tab_label', $tab->label, (int) $tab->id );

			// Offset by 100 so custom tabs always appear after built-in BuddyBoss tabs
			// (which occupy positions 10–100). Admin-set position controls relative
			// ordering among custom tabs only.
			$nav_args = [
				'name'            => $label,
				'slug'            => $tab->slug,
				'position'        => (int) $tab->position + 100,
				'screen_function' => '__return_false',
				'default_subnav_slug' => $tab->default_subnav_slug ?: false,
			];

			if ( ! empty( $tab->item_css_id ) ) {
				$nav_args['item_css_id'] = $tab->item_css_id;
			}

			if ( 'url' === $tab->content_source ) {
				// External URL tab — link directly, no screen function needed.
				$link = ! empty( $tab->tab_link ) ? $tab->tab_link : $tab->external_url;
				$nav_args['link']            = esc_url( $link );
				$nav_args['screen_function'] = '__return_false';
			} else {
				// Content tab — register a proper screen function.
				$tab_id = (int) $tab->id;
				$nav_args['screen_function'] = function() use ( $tab_id ) {
					self::render_screen( $tab_id );
				};

				if ( ! empty( $tab->tab_link ) ) {
					$nav_args['link'] = esc_url( $tab->tab_link );
				}
			}

			bp_core_new_nav_item( $nav_args );
		}
	}

	/**
	 * Registers custom sub-tabs under their parent tabs.
	 * Hooked to bp_setup_nav at priority 30.
	 */
	public static function register_subtabs(): void {
		if ( ! bp_is_user() ) {
			return;
		}

		$displayed_user_id = bp_displayed_user_id();
		$current_user_id   = get_current_user_id();
		$user_domain       = bp_displayed_user_domain();

		// Load all active sub-tabs for profile type.
		$subtabs = BBTC_DB::get_tabs( 'profile', [ 'status' => 'active' ] );
		$subtabs = array_filter( $subtabs, fn( $t ) => (int) $t->parent_id > 0 || ! empty( $t->parent_existing_slug ) );

		foreach ( $subtabs as $subtab ) {
			if ( ! $subtab->is_enabled ) {
				continue;
			}

			if ( $subtab->site_admin_only && ! current_user_can( 'manage_options' ) ) {
				continue;
			}

			// Resolve parent slug.
			if ( ! empty( $subtab->parent_existing_slug ) ) {
				$parent_slug = $subtab->parent_existing_slug;
			} elseif ( (int) $subtab->parent_id > 0 ) {
				$parent = BBTC_DB::get_tab( (int) $subtab->parent_id );
				if ( ! $parent ) {
					continue;
				}
				$parent_slug = $parent->slug;
			} else {
				continue;
			}

			// Determine effective availability/visibility rules (inherit or own).
			$rules       = BBTC_DB::get_rules_for_tab( (int) $subtab->id );
			$has_inherit = count( array_filter( $rules, fn( $r ) => 'inherit' === $r->rule_key ) ) > 0;

			if ( $has_inherit && (int) $subtab->parent_id > 0 ) {
				if ( ! BBTC_Profile_Visibility::can_use_tab( (int) $subtab->parent_id, $displayed_user_id ) ) {
					continue;
				}
				if ( ! BBTC_Profile_Visibility::can_view_tab( (int) $subtab->parent_id, $displayed_user_id, $current_user_id ) ) {
					continue;
				}
			} else {
				if ( ! BBTC_Profile_Visibility::can_use_tab( (int) $subtab->id, $displayed_user_id ) ) {
					continue;
				}
				if ( ! BBTC_Profile_Visibility::can_view_tab( (int) $subtab->id, $displayed_user_id, $current_user_id ) ) {
					continue;
				}
			}

			$label  = apply_filters( 'bbtc_tab_label', $subtab->label, (int) $subtab->id );
			$tab_id = (int) $subtab->id;

			$screen_function = '__return_false';

			if ( 'url' !== $subtab->content_source ) {
				$screen_function = function() use ( $tab_id ) {
					self::render_screen( $tab_id );
				};
			}

			$subnav_args = [
				'name'            => $label,
				'slug'            => $subtab->slug,
				'parent_slug'     => $parent_slug,
				'parent_url'      => trailingslashit( $user_domain . $parent_slug ),
				'position'        => (int) $subtab->position,
				'screen_function' => $screen_function,
			];

			if ( 'url' === $subtab->content_source ) {
				$link = ! empty( $subtab->tab_link ) ? $subtab->tab_link : $subtab->external_url;
				$subnav_args['link'] = esc_url( $link );
			} elseif ( ! empty( $subtab->tab_link ) ) {
				$subnav_args['link'] = esc_url( $subtab->tab_link );
			}

			if ( ! empty( $subtab->item_css_id ) ) {
				$subnav_args['item_css_id'] = $subtab->item_css_id;
			}

			bp_core_new_subnav_item( $subnav_args );
		}
	}

	/**
	 * Applies admin overrides to built-in BuddyBoss profile tabs.
	 * Hooked to bp_setup_nav at priority 50.
	 *
	 * Handles: label overrides, position overrides, hidden tabs, visibility /
	 * availability restrictions, and sub-tabs added to existing built-in tabs.
	 */
	public static function apply_existing_overrides(): void {
		if ( ! bp_is_user() ) {
			return;
		}

		$nav = buddypress()->members->nav ?? null;
		if ( ! $nav ) {
			return;
		}

		$displayed_user_id = bp_displayed_user_id();
		$current_user_id   = get_current_user_id();
		$user_domain       = bp_displayed_user_domain();

		$overrides = BBTC_DB::get_all_overrides( 'profile' );

		foreach ( $overrides as $override ) {
			$slug  = $override->original_slug;
			$rules = BBTC_DB::get_rules_for_override( (int) $override->id );

			// --- Availability check ------------------------------------------
			// Partition rules by type.
			$avail_rules = array_filter( $rules, fn( $r ) => 'availability' === $r->rule_type );
			$vis_rules   = array_filter( $rules, fn( $r ) => 'visibility' === $r->rule_type );

			if ( ! empty( $avail_rules ) ) {
				$passes = false;
				foreach ( $avail_rules as $rule ) {
					if ( 'scope' === $rule->rule_key && 'all_members' === $rule->rule_value ) {
						$passes = true;
						break;
					}
					if ( 'scope' === $rule->rule_key && 'none' === $rule->rule_value ) {
						$passes = false;
						break;
					}
					if ( 'role' === $rule->rule_key && user_can( $displayed_user_id, $rule->rule_value ) ) {
						$passes = true;
					}
					if ( 'user_id' === $rule->rule_key && (int) $rule->rule_value === $displayed_user_id ) {
						$passes = true;
					}
				}
				if ( ! $passes ) {
					$nav->delete_nav( $slug );
					continue;
				}
			}

			// --- Visibility check --------------------------------------------
			if ( ! empty( $vis_rules ) ) {
				$can_view = false;
				foreach ( $vis_rules as $rule ) {
					$key = $rule->rule_key;
					$val = $rule->rule_value;

					// Skip unsupported rule keys.
					if ( ! in_array( $key, [ 'audience', 'role' ], true ) ) {
						continue;
					}

					// Admin-only meta-values mean "do not restrict".
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
					if ( 'owner_only' === $val && $current_user_id === $displayed_user_id ) {
						$can_view = true;
						break;
					}
					if ( 'site_admin' === $val && current_user_can( 'manage_options' ) ) {
						$can_view = true;
						break;
					}
					if ( 'friends' === $val && function_exists( 'friends_check_friendship' ) && friends_check_friendship( $displayed_user_id, $current_user_id ) ) {
						$can_view = true;
						break;
					}
					// Role-based visibility rule (rule_key = 'role', rule_value = role slug).
					if ( 'role' === $key && user_can( $current_user_id, $val ) ) {
						$can_view = true;
						break;
					}
				}
				if ( ! $can_view ) {
					$nav->delete_nav( $slug );
					continue;
				}
			}

			// site_admin_only shortcut.
			if ( ! empty( $override->site_admin_only ) && ! current_user_can( 'manage_options' ) ) {
				$nav->delete_nav( $slug );
				continue;
			}

			// --- Hidden ------------------------------------------------------
			if ( ! empty( $override->is_hidden ) ) {
				$nav->delete_nav( $slug );
				continue;
			}

			// --- is_enabled = 0: hide the tab --------------------------------
			if ( isset( $override->is_enabled ) && ! (int) $override->is_enabled ) {
				$nav->delete_nav( $slug );
				continue;
			}

			// --- Label override ----------------------------------------------
			if ( ! empty( $override->label_override ) ) {
				$nav->edit_nav( [ 'name' => $override->label_override ], $slug );
			}

			// --- Tab link override -------------------------------------------
			if ( ! empty( $override->tab_link ) ) {
				$nav->edit_nav( [ 'link' => esc_url( $override->tab_link ) ], $slug );
			}

			// --- Item CSS ID override ----------------------------------------
			if ( ! empty( $override->item_css_id ) ) {
				$nav->edit_nav( [ 'css_id' => $override->item_css_id ], $slug );
			}
		}

		// --- Sub-tabs added to existing built-in profile tabs ----------------
		// These are rows in bbtc_tabs with parent_existing_slug set (parent_id = 0).
		$existing_subtabs = BBTC_DB::get_tabs( 'profile', [ 'status' => 'active' ] );
		$existing_subtabs = array_filter(
			$existing_subtabs,
			fn( $t ) => ! empty( $t->parent_existing_slug ) && 0 === (int) $t->parent_id
		);

		foreach ( $existing_subtabs as $subtab ) {
			if ( ! $subtab->is_enabled ) {
				continue;
			}

			if ( $subtab->site_admin_only && ! current_user_can( 'manage_options' ) ) {
				continue;
			}

			if ( ! BBTC_Profile_Visibility::can_use_tab( (int) $subtab->id, $displayed_user_id ) ) {
				continue;
			}

			if ( ! BBTC_Profile_Visibility::can_view_tab( (int) $subtab->id, $displayed_user_id, $current_user_id ) ) {
				continue;
			}

			$parent_slug     = $subtab->parent_existing_slug;
			$label           = apply_filters( 'bbtc_tab_label', $subtab->label, (int) $subtab->id );
			$tab_id          = (int) $subtab->id;
			$screen_function = '__return_false';

			if ( 'url' !== $subtab->content_source ) {
				$screen_function = function() use ( $tab_id ) {
					self::render_screen( $tab_id );
				};
			}

			$subnav_args = [
				'name'            => $label,
				'slug'            => $subtab->slug,
				'parent_slug'     => $parent_slug,
				'parent_url'      => trailingslashit( $user_domain . $parent_slug ),
				'position'        => (int) $subtab->position,
				'screen_function' => $screen_function,
			];

			if ( 'url' === $subtab->content_source ) {
				$link = ! empty( $subtab->tab_link ) ? $subtab->tab_link : $subtab->external_url;
				$subnav_args['link'] = esc_url( $link );
			} elseif ( ! empty( $subtab->tab_link ) ) {
				$subnav_args['link'] = esc_url( $subtab->tab_link );
			}

			if ( ! empty( $subtab->item_css_id ) ) {
				$subnav_args['item_css_id'] = $subtab->item_css_id;
			}

			bp_core_new_subnav_item( $subnav_args );
		}
	}

	/**
	 * Renders a custom tab's content on the front end.
	 * Called as the screen function for content-type tabs.
	 *
	 * If the tab has visible sub-tabs, renders a sub-nav and routes to the correct
	 * content via bp_action_variable(0). BuddyBoss Platform always calls the parent
	 * tab's screen function for any URL under that tab slug, so sub-tab routing is
	 * handled here rather than relying on bp_core_new_subnav_item() dispatch.
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

				$displayed_user_id = bp_displayed_user_id();
				$current_user_id   = get_current_user_id();

				// Query active sub-tabs of this custom profile tab.
				$all_subtabs = BBTC_DB::get_tabs( 'profile', [
					'status'    => 'active',
					'parent_id' => $tab_id,
					'orderby'   => 'position',
					'order'     => 'ASC',
				] );

				// Filter by is_enabled and visibility/availability rules.
				$visible_subtabs = [];
				foreach ( $all_subtabs as $st ) {
					if ( ! $st->is_enabled ) {
						continue;
					}
					if ( $st->site_admin_only && ! current_user_can( 'manage_options' ) ) {
						continue;
					}

					$rules       = BBTC_DB::get_rules_for_tab( (int) $st->id );
					$has_inherit = ! empty( array_filter( $rules, fn( $r ) => 'inherit' === $r->rule_key ) );

					if ( $has_inherit ) {
						// Inherit: parent is already accessible (we're inside its render_screen),
						// so this sub-tab is accessible too.
					} else {
						if ( ! BBTC_Profile_Visibility::can_use_tab( (int) $st->id, $displayed_user_id ) ) {
							continue;
						}
						if ( ! BBTC_Profile_Visibility::can_view_tab( (int) $st->id, $displayed_user_id, $current_user_id ) ) {
							continue;
						}
					}

					$visible_subtabs[] = $st;
				}

				// BuddyBoss renders the secondary nav automatically from bp_core_new_subnav_item()
				// registrations. Only handle content routing here — do not output a manual sub-nav.
				if ( ! empty( $visible_subtabs ) ) {
					$current_subtab_slug = bp_action_variable( 0 );

					$active_subtab = null;
					if ( ! empty( $current_subtab_slug ) ) {
						foreach ( $visible_subtabs as $st ) {
							if ( $st->slug === $current_subtab_slug ) {
								$active_subtab = $st;
								break;
							}
						}
					}

					if ( $active_subtab ) {
						// Sub-tab slug in URL — render that sub-tab's content.
						$content = apply_filters( 'bbtc_tab_content', $active_subtab->content, (int) $active_subtab->id );
						echo wp_kses_post( do_shortcode( $content ) );
					} else {
						// No sub-tab slug in URL — show the parent tab's own content.
						$content = apply_filters( 'bbtc_tab_content', $tab->content, $tab_id );
						echo wp_kses_post( do_shortcode( $content ) );
					}

					return;
				}

				// No sub-tabs: render the tab's own content directly.
				$content = apply_filters( 'bbtc_tab_content', $tab->content, $tab_id );
				echo wp_kses_post( do_shortcode( $content ) );
			}
		);

		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
	}
}
