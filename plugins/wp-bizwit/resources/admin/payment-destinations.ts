/**
 * Settings: multiple payment destinations (add/remove cards, type fields, bank selects).
 *
 * Bank pickers use the shared searchable-select module (variant=bank). Free-text
 * “other bank” sync is built into enhanceSearchableSelect when [data-bank-custom]
 * is present — boot order does not matter.
 */
import {
	destroySearchableSelect,
	destroyAllSearchableSelects,
	enhanceSearchableSelect,
} from './searchable-select';

function enhanceBankSelects( root: ParentNode ): void {
	root
		.querySelectorAll< HTMLSelectElement >(
			'select[data-bank-select], select[data-bw-select-variant="bank"]'
		)
		.forEach( ( select ) => {
			enhanceSearchableSelect( select, { variant: 'bank' } );
		} );
}

/**
 * Wire payment destination cards on the settings screen.
 */
export function bindPaymentDestinations(): void {
	const root = document.getElementById( 'wp-bizwit-pay-destinations' );
	if ( ! root ) {
		return;
	}

	const fieldsByType: Record< string, string[] > = {
		bank_transfer: [ 'label', 'bank_name', 'account_no', 'account_name', 'notes' ],
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

	const enhanceAll = (): void => {
		enhanceBankSelects( root );
	};

	const destroyAll = (): void => {
		destroyAllSearchableSelects( root );
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

	const resetCardFields = ( card: HTMLElement ): void => {
		card.querySelectorAll< HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement >(
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
			if ( input.name.includes( '[id]' ) ) {
				input.value = '';
				return;
			}
			input.value = '';
		} );
	};

	root.querySelectorAll< HTMLElement >( '[data-pay-card]' ).forEach( applyType );
	enhanceAll();

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
			destroyAll();
			resetCardFields( card );
			applyType( card );
			enhanceAll();
			return;
		}

		card
			.querySelectorAll< HTMLSelectElement >(
				'select[data-bank-select], select[data-bw-select]'
			)
			.forEach( destroySearchableSelect );
		card.remove();
		reindex();
	} );

	document.getElementById( 'wp-bizwit-pay-add' )?.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		const cards = root.querySelectorAll( '[data-pay-card]' );
		const last = cards[ cards.length - 1 ];
		if ( ! ( last instanceof HTMLElement ) ) {
			return;
		}

		destroyAll();

		const clone = last.cloneNode( true ) as HTMLElement;
		clone.querySelectorAll( '.ts-wrapper' ).forEach( ( n ) => n.remove() );
		clone.querySelectorAll< HTMLSelectElement >( 'select' ).forEach( ( sel ) => {
			sel.classList.remove( 'tomselected', 'ts-hidden-accessible', 'bw-ss-enhanced' );
			sel.removeAttribute( 'tabindex' );
			sel.style.display = '';
			sel.style.position = '';
			delete sel.dataset.bwSelectSize;
			delete sel.dataset.bwSelectVariant;
		} );

		resetCardFields( clone );
		root.appendChild( clone );
		applyType( clone );
		reindex();
		enhanceAll();
	} );
}
