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
 * Manages image
 */
class ImageUtil {
	/**
	 * Alias string
	 */
	protected string $alias;

	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * Creates a new image util
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
	 * Get captcha data
	 *
	 * @param ?int $height The height
	 * @param ?int $width The width
	 * @return array The captcha data
	 */
	public function getCaptcha(?int $height = null, ?int $width = null): array {
		//Without height
		if ($height === null) {
			//Set height from config
			$height = $this->config['captcha']['height'];
		}

		//Without width
		if ($width === null) {
			//Set width from config
			$width = $this->config['captcha']['width'];
		}

		//Get random
		$random = rand(0, 999);

		//Set a
		$a = $random % 10;

		//Set b
		$b = $random / 10 % 10;

		//Set c
		$c = $random / 100 % 10;

		//Set equation
		$equation = $a.' * '.$b.' + '.$c;

		//Short path
		$short = $this->slugger->short($equation);

		//Set hash
		$hash = $this->slugger->serialize([$short, $height, $width]);

		//Return array
		return [
			'token' => $this->slugger->hash(strval($a * $b + $c)),
			'value' => strval($a * $b + $c),
			'equation' => str_replace([' ', '*', '+'], ['-', 'mul', 'add'], $equation),
			'src' => $this->router->generate('rapsyspack_captcha', ['hash' => $hash, 'equation' => $short, 'height' => $height, 'width' => $width, '_format' => $this->config['captcha']['format']]),
			'width' => $width,
			'height' => $height
		];
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
	public function getFacebook(string $path, array $texts, int $updated, ?string $source = null, ?int $height = null, ?int $width = null): array {
		//Without source
		if ($source === null && $this->config['facebook']['source'] === null) {
			//Return empty image data
			return [];
		//Without local source
		} elseif ($source === null) {
			//Set local source
			$source = $this->config['facebook']['source'];
		}

		//Without height
		if ($height === null) {
			//Set height from config
			$height = $this->config['facebook']['height'];
		}

		//Without width
		if ($width === null) {
			//Set width from config
			$width = $this->config['facebook']['width'];
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
				'og:image' => $this->router->generate('rapsyspack_facebook', ['hash' => $hash, 'path' => $short, 'height' => $height, 'width' => $width, 'u' => $mtime, '_format' => $this->config['facebook']['format']], UrlGeneratorInterface::ABSOLUTE_URL),
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
			'og:image' => $this->router->generate('rapsyspack_facebook', ['hash' => $hash, 'path' => $short, 'height' => $height, 'width' => $width, 'u' => stat($facebook)['mtime'], '_format' => $this->config['facebook']['format']], UrlGeneratorInterface::ABSOLUTE_URL),
			'og:image:alt' => str_replace("\n", ' ', implode(' - ', array_keys($texts))),
			'og:image:height' => $height,
			'og:image:width' => $width
		];
	}

	/**
	 * Get map data
	 *
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param ?int $height The height
	 * @param ?int $width The width
	 * @param ?int $zoom The zoom
	 * @return array The map data
	 */
	public function getMap(float $latitude, float $longitude, ?int $height = null, ?int $width = null, ?int $zoom = null): array {
		//Without height
		if ($height === null) {
			//Set height from config
			$height = $this->config['map']['height'];
		}

		//Without width
		if ($width === null) {
			//Set width from config
			$width = $this->config['map']['width'];
		}

		//Without zoom
		if ($zoom === null) {
			//Set zoom from config
			$zoom = $this->config['map']['zoom'];
		}

		//Set hash
		$hash = $this->slugger->hash([$height, $width, $zoom, $latitude, $longitude]);

		//Return array
		return [
			'latitude' => $latitude,
			'longitude' => $longitude,
			'height' => $height,
			'src' => $this->router->generate('rapsyspack_map', ['hash' => $hash, 'height' => $height, 'width' => $width, 'zoom' => $zoom, 'latitude' => $latitude, 'longitude' => $longitude, '_format' => $this->config['map']['format']]),
			'width' => $width,
			'zoom' => $zoom
		];
	}

	/**
	 * Get multi map data
	 *
	 * @param array $coordinates The coordinates array
	 * @param ?int $height The height
	 * @param ?int $width The width
	 * @param ?int $zoom The zoom
	 * @return array The multi map data
	 */
	public function getMulti(array $coordinates, ?int $height = null, ?int $width = null, ?int $zoom = null): array {
		//Without coordinates
		if ($coordinates === []) {
			//Throw error
			throw new \Exception('Missing coordinates');
		}

		//Without height
		if ($height === null) {
			//Set height from config
			$height = $this->config['multi']['height'];
		}

		//Without width
		if ($width === null) {
			//Set width from config
			$width = $this->config['multi']['width'];
		}

		//Without zoom
		if ($zoom === null) {
			//Set zoom from config
			$zoom = $this->config['multi']['zoom'];
		}

		//Initialize latitudes and longitudes arrays
		$latitudes = $longitudes = [];

		//Set coordinate
		$coordinate = implode(
			'-',
			array_map(
				function ($v) use (&$latitudes, &$longitudes) {
					//Get latitude and longitude
					list($latitude, $longitude) = $v;

					//Append latitude
					$latitudes[] = $latitude;

					//Append longitude
					$longitudes[] = $longitude;

					//Append coordinate
					return $latitude.','.$longitude;
				},
				$coordinates
			)
		);

		//Set latitude
		$latitude = round((min($latitudes)+max($latitudes))/2, 6);

		//Set longitude
		$longitude = round((min($longitudes)+max($longitudes))/2, 6);

		//Set zoom
		$zoom = $this->getZoom($latitude, $longitude, $coordinates, $height, $width, $zoom);

		//Set hash
		$hash = $this->slugger->hash([$height, $width, $zoom, $coordinate]);

		//Return array
		return [
			'coordinate' => $coordinate,
			'height' => $height,
			'src' => $this->router->generate('rapsyspack_multi', ['hash' => $hash, 'height' => $height, 'width' => $width, 'zoom' => $zoom, 'coordinate' => $coordinate, '_format' => $this->config['multi']['format']]),
			'width' => $width,
			'zoom' => $zoom
		];
	}

	/**
	 * Get multi zoom
	 *
	 * Compute a zoom to have all coordinates on multi map
	 * Multi map visible only from -($width / 2) until ($width / 2) and from -($height / 2) until ($height / 2)
	 *
	 * @TODO Wether we need to take in consideration circle radius in coordinates comparisons, likely +/-(radius / $this->config['multi']['tz'])
	 *
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param array $coordinates The coordinates array
	 * @param int $height The height
	 * @param int $width The width
	 * @param int $zoom The zoom
	 * @return int The zoom
	 */
	public function getZoom(float $latitude, float $longitude, array $coordinates, int $height, int $width, int $zoom): int {
		//Iterate on each zoom
		for ($i = $zoom; $i >= 1; $i--) {
			//Get tile xy
			$centerX = $this->longitudeToX($longitude, $i);
			$centerY = $this->latitudeToY($latitude, $i);

			//Calculate start xy
			$startX = floor($centerX - $width / 2 / $this->config['multi']['tz']);
			$startY = floor($centerY - $height / 2 / $this->config['multi']['tz']);

			//Calculate end xy
			$endX = ceil($centerX + $width / 2 / $this->config['multi']['tz']);
			$endY = ceil($centerY + $height / 2 / $this->config['multi']['tz']);

			//Iterate on each coordinates
			foreach($coordinates as $k => $coordinate) {
				//Get coordinates
				list($clatitude, $clongitude) = $coordinate;

				//Set dest x
				$destX = $this->longitudeToX($clongitude, $i);

				//With outside point
				if ($startX >= $destX || $endX <= $destX) {
					//Skip zoom
					continue(2);
				}

				//Set dest y
				$destY = $this->latitudeToY($clatitude, $i);

				//With outside point
				if ($startY >= $destY || $endY <= $destY) {
					//Skip zoom
					continue(2);
				}
			}

			//Found zoom
			break;
		}

		//Return zoom
		return $i;
	}

	/**
	 * Get thumb data
	 *
	 * @param string $path The path
	 * @param ?int $height The height
	 * @param ?int $width The width
	 * @return array The thumb data
	 */
	public function getThumb(string $path, ?int $height = null, ?int $width = null): array {
		//Without height
		if ($height === null) {
			//Set height from config
			$height = $this->config['thumb']['height'];
		}

		//Without width
		if ($width === null) {
			//Set width from config
			$width = $this->config['thumb']['width'];
		}

		//Short path
		$short = $this->slugger->short($path);

		//Set hash
		$hash = $this->slugger->serialize([$short, $height, $width]);

		#TODO: compute thumb from file type ?
		#TODO: see if setting format there is smart ? we do not yet know if we want a image or movie thumb ?
		#TODO: do we add to route '_format' => $this->config['thumb']['format']

		//Return array
		return [
			'src' => $this->router->generate('rapsyspack_thumb', ['hash' => $hash, 'path' => $short, 'height' => $height, 'width' => $width]),
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Convert longitude to tile x number
	 *
	 * @see https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Lon..2Flat._to_tile_numbers_5
	 *
	 * @param float $longitude The longitude
	 * @param int $zoom The zoom
	 *
	 * @return float The tile x
	 */
	public function longitudeToX(float $longitude, int $zoom): float {
		return (($longitude + 180) / 360) * pow(2, $zoom);
	}

	/**
	 * Convert latitude to tile y number
	 *
	 * @see https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Lon..2Flat._to_tile_numbers_5
	 *
	 * @param $latitude The latitude
	 * @param $zoom The zoom
	 *
	 * @return float The tile y
	 */
	public function latitudeToY(float $latitude, int $zoom): float {
		return (1 - log(tan(deg2rad($latitude)) + 1 / cos(deg2rad($latitude))) / pi()) / 2 * pow(2, $zoom);
	}

	/**
	 * Convert tile x to longitude
	 *
	 * @param float $x The tile x
	 * @param int $zoom The zoom
	 *
	 * @return float The longitude
	 */
	public function xToLongitude(float $x, int $zoom): float {
		return $x / pow(2, $zoom) * 360.0 - 180.0;
	}

	/**
	 * Convert tile y to latitude
	 *
	 * @param float $y The tile y
	 * @param int $zoom The zoom
	 *
	 * @return float The latitude
	 */
	public function yToLatitude(float $y, int $zoom): float {
		return rad2deg(atan(sinh(pi() * (1 - 2 * $y / pow(2, $zoom)))));
	}

	/**
	 * Convert decimal latitude to sexagesimal
	 *
	 * @param float $latitude The decimal latitude
	 *
	 * @return string The sexagesimal longitude
	 */
	public function latitudeToSexagesimal(float $latitude): string {
		//Set degree
		//TODO: see if round or intval is better suited to fix the Deprecated: Implicit conversion from float to int loses precision
		$degree = round($latitude) % 60;

		//Set minute
		$minute = round(($latitude - $degree) * 60) % 60;

		//Set second
		$second = round(($latitude - $degree - $minute / 60) * 3600) % 3600;

		//Return sexagesimal longitude
		return $degree.'°'.$minute.'\''.$second.'"'.($latitude >= 0 ? 'N' : 'S');
	}

	/**
	 * Convert decimal longitude to sexagesimal
	 *
	 * @param float $longitude The decimal longitude
	 *
	 * @return string The sexagesimal longitude
	 */
	public function longitudeToSexagesimal(float $longitude): string {
		//Set degree
		//TODO: see if round or intval is better suited to fix the Deprecated: Implicit conversion from float to int loses precision
		$degree = round($longitude) % 60;

		//Set minute
		$minute = round(($longitude - $degree) * 60) % 60;

		//Set second
		$second = round(($longitude - $degree - $minute / 60) * 3600) % 3600;

		//Return sexagesimal longitude
		return $degree.'°'.$minute.'\''.$second.'"'.($longitude >= 0 ? 'E' : 'W');
	}

	/**
	 * Remove image
	 *
	 * @param int $updated The updated timestamp
	 * @param string $prefix The prefix
	 * @param string $path The path
	 * @return array The thumb clear success
	 */
	public function remove(int $updated, string $prefix, string $path): bool {
		die('TODO: see how to make it work');

		//Without valid prefix
		if (!isset($this->config['prefixes'][$prefix])) {
			//Throw error
			throw new \Exception(sprintf('Invalid prefix "%s"', $prefix));
		}

		//Set hash tree
		$hash = array_reverse(str_split(strval($updated)));

		//Set dir
		$dir = $this->config['public'].'/'.$this->config['prefixes'][$prefix].'/'.$hash[0].'/'.$hash[1].'/'.$hash[2].'/'.$this->slugger->short($path);

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
