/**
 * Gutenberg blocks for WP BizWit document templates.
 *
 * Uses wp.* globals (no Vite) so the block editor works without a frontend build.
 * Render callbacks live in PHP so printed labels follow the site language.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { useBlockProps, InnerBlocks, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, ToggleControl, Placeholder } = wp.components;
	const { __ } = wp.i18n;

	const config = window.wpBizwitDocumentBlocks || { fields: {}, areas: {} };
	const fieldOptions = Object.keys( config.fields || {} ).map( function ( key ) {
		return { label: config.fields[ key ], value: key };
	} );
	const areaOptions = [
		{ label: ( config.areas && config.areas.header ) || 'Header', value: 'header' },
		{ label: ( config.areas && config.areas.body ) || 'Body', value: 'body' },
		{ label: ( config.areas && config.areas.footer ) || 'Footer', value: 'footer' },
	];

	// —— Section (Header / Body / Footer) ————————————————————————————————

	registerBlockType( 'wp-bizwit/section', {
		apiVersion: 3,
		title: __( 'Document section', 'wp-bizwit' ),
		description: __(
			'Header, body or footer region of a document template.',
			'wp-bizwit'
		),
		category: 'wp-bizwit',
		icon: 'layout',
		attributes: {
			area: { type: 'string', default: 'body' },
		},
		supports: { html: false, anchor: true },
		edit: function ( props ) {
			const area = props.attributes.area || 'body';
			const labels = config.areas || {};
			const title =
				labels[ area ] ||
				area.charAt( 0 ).toUpperCase() + area.slice( 1 );
			const blockProps = useBlockProps( {
				className: 'wp-bizwit-section-editor wp-bizwit-section-editor--' + area,
				style: {
					border: '1px dashed #c3c4c7',
					padding: '12px',
					marginBottom: '16px',
					background: '#fff',
				},
			} );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Section', 'wp-bizwit' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Area', 'wp-bizwit' ),
							value: area,
							options: areaOptions,
							onChange: function ( value ) {
								props.setAttributes( { area: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{
							style: {
								fontSize: '11px',
								fontWeight: 600,
								textTransform: 'uppercase',
								letterSpacing: '0.04em',
								color: '#646970',
								marginBottom: '8px',
							},
						},
						title
					),
					el( InnerBlocks, {
						templateLock: false,
						renderAppender: InnerBlocks.ButtonBlockAppender,
					} )
				)
			);
		},
		save: function () {
			return el( InnerBlocks.Content, null );
		},
	} );

	// —— Field ————————————————————————————————————————————————————————————

	registerBlockType( 'wp-bizwit/field', {
		apiVersion: 3,
		title: __( 'Document field', 'wp-bizwit' ),
		description: __(
			'A dynamic value from the invoice or business settings. Labels follow the site language when printed.',
			'wp-bizwit'
		),
		category: 'wp-bizwit',
		icon: 'editor-textcolor',
		attributes: {
			field: { type: 'string', default: 'invoice_number' },
			showLabel: { type: 'boolean', default: false },
		},
		supports: { html: false },
		edit: function ( props ) {
			const field = props.attributes.field || 'invoice_number';
			const label =
				( config.fields && config.fields[ field ] ) || field;
			const blockProps = useBlockProps( {
				style: {
					padding: '6px 8px',
					background: '#f0f6fc',
					borderRadius: '2px',
					display: 'inline-block',
				},
			} );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Field', 'wp-bizwit' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Data field', 'wp-bizwit' ),
							value: field,
							options: fieldOptions,
							onChange: function ( value ) {
								props.setAttributes( { field: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show field label', 'wp-bizwit' ),
							checked: !! props.attributes.showLabel,
							onChange: function ( value ) {
								props.setAttributes( { showLabel: value } );
							},
						} )
					)
				),
				el(
					'span',
					blockProps,
					props.attributes.showLabel ? label + ': ' : '',
					'{' + label + '}'
				)
			);
		},
		save: function () {
			return null; // Dynamic.
		},
	} );

	// —— Line items ————————————————————————————————————————————————————————

	registerBlockType( 'wp-bizwit/line-items', {
		apiVersion: 3,
		title: __( 'Invoice line items', 'wp-bizwit' ),
		category: 'wp-bizwit',
		icon: 'editor-table',
		supports: { html: false, multiple: false },
		edit: function () {
			const blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el( Placeholder, {
					icon: 'editor-table',
					label: __( 'Invoice line items', 'wp-bizwit' ),
					instructions: __(
						'Renders the invoice lines when the document is printed. Column headers use the site language.',
						'wp-bizwit'
					),
				} )
			);
		},
		save: function () {
			return null;
		},
	} );

	// —— Totals ————————————————————————————————————————————————————————————

	registerBlockType( 'wp-bizwit/totals', {
		apiVersion: 3,
		title: __( 'Invoice totals', 'wp-bizwit' ),
		category: 'wp-bizwit',
		icon: 'money-alt',
		supports: { html: false, multiple: false },
		edit: function () {
			const blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el( Placeholder, {
					icon: 'money-alt',
					label: __( 'Invoice totals', 'wp-bizwit' ),
					instructions: __(
						'Subtotal, tax, total and balance. Labels follow the site language when printed.',
						'wp-bizwit'
					),
				} )
			);
		},
		save: function () {
			return null;
		},
	} );

	// —— Signature ————————————————————————————————————————————————————————

	registerBlockType( 'wp-bizwit/signature', {
		apiVersion: 3,
		title: __( 'Signature block', 'wp-bizwit' ),
		category: 'wp-bizwit',
		icon: 'edit',
		supports: { html: false },
		edit: function () {
			const blockProps = useBlockProps( {
				style: {
					display: 'flex',
					gap: '24px',
					marginTop: '24px',
				},
			} );
			return el(
				'div',
				blockProps,
				el(
					'div',
					{
						style: {
							flex: 1,
							borderTop: '1px solid #c3c4c7',
							paddingTop: '8px',
							color: '#646970',
							fontSize: '12px',
						},
					},
					__( 'Received by', 'wp-bizwit' )
				),
				el(
					'div',
					{
						style: {
							flex: 1,
							borderTop: '1px solid #c3c4c7',
							paddingTop: '8px',
							color: '#646970',
							fontSize: '12px',
						},
					},
					__( 'Signature and company stamp', 'wp-bizwit' )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
