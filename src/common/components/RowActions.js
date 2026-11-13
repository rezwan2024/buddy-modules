/**
 * RowActions — "⋯" more-options menu for list-table rows.
 *
 * Same pattern as the BuddyBoss Platform list tables: a dots-three toggle
 * opening a dropdown of icon + label items; destructive items in red.
 *
 * @since 2.0.0
 */

import { __ } from '@wordpress/i18n';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';

/**
 * @since 2.0.0
 *
 * @param {Object} props
 * @param {Array}  props.actions List of { key, label, icon, onClick, isDestructive? }.
 *                               `icon` is a bb-icons-rl-* class name.
 * @param {string} props.label   Accessible label for the toggle button.
 * @returns {JSX.Element}
 */
export function RowActions( { actions, label } ) {
	return (
		<DropdownMenu
			icon={ <i className="bb-icons-rl bb-icons-rl-dots-three" aria-hidden="true" /> }
			label={ label || __( 'More options', 'buddy-modules' ) }
			className="bdm-admin-row-menu"
			toggleProps={ { className: 'bdm-admin-row-menu__toggle' } }
			popoverProps={ { className: 'bdm-admin-row-menu__popover', placement: 'bottom-end' } }
		>
			{ function( { onClose } ) {
				return (
					<MenuGroup>
						{ actions.map( function( action ) {
							return (
								<MenuItem
									key={ action.key }
									className="bdm-admin-row-menu__item"
									isDestructive={ !! action.isDestructive }
									onClick={ function() {
										onClose();
										action.onClick();
									} }
								>
									<i className={ 'bb-icons-rl ' + action.icon } aria-hidden="true" />
									{ action.label }
								</MenuItem>
							);
						} ) }
					</MenuGroup>
				);
			} }
		</DropdownMenu>
	);
}
