/**
 * Reusable searchable <select> enhancer (Tom Select).
 *
 * Progressive enhancement: native <select> works without JS.
 * Activate with `data-bw-select` (and optional config attributes), or call
 * `enhanceSearchableSelect()` / `window.wpBizwitSearchableSelect.enhance()`.
 *
 * @see Admin\Searchable_Select (PHP markup helper)
 * @see styles: body > .ts-dropdown / .bw-ss-*
 */
import TomSelect from 'tom-select';
import { __ } from '@wordpress/i18n';

export type TomSelectEl = HTMLSelectElement & { tomselect?: TomSelect };

export type SelectVariant = 'default' | 'meta' | 'bank';
export type SelectSize = 'sm' | 'md' | 'lg';

export type SelectOptionData = {
	value?: string;
	text?: string;
	/** Primary label (data-label / data-name). */
	label?: string;
	name?: string;
	/** Secondary chip (data-meta / data-code). */
	meta?: string;
	code?: string;
	[ key: string ]: unknown;
};

export type EscapeFn = ( s: string ) => string;

/**
 * Full configuration for one searchable select.
 * Most keys can also be set via `data-*` attributes on the <select>.
 */
export type SearchableSelectOptions = {
	/** Visual/layout preset: plain text, primary+chip, or bank (alias of meta + bank hooks). */
	variant?: SelectVariant;
	/** Control height/padding: sm | md | lg. */
	size?: SelectSize;
	placeholder?: string;
	/** Cap on visible options while searching (Tom Select maxOptions). */
	maxOptions?: number;
	/**
	 * Max selected values. Default 1 (single). Use >1 or null for multi-select.
	 * null = unlimited.
	 */
	maxItems?: number | null;
	/** Fields Tom Select searches (maps to option data keys / data-* attrs). */
	searchFields?: string[];
	/** Where the dropdown mounts. Prefer 'body' to escape overflow:hidden parents. */
	dropdownParent?: string | HTMLElement;
	allowEmptyOption?: boolean;
	/** Allow creating free-text options (Tom Select create). */
	create?: boolean | ( ( input: string ) => boolean | string | object );
	/** Tom Select plugin names (e.g. dropdown_input, clear_button, remove_button). */
	plugins?: string[];
	noResultsText?: string;
	openOnFocus?: boolean;
	closeAfterSelect?: boolean;
	hideSelected?: boolean;
	/** Copy select class names onto the dropdown panel. */
	copyClassesToDropdown?: boolean;
	/** Extra class on the dropdown panel (joined with bw-ss-dropdown). */
	dropdownClass?: string;
	/** Extra class on the Tom Select control wrapper. */
	controlClass?: string;
	/** Disable interaction after init. */
	disabled?: boolean;
	/** Sort field(s) for option list. */
	sortField?: string | Array< { field: string; direction?: 'asc' | 'desc' } >;
	/**
	 * Extra Tom Select settings. Merged last before render/onChange hooks so
	 * you can pass any official Tom Select option without forking this module.
	 */
	tomSelect?: Record< string, unknown >;
	onChange?: ( value: string, select: HTMLSelectElement ) => void;
	onInitialize?: ( select: HTMLSelectElement, instance: TomSelect ) => void;
	onDropdownOpen?: ( dropdown: HTMLElement, select: HTMLSelectElement ) => void;
	onDropdownClose?: ( dropdown: HTMLElement, select: HTMLSelectElement ) => void;
	onType?: ( str: string, select: HTMLSelectElement ) => void;
	/** Full custom option HTML (optional). Root becomes the .option node. */
	renderOption?: ( data: SelectOptionData, escape: EscapeFn ) => string;
	/** Full custom selected-item HTML (optional). Root becomes the .item node. */
	renderItem?: ( data: SelectOptionData, escape: EscapeFn ) => string;
	renderOptgroupHeader?: ( data: { label?: string }, escape: EscapeFn ) => string;
	renderNoResults?: ( data: unknown, escape: EscapeFn ) => string;
};

const DEFAULT_SEARCH_FIELDS = [ 'text', 'code', 'name', 'label', 'meta' ];
const DEFAULT_PLUGINS = [ 'dropdown_input' ];

/**
 * Primary display label for an option (never includes meta/code by default).
 */
export function optionPrimary( data: SelectOptionData ): string {
	for ( const key of [ 'label', 'name', 'bankName' ] as const ) {
		const s = String( data[ key ] ?? '' ).trim();
		if ( s ) {
			return s;
		}
	}
	for ( const key of [ 'bank-name' ] ) {
		const s = String( data[ key ] ?? '' ).trim();
		if ( s ) {
			return s;
		}
	}
	const text = String( data.text ?? '' ).trim();
	// Strip trailing " 014" search suffix if present.
	const m = text.match( /^(.*?)\s+(\d{3,})$/ );
	return m ? m[ 1 ].trim() : text;
}

/**
 * Secondary meta chip (transfer code, short tag, …).
 */
export function optionMeta( data: SelectOptionData ): string {
	for ( const key of [ 'meta', 'code' ] as const ) {
		const s = String( data[ key ] ?? '' ).trim();
		if ( s && s !== '__custom__' ) {
			return s;
		}
	}
	const text = String( data.text ?? '' ).trim();
	const m = text.match( /\s(\d{3,})$/ );
	return m ? m[ 1 ] : '';
}

function parseBoolAttr( raw: string | null, defaultValue: boolean ): boolean {
	if ( raw === null || raw === '' ) {
		return defaultValue;
	}
	return raw !== '0' && raw !== 'false' && raw !== 'no';
}

function parseSize( raw: string | null | undefined ): SelectSize {
	if ( raw === 'sm' || raw === 'lg' || raw === 'md' ) {
		return raw;
	}
	return 'md';
}

function parseVariant( raw: string | null | undefined ): SelectVariant {
	if ( raw === 'default' || raw === 'bank' || raw === 'meta' ) {
		return raw;
	}
	return 'meta';
}

function defaultOptionHtml(
	data: SelectOptionData,
	escape: EscapeFn,
	variant: SelectVariant
): string {
	const value = String( data.value ?? '' );
	if ( value === '' ) {
		return `<div class="bw-ss-opt bw-ss-opt--placeholder">${ escape( String( data.text ?? '' ) ) }</div>`;
	}
	if ( value === '__custom__' ) {
		return `<div class="bw-ss-opt bw-ss-opt--custom">${ escape( String( data.text ?? '' ) ) }</div>`;
	}

	if ( variant === 'default' ) {
		return `<div class="bw-ss-opt bw-ss-opt--plain">${ escape( optionPrimary( data ) || String( data.text ?? '' ) ) }</div>`;
	}

	// meta | bank — primary + optional chip
	const primary = optionPrimary( data );
	const meta = optionMeta( data );
	const codeLabel = escape( __( 'Code', 'wp-bizwit' ) );
	return (
		`<div class="bw-ss-opt bw-ss-opt--meta">` +
		`<span class="bw-ss-opt__primary">${ escape( primary ) }</span>` +
		( meta
			? `<span class="bw-ss-opt__meta" aria-label="${ codeLabel }">${ escape( meta ) }</span>`
			: '' ) +
		`</div>`
	);
}

function defaultItemHtml(
	data: SelectOptionData,
	escape: EscapeFn,
	variant: SelectVariant
): string {
	const value = String( data.value ?? '' );
	if ( value === '' || value === '__custom__' ) {
		return `<div class="bw-ss-item bw-ss-item--muted">${ escape( String( data.text ?? '' ) ) }</div>`;
	}

	if ( variant === 'default' ) {
		return `<div class="bw-ss-item">${ escape( optionPrimary( data ) || String( data.text ?? '' ) ) }</div>`;
	}

	const primary = optionPrimary( data );
	const meta = optionMeta( data );
	const codeLabel = escape( __( 'Code', 'wp-bizwit' ) );
	return (
		`<div class="bw-ss-item bw-ss-item--meta">` +
		`<span class="bw-ss-item__primary">${ escape( primary ) }</span>` +
		( meta
			? `<span class="bw-ss-item__meta" aria-label="${ codeLabel }">${ escape( meta ) }</span>`
			: '' ) +
		`</div>`
	);
}

/**
 * Show free-text bank name when user picks "Other bank" (__custom__).
 * Built into the enhancer so boot order does not matter.
 */
export function syncBankCustomField( select: HTMLSelectElement ): void {
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
			const opt = ts.options[ select.value ] as SelectOptionData | undefined;
			if ( opt ) {
				custom.value = optionPrimary( opt );
				return;
			}
		}
		const optEl = select.selectedOptions[ 0 ];
		custom.value =
			optEl?.getAttribute( 'data-name' ) ||
			optEl?.getAttribute( 'data-label' ) ||
			optEl?.textContent?.trim() ||
			'';
	}
}

function needsBankCustomSync( select: HTMLSelectElement, variant: SelectVariant ): boolean {
	return (
		variant === 'bank' ||
		select.hasAttribute( 'data-bank-select' ) ||
		!! select.closest( '.wp-bizwit-pay-card__field' )?.querySelector( '[data-bank-custom]' )
	);
}

function readDataConfig( select: HTMLSelectElement ): SearchableSelectOptions {
	const variantAttr =
		select.getAttribute( 'data-bw-select-variant' ) ||
		select.getAttribute( 'data-variant' ) ||
		( select.hasAttribute( 'data-bank-select' ) ? 'bank' : null );

	const maxRaw = select.getAttribute( 'data-max-options' );
	const maxItemsRaw = select.getAttribute( 'data-max-items' );
	const searchFields = select.getAttribute( 'data-search-fields' );
	const plugins = select.getAttribute( 'data-plugins' );
	const parent = select.getAttribute( 'data-dropdown-parent' );
	const size = parseSize( select.getAttribute( 'data-size' ) );
	const sortField = select.getAttribute( 'data-sort-field' );

	let maxItems: number | null | undefined;
	if ( maxItemsRaw === 'null' || maxItemsRaw === 'unlimited' ) {
		maxItems = null;
	} else if ( maxItemsRaw !== null && maxItemsRaw !== '' ) {
		const n = parseInt( maxItemsRaw, 10 );
		maxItems = Number.isFinite( n ) && n > 0 ? n : 1;
	}

	const cfg: SearchableSelectOptions = {
		variant: parseVariant( variantAttr ),
		size,
		placeholder:
			select.getAttribute( 'data-placeholder' ) ||
			select.getAttribute( 'placeholder' ) ||
			undefined,
		maxOptions: maxRaw ? Math.max( 1, parseInt( maxRaw, 10 ) || 100 ) : 100,
		maxItems,
		searchFields: searchFields
			? searchFields.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean )
			: [ ...DEFAULT_SEARCH_FIELDS ],
		dropdownParent: parent === 'body' || ! parent ? 'body' : parent,
		allowEmptyOption: parseBoolAttr( select.getAttribute( 'data-allow-empty' ), true ),
		plugins: plugins
			? plugins.split( ',' ).map( ( s ) => s.trim() ).filter( Boolean )
			: [ ...DEFAULT_PLUGINS ],
		noResultsText:
			select.getAttribute( 'data-no-results' ) ||
			__( 'No matches found', 'wp-bizwit' ),
		openOnFocus: parseBoolAttr( select.getAttribute( 'data-open-on-focus' ), true ),
		closeAfterSelect: parseBoolAttr( select.getAttribute( 'data-close-after-select' ), true ),
		hideSelected: parseBoolAttr( select.getAttribute( 'data-hide-selected' ), false ),
		copyClassesToDropdown: parseBoolAttr(
			select.getAttribute( 'data-copy-classes' ),
			true
		),
		dropdownClass: select.getAttribute( 'data-dropdown-class' ) || undefined,
		controlClass: select.getAttribute( 'data-control-class' ) || undefined,
		disabled:
			select.disabled ||
			parseBoolAttr( select.getAttribute( 'data-disabled' ), false ),
		create: parseBoolAttr( select.getAttribute( 'data-create' ), false ),
	};

	if ( sortField ) {
		cfg.sortField = sortField;
	}

	return cfg;
}

function applyWrapperClasses(
	select: HTMLSelectElement,
	instance: TomSelect,
	cfg: SearchableSelectOptions
): void {
	const wrapper = instance.wrapper as HTMLElement | undefined;
	if ( ! wrapper ) {
		return;
	}

	wrapper.classList.add( 'bw-ss-control' );
	wrapper.classList.add( `bw-ss-control--${ cfg.size ?? 'md' }` );
	wrapper.classList.add( `bw-ss-control--${ cfg.variant ?? 'meta' }` );
	if ( cfg.controlClass ) {
		cfg.controlClass
			.split( /\s+/ )
			.filter( Boolean )
			.forEach( ( c ) => wrapper.classList.add( c ) );
	}

	// Mark the original select for CSS / destroyAll queries.
	select.classList.add( 'bw-ss-enhanced' );
	select.dataset.bwSelectSize = cfg.size ?? 'md';
	select.dataset.bwSelectVariant = cfg.variant ?? 'meta';
}

/**
 * Enhance one &lt;select&gt; with Tom Select.
 *
 * @returns Tom Select instance, or the existing one if already enhanced.
 */
export function enhanceSearchableSelect(
	select: HTMLSelectElement,
	options: SearchableSelectOptions = {}
): TomSelect | null {
	const el = select as TomSelectEl;
	if ( el.tomselect ) {
		return el.tomselect;
	}

	const fromDom = readDataConfig( select );
	const cfg: SearchableSelectOptions = {
		...fromDom,
		...options,
		variant: options.variant ?? fromDom.variant ?? 'meta',
		size: options.size ?? fromDom.size ?? 'md',
		maxOptions: options.maxOptions ?? fromDom.maxOptions ?? 100,
		maxItems:
			options.maxItems !== undefined
				? options.maxItems
				: fromDom.maxItems !== undefined
					? fromDom.maxItems
					: 1,
		searchFields: options.searchFields ?? fromDom.searchFields ?? [ ...DEFAULT_SEARCH_FIELDS ],
		dropdownParent: options.dropdownParent ?? fromDom.dropdownParent ?? 'body',
		allowEmptyOption: options.allowEmptyOption ?? fromDom.allowEmptyOption ?? true,
		plugins: options.plugins ?? fromDom.plugins ?? [ ...DEFAULT_PLUGINS ],
		noResultsText:
			options.noResultsText ??
			fromDom.noResultsText ??
			__( 'No matches found', 'wp-bizwit' ),
		openOnFocus: options.openOnFocus ?? fromDom.openOnFocus ?? true,
		closeAfterSelect: options.closeAfterSelect ?? fromDom.closeAfterSelect ?? true,
		hideSelected: options.hideSelected ?? fromDom.hideSelected ?? false,
		copyClassesToDropdown:
			options.copyClassesToDropdown ?? fromDom.copyClassesToDropdown ?? true,
		dropdownClass: options.dropdownClass ?? fromDom.dropdownClass,
		controlClass: options.controlClass ?? fromDom.controlClass,
		disabled: options.disabled ?? fromDom.disabled ?? false,
		create: options.create ?? fromDom.create ?? false,
		sortField: options.sortField ?? fromDom.sortField,
	};

	const variant = cfg.variant ?? 'meta';
	const placeholder = cfg.placeholder ?? fromDom.placeholder ?? '';
	const bankSync = needsBankCustomSync( select, variant );

	// Always keep Tom Select's base `ts-dropdown` class — without it the panel
	// loses position:absolute (and our body > .ts-dropdown styles never match),
	// so the list dumps statically at the bottom of <body>.
	// Merge extras from top-level dropdownClass *and* tomSelect.dropdownClass,
	// but force the required layout classes last so they cannot be dropped.
	const extraDropdownClasses = [
		...( cfg.dropdownClass ? cfg.dropdownClass.split( /\s+/ ) : [] ),
		...( typeof cfg.tomSelect?.dropdownClass === 'string'
			? String( cfg.tomSelect.dropdownClass ).split( /\s+/ )
			: [] ),
	].filter( Boolean );

	const dropdownClassList = [
		'ts-dropdown',
		'bw-ss-dropdown',
		`bw-ss-dropdown--${ variant }`,
		...extraDropdownClasses,
	];
	// De-dupe while preserving order.
	const dropdownClass = [ ...new Set( dropdownClassList ) ].join( ' ' );

	// Escape hatch first; layout keys forced after so callers cannot re-break
	// body-portaled positioning via tomSelect.dropdownClass / dropdownParent.
	const userTom = { ...( cfg.tomSelect ?? {} ) };
	delete userTom.dropdownClass;
	delete userTom.render;
	delete userTom.onChange;
	delete userTom.onInitialize;
	delete userTom.onDropdownOpen;
	delete userTom.onDropdownClose;
	delete userTom.onType;

	const baseSettings: Record< string, unknown > = {
		allowEmptyOption: cfg.allowEmptyOption,
		create: cfg.create,
		maxOptions: cfg.maxOptions,
		maxItems: cfg.maxItems === null ? null : cfg.maxItems ?? 1,
		searchField: cfg.searchFields,
		optgroupField: 'optgroup',
		lockOptgroupOrder: true,
		plugins: cfg.plugins,
		placeholder,
		openOnFocus: cfg.openOnFocus,
		closeAfterSelect: cfg.closeAfterSelect,
		hideSelected: cfg.hideSelected,
		copyClassesToDropdown: cfg.copyClassesToDropdown,
		...( cfg.sortField
			? {
					sortField:
						typeof cfg.sortField === 'string'
							? cfg.sortField
							: cfg.sortField,
			  }
			: {} ),
		...userTom,
		// Forced after escape hatch:
		dropdownParent: cfg.dropdownParent ?? 'body',
		dropdownClass,
	};

	// Tom Select invokes onInitialize from the constructor; use `this` there —
	// the outer const is still in TDZ until `new` returns.
	const instance = new TomSelect( select, {
		...baseSettings,
		render: {
			option( data: SelectOptionData, escape: EscapeFn ) {
				if ( cfg.renderOption ) {
					return cfg.renderOption( data, escape );
				}
				return defaultOptionHtml( data, escape, variant );
			},
			item( data: SelectOptionData, escape: EscapeFn ) {
				if ( cfg.renderItem ) {
					return cfg.renderItem( data, escape );
				}
				return defaultItemHtml( data, escape, variant );
			},
			optgroup_header( data: { label?: string }, escape: EscapeFn ) {
				if ( cfg.renderOptgroupHeader ) {
					return cfg.renderOptgroupHeader( data, escape );
				}
				return `<div class="bw-ss-group">${ escape( String( data.label ?? '' ) ) }</div>`;
			},
			no_results( data: unknown, escape: EscapeFn ) {
				if ( cfg.renderNoResults ) {
					return cfg.renderNoResults( data, escape );
				}
				return `<div class="bw-ss-empty">${ escape( cfg.noResultsText ?? '' ) }</div>`;
			},
		},
		onChange( value: string | number | string[] ) {
			const str = Array.isArray( value ) ? value.join( ',' ) : String( value );
			if ( bankSync ) {
				syncBankCustomField( select );
			}
			cfg.onChange?.( str, select );
		},
		onInitialize() {
			const ts = this as unknown as TomSelect;
			applyWrapperClasses( select, ts, cfg );
			if ( cfg.disabled ) {
				ts.disable();
			}
			if ( bankSync ) {
				syncBankCustomField( select );
			}
			cfg.onInitialize?.( select, ts );
		},
		onDropdownOpen( dropdown: HTMLElement ) {
			cfg.onDropdownOpen?.( dropdown, select );
		},
		onDropdownClose( dropdown: HTMLElement ) {
			cfg.onDropdownClose?.( dropdown, select );
		},
		onType( str: string ) {
			cfg.onType?.( str, select );
		},
	} );

	return instance;
}

/**
 * Destroy Tom Select on a select (safe if not enhanced).
 */
export function destroySearchableSelect( select: HTMLSelectElement ): void {
	const el = select as TomSelectEl;
	if ( el.tomselect ) {
		el.tomselect.destroy();
	}
	select.classList.remove( 'bw-ss-enhanced', 'tomselected', 'ts-hidden-accessible' );
	select.removeAttribute( 'tabindex' );
	select.style.display = '';
	select.style.position = '';
	delete select.dataset.bwSelectSize;
	delete select.dataset.bwSelectVariant;
}

/**
 * Enhance every `[data-bw-select]` (and legacy `[data-bank-select]`) under root.
 */
export function enhanceAllSearchableSelects(
	root: ParentNode = document,
	options: SearchableSelectOptions = {}
): void {
	const nodes = root.querySelectorAll< HTMLSelectElement >(
		'select[data-bw-select], select[data-bank-select]'
	);
	nodes.forEach( ( select ) => {
		const opts = { ...options };
		if (
			select.hasAttribute( 'data-bank-select' ) &&
			! opts.variant &&
			! select.getAttribute( 'data-bw-select-variant' ) &&
			! select.getAttribute( 'data-variant' )
		) {
			opts.variant = 'bank';
		}
		enhanceSearchableSelect( select, opts );
	} );
}

/**
 * Destroy every enhanced select under root.
 */
export function destroyAllSearchableSelects( root: ParentNode = document ): void {
	root
		.querySelectorAll< HTMLSelectElement >(
			'select[data-bw-select], select[data-bank-select], select.bw-ss-enhanced'
		)
		.forEach( destroySearchableSelect );
}

/** Public API for other BizWit scripts and `wp eval` / console debugging. */
export type SearchableSelectApi = {
	enhance: typeof enhanceSearchableSelect;
	destroy: typeof destroySearchableSelect;
	enhanceAll: typeof enhanceAllSearchableSelects;
	destroyAll: typeof destroyAllSearchableSelects;
	optionPrimary: typeof optionPrimary;
	optionMeta: typeof optionMeta;
	syncBankCustomField: typeof syncBankCustomField;
};

declare global {
	interface Window {
		wpBizwitSearchableSelect?: SearchableSelectApi;
	}
}

export function exposeSearchableSelectApi(): void {
	window.wpBizwitSearchableSelect = {
		enhance: enhanceSearchableSelect,
		destroy: destroySearchableSelect,
		enhanceAll: enhanceAllSearchableSelects,
		destroyAll: destroyAllSearchableSelects,
		optionPrimary,
		optionMeta,
		syncBankCustomField,
	};
}
