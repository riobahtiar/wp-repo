/**
 * Invoice line items: kind/period meta row behaviour.
 *
 * Progressive enhancement for src/Admin/Views/invoices-form.php — the form
 * works without JS. This adds: show/hide of the custom-length controls,
 * satuan suggestion on period change, and cloning a fresh trailing row pair
 * when the last pair's description is typed into.
 *
 * The preset lists below mirror WP_BizWit\Support\Line_Item_Meta — PHP is
 * the source of truth; keep the two in sync. Satuan presets are canonical
 * Indonesian terms (stored data), never translated.
 */

const CANONICAL_UNITS = [
	'pcs',
	'jam',
	'paket',
	'satuan',
	'hari',
	'minggu',
	'bulan',
	'kuartal',
	'tahun',
];

const CUSTOM_UNIT_CANONICAL: Record< string, string > = {
	day: 'hari',
	week: 'minggu',
	month: 'bulan',
	year: 'tahun',
};

const CUSTOM_PRESET_RE = /^[1-9][0-9]{0,2} (hari|minggu|bulan|tahun)$/;

interface ItemParts {
	desc: HTMLInputElement | null;
	unit: HTMLInputElement | null;
	period: HTMLSelectElement | null;
	count: HTMLInputElement | null;
	periodUnit: HTMLSelectElement | null;
	custom: HTMLElement | null;
	total: HTMLElement | null;
}

function parts( body: HTMLElement ): ItemParts {
	return {
		desc: body.querySelector< HTMLInputElement >( '.wp-bizwit-item-desc' ),
		unit: body.querySelector< HTMLInputElement >( '.wp-bizwit-item-unit' ),
		period: body.querySelector< HTMLSelectElement >( '.wp-bizwit-item-period' ),
		count: body.querySelector< HTMLInputElement >( '.wp-bizwit-item-count' ),
		periodUnit: body.querySelector< HTMLSelectElement >( '.wp-bizwit-item-period-unit' ),
		custom: body.querySelector< HTMLElement >( '.wp-bizwit-item-custom' ),
		total: body.querySelector< HTMLElement >( '.wp-bizwit-items__total' ),
	};
}

/**
 * Empty or known-preset satuan may be overwritten by suggestions.
 * Comparison runs against canonical terms only, case-insensitively.
 */
function isPresetUnit( unit: string ): boolean {
	const value = unit.trim().toLowerCase();
	if ( value === '' ) {
		return true;
	}
	return CANONICAL_UNITS.includes( value ) || CUSTOM_PRESET_RE.test( value );
}

/**
 * Suggested satuan for a period; '' means "leave the current unit alone".
 */
function suggestedUnit( period: string, count: number, periodUnit: string ): string {
	switch ( period ) {
		case 'monthly':
			return 'bulan';
		case 'quarterly':
			return 'kuartal';
		case 'yearly':
			return 'tahun';
		case 'custom': {
			const canonical = CUSTOM_UNIT_CANONICAL[ periodUnit ] ?? '';
			return count >= 1 && canonical !== '' ? `${ count } ${ canonical }` : '';
		}
		default:
			return '';
	}
}

function syncCustomVisibility( p: ItemParts ): void {
	if ( ! p.custom || ! p.period ) {
		return;
	}
	if ( p.period.value === 'custom' ) {
		p.custom.removeAttribute( 'hidden' );
	} else {
		p.custom.setAttribute( 'hidden', '' );
	}
}

/**
 * Fill the unit input from the period when the current value is a preset
 * (or empty). Never overwrites a custom satuan the user typed.
 */
function maybeSuggestUnit( p: ItemParts ): void {
	if ( ! p.period || ! p.unit || p.unit.disabled ) {
		return;
	}
	const suggestion = suggestedUnit(
		p.period.value,
		parseInt( p.count?.value ?? '0', 10 ) || 0,
		p.periodUnit?.value ?? ''
	);
	if ( suggestion === '' ) {
		return;
	}
	if ( isPresetUnit( p.unit.value ) ) {
		p.unit.value = suggestion;
	}
}

/**
 * Clone the trailing row pair so there is always an empty one for add-next.
 */
function cloneForNext( table: HTMLTableElement, body: HTMLElement ): void {
	const bodies = Array.from( table.querySelectorAll< HTMLElement >( 'tbody.wp-bizwit-item' ) );
	const next =
		bodies.reduce( ( max, el ) => {
			const index = parseInt( el.dataset.itemIndex ?? '0', 10 );
			return Number.isNaN( index ) ? max : Math.max( max, index );
		}, 0 ) + 1;

	const clone = body.cloneNode( true ) as HTMLElement;
	clone.dataset.itemIndex = String( next );

	clone.querySelectorAll< HTMLInputElement | HTMLSelectElement >( '[name]' ).forEach( ( field ) => {
		field.name = field.name.replace( /^items\[\d+\]/, `items[${ next }]` );

		if ( field instanceof HTMLInputElement ) {
			if ( field.classList.contains( 'wp-bizwit-item-desc' ) ) {
				field.value = '';
				field.required = false;
			} else if ( field.classList.contains( 'wp-bizwit-item-unit' ) ) {
				field.value = '';
			} else if ( field.classList.contains( 'wp-bizwit-item-count' ) ) {
				field.value = '';
			} else if ( field.name.endsWith( '[quantity]' ) ) {
				field.value = '1';
			} else if ( field.name.endsWith( '[unit_price]' ) ) {
				field.value = '';
			}
			// tax_rate keeps the default rate cloned from the trailing row.
		}

		if ( field instanceof HTMLSelectElement ) {
			if ( field.name.endsWith( '[item_kind]' ) ) {
				field.value = '';
			} else if ( field.name.endsWith( '[billing_period]' ) ) {
				field.value = 'one_time';
			} else if ( field.name.endsWith( '[period_unit]' ) ) {
				field.selectedIndex = 0;
			}
		}
	} );

	const cloneParts = parts( clone );
	if ( cloneParts.custom ) {
		cloneParts.custom.setAttribute( 'hidden', '' );
	}
	if ( cloneParts.total ) {
		cloneParts.total.innerHTML = '&mdash;';
	}

	body.after( clone );
	bindBody( table, clone );
}

function bindBody( table: HTMLTableElement, body: HTMLElement ): void {
	const p = parts( body );

	p.period?.addEventListener( 'change', () => {
		syncCustomVisibility( p );
		maybeSuggestUnit( p );
	} );
	p.count?.addEventListener( 'input', () => maybeSuggestUnit( p ) );
	p.periodUnit?.addEventListener( 'change', () => maybeSuggestUnit( p ) );

	p.desc?.addEventListener( 'input', () => {
		const lastBody = table.tBodies[ table.tBodies.length - 1 ];
		if ( body === lastBody && p.desc && p.desc.value.trim() !== '' && ! p.desc.disabled ) {
			cloneForNext( table, body );
		}
	} );
}

/**
 * Bind all item row pairs on the invoice form (no-op on other screens).
 */
export function bindInvoiceItems(): void {
	const table = document.getElementById( 'wp-bizwit-items' );
	if ( ! ( table instanceof HTMLTableElement ) ) {
		return;
	}
	table.querySelectorAll< HTMLElement >( 'tbody.wp-bizwit-item' ).forEach( ( body ) => {
		bindBody( table, body );
	} );
}
