<?php

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

// is_email() only checks shape; bounces hurt sender reputation far more than low opens.
// Conservative on purpose: a false positive silently drops a real subscriber.
class Validator {

	private static $typo_tlds = array(
		'con', 'cim', 'cmo', 'ocm', 'comm', 'vom', 'xom', 'clm', 'con.', 'co,',
		'nte', 'met', 'nrt', 'orgg', 'ogr', 'cok', 'cm', 'om',
	);

	private static $typo_domains = array(
		'gmial.com', 'gmai.com', 'gmil.com', 'gnail.com', 'gmaill.com', 'gmail.co',
		'gamil.com', 'gmail.cm', 'gmail.om', 'hotmial.com', 'hotmai.com', 'hotmal.com',
		'hotmail.co', 'yaho.com', 'yahooo.com', 'yahoo.co', 'ymail.co',
		'outlok.com', 'outloo.com', 'iclod.com', 'iclould.com',
	);

	private static $disposable = array(
		'10mail.xyz', 'mailinator.com', 'tempmail.com', 'guerrillamail.com',
		'10minutemail.com', 'yopmail.com', 'trashmail.com', 'sharklasers.com',
		'throwawaymail.com', 'getnada.com', 'temp-mail.org', 'dispostable.com',
	);

	// One or two character mailboxes on these providers are placeholders, not people.
	private static $major_providers = array(
		'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
		'live.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com',
	);

	public static function junk_reason( string $email ): string {
		$email = strtolower( trim( (string) $email ) );

		if ( ! $email || ! is_email( $email ) ) {
			return '';
		}

		$parts = explode( '@', $email );

		if ( 2 !== count( $parts ) ) {
			return '';
		}

		list( $local, $domain ) = $parts;

		$tld = substr( strrchr( $domain, '.' ), 1 );

		if ( in_array( $tld, self::$typo_tlds, true ) ) {
			return sprintf( 'Misspelled .%s — this will bounce', $tld );
		}

		if ( in_array( $domain, self::$typo_domains, true ) ) {
			return sprintf( 'Misspelled domain "%s" — this will bounce', $domain );
		}

		if ( in_array( $domain, self::$disposable, true ) ) {
			return 'Disposable inbox';
		}

		if ( strlen( $local ) <= 2 && in_array( $domain, self::$major_providers, true ) ) {
			return 'Placeholder address';
		}

		return '';
	}

	public static function is_junk( string $email ): bool {
		return '' !== self::junk_reason( $email );
	}
}
