/**
 * Runtime config localized from PHP (`src/Admin/Assets.php` → `wpBizwitConfig`).
 * Property names must match the localize object exactly.
 */
export interface BizWitConfig {
	restUrl: string;
	restNonce: string;
	pluginUrl: string;
	version: string;
	locale: string;
	region: string;
	currency: string;
}

declare global {
	interface Window {
		wpBizwitConfig: BizWitConfig;
	}
}

export {};
