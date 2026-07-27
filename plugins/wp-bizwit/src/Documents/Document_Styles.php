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
.wp-bizwit-print-bar a.button-print {
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
	border-left: 3px solid var(--doc-accent);
	padding: 3.5mm 4.5mm;
	margin: 3mm 0 5mm;
	font-size: 9.5pt;
	border-radius: 0 4px 4px 0;
	line-height: 1.55;
}
.wp-bizwit-pay-methods {
	display: flex;
	flex-direction: column;
	gap: 3mm;
}
.wp-bizwit-pay-method__title {
	font-weight: 700;
	font-size: 9pt;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: var(--doc-accent);
	margin-bottom: 1mm;
}
.wp-bizwit-pay-method__body {
	font-size: 9.5pt;
	line-height: 1.5;
}
.wp-bizwit-pay-method + .wp-bizwit-pay-method {
	padding-top: 2.5mm;
	border-top: 1px dashed var(--doc-line);
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
