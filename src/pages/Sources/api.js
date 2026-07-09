const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce;

export function api( path, opts = {} ) {
	return fetch( `${ API_URL }${ path }`, {
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
		...opts,
	} ).then( ( r ) => r.json() );
}
