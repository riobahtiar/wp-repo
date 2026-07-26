/**
 * Thin fetch wrapper for the BizWit REST API (`wp-bizwit/v1`).
 *
 * Uses cookie auth + the `wp_rest` nonce from `window.wpBizwitConfig`
 * (localized in `src/Admin/Assets.php`).
 */

/**
 * GET a path under the configured REST base URL.
 *
 * @param path - Path relative to `restUrl` (leading slash optional), e.g. `health`.
 */
export async function apiGet<T>( path: string ): Promise<T> {
	const cfg = window.wpBizwitConfig;
	const res = await fetch( `${ cfg.restUrl }/${ path.replace( /^\//, '' ) }`, {
		credentials: 'same-origin',
		headers: {
			'X-WP-Nonce': cfg.restNonce,
			Accept: 'application/json',
		},
	} );

	if ( ! res.ok ) {
		throw new Error( `API ${ res.status }` );
	}

	return res.json() as Promise<T>;
}
