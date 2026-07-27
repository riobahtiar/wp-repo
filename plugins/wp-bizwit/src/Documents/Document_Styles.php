<?php
/**
 * Shared styles for document print and on-screen preview.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Documents;

/**
 * Single source of document presentation CSS (A4, Indonesian paperwork feel).
 */
class Document_Styles {

	/**
	 * Full stylesheet for print pages and builder preview.
	 *
	 * @return string CSS (no &lt;style&gt; wrapper).
	 */
	public static function css(): string {
		/*
		 * Spacing strategy (screen ≈ print dialog ≈ Save as PDF):
		 * - Document breathing room lives on .wp-bizwit-document-sheet padding,
		 *   never zeroed in @media print (browsers often ignore or override @page).
		 * - @page keeps a small hardware-safe margin so content is not clipped.
		 * - Screen shows a grey desk + white sheet; print is plain white.
		 */
		return <<<'CSS'
/* —— Page —— */
@page {
	size: A4;
	margin: 8mm;
}
* { box-sizing: border-box; }
html {
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
	color-adjust: exact;
}
body.wp-bizwit-document {
	--doc-ink: #1a2332;
	--doc-muted: #5c6570;
	--doc-line: #e2e6ea;
	--doc-line-strong: #1a2332;
	--doc-accent: #1e4d6b;
	--doc-soft: #f4f7f9;
	--doc-total-bg: #f0f5f8;
	--doc-pad-y: 12mm;
	--doc-pad-x: 14mm;
	margin: 0;
	padding: 0;
	font-family: "Segoe UI", "Helvetica Neue", Arial, "Noto Sans", sans-serif;
	font-size: 10.5pt;
	line-height: 1.5;
	color: var(--doc-ink);
	background: #e8ecf0;
}
body.wp-bizwit-document .wp-bizwit-document-sheet {
	max-width: 210mm;
	min-height: 297mm;
	margin: 16px auto 24px;
	padding: var(--doc-pad-y) var(--doc-pad-x) 16mm;
	background: #fff;
	box-shadow: 0 4px 24px rgba(26, 35, 50, 0.12);
}
body.wp-bizwit-document .wp-bizwit-document__content {
	width: 100%;
}

/* Print: keep sheet padding so dialog / PDF match on-screen preview.
   Do NOT reset padding to 0 — that made the print dialog look edge-to-edge. */
@media print {
	html, body.wp-bizwit-document {
		width: 100%;
		height: auto;
		margin: 0 !important;
		padding: 0 !important;
		background: #fff !important;
	}
	body.wp-bizwit-document .wp-bizwit-document-sheet {
		display: block;
		width: 100% !important;
		max-width: none !important;
		min-height: 0 !important;
		margin: 0 !important;
		/* Same inner margins as the white sheet on screen */
		padding: var(--doc-pad-y) var(--doc-pad-x) 14mm !important;
		background: #fff !important;
		box-shadow: none !important;
		border: 0 !important;
		border-radius: 0 !important;
	}
	.no-print { display: none !important; }
	/* Avoid browsers collapsing flex gaps in print engines */
	.wp-bizwit-c-columns,
	.wp-bizwit-sign {
		display: flex !important;
		flex-wrap: nowrap !important;
	}
	.wp-bizwit-c-column { flex: 1 1 0 !important; min-width: 0 !important; }
	.wp-bizwit-sign-box { flex: 1 1 42% !important; }
	/* Keep table header colours when printing / Save as PDF */
	table.wp-bizwit-lines thead th {
		background: var(--doc-accent) !important;
		color: #fff !important;
		-webkit-print-color-adjust: exact;
		print-color-adjust: exact;
	}
	table.wp-bizwit-lines tbody tr:nth-child(even) td,
	table.wp-bizwit-totals tr.grand td,
	.wp-bizwit-c-bank {
		-webkit-print-color-adjust: exact;
		print-color-adjust: exact;
	}
}

/* —— Chrome (screen only) —— */
.wp-bizwit-print-bar {
	position: sticky;
	top: 0;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12px;
	flex-wrap: wrap;
	padding: 12px 16px;
	background: #1a2332;
	color: #fff;
	font-size: 13px;
}
.wp-bizwit-print-bar button,
.wp-bizwit-print-bar a.button-print,
.wp-bizwit-print-bar .button-print {
	appearance: none;
	border: 0;
	border-radius: 6px;
	padding: 8px 16px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	background: #fff;
	color: #1a2332;
	text-decoration: none;
}
.wp-bizwit-print-bar .hint { opacity: 0.75; font-size: 12px; }
.wp-bizwit-theme-badge {
	display: inline-flex;
	align-items: center;
	padding: 6px 10px;
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.12);
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.02em;
}
.wp-bizwit-template-switch {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	margin: 0;
}
.wp-bizwit-template-switch label {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-size: 12px;
	font-weight: 600;
}
.wp-bizwit-template-switch select {
	min-width: 14rem;
	max-width: 22rem;
	padding: 6px 10px;
	border: 0;
	border-radius: 6px;
	font-size: 13px;
	color: #1a2332;
	background: #fff;
}
.wp-bizwit-template-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	width: 100%;
	justify-content: center;
	margin-top: 4px;
}
.wp-bizwit-template-chip {
	display: inline-block;
	padding: 5px 10px;
	border-radius: 999px;
	background: rgba(255, 255, 255, 0.1);
	color: #fff !important;
	text-decoration: none !important;
	font-size: 11px;
	font-weight: 600;
	border: 1px solid rgba(255, 255, 255, 0.2);
}
.wp-bizwit-template-chip:hover {
	background: rgba(255, 255, 255, 0.2);
}
.wp-bizwit-template-chip.is-active {
	background: #fff;
	color: #1a2332 !important;
	border-color: #fff;
}

/* —— Sections —— */
.wp-bizwit-layout { width: 100%; }
.wp-bizwit-doc-section { margin: 0 0 5mm; }
.wp-bizwit-doc-section--header {
	padding-bottom: 5mm;
	margin-bottom: 7mm;
	border-bottom: 2.5px solid var(--doc-accent);
}
.wp-bizwit-doc-section--body { margin-bottom: 4mm; }
.wp-bizwit-doc-section--footer {
	margin-top: 8mm;
	padding-top: 5mm;
	border-top: 1px solid var(--doc-line);
}

/* —— Theme: header styles (body + layout class for reliability) —— */
body.wp-bizwit-header--open .wp-bizwit-doc-section--header,
.wp-bizwit-header--open .wp-bizwit-doc-section--header {
	border-bottom: 0 !important;
	padding-bottom: 2mm;
	margin-bottom: 4mm;
}
body.wp-bizwit-header--centered .wp-bizwit-doc-section--header,
.wp-bizwit-header--centered .wp-bizwit-doc-section--header {
	border-bottom: 0 !important;
	text-align: center;
}
body.wp-bizwit-header--band .wp-bizwit-doc-section--header,
.wp-bizwit-header--band .wp-bizwit-doc-section--header {
	background: var(--doc-accent) !important;
	color: #fff !important;
	border-bottom: 0 !important;
	/* Bleed into sheet padding for a full-width band */
	margin: calc(var(--doc-pad-y, 12mm) * -1) calc(var(--doc-pad-x, 14mm) * -1) 8mm !important;
	padding: 10mm var(--doc-pad-x, 14mm) 9mm !important;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}
body.wp-bizwit-header--band .wp-bizwit-doc-section--header .wp-bizwit-c-field,
body.wp-bizwit-header--band .wp-bizwit-doc-section--header .wp-bizwit-field-label,
body.wp-bizwit-header--band .wp-bizwit-doc-section--header .wp-bizwit-c-heading,
body.wp-bizwit-header--band .wp-bizwit-doc-section--header .wp-bizwit-c-field * {
	color: #fff !important;
}
body.wp-bizwit-header--band .wp-bizwit-doc-section--header .wp-bizwit-field-label {
	opacity: 0.85;
}

/* —— Theme: table styles —— */
body.wp-bizwit-table--underline table.wp-bizwit-lines thead th,
.wp-bizwit-table--underline table.wp-bizwit-lines thead th {
	background: transparent !important;
	color: var(--doc-accent) !important;
	border-bottom: 2.5px solid var(--doc-accent) !important;
	padding-bottom: 2.5mm;
}
body.wp-bizwit-table--underline table.wp-bizwit-lines tbody tr:nth-child(even) td,
.wp-bizwit-table--underline table.wp-bizwit-lines tbody tr:nth-child(even) td {
	background: transparent !important;
}
body.wp-bizwit-table--hairline table.wp-bizwit-lines thead th,
.wp-bizwit-table--hairline table.wp-bizwit-lines thead th {
	background: transparent !important;
	color: var(--doc-ink) !important;
	text-transform: none;
	letter-spacing: 0;
	font-weight: 600;
	border-bottom: 1px solid var(--doc-ink) !important;
	padding: 2mm 1.5mm;
}
body.wp-bizwit-table--hairline table.wp-bizwit-lines tbody td {
	padding: 2.2mm 1.5mm;
}
body.wp-bizwit-table--hairline table.wp-bizwit-lines tbody tr:nth-child(even) td {
	background: transparent !important;
}
body.wp-bizwit-table--double table.wp-bizwit-lines thead th,
.wp-bizwit-table--double table.wp-bizwit-lines thead th {
	background: transparent !important;
	color: var(--doc-accent) !important;
	border-top: 2px solid var(--doc-accent) !important;
	border-bottom: 2px solid var(--doc-accent) !important;
}
body.wp-bizwit-table--double table.wp-bizwit-lines tbody tr:nth-child(even) td {
	background: var(--doc-soft) !important;
}
body.wp-bizwit-table--dense table.wp-bizwit-lines {
	font-size: 8.5pt;
}
body.wp-bizwit-table--dense table.wp-bizwit-lines thead th,
body.wp-bizwit-table--dense table.wp-bizwit-lines tbody td {
	padding: 1.6mm 1.8mm;
}

/* Theme-specific bank / total polish */
body.wp-bizwit-theme-modern .wp-bizwit-c-bank,
.wp-bizwit-layout--modern .wp-bizwit-c-bank {
	border-left-width: 5px;
	border-radius: 0 10px 10px 0;
	box-shadow: 0 1px 0 rgba(26, 35, 50, 0.04);
}
body.wp-bizwit-theme-modern .wp-bizwit-c-bank__title,
.wp-bizwit-layout--modern .wp-bizwit-c-bank__title {
	background: var(--doc-soft);
}
body.wp-bizwit-theme-minimal .wp-bizwit-c-bank,
.wp-bizwit-layout--minimal .wp-bizwit-c-bank {
	background: transparent !important;
	border: 0 !important;
	border-top: 1.5px solid var(--doc-line-strong) !important;
	border-radius: 0;
	box-shadow: none;
}
body.wp-bizwit-theme-minimal .wp-bizwit-c-bank__title,
.wp-bizwit-layout--minimal .wp-bizwit-c-bank__title {
	background: transparent;
	border-bottom: 0;
	padding-left: 0;
	padding-right: 0;
	color: var(--doc-ink);
	letter-spacing: 0.14em;
}
body.wp-bizwit-theme-minimal .wp-bizwit-pay-method,
.wp-bizwit-layout--minimal .wp-bizwit-pay-method {
	padding-left: 0;
	padding-right: 0;
}
body.wp-bizwit-theme-minimal .wp-bizwit-pay-method__title::before,
.wp-bizwit-layout--minimal .wp-bizwit-pay-method__title::before {
	display: none;
}
body.wp-bizwit-theme-elegant .wp-bizwit-c-bank,
.wp-bizwit-layout--elegant .wp-bizwit-c-bank {
	background: #fff;
	border: 1px solid var(--doc-line);
	border-left: 1px solid var(--doc-line);
	border-radius: 2px;
	box-shadow: none;
}
body.wp-bizwit-theme-elegant .wp-bizwit-c-bank__title,
.wp-bizwit-layout--elegant .wp-bizwit-c-bank__title {
	background: transparent;
	border-bottom: 1px solid var(--doc-line);
	font-family: Georgia, "Times New Roman", serif;
	letter-spacing: 0.16em;
	font-weight: 600;
}
body.wp-bizwit-theme-professional .wp-bizwit-c-bank,
.wp-bizwit-layout--professional .wp-bizwit-c-bank {
	border-left-width: 0;
	border-top: 3px solid var(--doc-accent);
	border-radius: 0;
}
body.wp-bizwit-theme-professional .wp-bizwit-c-bank__title,
.wp-bizwit-layout--professional .wp-bizwit-c-bank__title {
	background: var(--doc-accent);
	color: #fff;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}
body.wp-bizwit-theme-classic .wp-bizwit-c-bank,
.wp-bizwit-layout--classic .wp-bizwit-c-bank {
	border-radius: 0 4px 4px 0;
}
body.wp-bizwit-theme-elegant table.wp-bizwit-totals tr.grand td,
.wp-bizwit-layout--elegant table.wp-bizwit-totals tr.grand td {
	background: var(--doc-total-bg) !important;
	color: var(--doc-accent) !important;
}
body.wp-bizwit-theme-professional table.wp-bizwit-totals tr.grand td,
.wp-bizwit-layout--professional table.wp-bizwit-totals tr.grand td {
	background: var(--doc-accent) !important;
	color: #fff !important;
	border-top-color: var(--doc-accent) !important;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}

/* —— Typography —— */
.wp-bizwit-c-heading { margin: 0 0 2.5mm; letter-spacing: 0.01em; color: var(--doc-ink); }
.wp-bizwit-c-text { margin: 0 0 2.5mm; }
.wp-bizwit-c-field { margin: 0 0 2mm; }
.wp-bizwit-field-label {
	display: inline;
	color: var(--doc-muted);
	font-weight: 600;
	font-size: 0.92em;
	margin-right: 0.35em;
}
.wp-bizwit-field-label::after { content: ":"; }

/* —— Columns —— */
.wp-bizwit-c-columns {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	gap: 8mm;
	width: 100%;
	margin-bottom: 2mm;
}
.wp-bizwit-c-column { flex: 1 1 40%; min-width: 0; }

/* —— Line items table —— */
table.wp-bizwit-lines {
	width: 100%;
	border-collapse: collapse;
	margin: 4mm 0 6mm;
	font-size: 9.5pt;
}
table.wp-bizwit-lines thead th {
	background: var(--doc-accent);
	color: #fff;
	font-size: 8pt;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 3mm 2.8mm;
	text-align: left;
	border: 0;
}
table.wp-bizwit-lines tbody td {
	padding: 3mm 2.8mm;
	border-bottom: 1px solid var(--doc-line);
	vertical-align: top;
}
table.wp-bizwit-lines tbody tr:nth-child(even) td { background: var(--doc-soft); }
table.wp-bizwit-lines .num,
table.wp-bizwit-totals .num {
	text-align: right;
	white-space: nowrap;
	font-variant-numeric: tabular-nums;
}

/* —— Totals —— */
.wp-bizwit-c-totals-wrap { width: 100%; margin: 3mm 0 5mm; }
table.wp-bizwit-totals {
	width: 72mm;
	margin-left: auto;
	border-collapse: collapse;
	font-size: 10pt;
}
table.wp-bizwit-totals td {
	padding: 2.2mm 2.8mm;
	border-bottom: 1px solid var(--doc-line);
}
table.wp-bizwit-totals tr.grand td {
	background: var(--doc-total-bg);
	border-bottom: 0;
	border-top: 2px solid var(--doc-accent);
	font-weight: 700;
	font-size: 11pt;
	padding-top: 3.2mm;
	padding-bottom: 3.2mm;
}
.wp-bizwit-terbilang {
	margin: 3.5mm 0 0;
	font-size: 9pt;
	font-style: italic;
	color: var(--doc-muted);
	text-align: right;
}

/* —— Bank & notes / multi payment methods —— */
.wp-bizwit-c-bank {
	background: var(--doc-soft);
	border: 1px solid var(--doc-line);
	border-left: 4px solid var(--doc-accent);
	padding: 0;
	margin: 4mm 0 6mm;
	font-size: 9.5pt;
	border-radius: 0 6px 6px 0;
	line-height: 1.45;
	overflow: hidden;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}
.wp-bizwit-c-bank__title {
	margin: 0;
	padding: 2.8mm 4.5mm;
	font-size: 8pt;
	font-weight: 700;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: var(--doc-accent);
	background: var(--doc-soft);
	border-bottom: 1px solid var(--doc-line);
}
.wp-bizwit-pay-methods {
	display: flex;
	flex-direction: column;
	gap: 0;
}
.wp-bizwit-pay-method {
	padding: 3.5mm 4.5mm 4mm;
}
.wp-bizwit-pay-method + .wp-bizwit-pay-method {
	border-top: 1px dashed var(--doc-line);
}
.wp-bizwit-pay-method__head {
	margin: 0 0 2.2mm;
}
.wp-bizwit-pay-method__title {
	display: inline-flex;
	align-items: center;
	gap: 2mm;
	font-weight: 700;
	font-size: 9.5pt;
	letter-spacing: 0.01em;
	text-transform: none;
	color: var(--doc-ink);
	margin: 0;
	line-height: 1.3;
}
.wp-bizwit-pay-method__title::before {
	content: "";
	display: inline-block;
	width: 2.4mm;
	height: 2.4mm;
	border-radius: 50%;
	background: var(--doc-accent);
	flex-shrink: 0;
	-webkit-print-color-adjust: exact;
	print-color-adjust: exact;
}
.wp-bizwit-pay-method__rows {
	margin: 0;
	display: grid;
	gap: 1.4mm;
}
.wp-bizwit-pay-method__row {
	display: grid;
	grid-template-columns: 28mm minmax(0, 1fr);
	column-gap: 3mm;
	align-items: baseline;
}
.wp-bizwit-pay-method__row dt {
	margin: 0;
	font-size: 8pt;
	font-weight: 500;
	color: var(--doc-muted);
	line-height: 1.35;
}
.wp-bizwit-pay-method__row dd {
	margin: 0;
	font-size: 10pt;
	font-weight: 600;
	color: var(--doc-ink);
	line-height: 1.35;
	word-break: break-word;
}
.wp-bizwit-pay-method__row--account dd {
	font-size: 11.5pt;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
	letter-spacing: 0.04em;
	font-family: "SF Mono", "Menlo", "Consolas", "Courier New", monospace;
}
.wp-bizwit-pay-method__row--link dd {
	font-weight: 500;
	font-size: 9pt;
	word-break: break-all;
}
.wp-bizwit-pay-method__notes {
	margin: 2.2mm 0 0;
	padding-top: 1.8mm;
	border-top: 1px solid var(--doc-line);
	font-size: 8.5pt;
	font-style: italic;
	color: var(--doc-muted);
	line-height: 1.45;
}
.wp-bizwit-pay-method__body {
	font-size: 9.5pt;
	line-height: 1.55;
	color: var(--doc-ink);
}

/* —— Signature —— */
.wp-bizwit-sign {
	display: flex;
	justify-content: space-between;
	gap: 16mm;
	margin-top: 12mm;
	padding-top: 2mm;
}
.wp-bizwit-sign-box {
	width: 42%;
	min-height: 38mm;
	padding-top: 2mm;
	font-size: 9pt;
	color: var(--doc-muted);
	text-align: center;
}
.wp-bizwit-sign-box::before {
	content: "";
	display: block;
	height: 30mm;
	margin-bottom: 3mm;
	border-bottom: 1px solid var(--doc-line-strong);
}
.wp-bizwit-sign-box strong {
	display: block;
	color: var(--doc-ink);
	font-weight: 600;
	margin-bottom: 1.5mm;
}
.wp-bizwit-sign-box span {
	display: block;
	line-height: 1.4;
}

/* —— Void —— */
.void-banner {
	border: 2px solid #b32d2e;
	color: #b32d2e;
	padding: 3.5mm;
	margin-bottom: 6mm;
	text-align: center;
	font-weight: 700;
	letter-spacing: 0.12em;
	font-size: 14pt;
}

/* —— Divider / spacer —— */
.wp-bizwit-c-divider {
	border: 0;
	border-top: 1px solid var(--doc-line);
	margin: 3mm 0;
}
.wp-bizwit-c-spacer { width: 100%; }

h1, h2, h3, h4 { margin: 0 0 2.5mm; font-weight: 700; color: var(--doc-ink); }
CSS;
	}
}
