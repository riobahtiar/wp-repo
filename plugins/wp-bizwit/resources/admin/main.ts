/**
 * Shared admin entry — product styles under `.wp-bizwit` + progressive enhancement.
 * Enqueued on every BizWit admin screen via Admin\Assets.
 */
import '@/styles/admin.css';

/**
 * Warn before leaving a dirty form (progressive enhancement; forms still work without JS).
 */
function bindDirtyFormGuard(): void {
	const forms = document.querySelectorAll< HTMLFormElement >(
		'.wp-bizwit form.wp-bizwit-form, .wp-bizwit form#wp-bizwit-invoice-form'
	);

	forms.forEach( ( form ) => {
		let dirty = false;

		const markDirty = () => {
			dirty = true;
		};

		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'change', markDirty );
		form.addEventListener( 'submit', () => {
			dirty = false;
		} );

		window.addEventListener( 'beforeunload', ( event ) => {
			if ( ! dirty ) {
				return;
			}
			event.preventDefault();
			// Modern browsers ignore custom strings; assignment still triggers the dialog.
			event.returnValue = true;
		} );
	} );
}

/**
 * Move focus to the admin notice when present (after PRG redirect).
 */
function focusAdminNotice(): void {
	const notice = document.getElementById( 'wp-bizwit-admin-notice' );
	if ( ! notice ) {
		return;
	}
	const panel = notice.closest( '.notice' );
	if ( panel instanceof HTMLElement ) {
		if ( ! panel.hasAttribute( 'tabindex' ) ) {
			panel.setAttribute( 'tabindex', '-1' );
		}
		panel.focus( { preventScroll: false } );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		bindDirtyFormGuard();
		focusAdminNotice();
	} );
} else {
	bindDirtyFormGuard();
	focusAdminNotice();
}
