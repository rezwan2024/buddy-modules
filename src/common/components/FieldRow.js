/**
 * FieldRow — one settings row: label on the left, control + help on the right.
 *
 * Same layout as the BuddyBoss Platform settings form
 * (.bb-admin-settings-form__field): a fixed label column, the control column
 * beside it, help text under the control and a divider between rows.
 *
 * @since 2.0.0
 */

/**
 * @since 2.0.0
 *
 * @param {Object}      props
 * @param {string}      props.label     Row label.
 * @param {string}      [props.htmlFor] ID of the control the label belongs to.
 * @param {string}      [props.help]    Help text shown under the control.
 * @param {string}      [props.error]   Validation message shown under the control.
 * @param {JSX.Element} props.children  The control(s).
 * @returns {JSX.Element}
 */
export function FieldRow( { label, htmlFor, help, error, children } ) {
	return (
		<div className="bdm-admin-field-row">
			<div className="bdm-admin-field-row__label">
				{ htmlFor
					? <label htmlFor={ htmlFor }>{ label }</label>
					: <span>{ label }</span>
				}
			</div>
			<div className="bdm-admin-field-row__content">
				{ children }
				{ help && (
					<p className="bdm-admin-field-row__help">{ help }</p>
				) }
				{ error && (
					<p className="bdm-admin-field-row__error" role="alert">{ error }</p>
				) }
			</div>
		</div>
	);
}
