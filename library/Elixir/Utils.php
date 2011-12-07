<?php
/**
 * Contains various helper functions used through Elixir
 * 
 * The class acts as a namespace and is not meant to be initialised
 * or extended.
 * 
 * @author Arwyn Hainsworth
 */
final class Elixir_Utils {

	/**
	 * Generate a valid RFC 4211 compliant UUIDv4 (psudo-random)
	 * @return string
	 */
	static public function UUIDv4() {
		// taken from php.net comment page.
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,
			// 48 bits for "node"
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}