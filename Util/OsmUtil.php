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

/**
 * Helps manage osm images
 */
class OsmUtil {
	/**
	 * The tile size
	 */
	const tz = 256;

	/**
	 * The cache directory
	 */
	protected $cache;

	/**
	 * The public path
	 */
	protected $path;

	/**
	 * The tile server
	 */
	protected $server;

	/**
	 * The tile servers
	 *
	 * @see https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Tile_servers
	 */
	protected $servers = [
		'osm' => 'https://tile.openstreetmap.org/{Z}/{X}/{Y}.png',
		'cycle' => 'http://a.tile.thunderforest.com/cycle/{Z}/{X}/{Y}.png',
		'transport' => 'http://a.tile.thunderforest.com/transport/{Z}/{X}/{Y}.png'
	];

	/**
	 * The public url
	 */
	protected $url;

	/**
	 * Creates a new osm util
	 *
	 * @param string $cache The cache directory
	 * @param string $path The public path
	 * @param string $url The public url
	 * @param string $server The server key
	 */
	function __construct(string $cache, string $path, string $url, string $server = 'osm') {
		//Set cache
		$this->cache = $cache.'/'.$server;

		//Set path
		$this->path = $path.'/'.$server;

		//Set url
		$this->url = $url.'/'.$server;

		//Set server key
		$this->server = $server;
	}

	/**
	 * Return the simple image
	 *
	 * Generate simple image in jpeg format or load it from cache
	 *
	 * @param string $pathInfo The path info
	 * @param string $caption The image caption
	 * @param int $updated The updated timestamp
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param int $zoom The zoom
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The image array
	 */
	#TODO: rename to getSimple ???
	public function getImage(string $pathInfo, string $caption, int $updated, float $latitude, float $longitude, int $zoom = 18, int $width = 1280, int $height = 1280): array {
		//Set path file
		$path = $this->path.$pathInfo.'.jpeg';

		//Set min file
		$min = $this->path.$pathInfo.'.min.jpeg';

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

		//With path and min up to date file
		if (is_file($path) && is_file($min) && ($mtime = stat($path)['mtime']) && ($mintime = stat($min)['mtime']) && $mtime >= $updated && $mintime >= $updated) {
			//Return image data
			return [
				'link' => $this->url.'/'.$mtime.$pathInfo.'.jpeg',
				'min' => $this->url.'/'.$mintime.$pathInfo.'.min.jpeg',
				'caption' => $caption,
				'height' => $height / 2,
				'width' => $width / 2
			];
		}

		//Create image instance
		$image = new \Imagick();

		//Add new image
		$image->newImage($width, $height, new \ImagickPixel('transparent'), 'jpeg');

		//Create tile instance
		$tile = new \Imagick();

		//Init context
		$ctx = stream_context_create(
			[
				'http' => [
					#'header' => ['Referer: https://www.openstreetmap.org/'],
					'max_redirects' => 5,
					'timeout' => (int)ini_get('default_socket_timeout'),
					#'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36',
					'user_agent' => (string)ini_get('user_agent')?:'rapsys_air/2.0.0',
				]
			]
		);

		//Get tile xy
		$tileX = floor($centerX = $this->longitudeToX($longitude, $zoom));
		$tileY = floor($centerY = $this->latitudeToY($latitude, $zoom));

		//Calculate start xy
		$startX = floor(($tileX * self::tz - $width) / self::tz);
		$startY = floor(($tileY * self::tz - $height) / self::tz);

		//Calculate end xy
		$endX = ceil(($tileX * self::tz + $width) / self::tz);
		$endY = ceil(($tileY * self::tz + $height) / self::tz);

		for($x = $startX; $x <= $endX; $x++) {
			for($y = $startY; $y <= $endY; $y++) {
				//Set cache path
				$cache = $this->cache.'/'.$zoom.'/'.$x.'/'.$y.'.png';

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

				//Without cache image
				if (!is_file($cache)) {
					//Set tile url
					$tileUri = str_replace(['{Z}', '{X}', '{Y}'], [$zoom, $x, $y], $this->servers[$this->server]);

					//Store tile in cache
					file_put_contents($cache, file_get_contents($tileUri, false, $ctx));
				}

				//Set dest x
				$destX = intval(floor(($width / 2) - self::tz * ($centerX - $x)));

				//Set dest y
				$destY = intval(floor(($height / 2) - self::tz * ($centerY - $y)));

				//Read tile from cache
				$tile->readImage($cache);

				//Compose image
				$image->compositeImage($tile, \Imagick::COMPOSITE_OVER, $destX, $destY);

				//Clear tile
				$tile->clear();
			}
		}

		//Add circle
		//XXX: see https://www.php.net/manual/fr/imagick.examples-1.php#example-3916
		$draw = new \ImagickDraw();

		//Set text antialias
		$draw->setTextAntialias(true);

		//Set stroke antialias
		$draw->setStrokeAntialias(true);

		//Set fill color
		$draw->setFillColor('#c3c3f9');

		//Set stroke color
		$draw->setStrokeColor('#3333c3');

		//Set stroke width
		$draw->setStrokeWidth(2);

		//Draw circle
		$draw->circle($width/2, $height/2 - 5, $width/2 + 10, $height/2 + 5);

		//Draw on image
		$image->drawImage($draw);

		//Strip image exif data and properties
		$image->stripImage();

		//Add latitude
		//XXX: not supported by imagick :'(
		$image->setImageProperty('exif:GPSLatitude', $this->latitudeToSexagesimal($latitude));

		//Add longitude
		//XXX: not supported by imagick :'(
		$image->setImageProperty('exif:GPSLongitude', $this->longitudeToSexagesimal($longitude));

		//Add description
		//XXX: not supported by imagick :'(
		$image->setImageProperty('exif:Description', $caption);

		//Set progressive jpeg
		$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

		//Save image
		if (!$image->writeImage($path)) {
			//Throw error
			throw new \Exception(sprintf('Unable to write image "%s"', $path));
		}

		//Crop using aspect ratio
		$image->cropThumbnailImage($width / 2, $height / 2);

		//Set compression quality
		$image->setImageCompressionQuality(70);

		//Save min image
		if (!$image->writeImage($min)) {
			//Throw error
			throw new \Exception(sprintf('Unable to write image "%s"', $min));
		}

		//Return image data
		return [
			'link' => $this->url.'/'.stat($path)['mtime'].$pathInfo.'.jpeg',
			'min' => $this->url.'/'.stat($min)['mtime'].$pathInfo.'.min.jpeg',
			'caption' => $caption,
			'height' => $height / 2,
			'width' => $width / 2
		];
	}

	/**
	 * Return the multi image
	 *
	 * Generate multi image in jpeg format or load it from cache
	 *
	 * @param string $pathInfo The path info
	 * @param string $caption The image caption
	 * @param int $updated The updated timestamp
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param array $locations The latitude array
	 * @param int $zoom The zoom
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The image array
	 */
	public function getMultiImage(string $pathInfo, string $caption, int $updated, float $latitude, float $longitude, array $locations, int $zoom = 18, int $width = 1280, int $height = 1280): array {
		//Set path file
		$path = $this->path.$pathInfo.'.jpeg';

		//Set min file
		$min = $this->path.$pathInfo.'.min.jpeg';

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

		//With path and min up to date file
		if (is_file($path) && is_file($min) && ($mtime = stat($path)['mtime']) && ($mintime = stat($min)['mtime']) && $mtime >= $updated && $mintime >= $updated) {
			//Return image data
			return [
				'link' => $this->url.'/'.$mtime.$pathInfo.'.jpeg',
				'min' => $this->url.'/'.$mintime.$pathInfo.'.min.jpeg',
				'caption' => $caption,
				'height' => $height / 2,
				'width' => $width / 2
			];
		}

		//Create image instance
		$image = new \Imagick();

		//Add new image
		$image->newImage($width, $height, new \ImagickPixel('transparent'), 'jpeg');

		//Create tile instance
		$tile = new \Imagick();

		//Init context
		$ctx = stream_context_create(
			[
				'http' => [
					#'header' => ['Referer: https://www.openstreetmap.org/'],
					'max_redirects' => 5,
					'timeout' => (int)ini_get('default_socket_timeout'),
					#'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36',
					'user_agent' => (string)ini_get('user_agent')?:'rapsys_air/2.0.0',
				]
			]
		);

		//Get tile xy
		$tileX = floor($centerX = $this->longitudeToX($longitude, $zoom));
		$tileY = floor($centerY = $this->latitudeToY($latitude, $zoom));

		//Calculate start xy
		//XXX: we draw every tile starting beween -($width / 2) and 0
		$startX = floor(($tileX * self::tz - $width) / self::tz);
		//XXX: we draw every tile starting beween -($height / 2) and 0
		$startY = floor(($tileY * self::tz - $height) / self::tz);

		//Calculate end xy
		//TODO: this seems stupid, check if we may just divide $width / 2 here !!!
		//XXX: we draw every tile starting beween $width + ($width / 2)
		$endX = ceil(($tileX * self::tz + $width) / self::tz);
		//XXX: we draw every tile starting beween $width + ($width / 2)
		$endY = ceil(($tileY * self::tz + $height) / self::tz);

		for($x = $startX; $x <= $endX; $x++) {
			for($y = $startY; $y <= $endY; $y++) {
				//Set cache path
				$cache = $this->cache.'/'.$zoom.'/'.$x.'/'.$y.'.png';

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

				//Without cache image
				if (!is_file($cache)) {
					//Set tile url
					$tileUri = str_replace(['{Z}', '{X}', '{Y}'], [$zoom, $x, $y], $this->servers[$this->server]);

					//Store tile in cache
					file_put_contents($cache, file_get_contents($tileUri, false, $ctx));
				}

				//Set dest x
				$destX = intval(floor(($width / 2) - self::tz * ($centerX - $x)));

				//Set dest y
				$destY = intval(floor(($height / 2) - self::tz * ($centerY - $y)));

				//Read tile from cache
				$tile->readImage($cache);

				//Compose image
				$image->compositeImage($tile, \Imagick::COMPOSITE_OVER, $destX, $destY);

				//Clear tile
				$tile->clear();
			}
		}

		//Add circle
		//XXX: see https://www.php.net/manual/fr/imagick.examples-1.php#example-3916
		$draw = new \ImagickDraw();

		//Set text alignment
		$draw->setTextAlignment(\Imagick::ALIGN_CENTER);

		//Set text antialias
		$draw->setTextAntialias(true);

		//Set stroke antialias
		$draw->setStrokeAntialias(true);

		//Iterate on locations
		foreach($locations as $k => $location) {
			//Set dest x
			$destX = intval(floor(($width / 2) - self::tz * ($centerX - $this->longitudeToX($location['longitude'], $zoom))));

			//Set dest y
			$destY = intval(floor(($height / 2) - self::tz * ($centerY - $this->latitudeToY($location['latitude'], $zoom))));

			//Set fill color
			$draw->setFillColor('#cff');

			//Set font size
			$draw->setFontSize(20);

			//Set stroke color
			$draw->setStrokeColor('#00c3f9');

			//Set circle radius
			$radius = 5;

			//Set stroke
			$stroke = 2;

			//With matching position
			if ($location['latitude'] === $latitude && $location['longitude'] == $longitude) {
				//Set fill color
				$draw->setFillColor('#c3c3f9');

				//Set font size
				$draw->setFontSize(30);

				//Set stroke color
				$draw->setStrokeColor('#3333c3');

				//Set circle radius
				$radius = 8;

				//Set stroke
				$stroke = 4;
			}

			//Set stroke width
			$draw->setStrokeWidth($stroke);

			//Draw circle
			$draw->circle($destX, $destY - $radius, $destX + $radius * 2, $destY + $radius);

			//Set fill color
			$draw->setFillColor($draw->getStrokeColor());

			//Set stroke width
			$draw->setStrokeWidth($stroke / 4);

			//Get font metrics
			$metrics = $image->queryFontMetrics($draw, strval($location['id']));

			//Add annotation
			$draw->annotation($destX, $destY - $metrics['descender'] / 3, strval($location['id']));
		}

		//Draw on image
		$image->drawImage($draw);

		//Strip image exif data and properties
		$image->stripImage();

		//Add latitude
		//XXX: not supported by imagick :'(
		$image->setImageProperty('exif:GPSLatitude', $this->latitudeToSexagesimal($latitude));

		//Add longitude
		//XXX: not supported by imagick :'(
		$image->setImageProperty('exif:GPSLongitude', $this->longitudeToSexagesimal($longitude));

		//Add description
		//XXX: not supported by imagick :'(
		$image->setImageProperty('exif:Description', $caption);

		//Set progressive jpeg
		$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

		//Save image
		if (!$image->writeImage($path)) {
			//Throw error
			throw new \Exception(sprintf('Unable to write image "%s"', $path));
		}

		//Crop using aspect ratio
		$image->cropThumbnailImage($width / 2, $height / 2);

		//Set compression quality
		$image->setImageCompressionQuality(70);

		//Save min image
		if (!$image->writeImage($min)) {
			//Throw error
			throw new \Exception(sprintf('Unable to write image "%s"', $min));
		}

		//Return image data
		return [
			'link' => $this->url.'/'.stat($path)['mtime'].$pathInfo.'.jpeg',
			'min' => $this->url.'/'.stat($min)['mtime'].$pathInfo.'.min.jpeg',
			'caption' => $caption,
			'height' => $height / 2,
			'width' => $width / 2
		];
	}

	/**
	 * Return multi zoom
	 *
	 * Compute multi image optimal zoom
	 *
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param array $locations The latitude array
	 * @param int $zoom The zoom
	 * @param int $width The width
	 * @param int $height The height
	 * @return int The zoom
	 */
	public function getMultiZoom(float $latitude, float $longitude, array $locations, int $zoom = 18, int $width = 1280, int $height = 1280): int {
		//Iterate on each zoom
		for ($i = $zoom; $i >= 1; $i--) {
			//Get tile xy
			$tileX = floor($this->longitudeToX($longitude, $i));
			$tileY = floor($this->latitudeToY($latitude, $i));

			//Calculate start xy
			$startX = floor(($tileX * self::tz - $width / 2) / self::tz);
			$startY = floor(($tileY * self::tz - $height / 2) / self::tz);

			//Calculate end xy
			$endX = ceil(($tileX * self::tz + $width / 2) / self::tz);
			$endY = ceil(($tileY * self::tz + $height / 2) / self::tz);

			//Iterate on each locations
			foreach($locations as $k => $location) {
				//Set dest x
				$destX = floor($this->longitudeToX($location['longitude'], $i));

				//With outside point
				if ($startX >= $destX || $endX <= $destX) {
					//Skip zoom
					continue(2);
				}

				//Set dest y
				$destY = floor($this->latitudeToY($location['latitude'], $i));

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
		$degree = $latitude % 60;

		//Set minute
		$minute = ($latitude - $degree) * 60 % 60;

		//Set second
		$second = ($latitude - $degree - $minute / 60) * 3600 % 3600;

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
		$degree = $longitude % 60;

		//Set minute
		$minute = ($longitude - $degree) * 60 % 60;

		//Set second
		$second = ($longitude - $degree - $minute / 60) * 3600 % 3600;

		//Return sexagesimal longitude
		return $degree.'°'.$minute.'\''.$second.'"'.($longitude >= 0 ? 'E' : 'W');
	}
}
