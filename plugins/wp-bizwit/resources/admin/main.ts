/**
 * Shared admin entry — product styles under `.wp-bizwit` + progressive enhancement.
 * Enqueued on every BizWit admin screen via Admin\Assets.
 */
import 'tom-select/dist/css/tom-select.default.css';
import '@/styles/admin.css';

import {
	enhanceAllSearchableSelects,
	exposeSearchableSelectApi,
} from './searchable-select';
import { bindPaymentDestinations } from './payment-destinations';
import { bindInvoiceItems } from './invoice-items';

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

/**
 * Settings: pick / clear business logo via the media library.
 */
function bindBusinessLogoPicker(): void {
	const input = document.getElementById( 'wp-bizwit-business-logo-id' ) as HTMLInputElement | null;
	const pick = document.getElementById( 'wp-bizwit-business-logo-pick' );
	const clear = document.getElementById( 'wp-bizwit-business-logo-clear' );
	const preview = document.getElementById( 'wp-bizwit-business-logo-preview' ) as HTMLImageElement | null;
	if ( ! input || ! pick ) {
		return;
	}

	// wp.media is only available when Assets enqueued media on settings.
	const wpMedia = (
		window as unknown as {
			wp?: { media?: ( opts: Record< string, unknown > ) => {
				on: ( e: string, cb: () => void ) => void;
				open: () => void;
				state: () => { get: ( k: string ) => { first: () => { get: ( k: string ) => string | number | undefined } | undefined } };
			} };
		}
	).wp?.media;

	pick.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		if ( ! wpMedia ) {
			return;
		}
		const frame = wpMedia( {
			title: pick.textContent || 'Select logo',
			button: { text: pick.textContent || 'Select logo' },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first();
			if ( ! attachment ) {
				return;
			}
			const id = attachment.get( 'id' );
			const url =
				( attachment.get( 'sizes' ) as { medium?: { url?: string }; full?: { url?: string } } | undefined )
					?.medium?.url ||
				( attachment.get( 'url' ) as string | undefined ) ||
				'';
			input.value = String( id ?? '' );
			if ( preview && url ) {
				preview.src = url;
				preview.hidden = false;
			}
			if ( clear ) {
				clear.hidden = false;
			}
		} );
		frame.open();
	} );

	clear?.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		input.value = '0';
		if ( preview ) {
			preview.removeAttribute( 'src' );
			preview.hidden = true;
		}
		clear.hidden = true;
	} );
}

/**
 * Boot progressive enhancements shared across BizWit admin screens.
 */
function boot(): void {
	exposeSearchableSelectApi();
	bindDirtyFormGuard();
	focusAdminNotice();
	bindBusinessLogoPicker();
	// Order-independent: bank “other name” sync is built into searchable-select
	// when [data-bank-custom] is present. Payment cards still manage add/remove.
	bindPaymentDestinations();
	// Invoice form: kind/period meta row, satuan suggest, add-next cloning.
	bindInvoiceItems();
	// Enhance any remaining [data-bw-select] (faktur picker, filters, …).
	// Already-enhanced selects are no-ops.
	enhanceAllSearchableSelects( document );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
