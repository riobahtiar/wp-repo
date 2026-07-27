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

/**
 * Settings: multiple payment destinations — show fields by type, add/remove cards.
 */
function bindPaymentDestinations(): void {
	const root = document.getElementById( 'wp-bizwit-pay-destinations' );
	if ( ! root ) {
		return;
	}

	const fieldsByType: Record< string, string[] > = {
		bank_transfer: [ 'label', 'bank_name', 'branch', 'account_no', 'account_name', 'notes' ],
		virtual_account: [ 'label', 'va_bank', 'va_number', 'account_name', 'notes' ],
		payment_link: [ 'label', 'url', 'notes' ],
		ewallet_dana: [ 'label', 'ewallet_id', 'notes' ],
		ewallet_gopay: [ 'label', 'ewallet_id', 'notes' ],
		ewallet_ovo: [ 'label', 'ewallet_id', 'notes' ],
		ewallet_shopeepay: [ 'label', 'ewallet_id', 'notes' ],
		offline: [ 'label', 'notes', 'account_no', 'account_name' ],
		other: [ 'label', 'notes', 'account_no', 'account_name', 'url' ],
	};

	const applyType = ( card: HTMLElement ): void => {
		const select = card.querySelector< HTMLSelectElement >( '[data-pay-type]' );
		const type = select?.value || 'bank_transfer';
		card.dataset.type = type;
		const allowed = fieldsByType[ type ] || fieldsByType.other;
		card.querySelectorAll< HTMLElement >( '[data-pay-field]' ).forEach( ( el ) => {
			const key = el.getAttribute( 'data-pay-field' ) || '';
			el.hidden = ! allowed.includes( key );
		} );
	};

	const reindex = (): void => {
		root.querySelectorAll< HTMLElement >( '[data-pay-card]' ).forEach( ( card, index ) => {
			const badge = card.querySelector( '.wp-bizwit-pay-card__index' );
			if ( badge ) {
				badge.textContent = String( index + 1 );
			}
			card.querySelectorAll< HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement >(
				'input, select, textarea'
			).forEach( ( input ) => {
				const name = input.getAttribute( 'name' );
				if ( name ) {
					input.setAttribute(
						'name',
						name.replace( /payment_destinations\[\d+\]/, `payment_destinations[${ index }]` )
					);
				}
				const id = input.getAttribute( 'id' );
				if ( id ) {
					const nextId = id.replace( /-\d+$/, `-${ index }` );
					input.setAttribute( 'id', nextId );
					const lab = card.querySelector( `label[for="${ id }"]` );
					if ( lab ) {
						lab.setAttribute( 'for', nextId );
					}
				}
			} );
		} );
	};

	root.querySelectorAll< HTMLElement >( '[data-pay-card]' ).forEach( applyType );

	root.addEventListener( 'change', ( event ) => {
		const t = event.target;
		if ( t instanceof HTMLSelectElement && t.matches( '[data-pay-type]' ) ) {
			const card = t.closest< HTMLElement >( '[data-pay-card]' );
			if ( card ) {
				applyType( card );
			}
		}
	} );

	root.addEventListener( 'click', ( event ) => {
		const t = event.target;
		if ( ! ( t instanceof HTMLElement ) ) {
			return;
		}
		const btn = t.closest( '[data-pay-remove]' );
		if ( ! btn ) {
			return;
		}
		event.preventDefault();
		const card = btn.closest< HTMLElement >( '[data-pay-card]' );
		if ( ! card ) {
			return;
		}
		const cards = root.querySelectorAll( '[data-pay-card]' );
		if ( cards.length <= 1 ) {
			// Clear fields instead of removing the last card.
			card.querySelectorAll< HTMLInputElement | HTMLTextAreaElement >( 'input, textarea' ).forEach(
				( input ) => {
					if ( input.type === 'checkbox' ) {
						( input as HTMLInputElement ).checked = true;
					} else if ( input.type !== 'hidden' || input.name.includes( '[id]' ) ) {
						if ( input.name.includes( '[id]' ) ) {
							input.value = '';
						} else if ( input.type !== 'checkbox' ) {
							input.value = '';
						}
					}
				}
			);
			return;
		}
		card.remove();
		reindex();
	} );

	const addBtn = document.getElementById( 'wp-bizwit-pay-add' );
	addBtn?.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		const cards = root.querySelectorAll( '[data-pay-card]' );
		const last = cards[ cards.length - 1 ];
		if ( ! ( last instanceof HTMLElement ) ) {
			return;
		}
		const clone = last.cloneNode( true ) as HTMLElement;
		clone.querySelectorAll< HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement >(
			'input, textarea, select'
		).forEach( ( input ) => {
			if ( input instanceof HTMLInputElement && input.type === 'checkbox' ) {
				input.checked = true;
				return;
			}
			if ( input instanceof HTMLSelectElement ) {
				input.selectedIndex = 0;
				return;
			}
			input.value = '';
		} );
		root.appendChild( clone );
		applyType( clone );
		reindex();
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		bindDirtyFormGuard();
		focusAdminNotice();
		bindPaymentDestinations();
	} );
} else {
	bindDirtyFormGuard();
	focusAdminNotice();
	bindPaymentDestinations();
}
