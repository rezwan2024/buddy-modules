// BuddyBoss Tab Creator — admin JS
/* global bbtcData */

document.addEventListener( 'DOMContentLoaded', function () {

	// ── Slug auto-generation (parent tab form) ────────────────────────────
	// Only runs on the add form (data-is-new="1").
	const tabForm   = document.getElementById( 'bbtc-tab-form' );
	const labelField = document.getElementById( 'bbtc-label' );
	const slugField  = document.getElementById( 'bbtc-slug' );

	function slugify( str ) {
		return str
			.toLowerCase()
			.replace( /[^a-z0-9\s-]/g, '' )
			.replace( /[\s]+/g, '-' )
			.replace( /-+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	if ( labelField && slugField && tabForm && tabForm.dataset.isNew === '1' ) {
		labelField.addEventListener( 'input', function () {
			slugField.value = slugify( labelField.value );
		} );
	}

	// ── Content source toggle ─────────────────────────────────────────────
	const contentEditorWrap = document.querySelector( '.bbtc-content-editor-wrap' );
	const urlWrap           = document.querySelector( '.bbtc-url-wrap' );
	const sourceRadios      = document.querySelectorAll( 'input[name="content_source"]' );

	function applyContentSourceToggle( value ) {
		if ( ! contentEditorWrap || ! urlWrap ) {
			return;
		}
		if ( 'url' === value ) {
			contentEditorWrap.style.display = 'none';
			urlWrap.style.display           = '';
		} else {
			contentEditorWrap.style.display = '';
			urlWrap.style.display           = 'none';
		}
	}

	if ( sourceRadios.length ) {
		// Run on page load.
		let currentSource = 'content';
		sourceRadios.forEach( function ( radio ) {
			if ( radio.checked ) {
				currentSource = radio.value;
			}
		} );
		applyContentSourceToggle( currentSource );

		sourceRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				applyContentSourceToggle( this.value );
			} );
		} );
	}

	// ── Select / Deselect All (event delegation — covers static + dynamic) ─
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '[data-select-all]' );
		if ( ! btn ) {
			return;
		}
		const groupId = btn.dataset.selectAll;
		const group   = document.getElementById( groupId );
		if ( ! group ) {
			return;
		}
		const checkboxes = group.querySelectorAll( 'input[type="checkbox"]' );
		const allChecked = Array.from( checkboxes ).every( function ( cb ) {
			return cb.checked;
		} );
		checkboxes.forEach( function ( cb ) {
			if ( ! cb.disabled ) {
				cb.checked = ! allChecked;
			}
		} );
	} );

	// ── Site admins only shortcut ─────────────────────────────────────────
	const siteAdminOnly = document.getElementById( 'bbtc-site-admin-only' );
	const availGroup    = document.getElementById( 'bbtc-avail-group' );
	const visGroup      = document.getElementById( 'bbtc-vis-group' );

	function applySiteAdminLock( locked ) {
		if ( availGroup ) {
			availGroup.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
				if ( locked ) {
					cb.disabled = true;
					cb.checked  = ( cb.value === 'administrator' );
				} else {
					cb.disabled = false;
				}
			} );
		}
		if ( visGroup ) {
			visGroup.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
				if ( locked ) {
					cb.disabled = true;
					cb.checked  = ( cb.value === 'site_admin' );
				} else {
					cb.disabled = false;
				}
			} );
		}
	}

	if ( siteAdminOnly ) {
		if ( siteAdminOnly.checked ) {
			applySiteAdminLock( true );
		}
		siteAdminOnly.addEventListener( 'change', function () {
			applySiteAdminLock( this.checked );
		} );
	}

	// ── Conditional section toggle (availability / visibility override) ──
	// Toggles .bbtc-conditional-section shown/hidden when checkbox with
	// class .bbtc-override-toggle is changed. Uses data-target attribute
	// to identify the section to toggle.
	document.addEventListener( 'change', function ( e ) {
		const cb = e.target;
		if ( ! cb.classList.contains( 'bbtc-override-toggle' ) ) {
			return;
		}
		const targetId = cb.dataset.target;
		if ( ! targetId ) {
			return;
		}
		const section = document.getElementById( targetId );
		if ( section ) {
			section.classList.toggle( 'bbtc-visible', cb.checked );
		}
	} );

	// ── Standalone subtab: label → slug auto-generation ──────────────────
	const standaloneForm      = document.getElementById( 'bbtc-subtab-form' );
	const standaloneLabelField = document.querySelector( '.bbtc-standalone-subtab-label' );
	const standaloneSlugField  = document.querySelector( '.bbtc-standalone-subtab-slug' );

	if ( standaloneForm && standaloneLabelField && standaloneSlugField && standaloneForm.dataset.isNew === '1' ) {
		standaloneLabelField.addEventListener( 'input', function () {
			standaloneSlugField.value = slugify( standaloneLabelField.value );
		} );
	}

	// ── Delete confirmation ───────────────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		const link = e.target.closest( '.bbtc-delete-link, .bbtc-confirm-delete' );
		if ( ! link ) {
			return;
		}
		const msg = ( typeof bbtcData !== 'undefined' && bbtcData.confirmDelete )
			? bbtcData.confirmDelete
			: 'Are you sure you want to delete this item?';
		if ( ! window.confirm( msg ) ) {
			e.preventDefault();
		}
	} );

	// ── Bulk delete confirmation ──────────────────────────────────────────
	document.querySelectorAll( '.wp-list-table' ).forEach( function ( table ) {
		const form = table.closest( 'form' );
		if ( ! form ) {
			return;
		}
		form.addEventListener( 'submit', function ( e ) {
			const actionSelect = form.querySelector( 'select[name="action"], select[name="action2"]' );
			if ( ! actionSelect || actionSelect.value !== 'delete' ) {
				return;
			}
			const checked = form.querySelectorAll( 'input[type="checkbox"]:checked' );
			if ( checked.length === 0 ) {
				return;
			}
			const msg = ( typeof bbtcData !== 'undefined' && bbtcData.confirmBulkDelete )
				? bbtcData.confirmBulkDelete
				: 'Are you sure you want to delete the selected items?';
			if ( ! window.confirm( msg ) ) {
				e.preventDefault();
			}
		} );
	} );

	// ── Drag-and-drop row reordering ─────────────────────────────────────
	// Generic helper — works for any tbody whose rows carry a data attribute.
	// opts.tbody      — HTMLElement (tbody)
	// opts.rowSelector — CSS selector for draggable rows, e.g. 'tr[data-tab-id]'
	// opts.getValue   — function(row) → string value to post
	// opts.paramName  — POST array param name, e.g. 'ordered_ids[]'
	// opts.action     — wp_ajax action name
	// opts.nonce      — nonce string
	function initDragSortTable( opts ) {
		const tbody = opts.tbody;
		if ( ! tbody || ! tbody.querySelector( opts.rowSelector ) ) {
			return;
		}

		let dragSrc = null;

		tbody.querySelectorAll( opts.rowSelector ).forEach( function ( row ) {
			row.setAttribute( 'draggable', 'true' );

			row.addEventListener( 'dragstart', function ( e ) {
				dragSrc = row;
				row.classList.add( 'bbtc-dragging' );
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData( 'text/plain', opts.getValue( row ) );
			} );

			row.addEventListener( 'dragend', function () {
				dragSrc = null;
				tbody.querySelectorAll( 'tr' ).forEach( function ( r ) {
					r.classList.remove( 'bbtc-dragging', 'bbtc-drag-over' );
				} );
				saveOrder();
			} );

			row.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				if ( dragSrc && row !== dragSrc ) {
					tbody.querySelectorAll( 'tr' ).forEach( function ( r ) {
						r.classList.remove( 'bbtc-drag-over' );
					} );
					row.classList.add( 'bbtc-drag-over' );
				}
			} );

			row.addEventListener( 'dragleave', function () {
				row.classList.remove( 'bbtc-drag-over' );
			} );

			row.addEventListener( 'drop', function ( e ) {
				e.stopPropagation();
				e.preventDefault();
				if ( ! dragSrc || dragSrc === row ) {
					return;
				}
				tbody.insertBefore( dragSrc, row );
				row.classList.remove( 'bbtc-drag-over' );
			} );
		} );

		function saveOrder() {
			const ajaxurl = ( typeof bbtcData !== 'undefined' ) ? bbtcData.ajaxurl : '';
			if ( ! opts.nonce || ! ajaxurl ) {
				return;
			}

			const rows   = tbody.querySelectorAll( opts.rowSelector );
			const values = Array.from( rows ).map( opts.getValue );
			if ( values.length === 0 ) {
				return;
			}

			const body = new URLSearchParams();
			body.append( 'action', opts.action );
			body.append( '_ajax_nonce', opts.nonce );
			values.forEach( function ( v ) {
				body.append( opts.paramName, v );
			} );

			fetch( ajaxurl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    body.toString(),
			} ).catch( function () {
				// Fail silently — order resets on next page load.
			} );
		}
	}

	// Init: custom profile/group tabs list table.
	const customTableBody = document.querySelector( '.wp-list-table tbody' );
	if ( customTableBody ) {
		const urlParams      = new URLSearchParams( window.location.search );
		const currentTab     = urlParams.get( 'tab' ) || 'profile';
		const isGroupTab     = ( currentTab === 'group' );
		const reorderAction  = isGroupTab ? 'bbtc_reorder_group_tabs' : 'bbtc_reorder_profile_tabs';
		const reorderNonce   = ( typeof bbtcData !== 'undefined' )
			? ( isGroupTab ? bbtcData.reorderGroupNonce : bbtcData.reorderNonce )
			: '';

		initDragSortTable( {
			tbody:       customTableBody,
			rowSelector: 'tr[data-tab-id]',
			getValue:    function ( row ) { return row.dataset.tabId; },
			paramName:   'ordered_ids[]',
			action:      reorderAction,
			nonce:       reorderNonce,
		} );
	}

	// Init: existing (built-in) tabs table (profile or group depending on active tab).
	const existingTableBody = document.querySelector( '.bbtc-existing-table tbody' );
	if ( existingTableBody ) {
		const urlParamsEx        = new URLSearchParams( window.location.search );
		const currentTabEx       = urlParamsEx.get( 'tab' ) || 'profile';
		const isGroupTabEx       = ( currentTabEx === 'group' );
		const reorderExAction    = isGroupTabEx ? 'bbtc_reorder_existing_group_tabs' : 'bbtc_reorder_existing_profile_tabs';
		const reorderExNonce     = ( typeof bbtcData !== 'undefined' )
			? ( isGroupTabEx ? bbtcData.reorderExistingGroupNonce : bbtcData.reorderExistingNonce )
			: '';

		initDragSortTable( {
			tbody:       existingTableBody,
			rowSelector: 'tr[data-slug]',
			getValue:    function ( row ) { return row.dataset.slug; },
			paramName:   'ordered_slugs[]',
			action:      reorderExAction,
			nonce:       reorderExNonce,
		} );
	}

	// ── Collapsible postboxes ─────────────────────────────────────────────
	// Makes every .postbox inside .bbtc-form-main collapsible by clicking the
	// .postbox-header. A chevron button is injected into each header. All
	// postboxes start closed except the first one (Tab Settings / the primary
	// settings panel that contains required fields).
	( function () {
		const formMain = document.querySelector( '.bbtc-form-main' );
		if ( ! formMain ) {
			return;
		}

		const boxes = formMain.querySelectorAll( '.postbox' );
		if ( ! boxes.length ) {
			return;
		}

		boxes.forEach( function ( box, idx ) {
			const header = box.querySelector( '.postbox-header' );
			const inside = box.querySelector( '.inside' );
			if ( ! header || ! inside ) {
				return;
			}

			// Inject chevron button.
			const chevron = document.createElement( 'button' );
			chevron.type      = 'button';
			chevron.className = 'bbtc-postbox-toggle';
			chevron.setAttribute( 'aria-expanded', idx === 0 ? 'true' : 'false' );
			chevron.innerHTML = '<span class="bbtc-postbox-chevron" aria-hidden="true">▾</span>';
			header.appendChild( chevron );

			// Mark header as clickable.
			header.classList.add( 'bbtc-postbox-header-collapsible' );

			// Close all except the first box.
			if ( idx !== 0 ) {
				box.classList.add( 'bbtc-postbox-closed' );
			}

			// Toggle on header click (button or anywhere in header).
			header.addEventListener( 'click', function ( e ) {
				// Ignore clicks inside the header that are not on the header itself
				// or the toggle button (e.g. links inside a heading).
				if ( e.target.closest( 'a' ) ) {
					return;
				}
				const isClosed = box.classList.toggle( 'bbtc-postbox-closed' );
				chevron.setAttribute( 'aria-expanded', isClosed ? 'false' : 'true' );
			} );
		} );
	} )();

	// ── Sub-nav accordion drag-and-drop reorder ──────────────────────────
	// Initialises drag-and-drop on a given accordion container div.
	// Drag is only activated when the user presses the ⠿ handle — this is
	// achieved by setting draggable="true" on pointerdown on the handle and
	// removing it on pointerup/dragend, so regular header clicks still toggle
	// the accordion without triggering a drag.
	function initAccordionDragSort( container ) {
		if ( ! container ) {
			return;
		}

		let dragSrc = null;

		// Enable dragging only when the pointer goes down on the handle.
		container.addEventListener( 'pointerdown', function ( e ) {
			const handle = e.target.closest( '.bbtc-accordion-drag-handle' );
			if ( handle ) {
				const item = handle.closest( '.bbtc-accordion-item' );
				if ( item ) {
					item.setAttribute( 'draggable', 'true' );
				}
			}
		} );

		// Remove draggable when pointer lifts (covers click-without-drag).
		container.addEventListener( 'pointerup', function () {
			container.querySelectorAll( '.bbtc-accordion-item[draggable="true"]' ).forEach( function ( el ) {
				el.removeAttribute( 'draggable' );
			} );
		} );

		function onDragStart( e ) {
			dragSrc = e.currentTarget;
			dragSrc.classList.add( 'bbtc-dragging' );
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData( 'text/plain', '' );
		}

		function onDragEnd() {
			if ( dragSrc ) {
				dragSrc.classList.remove( 'bbtc-dragging' );
				dragSrc.removeAttribute( 'draggable' );
			}
			dragSrc = null;
			container.querySelectorAll( '.bbtc-accordion-item' ).forEach( function ( el ) {
				el.classList.remove( 'bbtc-drag-over' );
			} );
			updateAccordionPositions( container );
		}

		function onDragOver( e ) {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			const overItem = e.currentTarget;
			if ( dragSrc && overItem !== dragSrc ) {
				container.querySelectorAll( '.bbtc-accordion-item' ).forEach( function ( el ) {
					el.classList.remove( 'bbtc-drag-over' );
				} );
				overItem.classList.add( 'bbtc-drag-over' );
			}
		}

		function onDragLeave( e ) {
			e.currentTarget.classList.remove( 'bbtc-drag-over' );
		}

		function onDrop( e ) {
			e.stopPropagation();
			e.preventDefault();
			const overItem = e.currentTarget;
			if ( ! dragSrc || dragSrc === overItem ) {
				return;
			}
			container.insertBefore( dragSrc, overItem );
			overItem.classList.remove( 'bbtc-drag-over' );
		}

		function attachToItem( item ) {
			// Do NOT set draggable here — it is set on pointerdown on the handle.
			item.addEventListener( 'dragstart', onDragStart );
			item.addEventListener( 'dragend',   onDragEnd );
			item.addEventListener( 'dragover',  onDragOver );
			item.addEventListener( 'dragleave', onDragLeave );
			item.addEventListener( 'drop',      onDrop );
		}

		container.querySelectorAll( '.bbtc-accordion-item' ).forEach( attachToItem );

		// Expose so new items added via JS can also get drag behaviour.
		container._attachDragToItem = attachToItem;
	}

	function updateAccordionPositions( container ) {
		if ( ! container ) {
			return;
		}
		container.querySelectorAll( '.bbtc-accordion-item' ).forEach( function ( el, idx ) {
			const posInput = el.querySelector( 'input[data-position]' );
			if ( posInput ) {
				posInput.value = ( idx + 1 ) * 10;
			}
		} );
	}

	const subtabsAccordionEl = document.getElementById( 'bbtc-subtabs-accordion' );
	initAccordionDragSort( subtabsAccordionEl );

	// ── Sub-nav accordion (event delegation — works for static + dynamic) ─

	// Toggle accordion body open/closed on header click.
	document.addEventListener( 'click', function ( e ) {
		const header = e.target.closest( '.bbtc-accordion-header' );
		if ( ! header ) {
			return;
		}
		// Ignore clicks that originated from a button, link, or drag handle inside the header.
		if ( e.target.closest( 'button, a, .bbtc-accordion-drag-handle' ) ) {
			return;
		}
		const item = header.closest( '.bbtc-accordion-item' );
		if ( ! item ) {
			return;
		}
		const body = item.querySelector( '.bbtc-accordion-body' );
		if ( body ) {
			body.classList.toggle( 'bbtc-collapsed' );
		}
	} );

	// Reorder accordion items (▲ / ▼ buttons with data-accordion-reorder).
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '[data-accordion-reorder]' );
		if ( ! btn ) {
			return;
		}
		e.stopPropagation();

		const direction = btn.dataset.accordionReorder;
		const item      = btn.closest( '.bbtc-accordion-item' );
		if ( ! item ) {
			return;
		}

		if ( 'up' === direction ) {
			const prev = item.previousElementSibling;
			if ( prev && prev.classList.contains( 'bbtc-accordion-item' ) ) {
				item.parentNode.insertBefore( item, prev );
			}
		} else if ( 'down' === direction ) {
			const next = item.nextElementSibling;
			if ( next && next.classList.contains( 'bbtc-accordion-item' ) ) {
				item.parentNode.insertBefore( next, item );
			}
		}

		updateAccordionPositions( item.parentNode );
	} );

	// ── Sub-tab: label input → update accordion title + auto-slug (new) ──
	document.addEventListener( 'input', function ( e ) {
		const input = e.target;
		if ( ! input.classList.contains( 'bbtc-subtab-label-input' ) ) {
			return;
		}
		const item = input.closest( '.bbtc-accordion-item' );
		if ( ! item ) {
			return;
		}

		// Update accordion header title.
		const title = item.querySelector( '.bbtc-accordion-title' );
		if ( title ) {
			title.textContent = input.value || ( typeof bbtcData !== 'undefined' && bbtcData.newSubnavLabel
				? bbtcData.newSubnavLabel
				: 'New Subnav' );
		}

		// Auto-generate slug only for new (unsaved) sub-tabs.
		if ( item.dataset.subtabId === '0' ) {
			const slugInput = item.querySelector( '.bbtc-subtab-slug-input' );
			if ( slugInput && ! slugInput.readOnly ) {
				slugInput.value = slugify( input.value );
			}
		}
	} );

	// ── Sub-tab: Remove button for new (unsaved) items ────────────────────
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.classList.contains( 'bbtc-subtab-remove-new' ) ) {
			return;
		}
		const item = e.target.closest( '.bbtc-accordion-item' );
		if ( item ) {
			item.remove();
		}
	} );

	// ── Add New Subnav button ─────────────────────────────────────────────
	const addSubtabBtn      = document.getElementById( 'bbtc-add-subtab-btn' );
	const subtabTemplate    = document.getElementById( 'bbtc-subtab-template' );
	const subtabsAccordion  = document.getElementById( 'bbtc-subtabs-accordion' );

	if ( addSubtabBtn && subtabTemplate && subtabsAccordion ) {
		addSubtabBtn.addEventListener( 'click', function () {
			const nextIdx = parseInt( subtabsAccordion.dataset.nextIdx || '0', 10 );
			subtabsAccordion.dataset.nextIdx = String( nextIdx + 1 );

			// Clone template content and replace the __IDX__ placeholder.
			const frag        = subtabTemplate.content.cloneNode( true );
			const tempWrap    = document.createElement( 'div' );
			tempWrap.appendChild( frag );
			tempWrap.innerHTML = tempWrap.innerHTML.replace( /__IDX__/g, String( nextIdx ) );

			// Hide the "no sub-navs" empty-state message if present.
			const noMsg = document.getElementById( 'bbtc-no-subtabs-msg' );
			if ( noMsg ) {
				noMsg.style.display = 'none';
			}

			// Append the new item and open it immediately.
			const newItem = tempWrap.querySelector( '.bbtc-accordion-item' );
			if ( newItem ) {
				const body = newItem.querySelector( '.bbtc-accordion-body' );
				if ( body ) {
					body.classList.remove( 'bbtc-collapsed' );
				}
				subtabsAccordion.appendChild( newItem );
				// Attach drag-and-drop to the new item.
				if ( subtabsAccordion._attachDragToItem ) {
					subtabsAccordion._attachDragToItem( newItem );
				}
				// Focus the label input.
				const labelInput = newItem.querySelector( '.bbtc-subtab-label-input' );
				if ( labelInput ) {
					labelInput.focus();
				}
			}
		} );
	}

	// ── Group "Enabled For" None checkbox logic ───────────────────────────
	// Works for main form enabled_for[] AND sub-tab accordion enabled_for[].
	function applyEnabledForNone( group ) {
		if ( ! group ) {
			return;
		}
		const noneCb   = group.querySelector( 'input[value="none"]' );
		const otherCbs = group.querySelectorAll( 'input:not([value="none"])' );
		if ( ! noneCb ) {
			return;
		}
		if ( noneCb.checked ) {
			otherCbs.forEach( function ( cb ) {
				cb.checked  = false;
				cb.disabled = true;
			} );
		} else {
			otherCbs.forEach( function ( cb ) {
				cb.disabled = false;
			} );
		}
	}

	// Apply on page load to the main form's enabled_for group.
	const mainEnabledForGroup = document.getElementById( 'bbtc-enabled-for-group' );
	if ( mainEnabledForGroup ) {
		applyEnabledForNone( mainEnabledForGroup );
	}

	// Event delegation: handle changes to any enabled_for checkbox.
	document.addEventListener( 'change', function ( e ) {
		const cb = e.target;
		if ( ! cb.matches( 'input[name="enabled_for[]"], input[name*="[enabled_for][]"]' ) ) {
			return;
		}
		const group = cb.closest( '.bbtc-checkbox-group' );
		if ( ! group ) {
			return;
		}
		const noneCb   = group.querySelector( 'input[value="none"]' );
		const otherCbs = group.querySelectorAll( 'input:not([value="none"])' );
		if ( ! noneCb ) {
			return;
		}
		if ( 'none' === cb.value && cb.checked ) {
			otherCbs.forEach( function ( other ) {
				other.checked  = false;
				other.disabled = true;
			} );
		} else if ( 'none' !== cb.value && cb.checked && noneCb.checked ) {
			noneCb.checked = false;
			otherCbs.forEach( function ( other ) {
				other.disabled = false;
			} );
		} else if ( 'none' === cb.value && ! cb.checked ) {
			otherCbs.forEach( function ( other ) {
				other.disabled = false;
			} );
		}
	} );

	// ── Group search AJAX (Tab Scope sidebar) ─────────────────────────────
	( function () {
		const searchInputs = document.querySelectorAll( '.bbtc-group-search-input' );
		if ( ! searchInputs.length ) {
			return;
		}

		const ajaxurl = ( typeof bbtcData !== 'undefined' ) ? bbtcData.ajaxurl         : '';
		const nonce   = ( typeof bbtcData !== 'undefined' ) ? bbtcData.groupSearchNonce : '';

		searchInputs.forEach( function ( input ) {
			const target    = input.dataset.target; // 'assoc' or 'excl'
			const resultsEl = document.getElementById( 'bbtc-' + target + '-group-results' );
			const tagsEl    = document.getElementById( 'bbtc-' + target + '-groups-tags' );
			const fieldName = ( target === 'assoc' ) ? 'associated_groups[]' : 'excluded_groups[]';

			if ( ! resultsEl || ! tagsEl ) {
				return;
			}

			let debounceTimer = null;

			function getSelectedIds() {
				return Array.from( tagsEl.querySelectorAll( 'input[type="hidden"]' ) ).map( function ( inp ) {
					return parseInt( inp.value, 10 );
				} );
			}

			function addGroupTag( id, name ) {
				if ( getSelectedIds().indexOf( id ) !== -1 ) {
					return;
				}
				const safe = String( name ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
				const tag  = document.createElement( 'span' );
				tag.className = 'bbtc-group-tag';
				tag.innerHTML =
					'<span>' + safe + '</span>' +
					'<button type="button" class="bbtc-remove-group-tag" aria-label="Remove">\u00d7</button>' +
					'<input type="hidden" name="' + fieldName + '" value="' + id + '">';
				tagsEl.appendChild( tag );
			}

			function renderResults( groups ) {
				resultsEl.innerHTML = '';
				if ( ! groups.length ) {
					resultsEl.style.display = 'none';
					return;
				}
				const selectedIds = getSelectedIds();
				groups.forEach( function ( group ) {
					if ( selectedIds.indexOf( group.id ) !== -1 ) {
						return;
					}
					const item       = document.createElement( 'div' );
					item.className   = 'bbtc-group-result';
					item.textContent = group.name;
					item.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault(); // Prevent blur before click fires.
						addGroupTag( group.id, group.name );
						resultsEl.style.display = 'none';
						input.value = '';
					} );
					resultsEl.appendChild( item );
				} );
				resultsEl.style.display = resultsEl.children.length ? 'block' : 'none';
			}

			input.addEventListener( 'keyup', function () {
				const term = input.value.trim();
				clearTimeout( debounceTimer );
				if ( term.length < 2 ) {
					resultsEl.style.display = 'none';
					return;
				}
				debounceTimer = setTimeout( function () {
					const url = ajaxurl +
						'?action=bbtc_search_groups' +
						'&term=' + encodeURIComponent( term ) +
						'&_ajax_nonce=' + encodeURIComponent( nonce );
					fetch( url )
						.then( function ( r ) { return r.json(); } )
						.then( function ( data ) {
							if ( data.success && data.data ) {
								renderResults( data.data );
							}
						} )
						.catch( function () {} );
				}, 300 );
			} );

			input.addEventListener( 'blur', function () {
				// Slight delay so mousedown on a result item fires first.
				setTimeout( function () {
					resultsEl.style.display = 'none';
				}, 150 );
			} );
		} );

		// Remove group tags via event delegation (covers PHP-rendered + JS-added).
		document.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '.bbtc-remove-group-tag, .bbtc-remove-assoc-group, .bbtc-remove-excl-group' );
			if ( ! btn ) {
				return;
			}
			const tag = btn.closest( '.bbtc-group-tag' );
			if ( tag ) {
				tag.remove();
			}
		} );
	} )();

} );
