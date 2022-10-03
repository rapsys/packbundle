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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Helps manage facebook images
 */
class FacebookUtil {
	/**
	 * The cache directory
	 *
	 * @var string
	 */
	protected $cache;

	/**
	 * The fonts array
	 *
	 * @var array
	 */
	protected $fonts;

	/**
	 * The public path
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * The prefix
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * The RouterInterface instance
	 */
	protected RouterInterface $router;

	/**
	 * The source
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * Creates a new facebook util
	 *
	 * @param RouterInterface $router The RouterInterface instance
	 * @param string $cache The cache directory
	 * @param string $path The public path
	 * @param string $prefix The prefix
	 */
	function __construct(RouterInterface $router, string $cache = '../var/cache', string $path = './bundles/rapsyspack', string $prefix = 'facebook', string $source = 'png/facebook.png', array $fonts = [ 'default' => 'ttf/default.ttf' ]) {
		//Set cache
		$this->cache = $cache.'/'.$prefix;

		//Set fonts
		$this->fonts = $fonts;

		//Set path
		$this->path = $path.'/'.$prefix;

		//Set router
		$this->router = $router;

		//Set prefix key
		$this->prefix = $prefix;

		//Set source
		$this->source = $source;
	}

	/**
	 * Return the facebook image
	 *
	 * Generate simple image in jpeg format or load it from cache
	 *
	 * @param string $pathInfo The request path info
	 * @param array $texts The image texts
	 * @param int $updated The updated timestamp
	 * @param ?string $source The image source
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The image array
	 */
	public function getImage(string $pathInfo, array $texts, int $updated, ?string $source = null, int $width = 1200, int $height = 630): array {
		//Set path file
		$path = $this->path.$pathInfo.'.jpeg';

		//Without existing path
		if (!is_dir($dir = dirname($path))) {
			//Create filesystem object
			$filesystem = new Filesystem();

			try {
				//Create path
				//XXX: set as 0775, symfony umask (0022) will reduce rights (0755)
				$filesystem->mkdir($dir, 0775);
			} catch (IOExceptionInterface $e) {
				//Throw error
				throw new \Exception(sprintf('Output path "%s" do not exists and unable to create it', $dir), 0, $e);
			}
		}

		//With path file
		if (is_file($path) && ($mtime = stat($path)['mtime']) && $mtime >= $updated) {
			#XXX: we used to drop texts with $data['canonical'] === true !!!

			//Return image data
			return [
				'og:image' => $this->router->generate('rapsys_pack_facebook', ['mtime' => $mtime, 'path' => $pathInfo], UrlGeneratorInterface::ABSOLUTE_URL),
				'og:image:alt' => str_replace("\n", ' ', implode(' - ', array_keys($texts))),
				'og:image:height' => $height,
				'og:image:width' => $width
			];
		}

		//Set cache path
		$cache = $this->cache.$pathInfo.'.png';

		//Without cache path
		if (!is_dir($dir = dirname($cache))) {
			//Create filesystem object
			$filesystem = new Filesystem();

			try {
				//Create path
				//XXX: set as 0775, symfony umask (0022) will reduce rights (0755)
				$filesystem->mkdir($dir, 0775);
			} catch (IOExceptionInterface $e) {
				//Throw error
				throw new \Exception(sprintf('Output directory "%s" do not exists and unable to create it', $dir), 0, $e);
			}
		}

		//Without source
		if ($source === null) {
			//Set source
			$source = realpath($this->source);
		}

		//Create image object
		$image = new \Imagick();

		//Without cache image
		if (!is_file($cache) || stat($cache)['mtime'] < stat($source)['mtime']) {
			//Check target directory
			if (!is_dir($dir = dirname($cache))) {
				//Create filesystem object
				$filesystem = new Filesystem();

				try {
					//Create dir
					//XXX: set as 0775, symfony umask (0022) will reduce rights (0755)
					$filesystem->mkdir($dir, 0775);
				} catch (IOExceptionInterface $e) {
					//Throw error
					throw new \Exception(sprintf('Output directory "%s" do not exists and unable to create it', $dir), 0, $e);
				}
			}

			//Read image
			$image->readImage($source);

			//Crop using aspect ratio
			//XXX: for better result upload image directly in aspect ratio :)
			$image->cropThumbnailImage($width, $height);

			//Strip image exif data and properties
			$image->stripImage();

			//Save cache image
			if (!$image->writeImage($cache)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $cache));
			}
		//With cache
		} else {
			//Read image
			$image->readImage($cache);
		}

		//Create draw
		$draw = new \ImagickDraw();

		//Set stroke antialias
		$draw->setStrokeAntialias(true);

		//Set text antialias
		$draw->setTextAntialias(true);

		//Set align aliases
		$aligns = [
			'left' => \Imagick::ALIGN_LEFT,
			'center' => \Imagick::ALIGN_CENTER,
			'right' => \Imagick::ALIGN_RIGHT
		];

		//Set default font
		$defaultFont = 'dejavusans';

		//Set default align
		$defaultAlign = 'center';

		//Set default size
		$defaultSize = 60;

		//Set default stroke
		$defaultStroke = '#00c3f9';

		//Set default width
		$defaultWidth = 15;

		//Set default fill
		$defaultFill = 'white';

		//Init counter
		$i = 1;

		//Set text count
		$count = count($texts);

		//Draw each text stroke
		foreach($texts as $text => $data) {
			//Set font
			$draw->setFont($this->fonts[$data['font']??$defaultFont]);

			//Set font size
			$draw->setFontSize($data['size']??$defaultSize);

			//Set stroke width
			$draw->setStrokeWidth($data['width']??$defaultWidth);

			//Set text alignment
			$draw->setTextAlignment($align = ($aligns[$data['align']??$defaultAlign]));

			//Get font metrics
			$metrics = $image->queryFontMetrics($draw, $text);

			//Without y
			if (empty($data['y'])) {
				//Position verticaly each text evenly
				$texts[$text]['y'] = $data['y'] = (($height + 100) / (count($texts) + 1) * $i) - 50;
			}

			//Without x
			if (empty($data['x'])) {
				if ($align == \Imagick::ALIGN_CENTER) {
					$texts[$text]['x'] = $data['x'] = $width/2;
				} elseif ($align == \Imagick::ALIGN_LEFT) {
					$texts[$text]['x'] = $data['x'] = 50;
				} elseif ($align == \Imagick::ALIGN_RIGHT) {
					$texts[$text]['x'] = $data['x'] = $width - 50;
				}
			}

			//Center verticaly
			//XXX: add ascender part then center it back by half of textHeight
			//TODO: maybe add a boundingbox ???
			$texts[$text]['y'] = $data['y'] += $metrics['ascender'] - $metrics['textHeight']/2;

			//Set stroke color
			$draw->setStrokeColor(new \ImagickPixel($data['stroke']??$defaultStroke));

			//Set fill color
			$draw->setFillColor(new \ImagickPixel($data['stroke']??$defaultStroke));

			//Add annotation
			$draw->annotation($data['x'], $data['y'], $text);

			//Increase counter
			$i++;
		}

		//Create stroke object
		$stroke = new \Imagick();

		//Add new image
		$stroke->newImage($width, $height, new \ImagickPixel('transparent'));

		//Draw on image
		$stroke->drawImage($draw);

		//Blur image
		//XXX: blur the stroke canvas only
		$stroke->blurImage(5,3);

		//Set opacity to 0.5
		//XXX: see https://www.php.net/manual/en/image.evaluateimage.php
		$stroke->evaluateImage(\Imagick::EVALUATE_DIVIDE, 1.5, \Imagick::CHANNEL_ALPHA);

		//Compose image
		$image->compositeImage($stroke, \Imagick::COMPOSITE_OVER, 0, 0);

		//Clear stroke
		$stroke->clear();

		//Destroy stroke
		unset($stroke);

		//Clear draw
		$draw->clear();

		//Set text antialias
		$draw->setTextAntialias(true);

		//Draw each text
		foreach($texts as $text => $data) {
			//Set font
			$draw->setFont($this->fonts[$data['font']??$defaultFont]);

			//Set font size
			$draw->setFontSize($data['size']??$defaultSize);

			//Set text alignment
			$draw->setTextAlignment($aligns[$data['align']??$defaultAlign]);

			//Set fill color
			$draw->setFillColor(new \ImagickPixel($data['fill']??$defaultFill));

			//Add annotation
			$draw->annotation($data['x'], $data['y'], $text);

			//With canonical text
			if (!empty($data['canonical'])) {
				//Prevent canonical to finish in alt
				unset($texts[$text]);
			}
		}

		//Draw on image
		$image->drawImage($draw);

		//Strip image exif data and properties
		$image->stripImage();

		//Set image format
		$image->setImageFormat('jpeg');

		//Set progressive jpeg
		$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

		//Save image
		if (!$image->writeImage($path)) {
			//Throw error
			throw new \Exception(sprintf('Unable to write image "%s"', $path));
		}

		//Return image data
		return [
			'og:image' => $this->router->generate('rapsys_pack_facebook', ['mtime' => stat($path)['mtime'], 'path' => $pathInfo], UrlGeneratorInterface::ABSOLUTE_URL),
			'og:image:alt' => str_replace("\n", ' ', implode(' - ', array_keys($texts))),
			'og:image:height' => $height,
			'og:image:width' => $width
		];
	}
}
