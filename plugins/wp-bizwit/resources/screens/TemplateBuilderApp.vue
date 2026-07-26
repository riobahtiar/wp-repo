<script setup lang="ts">
/**
 * A4 document layout builder: palette · canvas (header/body/footer) · properties.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { __ } from '@wordpress/i18n';

type Zone = 'header' | 'body' | 'footer';

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
	componentMeta: Record< string, { label: string; description: string } >;
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

function readConfig(): BuilderConfig {
	const el = document.getElementById( 'wp-bizwit-template-builder' );
	const raw = el?.getAttribute( 'data-config' ) || '{}';
	try {
		return JSON.parse( raw ) as BuilderConfig;
	} catch {
		return {
			layout: {
				version: 1,
				page: { size: 'A4', marginMm: 16 },
				sections: {
					header: { components: [] },
					body: { components: [] },
					footer: { components: [] },
				},
			},
			fields: {},
			componentMeta: {},
			zones: { header: 'Header', body: 'Body', footer: 'Footer' },
			i18n: {},
			defaultLayout: {
				version: 1,
				page: { size: 'A4', marginMm: 16 },
				sections: {
					header: { components: [] },
					body: { components: [] },
					footer: { components: [] },
				},
			},
		};
	}
}

const config = readConfig();
const t = ( key: string, fallback: string ) => config.i18n[ key ] || fallback;

const layout = reactive< LayoutDoc >( deepClone( config.layout ) );
const selectedId = ref< string | null >( null );
const activeZone = ref< Zone >( 'body' );

const hiddenInput = () =>
	document.getElementById( 'wp-bizwit-layout-json' ) as HTMLInputElement | null;

function syncHidden(): void {
	const input = hiddenInput();
	if ( input ) {
		input.value = JSON.stringify( layout );
	}
}

watch( layout, () => syncHidden(), { deep: true } );
onMounted( () => syncHidden() );

const zones: Zone[] = [ 'header', 'body', 'footer' ];

const paletteTypes = computed( () => Object.keys( config.componentMeta ) );

const selected = computed( () => {
	if ( ! selectedId.value ) {
		return null;
	}
	return findComponent( selectedId.value );
} );

function findComponent(
	id: string,
	nodes?: ComponentNode[]
): { node: ComponentNode; zone: Zone; index: number; parent: ComponentNode[] } | null {
	for ( const zone of zones ) {
		const list = nodes ?? layout.sections[ zone ].components;
		for ( let i = 0; i < list.length; i++ ) {
			if ( list[ i ].id === id ) {
				return { node: list[ i ], zone, index: i, parent: list };
			}
			if ( list[ i ].type === 'columns' ) {
				const cols = list[ i ].props.columns as ComponentNode[][] | undefined;
				if ( cols ) {
					for ( const col of cols ) {
						const hit = findInList( id, col, zone );
						if ( hit ) {
							return hit;
						}
					}
				}
			}
		}
	}
	return null;
}

function findInList(
	id: string,
	list: ComponentNode[],
	zone: Zone
): { node: ComponentNode; zone: Zone; index: number; parent: ComponentNode[] } | null {
	for ( let i = 0; i < list.length; i++ ) {
		if ( list[ i ].id === id ) {
			return { node: list[ i ], zone, index: i, parent: list };
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
				fontSize: 14,
				fontWeight: '600',
				align: 'left',
				color: '#1d2327',
				marginTop: 0,
				marginBottom: 8,
			};
		case 'text':
			return {
				content: '',
				fontSize: 12,
				fontWeight: '400',
				align: 'left',
				color: '#1d2327',
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
				color: '#1d2327',
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
			return { color: '#c3c4c7', marginTop: 8, marginBottom: 8 };
		case 'columns':
			return { gap: 24, columns: [ [], [] ], marginTop: 0, marginBottom: 8 };
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
}

function resetDefault(): void {
	if (
		! window.confirm(
			t( 'resetDefault', 'Reset to default layout' ) + '?'
		)
	) {
		return;
	}
	const next = deepClone( config.defaultLayout );
	layout.version = next.version;
	layout.page = next.page;
	layout.sections = next.sections;
	selectedId.value = null;
}

function previewLabel( node: ComponentNode ): string {
	const meta = config.componentMeta[ node.type ];
	const base = meta?.label || node.type;
	if ( node.type === 'field' ) {
		const f = String( node.props.field || '' );
		return `${ base }: ${ config.fields[ f ] || f }`;
	}
	if ( node.type === 'heading' || node.type === 'text' ) {
		const c = String( node.props.content || '' ).slice( 0, 32 );
		return c ? `${ base }: ${ c }` : base;
	}
	return base;
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

const fieldOptions = computed( () =>
	Object.entries( config.fields ).map( ( [ value, label ] ) => ( { value, label } ) )
);
</script>

<template>
	<div class="bw-lb">
		<!-- Palette -->
		<aside class="bw-lb__palette">
			<div class="bw-lb__panel-title">{{ t( 'palette', 'Components' ) }}</div>
			<button
				v-for="type in paletteTypes"
				:key="type"
				type="button"
				class="bw-lb__palette-item"
				@click="addComponent( type )"
			>
				<strong>{{ config.componentMeta[ type ]?.label || type }}</strong>
				<span>{{ config.componentMeta[ type ]?.description }}</span>
			</button>
			<button type="button" class="bw-lb__reset button" @click="resetDefault">
				{{ t( 'resetDefault', 'Reset to default layout' ) }}
			</button>
		</aside>

		<!-- Canvas -->
		<main class="bw-lb__canvas-wrap">
			<div class="bw-lb__panel-title">{{ t( 'canvas', 'Canvas (A4)' ) }}</div>
			<div
				class="bw-lb__paper"
				:style="{ padding: layout.page.marginMm + 'mm' }"
			>
				<section
					v-for="zone in zones"
					:key="zone"
					class="bw-lb__zone"
					:class="{
						'bw-lb__zone--active': activeZone === zone,
						[ 'bw-lb__zone--' + zone ]: true,
					}"
					@click.self="activeZone = zone"
				>
					<div class="bw-lb__zone-label">{{ config.zones[ zone ] }}</div>
					<div
						v-if="layout.sections[ zone ].components.length === 0"
						class="bw-lb__empty"
						@click="activeZone = zone"
					>
						{{ t( 'emptyZone', 'Drop or add components here' ) }}
					</div>
					<div
						v-for="node in layout.sections[ zone ].components"
						:key="node.id"
						class="bw-lb__comp"
						:class="{ 'bw-lb__comp--selected': selectedId === node.id }"
						:style="styleOf( node )"
						@click.stop="select( node.id, zone )"
					>
						<template v-if="node.type === 'divider'">
							<hr class="bw-lb__divider" :style="{ borderColor: String( node.props.color || '#c3c4c7' ) }" />
						</template>
						<template v-else-if="node.type === 'spacer'">
							<div class="bw-lb__spacer-mark" :style="{ height: ( node.props.height || 16 ) + 'px' }" />
						</template>
						<template v-else-if="node.type === 'line_items'">
							<div class="bw-lb__table-ph">{{ previewLabel( node ) }}</div>
						</template>
						<template v-else-if="node.type === 'totals'">
							<div class="bw-lb__table-ph bw-lb__table-ph--right">{{ previewLabel( node ) }}</div>
						</template>
						<template v-else-if="node.type === 'signature'">
							<div class="bw-lb__sign-ph">
								<span>{{ __( 'Received by', 'wp-bizwit' ) }}</span>
								<span>{{ __( 'Signature and company stamp', 'wp-bizwit' ) }}</span>
							</div>
						</template>
						<template v-else-if="node.type === 'columns'">
							<div class="bw-lb__cols" :style="{ gap: ( node.props.gap || 24 ) + 'px' }">
								<div
									v-for="( col, ci ) in ( node.props.columns as ComponentNode[][] ) || [ [], [] ]"
									:key="ci"
									class="bw-lb__col"
								>
									<div
										v-for="child in col"
										:key="child.id"
										class="bw-lb__comp bw-lb__comp--nested"
										:class="{ 'bw-lb__comp--selected': selectedId === child.id }"
										:style="styleOf( child )"
										@click.stop="select( child.id, zone )"
									>
										{{ previewLabel( child ) }}
									</div>
									<button
										type="button"
										class="bw-lb__col-add"
										@click.stop="
											( node.props.columns as ComponentNode[][] )[ ci ].push( {
												id: uid(),
												type: 'field',
												props: defaultProps( 'field' ),
											} )
										"
									>
										+
									</button>
								</div>
							</div>
						</template>
						<template v-else>
							<span v-if="node.props.showLabel && node.type === 'field'" class="bw-lb__label">
								{{ config.fields[ String( node.props.field ) ] || node.props.field }}:
							</span>
							{{
								node.type === 'heading' || node.type === 'text'
									? node.props.content || previewLabel( node )
									: previewLabel( node )
							}}
						</template>
					</div>
				</section>
			</div>
		</main>

		<!-- Properties -->
		<aside class="bw-lb__props">
			<div class="bw-lb__panel-title">{{ t( 'properties', 'Properties' ) }}</div>
			<div v-if="! selected" class="bw-lb__hint">
				{{ t( 'selectHint', 'Select a component on the canvas to edit its properties.' ) }}
			</div>
			<template v-else>
				<div class="bw-lb__prop-type">
					{{ config.componentMeta[ selected.node.type ]?.label || selected.node.type }}
				</div>
				<div class="bw-lb__toolbar">
					<button type="button" class="button button-small" @click="moveSelected( -1 )">↑</button>
					<button type="button" class="button button-small" @click="moveSelected( 1 )">↓</button>
					<button type="button" class="button button-small" @click="duplicateSelected">
						{{ t( 'duplicate', 'Duplicate' ) }}
					</button>
					<button type="button" class="button button-small button-link-delete" @click="removeSelected">
						{{ t( 'remove', 'Remove' ) }}
					</button>
				</div>

				<!-- Field -->
				<label v-if="selected.node.type === 'field'" class="bw-lb__field">
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
				<label v-if="selected.node.type === 'field'" class="bw-lb__check">
					<input
						type="checkbox"
						:checked="!! selected.node.props.showLabel"
						@change="setProp( 'showLabel', ( $event.target as HTMLInputElement ).checked )"
					/>
					{{ t( 'showLabel', 'Show label' ) }}
				</label>

				<!-- Text / heading -->
				<label
					v-if="selected.node.type === 'heading' || selected.node.type === 'text'"
					class="bw-lb__field"
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

				<!-- Shared style -->
				<template
					v-if="
						[ 'heading', 'text', 'field', 'totals' ].includes( selected.node.type )
					"
				>
					<label class="bw-lb__field">
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
				<template
					v-if="[ 'heading', 'text', 'field' ].includes( selected.node.type )"
				>
					<label class="bw-lb__field">
						<span>{{ t( 'fontSize', 'Font size' ) }}</span>
						<input
							type="number"
							min="9"
							max="36"
							:value="Number( selected.node.props.fontSize || 12 )"
							@input="setProp( 'fontSize', Number( ( $event.target as HTMLInputElement ).value ) )"
						/>
					</label>
					<label class="bw-lb__field">
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
					<label class="bw-lb__field">
						<span>{{ t( 'color', 'Colour' ) }}</span>
						<input
							type="color"
							:value="String( selected.node.props.color || '#1d2327' )"
							@input="setProp( 'color', ( $event.target as HTMLInputElement ).value )"
						/>
					</label>
				</template>

				<label v-if="selected.node.type === 'spacer'" class="bw-lb__field">
					<span>{{ t( 'height', 'Height' ) }} (px)</span>
					<input
						type="number"
						min="4"
						max="120"
						:value="Number( selected.node.props.height || 16 )"
						@input="setProp( 'height', Number( ( $event.target as HTMLInputElement ).value ) )"
					/>
				</label>

				<label v-if="selected.node.type === 'line_items'" class="bw-lb__check">
					<input
						type="checkbox"
						:checked="selected.node.props.showTax !== false"
						@change="setProp( 'showTax', ( $event.target as HTMLInputElement ).checked )"
					/>
					{{ t( 'showTax', 'Show tax column' ) }}
				</label>
				<label v-if="selected.node.type === 'totals'" class="bw-lb__check">
					<input
						type="checkbox"
						:checked="selected.node.props.showTerbilang !== false"
						@change="setProp( 'showTerbilang', ( $event.target as HTMLInputElement ).checked )"
					/>
					{{ t( 'showTerbilang', 'Show amount in words' ) }}
				</label>
				<label v-if="selected.node.type === 'bank'" class="bw-lb__check">
					<input
						type="checkbox"
						:checked="!! selected.node.props.showTitle"
						@change="setProp( 'showTitle', ( $event.target as HTMLInputElement ).checked )"
					/>
					{{ t( 'showTitle', 'Show title' ) }}
				</label>
				<label v-if="selected.node.type === 'columns'" class="bw-lb__field">
					<span>{{ t( 'gap', 'Column gap' ) }}</span>
					<input
						type="number"
						min="8"
						max="48"
						:value="Number( selected.node.props.gap || 24 )"
						@input="setProp( 'gap', Number( ( $event.target as HTMLInputElement ).value ) )"
					/>
				</label>
				<label v-if="selected.node.type === 'divider'" class="bw-lb__field">
					<span>{{ t( 'color', 'Colour' ) }}</span>
					<input
						type="color"
						:value="String( selected.node.props.color || '#c3c4c7' )"
						@input="setProp( 'color', ( $event.target as HTMLInputElement ).value )"
					/>
				</label>

				<label
					v-if="selected.node.type !== 'spacer'"
					class="bw-lb__field"
				>
					<span>{{ t( 'marginTop', 'Margin top' ) }}</span>
					<input
						type="number"
						min="0"
						max="64"
						:value="Number( selected.node.props.marginTop || 0 )"
						@input="setProp( 'marginTop', Number( ( $event.target as HTMLInputElement ).value ) )"
					/>
				</label>
				<label
					v-if="selected.node.type !== 'spacer'"
					class="bw-lb__field"
				>
					<span>{{ t( 'marginBottom', 'Margin bottom' ) }}</span>
					<input
						type="number"
						min="0"
						max="64"
						:value="Number( selected.node.props.marginBottom || 0 )"
						@input="setProp( 'marginBottom', Number( ( $event.target as HTMLInputElement ).value ) )"
					/>
				</label>
			</template>
		</aside>
	</div>
</template>

<style scoped>
.bw-lb {
	display: grid;
	grid-template-columns: 200px minmax(0, 1fr) 240px;
	gap: 0;
	min-height: 640px;
	background: #f0f0f1;
	border: 1px solid #c3c4c7;
	font-size: 13px;
}
.bw-lb__palette,
.bw-lb__props {
	background: #fff;
	border-right: 1px solid #dcdcde;
	padding: 12px;
	overflow: auto;
}
.bw-lb__props {
	border-right: 0;
	border-left: 1px solid #dcdcde;
}
.bw-lb__panel-title {
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #646970;
	margin-bottom: 10px;
}
.bw-lb__palette-item {
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	width: 100%;
	margin: 0 0 6px;
	padding: 8px 10px;
	border: 1px solid #dcdcde;
	border-radius: 4px;
	background: #f6f7f7;
	cursor: pointer;
	text-align: left;
}
.bw-lb__palette-item:hover {
	border-color: #2271b1;
	background: #f0f6fc;
}
.bw-lb__palette-item strong {
	font-size: 12px;
	color: #1d2327;
}
.bw-lb__palette-item span {
	font-size: 11px;
	color: #646970;
	margin-top: 2px;
}
.bw-lb__reset {
	margin-top: 12px;
	width: 100%;
}
.bw-lb__canvas-wrap {
	padding: 12px 16px 24px;
	overflow: auto;
}
.bw-lb__paper {
	max-width: 210mm;
	min-height: 280mm;
	margin: 0 auto;
	background: #fff;
	box-shadow: 0 2px 12px rgba( 0, 0, 0, 0.12 );
	border: 1px solid #c3c4c7;
}
.bw-lb__zone {
	position: relative;
	min-height: 64px;
	padding: 8px 4px 12px;
	border-bottom: 1px dashed #dcdcde;
}
.bw-lb__zone--footer {
	border-bottom: 0;
}
.bw-lb__zone--active {
	background: #fafcfe;
	outline: 1px solid #c5d9ed;
}
.bw-lb__zone-label {
	font-size: 10px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	color: #8c8f94;
	margin-bottom: 6px;
}
.bw-lb__empty {
	border: 1px dashed #c3c4c7;
	border-radius: 4px;
	padding: 16px;
	text-align: center;
	color: #8c8f94;
	font-size: 12px;
	cursor: pointer;
}
.bw-lb__comp {
	padding: 6px 8px;
	margin-bottom: 4px;
	border: 1px solid transparent;
	border-radius: 3px;
	cursor: pointer;
	line-height: 1.4;
	word-break: break-word;
}
.bw-lb__comp:hover {
	border-color: #c5d9ed;
	background: #f6f7f7;
}
.bw-lb__comp--selected {
	border-color: #2271b1 !important;
	box-shadow: 0 0 0 1px #2271b1;
	background: #f0f6fc;
}
.bw-lb__comp--nested {
	font-size: 12px;
	background: #fff;
	border: 1px solid #e0e0e0;
}
.bw-lb__label {
	color: #646970;
	font-weight: 600;
	margin-right: 4px;
}
.bw-lb__divider {
	border: 0;
	border-top: 1px solid #c3c4c7;
	margin: 0;
}
.bw-lb__spacer-mark {
	background: repeating-linear-gradient(
		-45deg,
		#f0f0f1,
		#f0f0f1 4px,
		#e2e4e7 4px,
		#e2e4e7 8px
	);
	border-radius: 2px;
	opacity: 0.7;
}
.bw-lb__table-ph {
	padding: 12px;
	background: #f6f7f7;
	border: 1px dashed #c3c4c7;
	font-size: 12px;
	color: #646970;
	text-align: center;
}
.bw-lb__table-ph--right {
	margin-left: auto;
	max-width: 220px;
}
.bw-lb__sign-ph {
	display: flex;
	justify-content: space-between;
	gap: 24px;
	margin-top: 8px;
}
.bw-lb__sign-ph span {
	flex: 1;
	border-top: 1px solid #c3c4c7;
	padding-top: 8px;
	font-size: 11px;
	color: #646970;
	min-height: 48px;
}
.bw-lb__cols {
	display: flex;
	width: 100%;
}
.bw-lb__col {
	flex: 1;
	min-width: 0;
	border: 1px dashed #dcdcde;
	border-radius: 3px;
	padding: 4px;
	min-height: 40px;
}
.bw-lb__col-add {
	display: block;
	width: 100%;
	margin-top: 4px;
	border: 1px dashed #c3c4c7;
	background: transparent;
	cursor: pointer;
	color: #646970;
	font-size: 14px;
	line-height: 1.6;
}
.bw-lb__hint {
	color: #646970;
	font-size: 12px;
	line-height: 1.5;
}
.bw-lb__prop-type {
	font-weight: 600;
	margin-bottom: 8px;
}
.bw-lb__toolbar {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-bottom: 12px;
}
.bw-lb__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-bottom: 10px;
}
.bw-lb__field span {
	font-size: 11px;
	font-weight: 600;
	color: #646970;
}
.bw-lb__field input[type='text'],
.bw-lb__field input[type='number'],
.bw-lb__field select,
.bw-lb__field textarea {
	width: 100%;
	max-width: 100%;
}
.bw-lb__field input[type='color'] {
	width: 48px;
	height: 28px;
	padding: 0;
	border: 1px solid #c3c4c7;
}
.bw-lb__check {
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 10px;
	font-size: 12px;
}
@media ( max-width: 1100px ) {
	.bw-lb {
		grid-template-columns: 1fr;
	}
}
</style>
