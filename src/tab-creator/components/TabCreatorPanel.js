/**
 * TabCreatorPanel — view-level router for Profile Tabs and Group Tabs panels.
 *
 * route.view undefined/null → TabsList (list)
 * route.view 'add'          → TabEditor (add)
 * route.view 'edit'         → TabEditor (edit, route.id = tab ID)
 *
 * @since 2.0.0
 */

import TabsList   from './TabsList';
import TabEditor  from './TabEditor';

/**
 * @param {Object}   props
 * @param {Object}   props.route
 * @param {Function} props.navigate
 * @returns {JSX.Element}
 */
export default function TabCreatorPanel( props ) {
	var view = props.route.view;

	if ( 'add' === view || 'edit' === view ) {
		return <TabEditor { ...props } />;
	}

	return <TabsList { ...props } />;
}
