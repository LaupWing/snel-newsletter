const API_URL = window.snelNewsletter?.restUrl;
const NONCE   = window.snelNewsletter?.nonce as string;

export function api< T = any >( path: string, opts: RequestInit = {} ): Promise< T > {
	return fetch( `${ API_URL }${ path }`, {
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
		...opts,
	} ).then( ( r ) => r.json() );
}
