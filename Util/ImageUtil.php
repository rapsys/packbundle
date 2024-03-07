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
 * Manages image
 */
class ImageUtil {
	/**
	 * The captcha width
	 */
	const width = 192;

	/**
	 * The captcha height
	 */
	const height = 52;

	/**
	 * The captcha background color
	 */
	const background = 'white';

	/**
	 * The captcha fill color
	 */
	const fill = '#cff';

	/**
	 * The captcha font size
	 */
	const fontSize = 45;

	/**
	 * The captcha stroke color
	 */
	const stroke = '#00c3f9';

	/**
	 * The captcha stroke width
	 */
	const strokeWidth = 2;

	/**
	 * The thumb width
	 */
	const thumbWidth = 640;

	/**
	 * The thumb height
	 */
	const thumbHeight = 640;

	/**
	 * Creates a new image util
	 *
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param string $cache The cache directory
	 * @param string $path The public path
	 * @param string $prefix The prefix
	 */
	function __construct(protected RouterInterface $router, protected SluggerUtil $slugger, protected string $cache = '../var/cache', protected string $path = './bundles/rapsyspack', protected string $prefix = 'image', protected string $background = self::background, protected string $fill = self::fill, protected int $fontSize = self::fontSize, protected string $stroke = self::stroke, protected int $strokeWidth = self::strokeWidth) {
	}

	/**
	 * Get captcha data
	 *
	 * @param int $updated The updated timestamp
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The captcha data
	 */
	public function getCaptcha(int $updated, int $width = self::width, int $height = self::height): array {
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
			'src' => $this->router->generate('rapsyspack_captcha', ['hash' => $hash, 'updated' => $updated, 'equation' => $short, 'width' => $width, 'height' => $height]),
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
			'link' => $this->router->generate('rapsyspack_thumb', ['hash' => $link, 'updated' => $updated, 'path' => $short, 'width' => $imageWidth, 'height' => $imageHeight]),
			'src' => $this->router->generate('rapsyspack_thumb', ['hash' => $src, 'updated' => $updated, 'path' => $short, 'width' => $width, 'height' => $height]),
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Get captcha background color
	 */
	public function getBackground() {
		return $this->background;
	}

	/**
	 * Get captcha fill color
	 */
	public function getFill() {
		return $this->fill;
	}

	/**
	 * Get captcha font size
	 */
	public function getFontSize() {
		return $this->fontSize;
	}

	/**
	 * Get captcha stroke color
	 */
	public function getStroke() {
		return $this->stroke;
	}

	/**
	 * Get captcha stroke width
	 */
	public function getStrokeWidth() {
		return $this->strokeWidth;
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
		$dir = $this->path.'/'.$this->prefix.'/'.$hash[0].'/'.$hash[1].'/'.$hash[2].'/'.$updated.'/'.$this->slugger->short($path);

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
