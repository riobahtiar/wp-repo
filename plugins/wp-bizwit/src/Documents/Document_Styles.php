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
		return <<<'CSS'
/* —— Page —— */
@page { size: A4; margin: 14mm 16mm; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body.wp-bizwit-document {
	--doc-ink: #1a2332;
	--doc-muted: #5c6570;
	--doc-line: #e2e6ea;
	--doc-line-strong: #1a2332;
	--doc-accent: #1e4d6b;
	--doc-soft: #f4f7f9;
	--doc-total-bg: #f0f5f8;
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
	margin: 16px auto;
	padding: 14mm 16mm 18mm;
	background: #fff;
	box-shadow: 0 4px 24px rgba(26, 35, 50, 0.12);
}
@media print {
	body.wp-bizwit-document { background: #fff; }
	body.wp-bizwit-document .wp-bizwit-document-sheet {
		margin: 0;
		max-width: none;
		min-height: 0;
		padding: 0;
		box-shadow: none;
	}
	.no-print { display: none !important; }
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
.wp-bizwit-doc-section { margin: 0 0 6mm; }
.wp-bizwit-doc-section--header {
	padding-bottom: 5mm;
	margin-bottom: 6mm;
	border-bottom: 2.5px solid var(--doc-accent);
}
.wp-bizwit-doc-section--footer {
	margin-top: 8mm;
	padding-top: 4mm;
	border-top: 1px solid var(--doc-line);
}

/* —— Typography —— */
.wp-bizwit-c-heading { margin: 0 0 2mm; letter-spacing: 0.01em; color: var(--doc-ink); }
.wp-bizwit-c-text { margin: 0 0 2mm; }
.wp-bizwit-c-field { margin: 0 0 1.5mm; }
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
	width: 100%;
}
.wp-bizwit-c-column { flex: 1 1 40%; min-width: 0; }

/* —— Line items table —— */
table.wp-bizwit-lines {
	width: 100%;
	border-collapse: collapse;
	margin: 3mm 0 5mm;
	font-size: 9.5pt;
}
table.wp-bizwit-lines thead th {
	background: var(--doc-accent);
	color: #fff;
	font-size: 8pt;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 2.8mm 2.5mm;
	text-align: left;
	border: 0;
}
table.wp-bizwit-lines tbody td {
	padding: 2.6mm 2.5mm;
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
.wp-bizwit-c-totals-wrap { width: 100%; margin: 2mm 0 4mm; }
table.wp-bizwit-totals {
	width: 72mm;
	margin-left: auto;
	border-collapse: collapse;
	font-size: 10pt;
}
table.wp-bizwit-totals td {
	padding: 1.8mm 2.5mm;
	border-bottom: 1px solid var(--doc-line);
}
table.wp-bizwit-totals tr.grand td {
	background: var(--doc-total-bg);
	border-bottom: 0;
	border-top: 2px solid var(--doc-accent);
	font-weight: 700;
	font-size: 11pt;
	padding-top: 3mm;
	padding-bottom: 3mm;
}
.wp-bizwit-terbilang {
	margin: 3mm 0 0;
	font-size: 9pt;
	font-style: italic;
	color: var(--doc-muted);
	text-align: right;
}

/* —— Bank & notes —— */
.wp-bizwit-c-bank {
	background: var(--doc-soft);
	border-left: 3px solid var(--doc-accent);
	padding: 3mm 4mm;
	margin: 2mm 0 4mm;
	font-size: 9.5pt;
	border-radius: 0 4px 4px 0;
}

/* —— Signature —— */
.wp-bizwit-sign {
	display: flex;
	justify-content: space-between;
	gap: 16mm;
	margin-top: 10mm;
}
.wp-bizwit-sign-box {
	width: 42%;
	min-height: 36mm;
	padding-top: 2mm;
	font-size: 9pt;
	color: var(--doc-muted);
	text-align: center;
}
.wp-bizwit-sign-box::before {
	content: "";
	display: block;
	height: 28mm;
	margin-bottom: 2mm;
	border-bottom: 1px solid var(--doc-line-strong);
}
.wp-bizwit-sign-box strong {
	display: block;
	color: var(--doc-ink);
	font-weight: 600;
	margin-bottom: 1mm;
}

/* —— Void —— */
.void-banner {
	border: 2px solid #b32d2e;
	color: #b32d2e;
	padding: 3mm;
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
	margin: 2mm 0;
}
.wp-bizwit-c-spacer { width: 100%; }

h1, h2, h3, h4 { margin: 0 0 2mm; font-weight: 700; color: var(--doc-ink); }
CSS;
	}
}
