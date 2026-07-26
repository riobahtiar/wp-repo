<script setup lang="ts">
/**
 * Document layout builder — design canvas + live print-style preview.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Zone = 'header' | 'body' | 'footer';
type Mode = 'design' | 'preview';

interface ComponentNode {
	id: string;
	type: string;
	props: Record< string, unknown >;
}

interface LayoutDoc {
	version: number;
	page: { size: string; marginMm: number };
	sections: Record< Zone, { components: ComponentNode[] } >;
}

interface BuilderConfig {
	layout: LayoutDoc;
	fields: Record< string, string >;
	sampleValues: Record< string, string >;
	/** English msgid → translated string for the active locale. */
	chrome: Record< string, string >;
	locale: string;
	documentCss: string;
	componentMeta: Record< string, { label: string; description: string; icon?: string } >;
	zones: Record< Zone, string >;
	i18n: Record< string, string >;
	defaultLayout: LayoutDoc;
}

function uid(): string {
	return 'c_' + Math.random().toString( 36 ).slice( 2, 10 );
}

function deepClone< T >( v: T ): T {
	return JSON.parse( JSON.stringify( v ) ) as T;
}

function emptyConfig(): BuilderConfig {
	return {
		layout: {
			version: 1,
			page: { size: 'A4', marginMm: 14 },
			sections: {
				header: { components: [] },
				body: { components: [] },
				footer: { components: [] },
			},
		},
		fields: {},
		sampleValues: {},
		chrome: {},
		locale: 'en_US',
		documentCss: '',
		componentMeta: {},
		zones: { header: 'Header', body: 'Body', footer: 'Footer' },
		i18n: {},
		defaultLayout: {
			version: 1,
			page: { size: 'A4', marginMm: 14 },
			sections: {
				header: { components: [] },
				body: { components: [] },
				footer: { components: [] },
			},
		},
	};
}

function readConfig(): BuilderConfig {
	// Prefer JSON script node (reliable for large payloads + unicode).
	const script = document.getElementById( 'wp-bizwit-template-builder-config' );
	const fromScript = script?.textContent?.trim();
	if ( fromScript ) {
		try {
			return JSON.parse( fromScript ) as BuilderConfig;
		} catch {
			// fall through
		}
	}

	// Legacy fallback: data-config attribute.
	const el = document.getElementById( 'wp-bizwit-template-builder' );
	const raw = el?.getAttribute( 'data-config' ) || '';
	if ( raw ) {
		try {
			return JSON.parse( raw ) as BuilderConfig;
		} catch {
			// fall through
		}
	}

	return emptyConfig();
}

const config = readConfig();
const t = ( key: string, fallback: string ) => config.i18n[ key ] || fallback;

const layout = reactive< LayoutDoc >( deepClone( config.layout ) );
const selectedId = ref< string | null >( null );
const activeZone = ref< Zone >( 'body' );
const mode = ref< Mode >( 'design' );

const hiddenInput = () =>
	document.getElementById( 'wp-bizwit-layout-json' ) as HTMLInputElement | null;

function syncHidden(): void {
	const input = hiddenInput();
	if ( input ) {
		input.value = JSON.stringify( layout );
	}
}

watch( layout, () => syncHidden(), { deep: true } );
onMounted( () => {
	syncHidden();
	// Expand the postbox to full width of the editor column.
	const box = document.getElementById( 'wp-bizwit-layout-builder' );
	if ( box ) {
		box.classList.add( 'bw-lb-host' );
	}
} );

const zones: Zone[] = [ 'header', 'body', 'footer' ];

const paletteTypes = computed( () => Object.keys( config.componentMeta ) );

const selected = computed( () => {
	if ( ! selectedId.value ) {
		return null;
	}
	return findComponent( selectedId.value );
} );

function findComponent(
	id: string
): { node: ComponentNode; zone: Zone; index: number; parent: ComponentNode[] } | null {
	for ( const zone of zones ) {
		const list = layout.sections[ zone ].components;
		for ( let i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === id ) {
				return { node: list[ i ], zone, index: i, parent: list };
			}
			if ( list[ i ].type === 'columns' ) {
				const cols = list[ i ].props.columns as ComponentNode[][] | undefined;
				if ( cols ) {
					for ( const col of cols ) {
						for ( let j = 0; j < col.length; j++ ) {
							if ( col[ j ].id === id ) {
								return { node: col[ j ], zone, index: j, parent: col };
							}
						}
					}
				}
			}
		}
	}
	return null;
}

function defaultProps( type: string ): Record< string, unknown > {
	switch ( type ) {
		case 'heading':
			return {
				content: 'Heading',
				level: 3,
				fontSize: 11,
				fontWeight: '600',
				align: 'left',
				color: '#5c6570',
				marginTop: 0,
				marginBottom: 8,
			};
		case 'text':
			return {
				content: '',
				fontSize: 11,
				fontWeight: '400',
				align: 'left',
				color: '#1a2332',
				marginTop: 0,
				marginBottom: 6,
			};
		case 'field':
			return {
				field: 'invoice_number',
				showLabel: false,
				fontSize: 12,
				fontWeight: '400',
				align: 'left',
				color: '#1a2332',
				marginTop: 0,
				marginBottom: 4,
			};
		case 'line_items':
			return { showTax: true, marginTop: 8, marginBottom: 8 };
		case 'totals':
			return { showTerbilang: true, align: 'right', marginTop: 8, marginBottom: 8 };
		case 'bank':
			return { showTitle: false, marginTop: 4, marginBottom: 8 };
		case 'signature':
			return { marginTop: 24, marginBottom: 0 };
		case 'spacer':
			return { height: 16 };
		case 'divider':
			return { color: '#e2e6ea', marginTop: 8, marginBottom: 8 };
		case 'columns':
			return { gap: 28, columns: [ [], [] ], marginTop: 0, marginBottom: 8 };
		default:
			return {};
	}
}

function addComponent( type: string, zone?: Zone ): void {
	const z = zone ?? activeZone.value;
	const node: ComponentNode = {
		id: uid(),
		type,
		props: defaultProps( type ),
	};
	layout.sections[ z ].components.push( node );
	selectedId.value = node.id;
	activeZone.value = z;
	mode.value = 'design';
}

function removeSelected(): void {
	const hit = selected.value;
	if ( ! hit ) {
		return;
	}
	hit.parent.splice( hit.index, 1 );
	selectedId.value = null;
}

function moveSelected( dir: -1 | 1 ): void {
	const hit = selected.value;
	if ( ! hit ) {
		return;
	}
	const j = hit.index + dir;
	if ( j < 0 || j >= hit.parent.length ) {
		return;
	}
	const tmp = hit.parent[ hit.index ];
	hit.parent[ hit.index ] = hit.parent[ j ];
	hit.parent[ j ] = tmp;
}

function duplicateSelected(): void {
	const hit = selected.value;
	if ( ! hit ) {
		return;
	}
	const copy = deepClone( hit.node );
	copy.id = uid();
	if ( copy.type === 'columns' && Array.isArray( copy.props.columns ) ) {
		copy.props.columns = ( copy.props.columns as ComponentNode[][] ).map( ( col ) =>
			col.map( ( c ) => ( { ...deepClone( c ), id: uid() } ) )
		);
	}
	hit.parent.splice( hit.index + 1, 0, copy );
	selectedId.value = copy.id;
}

function select( id: string, zone: Zone ): void {
	selectedId.value = id;
	activeZone.value = zone;
	mode.value = 'design';
}

function resetDefault(): void {
	if ( ! window.confirm( t( 'resetDefault', 'Reset to default layout' ) + '?' ) ) {
		return;
	}
	const next = deepClone( config.defaultLayout );
	layout.version = next.version;
	layout.page = next.page;
	layout.sections = next.sections;
	selectedId.value = null;
}

function sampleField( field: string ): string {
	return config.sampleValues[ field ] || '—';
}

/** Translate stored English chrome (layout headings) for the active locale. */
function chrome( text: string ): string {
	const key = String( text || '' ).trim();
	if ( ! key ) {
		return '';
	}
	return config.chrome[ key ] || key;
}

/** Heading / free-text display: translate known chrome, keep custom text as-is. */
function displayText( content: unknown ): string {
	return chrome( String( content || '' ) );
}

/** sprintf-style %s replace for PHP-translated patterns (For %s, In words: %s). */
function tFormat( key: string, fallback: string, value: string ): string {
	const pattern = t( key, fallback );
	return pattern.replace( /%s/g, value );
}

function styleOf( node: ComponentNode ): Record< string, string > {
	const p = node.props;
	const s: Record< string, string > = {};
	if ( p.align ) {
		s.textAlign = String( p.align );
	}
	if ( p.fontSize ) {
		s.fontSize = `${ p.fontSize }px`;
	}
	if ( p.fontWeight ) {
		s.fontWeight = String( p.fontWeight );
	}
	if ( p.color ) {
		s.color = String( p.color );
	}
	if ( p.marginTop !== undefined ) {
		s.marginTop = `${ p.marginTop }px`;
	}
	if ( p.marginBottom !== undefined ) {
		s.marginBottom = `${ p.marginBottom }px`;
	}
	if ( node.type === 'spacer' && p.height ) {
		s.height = `${ p.height }px`;
	}
	return s;
}

function setProp( key: string, value: unknown ): void {
	const hit = selected.value;
	if ( ! hit ) {
		return;
	}
	hit.node.props[ key ] = value;
}

function fieldDisplay( node: ComponentNode ): string {
	const f = String( node.props.field || '' );
	const val = sampleField( f );
	if ( node.props.showLabel ) {
		const lab = config.fields[ f ] || f;
		return `${ lab }: ${ val }`;
	}
	return val;
}

const fieldOptions = computed( () =>
	Object.entries( config.fields ).map( ( [ value, label ] ) => ( { value, label } ) )
);

const icons: Record< string, string > = {
	heading: 'H',
	text: 'T',
	field: '··',
	line_items: '☰',
	totals: 'Σ',
	bank: '₳',
	signature: '✍',
	spacer: '↕',
	divider: '—',
	columns: '▥',
};
</script>

<template>
	<div class="bw-studio">
		<header class="bw-studio__top">
			<div class="bw-studio__brand">
				<span class="bw-studio__brand-mark">A4</span>
				<div>
					<strong>{{ t( 'studioTitle', 'Document studio' ) }}</strong>
					<span>{{ t( 'studioHint', 'Design the layout · preview as printed' ) }}</span>
				</div>
			</div>
			<div class="bw-studio__modes" role="tablist">
				<button
					type="button"
					role="tab"
					:aria-selected="mode === 'design'"
					:class="{ 'is-active': mode === 'design' }"
					@click="mode = 'design'"
				>
					{{ t( 'modeDesign', 'Design' ) }}
				</button>
				<button
					type="button"
					role="tab"
					:aria-selected="mode === 'preview'"
					:class="{ 'is-active': mode === 'preview' }"
					@click="mode = 'preview'"
				>
					{{ t( 'modePreview', 'Print preview' ) }}
				</button>
			</div>
			<button type="button" class="bw-studio__ghost" @click="resetDefault">
				{{ t( 'resetDefault', 'Reset default' ) }}
			</button>
		</header>

		<div class="bw-studio__body" :class="{ 'bw-studio__body--preview': mode === 'preview' }">
			<!-- Palette (design only) -->
			<aside v-show="mode === 'design'" class="bw-studio__side bw-studio__side--left">
				<div class="bw-studio__side-title">{{ t( 'palette', 'Components' ) }}</div>
				<p class="bw-studio__side-hint">
					{{ t( 'paletteHint', 'Click to add into the active section' ) }}
				</p>
				<button
					v-for="type in paletteTypes"
					:key="type"
					type="button"
					class="bw-studio__chip"
					@click="addComponent( type )"
				>
					<span class="bw-studio__chip-icon">{{ icons[ type ] || '+' }}</span>
					<span class="bw-studio__chip-text">
						<strong>{{ config.componentMeta[ type ]?.label || type }}</strong>
						<small>{{ config.componentMeta[ type ]?.description }}</small>
					</span>
				</button>
			</aside>

			<!-- Canvas -->
			<main class="bw-studio__canvas-wrap">
				<div
					class="bw-studio__paper"
					:class="{ 'is-preview': mode === 'preview' }"
					:style="{ padding: layout.page.marginMm + 'mm' }"
				>
					<section
						v-for="zone in zones"
						:key="zone"
						class="bw-studio__zone"
						:class="[
							'bw-studio__zone--' + zone,
							{
								'is-active': mode === 'design' && activeZone === zone,
								'is-preview': mode === 'preview',
							},
						]"
						@click.self="activeZone = zone"
					>
						<div v-if="mode === 'design'" class="bw-studio__zone-tag">
							{{ config.zones[ zone ] }}
						</div>

						<div
							v-if="layout.sections[ zone ].components.length === 0"
							class="bw-studio__empty"
							@click="activeZone = zone"
						>
							{{ t( 'emptyZone', 'Add components to this section' ) }}
						</div>

						<div
							v-for="node in layout.sections[ zone ].components"
							:key="node.id"
							class="bw-studio__block"
							:class="{
								'is-selected': mode === 'design' && selectedId === node.id,
								'is-preview': mode === 'preview',
								[ 'type-' + node.type ]: true,
							}"
							:style="styleOf( node )"
							@click.stop="mode === 'design' && select( node.id, zone )"
						>
							<!-- DESIGN chrome label -->
							<span v-if="mode === 'design'" class="bw-studio__block-badge">
								{{ config.componentMeta[ node.type ]?.label || node.type }}
							</span>

							<template v-if="node.type === 'divider'">
								<hr class="bw-studio__hr" :style="{ borderColor: String( node.props.color || '#e2e6ea' ) }" />
							</template>
							<template v-else-if="node.type === 'spacer'">
								<div
									class="bw-studio__spacer"
									:class="{ 'is-design': mode === 'design' }"
									:style="{ height: ( node.props.height || 16 ) + 'px' }"
								/>
							</template>
							<template v-else-if="node.type === 'line_items'">
								<table class="bw-studio__table">
									<thead>
										<tr>
											<th>{{ t( 'colDescription', 'Description' ) }}</th>
											<th class="num">{{ t( 'colQty', 'Qty' ) }}</th>
											<th class="num">{{ t( 'colUnitPrice', 'Unit price' ) }}</th>
											<th class="num">{{ t( 'colAmount', 'Amount' ) }}</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>{{ sampleField( '_item1' ) }}</td>
											<td class="num">1</td>
											<td class="num">{{ sampleField( '_price1' ) }}</td>
											<td class="num">{{ sampleField( '_price1' ) }}</td>
										</tr>
										<tr>
											<td>{{ sampleField( '_item2' ) }}</td>
											<td class="num">1</td>
											<td class="num">{{ sampleField( '_price2' ) }}</td>
											<td class="num">{{ sampleField( '_price2' ) }}</td>
										</tr>
									</tbody>
								</table>
							</template>
							<template v-else-if="node.type === 'totals'">
								<div
									class="bw-studio__totals"
									:style="{ textAlign: ( String( node.props.align || 'right' ) as 'left' | 'center' | 'right' ) }"
								>
									<table>
										<tr>
											<td>{{ t( 'labelSubtotal', 'Subtotal' ) }}</td>
											<td class="num">{{ sampleField( 'subtotal' ) }}</td>
										</tr>
										<tr class="grand">
											<td>{{ t( 'labelTotal', 'Total' ) }}</td>
											<td class="num">{{ sampleField( 'total' ) }}</td>
										</tr>
									</table>
									<p v-if="node.props.showTerbilang !== false" class="bw-studio__words">
										{{
											tFormat(
												'labelInWords',
												'In words: %s',
												sampleField( 'total_in_words' )
											)
										}}
									</p>
								</div>
							</template>
							<template v-else-if="node.type === 'bank'">
								<div class="bw-studio__bank">
									{{ sampleField( 'bank_block' ) || t( 'bankPlaceholder', 'Bank transfer details' ) }}
								</div>
							</template>
							<template v-else-if="node.type === 'signature'">
								<div class="bw-studio__sign">
									<div>
										<strong>{{ t( 'labelReceivedBy', 'Received by' ) }}</strong>
										<span>{{ t( 'labelNameSign', 'Name & signature' ) }}</span>
									</div>
									<div>
										<strong>{{
											tFormat(
												'labelFor',
												'For %s',
												sampleField( 'business_name' ) || '—'
											)
										}}</strong>
										<span>{{ t( 'labelStamp', 'Signature and company stamp' ) }}</span>
									</div>
								</div>
							</template>
							<template v-else-if="node.type === 'columns'">
								<div class="bw-studio__cols" :style="{ gap: ( node.props.gap || 28 ) + 'px' }">
									<div
										v-for="( col, ci ) in ( node.props.columns as ComponentNode[][] ) || [ [], [] ]"
										:key="ci"
										class="bw-studio__col"
										:class="{ 'is-design': mode === 'design' }"
									>
										<div
											v-for="child in col"
											:key="child.id"
											class="bw-studio__block bw-studio__block--nested"
											:class="{ 'is-selected': mode === 'design' && selectedId === child.id }"
											:style="styleOf( child )"
											@click.stop="mode === 'design' && select( child.id, zone )"
										>
											<template v-if="child.type === 'field'">
												{{ fieldDisplay( child ) }}
											</template>
											<template v-else-if="child.type === 'heading' || child.type === 'text'">
												{{ displayText( child.props.content ) }}
											</template>
											<template v-else>
												{{ config.componentMeta[ child.type ]?.label }}
											</template>
										</div>
										<button
											v-if="mode === 'design'"
											type="button"
											class="bw-studio__col-add"
											@click.stop="
												( node.props.columns as ComponentNode[][] )[ ci ].push( {
													id: uid(),
													type: 'field',
													props: defaultProps( 'field' ),
												} )
											"
										>
											+ {{ t( 'addField', 'Field' ) }}
										</button>
									</div>
								</div>
							</template>
							<template v-else-if="node.type === 'field'">
								{{ fieldDisplay( node ) }}
							</template>
							<template v-else-if="node.type === 'heading' || node.type === 'text'">
								{{ displayText( node.props.content ) }}
							</template>
							<template v-else>
								{{ config.componentMeta[ node.type ]?.label }}
							</template>
						</div>
					</section>
				</div>
			</main>

			<!-- Properties (design only) -->
			<aside v-show="mode === 'design'" class="bw-studio__side bw-studio__side--right">
				<div class="bw-studio__side-title">{{ t( 'properties', 'Properties' ) }}</div>
				<div v-if="! selected" class="bw-studio__empty-props">
					{{ t( 'selectHint', 'Select a block on the page to edit it.' ) }}
				</div>
				<template v-else>
					<div class="bw-studio__sel-type">
						{{ config.componentMeta[ selected.node.type ]?.label || selected.node.type }}
					</div>
					<div class="bw-studio__toolbar">
						<button type="button" class="button button-small" :title="t( 'moveUp', 'Up' )" @click="moveSelected( -1 )">↑</button>
						<button type="button" class="button button-small" :title="t( 'moveDown', 'Down' )" @click="moveSelected( 1 )">↓</button>
						<button type="button" class="button button-small" @click="duplicateSelected">
							{{ t( 'duplicate', 'Duplicate' ) }}
						</button>
						<button type="button" class="button button-small button-link-delete" @click="removeSelected">
							{{ t( 'remove', 'Remove' ) }}
						</button>
					</div>

					<label v-if="selected.node.type === 'field'" class="bw-f">
						<span>{{ t( 'field', 'Data field' ) }}</span>
						<select
							:value="String( selected.node.props.field || '' )"
							@change="setProp( 'field', ( $event.target as HTMLSelectElement ).value )"
						>
							<option v-for="opt in fieldOptions" :key="opt.value" :value="opt.value">
								{{ opt.label }}
							</option>
						</select>
					</label>
					<label v-if="selected.node.type === 'field'" class="bw-check">
						<input
							type="checkbox"
							:checked="!! selected.node.props.showLabel"
							@change="setProp( 'showLabel', ( $event.target as HTMLInputElement ).checked )"
						/>
						{{ t( 'showLabel', 'Show label' ) }}
					</label>

					<label
						v-if="selected.node.type === 'heading' || selected.node.type === 'text'"
						class="bw-f"
					>
						<span>{{ t( 'content', 'Text' ) }}</span>
						<textarea
							v-if="selected.node.type === 'text'"
							rows="3"
							:value="String( selected.node.props.content || '' )"
							@input="setProp( 'content', ( $event.target as HTMLTextAreaElement ).value )"
						/>
						<input
							v-else
							type="text"
							:value="String( selected.node.props.content || '' )"
							@input="setProp( 'content', ( $event.target as HTMLInputElement ).value )"
						/>
					</label>

					<template v-if="[ 'heading', 'text', 'field', 'totals' ].includes( selected.node.type )">
						<label class="bw-f">
							<span>{{ t( 'align', 'Alignment' ) }}</span>
							<select
								:value="String( selected.node.props.align || 'left' )"
								@change="setProp( 'align', ( $event.target as HTMLSelectElement ).value )"
							>
								<option value="left">{{ t( 'left', 'Left' ) }}</option>
								<option value="center">{{ t( 'center', 'Center' ) }}</option>
								<option value="right">{{ t( 'right', 'Right' ) }}</option>
							</select>
						</label>
					</template>
					<template v-if="[ 'heading', 'text', 'field' ].includes( selected.node.type )">
						<label class="bw-f">
							<span>{{ t( 'fontSize', 'Font size' ) }}</span>
							<input
								type="range"
								min="9"
								max="28"
								:value="Number( selected.node.props.fontSize || 12 )"
								@input="setProp( 'fontSize', Number( ( $event.target as HTMLInputElement ).value ) )"
							/>
							<em>{{ selected.node.props.fontSize || 12 }}px</em>
						</label>
						<label class="bw-f">
							<span>{{ t( 'fontWeight', 'Weight' ) }}</span>
							<select
								:value="String( selected.node.props.fontWeight || '400' )"
								@change="setProp( 'fontWeight', ( $event.target as HTMLSelectElement ).value )"
							>
								<option value="400">Regular</option>
								<option value="600">Semi-bold</option>
								<option value="700">Bold</option>
							</select>
						</label>
						<label class="bw-f">
							<span>{{ t( 'color', 'Colour' ) }}</span>
							<input
								type="color"
								:value="String( selected.node.props.color || '#1a2332' )"
								@input="setProp( 'color', ( $event.target as HTMLInputElement ).value )"
							/>
						</label>
					</template>

					<label v-if="selected.node.type === 'spacer'" class="bw-f">
						<span>{{ t( 'height', 'Height' ) }}</span>
						<input
							type="range"
							min="4"
							max="80"
							:value="Number( selected.node.props.height || 16 )"
							@input="setProp( 'height', Number( ( $event.target as HTMLInputElement ).value ) )"
						/>
						<em>{{ selected.node.props.height || 16 }}px</em>
					</label>
					<label v-if="selected.node.type === 'line_items'" class="bw-check">
						<input
							type="checkbox"
							:checked="selected.node.props.showTax !== false"
							@change="setProp( 'showTax', ( $event.target as HTMLInputElement ).checked )"
						/>
						{{ t( 'showTax', 'Show tax column' ) }}
					</label>
					<label v-if="selected.node.type === 'totals'" class="bw-check">
						<input
							type="checkbox"
							:checked="selected.node.props.showTerbilang !== false"
							@change="setProp( 'showTerbilang', ( $event.target as HTMLInputElement ).checked )"
						/>
						{{ t( 'showTerbilang', 'Show amount in words' ) }}
					</label>
					<label v-if="selected.node.type === 'columns'" class="bw-f">
						<span>{{ t( 'gap', 'Column gap' ) }}</span>
						<input
							type="range"
							min="8"
							max="48"
							:value="Number( selected.node.props.gap || 28 )"
							@input="setProp( 'gap', Number( ( $event.target as HTMLInputElement ).value ) )"
						/>
						<em>{{ selected.node.props.gap || 28 }}px</em>
					</label>
					<label v-if="selected.node.type !== 'spacer'" class="bw-f">
						<span>{{ t( 'marginTop', 'Space above' ) }}</span>
						<input
							type="range"
							min="0"
							max="48"
							:value="Number( selected.node.props.marginTop || 0 )"
							@input="setProp( 'marginTop', Number( ( $event.target as HTMLInputElement ).value ) )"
						/>
						<em>{{ selected.node.props.marginTop || 0 }}px</em>
					</label>
					<label v-if="selected.node.type !== 'spacer'" class="bw-f">
						<span>{{ t( 'marginBottom', 'Space below' ) }}</span>
						<input
							type="range"
							min="0"
							max="48"
							:value="Number( selected.node.props.marginBottom || 0 )"
							@input="setProp( 'marginBottom', Number( ( $event.target as HTMLInputElement ).value ) )"
						/>
						<em>{{ selected.node.props.marginBottom || 0 }}px</em>
					</label>
				</template>
			</aside>
		</div>
	</div>
</template>

<style scoped>
.bw-studio {
	--ink: #1a2332;
	--muted: #5c6570;
	--line: #e2e6ea;
	--accent: #1e4d6b;
	--soft: #f4f7f9;
	--paper-shadow: 0 12px 40px rgba(26, 35, 50, 0.14);
	font-family: "Segoe UI", system-ui, sans-serif;
	color: var(--ink);
	background: linear-gradient(165deg, #dfe6ec 0%, #eef2f5 40%, #e4e9ee 100%);
	border-radius: 12px;
	overflow: hidden;
	border: 1px solid #cfd6dd;
	min-height: 720px;
}
.bw-studio__top {
	display: flex;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
	padding: 12px 16px;
	background: #fff;
	border-bottom: 1px solid var(--line);
}
.bw-studio__brand {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-right: auto;
}
.bw-studio__brand-mark {
	display: grid;
	place-items: center;
	width: 36px;
	height: 36px;
	border-radius: 8px;
	background: var(--accent);
	color: #fff;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.04em;
}
.bw-studio__brand strong {
	display: block;
	font-size: 14px;
}
.bw-studio__brand span {
	display: block;
	font-size: 11px;
	color: var(--muted);
}
.bw-studio__modes {
	display: inline-flex;
	padding: 3px;
	background: var(--soft);
	border-radius: 8px;
	border: 1px solid var(--line);
}
.bw-studio__modes button {
	border: 0;
	background: transparent;
	padding: 7px 14px;
	border-radius: 6px;
	font-size: 12px;
	font-weight: 600;
	color: var(--muted);
	cursor: pointer;
}
.bw-studio__modes button.is-active {
	background: #fff;
	color: var(--ink);
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}
.bw-studio__ghost {
	border: 1px solid var(--line);
	background: #fff;
	border-radius: 6px;
	padding: 7px 12px;
	font-size: 12px;
	cursor: pointer;
	color: var(--muted);
}
.bw-studio__ghost:hover {
	border-color: var(--accent);
	color: var(--accent);
}
.bw-studio__body {
	display: grid;
	grid-template-columns: 220px minmax(0, 1fr) 260px;
	min-height: 640px;
}
.bw-studio__body--preview {
	grid-template-columns: 1fr;
}
.bw-studio__side {
	background: #fff;
	padding: 14px 12px;
	overflow: auto;
	border-right: 1px solid var(--line);
}
.bw-studio__side--right {
	border-right: 0;
	border-left: 1px solid var(--line);
}
.bw-studio__side-title {
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--muted);
	margin-bottom: 6px;
}
.bw-studio__side-hint {
	font-size: 11px;
	color: var(--muted);
	margin: 0 0 12px;
	line-height: 1.4;
}
.bw-studio__chip {
	display: flex;
	gap: 10px;
	width: 100%;
	align-items: flex-start;
	text-align: left;
	border: 1px solid var(--line);
	background: var(--soft);
	border-radius: 10px;
	padding: 10px;
	margin-bottom: 8px;
	cursor: pointer;
	transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
}
.bw-studio__chip:hover {
	border-color: var(--accent);
	box-shadow: 0 4px 12px rgba(30, 77, 107, 0.1);
	transform: translateY(-1px);
}
.bw-studio__chip-icon {
	flex: 0 0 28px;
	height: 28px;
	display: grid;
	place-items: center;
	border-radius: 6px;
	background: #fff;
	border: 1px solid var(--line);
	font-size: 12px;
	font-weight: 700;
	color: var(--accent);
}
.bw-studio__chip-text strong {
	display: block;
	font-size: 12px;
}
.bw-studio__chip-text small {
	display: block;
	font-size: 11px;
	color: var(--muted);
	margin-top: 2px;
}
.bw-studio__canvas-wrap {
	padding: 28px 20px 40px;
	overflow: auto;
	display: flex;
	justify-content: center;
}
.bw-studio__paper {
	width: min(210mm, 100%);
	min-height: 280mm;
	background: #fff;
	box-shadow: var(--paper-shadow);
	border-radius: 2px;
}
.bw-studio__paper.is-preview .bw-studio__zone--header {
	border-bottom: 2.5px solid var(--accent);
	padding-bottom: 12px;
	margin-bottom: 16px;
}
.bw-studio__zone {
	position: relative;
	min-height: 56px;
	padding: 10px 4px 14px;
}
.bw-studio__zone.is-active:not(.is-preview) {
	background: linear-gradient(180deg, rgba(30, 77, 107, 0.04), transparent);
	outline: 1px dashed rgba(30, 77, 107, 0.25);
	outline-offset: 2px;
	border-radius: 6px;
}
.bw-studio__zone-tag {
	font-size: 9px;
	font-weight: 700;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: #8a939c;
	margin-bottom: 8px;
}
.bw-studio__empty {
	border: 1.5px dashed #c5ced6;
	border-radius: 8px;
	padding: 20px;
	text-align: center;
	color: #8a939c;
	font-size: 12px;
	cursor: pointer;
	background: rgba(255, 255, 255, 0.6);
}
.bw-studio__block {
	position: relative;
	padding: 4px 6px;
	margin-bottom: 2px;
	border-radius: 4px;
	cursor: pointer;
	line-height: 1.45;
	word-break: break-word;
	border: 1px solid transparent;
}
.bw-studio__block:not(.is-preview):hover {
	background: rgba(30, 77, 107, 0.04);
}
.bw-studio__block.is-selected {
	border-color: var(--accent) !important;
	box-shadow: 0 0 0 1px var(--accent);
	background: rgba(30, 77, 107, 0.06);
}
.bw-studio__block.is-preview {
	cursor: default;
	border: 0 !important;
	box-shadow: none !important;
	background: transparent !important;
	padding-left: 0;
	padding-right: 0;
}
.bw-studio__block-badge {
	position: absolute;
	top: -8px;
	right: 4px;
	font-size: 9px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	background: var(--accent);
	color: #fff;
	padding: 1px 6px;
	border-radius: 99px;
	opacity: 0;
	pointer-events: none;
	transition: opacity 0.12s;
}
.bw-studio__block:not(.is-preview):hover .bw-studio__block-badge,
.bw-studio__block.is-selected .bw-studio__block-badge {
	opacity: 1;
}
.bw-studio__block--nested {
	font-size: 12px;
}
.bw-studio__hr {
	border: 0;
	border-top: 1px solid #e2e6ea;
	margin: 4px 0;
}
.bw-studio__spacer.is-design {
	background: repeating-linear-gradient(
		-45deg,
		#f0f3f6,
		#f0f3f6 4px,
		#e4e9ee 4px,
		#e4e9ee 8px
	);
	border-radius: 3px;
	opacity: 0.85;
}
.bw-studio__table {
	width: 100%;
	border-collapse: collapse;
	font-size: 11px;
	margin: 4px 0;
}
.bw-studio__table th {
	background: var(--accent);
	color: #fff;
	text-align: left;
	padding: 8px 10px;
	font-size: 9px;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.bw-studio__table td {
	padding: 8px 10px;
	border-bottom: 1px solid var(--line);
}
.bw-studio__table tr:nth-child(even) td {
	background: var(--soft);
}
.bw-studio__table .num {
	text-align: right;
	white-space: nowrap;
}
.bw-studio__totals table {
	width: 220px;
	margin-left: auto;
	border-collapse: collapse;
	font-size: 12px;
}
.bw-studio__totals td {
	padding: 6px 10px;
	border-bottom: 1px solid var(--line);
}
.bw-studio__totals tr.grand td {
	background: #f0f5f8;
	font-weight: 700;
	border-top: 2px solid var(--accent);
	border-bottom: 0;
}
.bw-studio__totals .num {
	text-align: right;
}
.bw-studio__words {
	margin: 8px 0 0;
	font-size: 11px;
	font-style: italic;
	color: var(--muted);
	text-align: right;
}
.bw-studio__bank {
	background: var(--soft);
	border-left: 3px solid var(--accent);
	padding: 10px 14px;
	border-radius: 0 6px 6px 0;
	font-size: 12px;
	white-space: pre-line;
}
.bw-studio__sign {
	display: flex;
	justify-content: space-between;
	gap: 32px;
	margin-top: 12px;
}
.bw-studio__sign > div {
	flex: 1;
	text-align: center;
	font-size: 11px;
	color: var(--muted);
}
.bw-studio__sign > div::before {
	content: "";
	display: block;
	height: 72px;
	margin-bottom: 8px;
	border-bottom: 1px solid var(--ink);
}
.bw-studio__sign strong {
	display: block;
	color: var(--ink);
	margin-bottom: 2px;
}
.bw-studio__cols {
	display: flex;
	width: 100%;
}
.bw-studio__col {
	flex: 1;
	min-width: 0;
}
.bw-studio__col.is-design {
	border: 1px dashed #d0d7de;
	border-radius: 6px;
	padding: 6px;
	min-height: 48px;
}
.bw-studio__col-add {
	display: block;
	width: 100%;
	margin-top: 6px;
	border: 1px dashed #c5ced6;
	background: transparent;
	border-radius: 4px;
	color: var(--muted);
	font-size: 11px;
	padding: 4px;
	cursor: pointer;
}
.bw-studio__empty-props {
	font-size: 12px;
	color: var(--muted);
	line-height: 1.5;
	padding: 8px 0;
}
.bw-studio__sel-type {
	font-weight: 700;
	font-size: 13px;
	margin-bottom: 10px;
}
.bw-studio__toolbar {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-bottom: 14px;
}
.bw-f {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-bottom: 12px;
	font-size: 12px;
}
.bw-f span {
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: var(--muted);
}
.bw-f em {
	font-style: normal;
	font-size: 11px;
	color: var(--muted);
}
.bw-f input[type='text'],
.bw-f input[type='number'],
.bw-f select,
.bw-f textarea {
	width: 100%;
	border-radius: 6px;
	border: 1px solid var(--line);
	padding: 6px 8px;
}
.bw-f input[type='range'] {
	width: 100%;
}
.bw-f input[type='color'] {
	width: 48px;
	height: 32px;
	padding: 0;
	border: 1px solid var(--line);
	border-radius: 6px;
}
.bw-check {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 12px;
	font-size: 12px;
}
@media (max-width: 1200px) {
	.bw-studio__body:not(.bw-studio__body--preview) {
		grid-template-columns: 1fr;
	}
	.bw-studio__side {
		border: 0;
		border-bottom: 1px solid var(--line);
		max-height: 220px;
	}
}
</style>

<style>
/* Host postbox: bleed builder edge-to-edge */
#wp-bizwit-layout-builder.bw-lb-host {
	border: 0;
	box-shadow: none;
	background: transparent;
}
#wp-bizwit-layout-builder.bw-lb-host > .postbox-header {
	display: none;
}
#wp-bizwit-layout-builder.bw-lb-host > .inside {
	margin: 0 !important;
	padding: 0 !important;
}
</style>
