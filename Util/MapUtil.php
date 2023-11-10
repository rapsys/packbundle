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

use Symfony\Component\Routing\RouterInterface;

/**
 * Helps manage map
 */
class MapUtil {
	/**
	 * The cycle tile server
	 *
	 * @see https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Tile_servers
	 */
	const cycle = 'http://a.tile.thunderforest.com/cycle/{Z}/{X}/{Y}.png';

	/**
	 * The fill color
	 */
	const fill = '#cff';

	/**
	 * The font size
	 */
	const fontSize = 20;

	/**
	 * The high fill color
	 */
	const highFill = '#c3c3f9';

	/**
	 * The high font size
	 */
	const highFontSize = 30;

	/**
	 * The high radius size
	 */
	const highRadius = 6;

	/**
	 * The high stroke color
	 */
	const highStroke = '#3333c3';

	/**
	 * The high stroke size
	 */
	const highStrokeWidth = 4;

	/**
	 * The map width
	 */
	const width = 640;

	/**
	 * The map height
	 */
	const height = 640;
	
	/**
	 * The osm tile server
	 *
	 * @see https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Tile_servers
	 */
	const osm = 'https://tile.openstreetmap.org/{Z}/{X}/{Y}.png';

	/**
	 * The radius size
	 */
	const radius = 5;

	/**
	 * The stroke color
	 */
	const stroke = '#00c3f9';

	/**
	 * The stroke size
	 */
	const strokeWidth = 2;

	/**
	 * The transport tile server
	 *
	 * @see https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Tile_servers
	 */
	const transport = 'http://a.tile.thunderforest.com/transport/{Z}/{X}/{Y}.png';

	/**
	 * The tile size
	 */
	const tz = 256;

	/**
	 * The map zoom
	 */
	const zoom = 17;

	/**
	 * The RouterInterface instance
	 */
	protected RouterInterface $router;

	/**
	 * The SluggerUtil instance
	 */
	protected SluggerUtil $slugger;

	/**
	 * The fill color
	 */
	public string $fill;

	/**
	 * The font size
	 */
	public int $fontSize;

	/**
	 * The high fill color
	 */
	public string $highFill;

	/**
	 * The font size
	 */
	public int $highFontSize;

	/**
	 * The radius size
	 */
	public int $highRadius;

	/**
	 * The high stroke color
	 */
	public string $highStroke;

	/**
	 * The stroke size
	 */
	public int $highStrokeWidth;

	/**
	 * The stroke color
	 */
	public string $stroke;

	/**
	 * The stroke size
	 */
	public int $strokeWidth;

	/**
	 * The radius size
	 */
	public int $radius;

	/**
	 * Creates a new map util
	 *
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 */
	function __construct(RouterInterface $router, SluggerUtil $slugger, string $fill = self::fill, int $fontSize = self::fontSize, string $highFill = self::highFill, int $highFontSize = self::highFontSize, int $highRadius = self::highRadius, string $highStroke = self::highStroke, int $highStrokeWidth = self::highStrokeWidth, int $radius = self::radius, string $stroke = self::stroke, int $strokeWidth = self::strokeWidth) {
		//Set router
		$this->router = $router;

		//Set slugger
		$this->slugger = $slugger;

		//Set fill
		$this->fill = $fill;

		//Set font size
		$this->fontSize = $fontSize;

		//Set highFill
		$this->highFill = $highFill;

		//Set high font size
		$this->highFontSize = $highFontSize;

		//Set high radius size
		$this->highRadius = $highRadius;

		//Set highStroke
		$this->highStroke = $highStroke;

		//Set high stroke size
		$this->highStrokeWidth = $highStrokeWidth;

		//Set radius size
		$this->radius = $radius;

		//Set stroke
		$this->stroke = $stroke;

		//Set stroke size
		$this->strokeWidth = $strokeWidth;
	}

	/**
	 * Get map data
	 *
	 * @param string $caption The caption
	 * @param int $updated The updated timestamp
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param int $zoom The zoom
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The map data
	 */
	public function getMap(string $caption, int $updated, float $latitude, float $longitude, int $zoom = self::zoom, int $width = self::width, int $height = self::height): array {
		//Set link hash
		$link = $this->slugger->hash([$updated, $latitude, $longitude, $zoom + 1, $width * 2, $height * 2]);

		//Set src hash
		$src = $this->slugger->hash([$updated, $latitude, $longitude, $zoom, $width, $height]);

		//Return array
		return [
			'caption' => $caption,
			'link' => $this->router->generate('rapsys_pack_map', ['hash' => $link, 'updated' => $updated, 'latitude' => $latitude, 'longitude' => $longitude, 'zoom' => $zoom + 1, 'width' => $width * 2, 'height' => $height * 2]),
			'src' => $this->router->generate('rapsys_pack_map', ['hash' => $src, 'updated' => $updated, 'latitude' => $latitude, 'longitude' => $longitude, 'zoom' => $zoom, 'width' => $width, 'height' => $height]),
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Get multi map data
	 *
	 * @param string $caption The caption
	 * @param int $updated The updated timestamp
	 * @param array $coordinates The coordinates array
	 * @param int $width The width
	 * @param int $height The height
	 * @return array The multi map data
	 */
	public function getMultiMap(string $caption, int $updated, array $coordinates, int $width = self::width, int $height = self::height): array {
		//Set latitudes
		$latitudes = array_map(function ($v) { return $v['latitude']; }, $coordinates);

		//Set longitudes
		$longitudes = array_map(function ($v) { return $v['longitude']; }, $coordinates);

		//Set latitude
		$latitude = round((min($latitudes)+max($latitudes))/2, 6);

		//Set longitude
		$longitude = round((min($longitudes)+max($longitudes))/2, 6);

		//Set zoom
		$zoom = $this->getMultiZoom($latitude, $longitude, $coordinates, $width, $height);

		//Set coordinate
		$coordinate = implode('-', array_map(function ($v) { return $v['latitude'].','.$v['longitude']; }, $coordinates));

		//Set coordinate hash
		$hash = $this->slugger->hash($coordinate);

		//Set link hash
		$link = $this->slugger->hash([$updated, $latitude, $longitude, $hash, $zoom + 1, $width * 2, $height * 2]);

		//Set src hash
		$src = $this->slugger->hash([$updated, $latitude, $longitude, $hash, $zoom, $width, $height]);

		//Return array
		return [
			'caption' => $caption,
			'link' => $this->router->generate('rapsys_pack_multimap', ['hash' => $link, 'updated' => $updated, 'latitude' => $latitude, 'longitude' => $longitude, 'coordinates' => $coordinate, 'zoom' => $zoom + 1, 'width' => $width * 2, 'height' => $height * 2]),
			'src' => $this->router->generate('rapsys_pack_multimap', ['hash' => $src, 'updated' => $updated, 'latitude' => $latitude, 'longitude' => $longitude, 'coordinates' => $coordinate, 'zoom' => $zoom, 'width' => $width, 'height' => $height]),
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Get multi zoom
	 *
	 * Compute a zoom to have all coordinates on multi map
	 * Multi map visible only from -($width / 2) until ($width / 2) and from -($height / 2) until ($height / 2)
	 *
	 * @see Wether we need to take in consideration circle radius in coordinates comparisons, likely +/-(radius / self::tz)
	 *
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param array $coordinates The coordinates array
	 * @param int $width The width
	 * @param int $height The height
	 * @param int $zoom The zoom
	 * @return int The zoom
	 */
	public function getMultiZoom(float $latitude, float $longitude, array $coordinates, int $width, int $height, int $zoom = self::zoom): int {
		//Iterate on each zoom
		for ($i = $zoom; $i >= 1; $i--) {
			//Get tile xy
			$centerX = self::longitudeToX($longitude, $i);
			$centerY = self::latitudeToY($latitude, $i);

			//Calculate start xy
			$startX = floor($centerX - $width / 2 / self::tz);
			$startY = floor($centerY - $height / 2 / self::tz);

			//Calculate end xy
			$endX = ceil($centerX + $width / 2 / self::tz);
			$endY = ceil($centerY + $height / 2 / self::tz);

			//Iterate on each coordinates
			foreach($coordinates as $k => $coordinate) {
				//Set dest x
				$destX = self::longitudeToX($coordinate['longitude'], $i);

				//With outside point
				if ($startX >= $destX || $endX <= $destX) {
					//Skip zoom
					continue(2);
				}

				//Set dest y
				$destY = self::latitudeToY($coordinate['latitude'], $i);

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
	public static function longitudeToX(float $longitude, int $zoom): float {
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
	public static function latitudeToY(float $latitude, int $zoom): float {
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
	public static function xToLongitude(float $x, int $zoom): float {
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
	public static function yToLatitude(float $y, int $zoom): float {
		return rad2deg(atan(sinh(pi() * (1 - 2 * $y / pow(2, $zoom)))));
	}

	/**
	 * Convert decimal latitude to sexagesimal
	 *
	 * @param float $latitude The decimal latitude
	 *
	 * @return string The sexagesimal longitude
	 */
	public static function latitudeToSexagesimal(float $latitude): string {
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
	public static function longitudeToSexagesimal(float $longitude): string {
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
}
