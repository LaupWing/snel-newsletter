<?php
/**
 * Junk detection for imported emails.
 *
 * is_email() only checks shape, so "loc@gmail.con" sails through and hard-bounces.
 * Bounces cost sender reputation far faster than low open rates do, so anything
 * that is almost certainly undeliverable is held back from the import.
 *
 * Deliberately conservative: it only flags mistakes we can name. A weird-looking
 * address on an unknown domain is left alone — a false positive here silently
 * drops a real subscriber, which is worse than one bounce.
 *
 * @package SnelNewsletter
 */

namespace Snel\Newsletter\Subscribers;

defined( 'ABSPATH' ) || exit;

class Validator {

	/** Fat-fingered TLDs. ".con" is one key over from ".com". */
	private static $typo_tlds = array(
		'con', 'cim', 'cmo', 'ocm', 'comm', 'vom', 'xom', 'clm', 'con.', 'co,',
		'nte', 'met', 'nrt', 'orgg', 'ogr', 'cok', 'cm', 'om',
	);

	/** Misspelled mailbox providers. */
	private static $typo_domains = array(
		'gmial.com', 'gmai.com', 'gmil.com', 'gnail.com', 'gmaill.com', 'gmail.co',
		'gamil.com', 'gmail.cm', 'gmail.om', 'hotmial.com', 'hotmai.com', 'hotmal.com',
		'hotmail.co', 'yaho.com', 'yahooo.com', 'yahoo.co', 'ymail.co',
		'outlok.com', 'outloo.com', 'iclod.com', 'iclould.com',
	);

	/** Throwaway inboxes — they accept mail, then vanish. */
	private static $disposable = array(
		'10mail.xyz', 'mailinator.com', 'tempmail.com', 'guerrillamail.com',
		'10minutemail.com', 'yopmail.com', 'trashmail.com', 'sharklasers.com',
		'throwawaymail.com', 'getnada.com', 'temp-mail.org', 'dispostable.com',
	);

	/** Providers where a one or two character mailbox is a placeholder, not a person. */
	private static $major_providers = array(
		'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
		'live.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me', 'protonmail.com',
	);

	/**
	 * Why this address is almost certainly undeliverable.
	 *
	 * @param string $email
	 * @return string Empty when the address looks fine.
	 */
	public static function junk_reason( $email ) {
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

	/**
	 * @return bool
	 */
	public static function is_junk( $email ) {
		return '' !== self::junk_reason( $email );
	}
}
