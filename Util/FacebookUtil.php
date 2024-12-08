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

use Psr\Container\ContainerInterface;

use Rapsys\PackBundle\RapsysPackBundle;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Helps manage facebook images
 */
class FacebookUtil {
	/**
	 * Alias string
	 */
	protected string $alias;

	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * Creates a new facebook util
	 *
	 * @param ContainerInterface $container The container instance
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 */
	public function __construct(protected ContainerInterface $container, protected RouterInterface $router, protected SluggerUtil $slugger) {
		//Retrieve config
		$this->config = $container->getParameter($this->alias = RapsysPackBundle::getAlias());
	}

	/**
	 * Return the facebook image
	 *
	 * Generate simple image in jpeg format or load it from cache
	 *
	 * @TODO: move to a svg merging system ?
	 *
	 * @param string $path The request path info
	 * @param array $texts The image texts
	 * @param int $updated The updated timestamp
	 * @param ?string $source The image source
	 * @param ?int $height The height
	 * @param ?int $width The width
	 * @return array The image array
	 */
	public function getImage(string $path, array $texts, int $updated, ?string $source = null, ?int $height = null, ?int $width = null): array {
		//Without source
		if ($source === null && $this->config['facebook']['source'] === null) {
			//Return empty image data
			return [];
		//Without local source
		} elseif ($source === null) {
			//Set local source
			$source = $this->config['facebook']['source'];
		}

		//Without width
		if ($width === null) {
			//Set width from config
			$width = $this->config['facebook']['width'];
		}

		//Without height
		if ($height === null) {
			//Set height from config
			$height = $this->config['facebook']['height'];
		}

		//Set path file
		$facebook = $this->config['cache'].'/'.$this->config['prefixes']['facebook'].$path.'.jpeg';

		//Without existing path
		if (!is_dir($dir = dirname($facebook))) {
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
		if (is_file($facebook) && ($mtime = stat($facebook)['mtime']) && $mtime >= $updated) {
			#XXX: we used to drop texts with $data['canonical'] === true !!!

			//Set short path
			$short = $this->slugger->short($path);

			//Set hash
			$hash = $this->slugger->serialize([$short, $height, $width]);

			//Return image data
			return [
				'og:image' => $this->router->generate('rapsyspack_facebook', ['hash' => $hash, 'path' => $short, 'height' => $height, 'width' => $width, 'u' => $mtime], UrlGeneratorInterface::ABSOLUTE_URL),
				'og:image:alt' => str_replace("\n", ' ', implode(' - ', array_keys($texts))),
				'og:image:height' => $height,
				'og:image:width' => $width
			];
		}

		//Set cache path
		$cache = $this->config['cache'].'/'.$this->config['prefixes']['facebook'].$path.'.png';

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

			//Without source
			if (!is_file($source)) {
				//Throw error
				throw new \Exception(sprintf('Source file "%s" do not exists', $source));
			}

			//Convert to absolute path
			$source = realpath($source);

			//Read image
			//XXX: Imagick::readImage only supports absolute path
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

		//Init counter
		$i = 1;

		//Set text count
		$count = count($texts);

		//Draw each text stroke
		foreach($texts as $text => $data) {
			//Set font
			$draw->setFont($this->config['fonts'][$data['font']??$this->config['facebook']['font']]);

			//Set font size
			$draw->setFontSize($data['size']??$this->config['facebook']['size']);

			//Set stroke width
			$draw->setStrokeWidth($data['thickness']??$this->config['facebook']['thickness']);

			//Set text alignment
			$draw->setTextAlignment($align = ($aligns[$data['align']??$this->config['facebook']['align']]));

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
			$draw->setStrokeColor(new \ImagickPixel($data['border']??$this->config['facebook']['border']));

			//Set fill color
			$draw->setFillColor(new \ImagickPixel($data['fill']??$this->config['facebook']['fill']));

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
			$draw->setFont($this->config['fonts'][$data['font']??$this->config['facebook']['font']]);

			//Set font size
			$draw->setFontSize($data['size']??$this->config['facebook']['size']);

			//Set text alignment
			$draw->setTextAlignment($aligns[$data['align']??$this->config['facebook']['align']]);

			//Set fill color
			$draw->setFillColor(new \ImagickPixel($data['fill']??$this->config['facebook']['fill']));

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
		if (!$image->writeImage($facebook)) {
			//Throw error
			throw new \Exception(sprintf('Unable to write image "%s"', $facebook));
		}

		//Set short path
		$short = $this->slugger->short($path);

		//Set hash
		$hash = $this->slugger->serialize([$short, $height, $width]);

		//Return image data
		return [
			'og:image' => $this->router->generate('rapsyspack_facebook', ['hash' => $hash, 'path' => $short, 'height' => $height, 'width' => $width, 'u' => stat($facebook)['mtime']], UrlGeneratorInterface::ABSOLUTE_URL),
			'og:image:alt' => str_replace("\n", ' ', implode(' - ', array_keys($texts))),
			'og:image:height' => $height,
			'og:image:width' => $width
		];
	}
}
