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

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

/**
 * Helps manage map
 */
class ImageUtil {
	/**
	 * The captcha width
	 */
	const captchaWidth = 192;

	/**
	 * The captcha height
	 */
	const captchaHeight = 52;

	/**
	 * The captcha background color
	 */
	const captchaBackground = 'white';

	/**
	 * The captcha fill color
	 */
	const captchaFill = '#cff';

	/**
	 * The captcha font size
	 */
	const captchaFontSize = 45;

	/**
	 * The captcha stroke color
	 */
	const captchaStroke = '#00c3f9';

	/**
	 * The captcha stroke width
	 */
	const captchaStrokeWidth = 2;

	/**
	 * The thumb width
	 */
	const thumbWidth = 640;

	/**
	 * The thumb height
	 */
	const thumbHeight = 640;

	/**
	 * The cache path
	 */
	protected string $cache;

	/**
	 * The path
	 */
	protected string $path;

	/**
	 * The RouterInterface instance
	 */
	protected RouterInterface $router;

	/**
	 * The SluggerUtil instance
	 */
	protected SluggerUtil $slugger;

	/**
	 * The captcha background
	 */
	public string $captchaBackground;

	/**
	 * The captcha fill
	 */
	public string $captchaFill;

	/**
	 * The captcha font size
	 */
	public int $captchaFontSize;

	/**
	 * The captcha stroke
	 */
	public string $captchaStroke;

	/**
	 * The captcha stroke width
	 */
	public int $captchaStrokeWidth;

	/**
	 * Creates a new image util
	 *
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param string $cache The cache directory
	 * @param string $path The public path
	 * @param string $prefix The prefix
	 */
	function __construct(RouterInterface $router, SluggerUtil $slugger, string $cache = '../var/cache', string $path = './bundles/rapsyspack', string $prefix = 'image', string $captchaBackground = self::captchaBackground, string $captchaFill = self::captchaFill, int $captchaFontSize = self::captchaFontSize, string $captchaStroke = self::captchaStroke, int $captchaStrokeWidth = self::captchaStrokeWidth) {
		//Set cache
		$this->cache = $cache.'/'.$prefix;

		//Set captcha background
		$this->captchaBackground = $captchaBackground;

		//Set captcha fill
		$this->captchaFill = $captchaFill;

		//Set captcha font size
		$this->captchaFontSize = $captchaFontSize;

		//Set captcha stroke
		$this->captchaStroke = $captchaStroke;

		//Set captcha stroke width
		$this->captchaStrokeWidth = $captchaStrokeWidth;

		//Set path
		$this->path = $path.'/'.$prefix;

		//Set router
		$this->router = $router;

		//Set slugger
		$this->slugger = $slugger;
	}

	/**
	 * Get captcha data
	 *
	 * @param int $updated The updated timestamp
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The captcha data
	 */
	public function getCaptcha(int $updated, int $width = self::captchaWidth, int $height = self::captchaHeight): array {
		//Set a
		$a = rand(0, 9);

		//Set b
		$b = rand(0, 5);

		//Set c
		$c = rand(0, 9);

		//Set equation
		$equation = $a.' * '.$b.' + '.$c;

		//Short path
		$short = $this->slugger->short($equation);

		//Set hash
		$hash = $this->slugger->serialize([$updated, $short, $width, $height]);

		//Return array
		return [
			'token' => $this->slugger->hash(strval($a * $b + $c)),
			'value' => strval($a * $b + $c),
			'equation' => str_replace([' ', '*', '+'], ['-', 'mul', 'add'], $equation),
			'src' => $this->router->generate('rapsys_pack_captcha', ['hash' => $hash, 'updated' => $updated, 'equation' => $short, 'width' => $width, 'height' => $height]),
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Get thumb data
	 *
	 * @param string $caption The caption
	 * @param int $updated The updated timestamp
	 * @param string $path The path
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The thumb data
	 */
	public function getThumb(string $caption, int $updated, string $path, int $width = self::thumbWidth, int $height = self::thumbHeight): array {
		//Get image width and height
		list($imageWidth, $imageHeight) = getimagesize($path);

		//Short path
		$short = $this->slugger->short($path);

		//Set link hash
		$link = $this->slugger->serialize([$updated, $short, $imageWidth, $imageHeight]);

		//Set src hash
		$src = $this->slugger->serialize([$updated, $short, $width, $height]);

		//Return array
		return [
			'caption' => $caption,
			'link' => $this->router->generate('rapsys_pack_thumb', ['hash' => $link, 'updated' => $updated, 'path' => $short, 'width' => $imageWidth, 'height' => $imageHeight]),
			'src' => $this->router->generate('rapsys_pack_thumb', ['hash' => $src, 'updated' => $updated, 'path' => $short, 'width' => $width, 'height' => $height]),
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Remove image
	 *
	 * @param int $updated The updated timestamp
	 * @param string $path The path
	 * @return array The thumb clear success
	 */
	public function remove(int $updated, string $path): bool {
		//Set hash tree
		$hash = array_reverse(str_split(strval($updated)));

		//Set dir
		$dir = $this->path.'/'.$hash[0].'/'.$hash[1].'/'.$hash[2].'/'.$updated.'/'.$this->slugger->short($path);

		//Set removes
		$removes = [];

		//With dir
		if (is_dir($dir)) {
			//Add tree to remove
			$removes[] = $dir;

			//Iterate on each file
			foreach(array_merge($removes, array_diff(scandir($dir), ['..', '.'])) as $file) {
				//With file
				if (is_file($dir.'/'.$file)) {
					//Add file to remove
					$removes[] = $dir.'/'.$file;
				}
			}
		}

		//Create filesystem object
		$filesystem = new Filesystem();

		try {
			//Remove list
			$filesystem->remove($removes);
		} catch (IOExceptionInterface $e) {
			//Throw error
			throw new \Exception(sprintf('Unable to delete thumb directory "%s"', $dir), 0, $e);
		}

		//Return success
		return true;
	}
}
