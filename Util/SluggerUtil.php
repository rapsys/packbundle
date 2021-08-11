<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Util;

/**
 * Helps manage string conversions
 */
class SluggerUtil {
	//The secret parameter
	private $secret;

	//The alpha array
	private $alpha;

	//The rev array
	private $rev;

	//The alpha array key number
	private $count;

	//The offset reduced from secret
	private $offset;

	/**
	 * Creates a new slugger util
	 *
	 * @todo Add a command to generate alpha array or generate it on first run with cache storage ?
	 * @todo Use Cache like in calendar controller through FilesystemAdapter
	 *
	 * @param string $secret The secret string
	 */
	public function __construct(string $secret) {
		//Set secret
		$this->secret = $secret;

		/**
		 * Pseudo-random alphabet
		 * @xxx use array flip and keys to workaround php "smart" that cast range('0', '9') as int instead of string
		 * @xxx The key count mismatch, count(alpha)>count(rev), resulted in a data corruption due to duplicate numeric values
		 * @todosee required range by json_encode result and short input (0->255 ???)
		 */
		$this->alpha = array_keys(array_flip(array_merge(
			range('^', '[', -1),
			range('V', 'Z'),
			range('9', '7', -1),
			range('L', 'O'),
			range('f', 'a', -1),
			range('_', '`'),
			range('3', '0', -1),
			range('E', 'H'),
			range('v', 'r', -1),
			range('+', '/'),
			range('K', 'I', -1),
			range('g', 'j'),
			range('=', ':', -1),
			range('>', '@'),
			range('m', 'k', -1),
			range('4', '6'),
			range('*', '%', -1),
			range('n', 'q'),
			range('U', 'P', -1),
			range(' ', '$'),
			range('D', 'A', -1),
			range('w', 'z'),
			range('~', '!', -1)
		)));

		//Init rev array
		$this->count = count($rev = $this->rev = array_flip($this->alpha));

		//Init split
		$split = str_split($this->secret);

		//Set offset
		$this->offset = array_reduce($split, function ($res, $a) use ($rev) { return $res += $rev[$a]; }, count($split)) % $this->count;
	}

	/**
	 * Flatten recursively an array
	 *
	 * @param array|string $data The data tree
	 * @param string|null $current The current prefix
	 * @param string $sep The key separator
	 * @param string $prefix The key prefix
	 * @param string $suffix The key suffix
	 * @return array The flattened data
	 */
	public function flatten($data, ?string $current = null, string $sep = '.', string $prefix = '', string $suffix = ''): array {
		//Init result
		$ret = [];

		//Look for data array
		if (is_array($data)) {
			//Iteare on each pair
			foreach($data as $k => $v) {
				//Merge flattened value in return array
				$ret += $this->flatten($v, empty($current) ? $k : $current.$sep.$k, $sep, $prefix, $suffix);
			}
		//Look flat data
		} else {
			//Store data in flattened key
			$ret[$prefix.$current.$suffix] = $data;
		}

		//Return result
		return $ret;
	}

	/**
	 * Crypt and base64uri encode string
	 *
	 * @param string $data The data string
	 * @return string The hashed data
	 */
	public function hash(string $data): string {
		//Return hashed data
		//XXX: we use hash_hmac with md5 hash
		//XXX: crypt was dropped because it provided identical signature for string starting with same pattern
		return str_replace(['+','/'], ['-','_'], base64_encode(hash_hmac('md5', $data, $this->secret, true)));
	}

	/**
	 * Serialize then short
	 *
	 * @param array $data The data array
	 * @return string The serialized and shorted data
	 */
	public function serialize(array $data): string {
		//Return shorted serialized data
		//XXX: dropped serialize use to prevent short function from dropping utf-8 characters
		return $this->short(json_encode($data));
	}

	/**
	 * Short
	 *
	 * @param string $data The data string
	 * @return string The shorted data
	 */
	public function short(string $data): string {
		//Return string
		$ret = '';

		//With data
		if (!empty($data)) {
			//Iterate on each character
			foreach(str_split($data) as $k => $c) {
				if (isset($this->rev[$c]) && isset($this->alpha[($this->rev[$c]+$this->offset)%$this->count])) {
					//XXX: Remap char to an other one
					$ret .= chr(($this->rev[$c] - $this->offset + $this->count) % $this->count);
				} else {
					throw new \RuntimeException(sprintf('Unable to retrieve character: %c', $c));
				}
			}
		}

		//Send result
		return str_replace(['+','/'], ['-','_'], base64_encode($ret));
	}

	/**
	 * Convert string to safe slug
	 *
	 * @param string $data The data string
	 * @return string The slugged data
	 */
	function slug(string $data): string {
		//Use Transliterator if available
		if (class_exists('Transliterator')) {
			//Convert from any to latin, then to ascii and lowercase
			$trans = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
			//Replace every non alphanumeric character by dash then trim dash
			return trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $trans->transliterate($data)), '-');
		}
		//Convert from utf-8 to ascii, replace quotes with space, remove non alphanumericseparator, replace separator with dash and trim dash
		return trim(preg_replace('/[\/_|+ -]+/', '-', strtolower(preg_replace('/[^a-zA-Z0-9\/_|+ -]/', '', str_replace(['\'', '"'], ' ', iconv('UTF-8', 'ASCII//TRANSLIT', $data))))), '-');
	}

	/**
	 * Unshort then unserialize
	 *
	 * @param string $data The data string
	 * @return array The unshorted and unserialized data
	 */
	public function unserialize(string $data): array {
		//Return unshorted unserialized string
		return json_decode($this->unshort($data), true);
	}

	/**
	 * Unshort
	 *
	 * @param string $data The data string
	 * @return string The unshorted data
	 */
	public function unshort(string $data): string {
		//Return string
		$ret = '';

		//Iterate on each character
		foreach(str_split(base64_decode(str_replace(['-','_'], ['+','/'], $data))) as $c) {
			//XXX: Reverse map char to an other one
			$ret .= $this->alpha[(ord($c) + $this->offset) % $this->count];
		}

		//Send result
		return $ret;
	}
}
