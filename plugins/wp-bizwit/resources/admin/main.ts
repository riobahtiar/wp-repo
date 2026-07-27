/**
 * Shared admin entry — product styles under `.wp-bizwit` + progressive enhancement.
 * Enqueued on every BizWit admin screen via Admin\Assets.
 */
import TomSelect from 'tom-select';

import 'tom-select/dist/css/tom-select.default.css';
import '@/styles/admin.css';

type TomSelectEl = HTMLSelectElement & { tomselect?: TomSelect };

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

type BankOptionData = {
	value?: string;
	text?: string;
	code?: string;
	/** data-name → dataset.name */
	name?: string;
	/** data-bank-name → dataset.bankName */
	bankName?: string;
	[ key: string ]: unknown;
};

/**
 * Resolve display name (never include transfer code in this string).
 */
function bankOptionName( data: BankOptionData ): string {
	const candidates = [ data.name, data.bankName, data[ 'bank-name' ] ];
	for ( const c of candidates ) {
		const s = String( c ?? '' ).trim();
		if ( s ) {
			return s;
		}
	}
	// Fallback: strip trailing code from search text "Name 014".
	const text = String( data.text ?? '' ).trim();
	const m = text.match( /^(.*?)\s+(\d{3,})$/ );
	return m ? m[ 1 ].trim() : text;
}

function bankOptionCode( data: BankOptionData ): string {
	const fromData = String( data.code ?? '' ).trim();
	if ( fromData && fromData !== '__custom__' ) {
		return fromData;
	}
	const text = String( data.text ?? '' ).trim();
	const m = text.match( /\s(\d{3,})$/ );
	return m ? m[ 1 ] : '';
}

/**
 * Show free-text bank name when user picks "Other bank".
 */
function syncBankCustom( select: HTMLSelectElement ): void {
	const wrap = select.closest( '.wp-bizwit-pay-card__field' );
	const custom = wrap?.querySelector< HTMLInputElement >( '[data-bank-custom]' );
	if ( ! custom ) {
		return;
	}

	const show = select.value === '__custom__';
	custom.hidden = ! show;

	if ( ! show && select.value && select.value !== '__custom__' ) {
		const el = select as TomSelectEl;
		const ts = el.tomselect;
		if ( ts ) {
			const opt = ts.options[ select.value ] as BankOptionData | undefined;
			if ( opt ) {
				custom.value = bankOptionName( opt );
				return;
			}
		}
		const optEl = select.selectedOptions[ 0 ];
		custom.value =
			optEl?.getAttribute( 'data-name' ) ||
			optEl?.getAttribute( 'data-bank-name' ) ||
			optEl?.textContent?.trim() ||
			'';
	}
}

/**
 * Enhance a bank &lt;select&gt; with Tom Select (search by name or code).
 */
function enhanceBankSelect( select: HTMLSelectElement ): void {
	const el = select as TomSelectEl;
	if ( el.tomselect ) {
		return;
	}

	const placeholder =
		select.getAttribute( 'data-placeholder' ) || 'Search by bank name or transfer code…';

	new TomSelect( select, {
		allowEmptyOption: true,
		create: false,
		maxOptions: 100,
		// Search name (text), transfer code, and explicit name field.
		searchField: [ 'text', 'code', 'name', 'bankName' ],
		dropdownParent: 'body',
		optgroupField: 'optgroup',
		lockOptgroupOrder: true,
		plugins: [ 'dropdown_input' ],
		placeholder,
		render: {
			// Root element becomes the .option / .item node — keep name & code as children only.
			option( data: BankOptionData, escape: ( s: string ) => string ) {
				const value = String( data.value ?? '' );
				if ( value === '' ) {
					return `<div class="bw-bank-opt bw-bank-opt--placeholder">${ escape(
						String( data.text ?? '' )
					) }</div>`;
				}
				if ( value === '__custom__' ) {
					return `<div class="bw-bank-opt bw-bank-opt--custom">${ escape(
						String( data.text ?? '' )
					) }</div>`;
				}
				const name = bankOptionName( data );
				const code = bankOptionCode( data );
				return (
					`<div class="bw-bank-opt">` +
					`<span class="bw-bank-opt__name">${ escape( name ) }</span>` +
					( code
						? `<span class="bw-bank-opt__code" aria-label="Transfer code">${ escape(
								code
						  ) }</span>`
						: '' ) +
					`</div>`
				);
			},
			item( data: BankOptionData, escape: ( s: string ) => string ) {
				const value = String( data.value ?? '' );
				if ( value === '' || value === '__custom__' ) {
					return `<div class="bw-bank-item bw-bank-item--muted">${ escape(
						String( data.text ?? '' )
					) }</div>`;
				}
				const name = bankOptionName( data );
				const code = bankOptionCode( data );
				return (
					`<div class="bw-bank-item">` +
					`<span class="bw-bank-item__name">${ escape( name ) }</span>` +
					( code
						? `<span class="bw-bank-item__code" aria-label="Transfer code">${ escape(
								code
						  ) }</span>`
						: '' ) +
					`</div>`
				);
			},
			optgroup_header( data: { label?: string }, escape: ( s: string ) => string ) {
				return `<div class="bw-bank-optgroup">${ escape( String( data.label ?? '' ) ) }</div>`;
			},
			no_results( _data: unknown, escape: ( s: string ) => string ) {
				return `<div class="bw-bank-empty">${ escape(
					'No banks match — try another name or code'
				) }</div>`;
			},
		},
		onChange() {
			syncBankCustom( select );
		},
		onInitialize() {
			syncBankCustom( select );
		},
	} );
}

function destroyBankSelect( select: HTMLSelectElement ): void {
	const el = select as TomSelectEl;
	if ( el.tomselect ) {
		el.tomselect.destroy();
	}
}

/**
 * Settings: multiple payment destinations — type fields, add/remove, searchable banks.
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

	const enhanceAll = (): void => {
		root.querySelectorAll< HTMLSelectElement >( '[data-bank-select]' ).forEach( enhanceBankSelect );
	};

	const destroyAll = (): void => {
		root.querySelectorAll< HTMLSelectElement >( '[data-bank-select]' ).forEach( destroyBankSelect );
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

		card.querySelectorAll< HTMLSelectElement >( '[data-bank-select]' ).forEach( destroyBankSelect );
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

		// Destroy Tom Select so the clone is a clean native <select>.
		destroyAll();

		const clone = last.cloneNode( true ) as HTMLElement;
		clone.querySelectorAll( '.ts-wrapper' ).forEach( ( n ) => n.remove() );
		clone.querySelectorAll< HTMLSelectElement >( 'select' ).forEach( ( sel ) => {
			sel.classList.remove( 'tomselected', 'ts-hidden-accessible' );
			sel.removeAttribute( 'tabindex' );
			sel.style.display = '';
			sel.style.position = '';
		} );

		resetCardFields( clone );
		root.appendChild( clone );
		applyType( clone );
		reindex();
		enhanceAll();
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
