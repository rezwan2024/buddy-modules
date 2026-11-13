/**
 * ExistingTabCreatorPanel — view-level router for Existing Tabs panels.
 *
 * route.view undefined/null → ExistingTabsList (list)
 * route.view 'edit'         → ExistingTabEditor (route.id = slug string)
 *
 * @since 2.0.0
 */

import ExistingTabsList   from './ExistingTabsList';
import ExistingTabEditor  from './ExistingTabEditor';

/**
 * @param {Object}   props
 * @param {Object}   props.route
 * @param {Function} props.navigate
 * @returns {JSX.Element}
 */
export default function ExistingTabCreatorPanel( props ) {
	var view = props.route.view;

	if ( 'edit' === view ) {
		return <ExistingTabEditor { ...props } />;
	}

	return <ExistingTabsList { ...props } />;
}
