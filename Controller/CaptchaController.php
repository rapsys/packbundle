<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Controller;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

use Rapsys\PackBundle\Util\MapUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

/**
 * {@inheritdoc}
 */
class CaptchaController extends AbstractController implements ServiceSubscriberInterface {
	/**
	 * The cache path
	 */
	protected string $cache;

	/**
	 * The ContainerInterface instance
	 *
	 * @var ContainerInterface 
	 */
	protected $container;

	/**
	 * The stream context instance
	 */
	protected mixed $ctx;

	/**
	 * The MapUtil instance
	 */
	protected MapUtil $map;

	/**
	 * The public path
	 */
	protected string $path;

	/**
	 * The SluggerUtil instance
	 */
	protected SluggerUtil $slugger;

	/**
	 * The tile server url
	 */
	protected string $url;

	/**
	 * Creates a new captcha controller
	 *
	 * @param ContainerInterface $container The ContainerInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param string $cache The cache path
	 * @param string $path The public path
	 * @param string $prefix The prefix
	 */
	function __construct(ContainerInterface $container, SluggerUtil $slugger, string $cache = '../var/cache', string $path = './bundles/rapsyspack', string $prefix = 'captcha') {
		//Set cache
		$this->cache = $cache.'/'.$prefix;

		//Set container
		$this->container = $container;

		//Set path
		$this->path = $path.'/'.$prefix;

		//Set slugger
		$this->slugger = $slugger;
	}

	/**
	 * Return map image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param int $updated The updated timestamp
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param int $zoom The zoom
	 * @param int $width The width
	 * @param int $height The height
	 * @return Response The rendered image
	 */
	public function map(Request $request, string $hash, int $updated, float $latitude, float $longitude, int $zoom, int $width, int $height): Response {
		//Without matching hash
		if ($hash !== $this->slugger->hash([$updated, $latitude, $longitude, $zoom, $width, $height])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match map hash: %s', $hash));
		}

		//Set map
		$map = $this->path.'/'.$zoom.'/'.$latitude.'/'.$longitude.'/'.$width.'x'.$height.'.jpeg';

		//Without multi up to date file
		if (!is_file($map) || !($mtime = stat($map)['mtime']) || $mtime < $updated) {
			//Without existing map path
			if (!is_dir($dir = dirname($map))) {
				//Create filesystem object
				$filesystem = new Filesystem();

				try {
					//Create path
					//XXX: set as 0775, symfony umask (0022) will reduce rights (0755)
					//XXX: on CoW filesystems execute a chattr +C before filling
					$filesystem->mkdir($dir, 0775);
				} catch (IOExceptionInterface $e) {
					//Throw error
					throw new \Exception(sprintf('Output path "%s" do not exists and unable to create it', $dir), 0, $e);
				}
			}

			//Create image instance
			$image = new \Imagick();

			//Add new image
			$image->newImage($width, $height, new \ImagickPixel('transparent'), 'jpeg');

			//Create tile instance
			$tile = new \Imagick();

			//Get tile xy
			$centerX = $this->map->longitudeToX($longitude, $zoom);
			$centerY = $this->map->latitudeToY($latitude, $zoom);

			//Calculate start xy
			$startX = floor(floor($centerX) - $width / MapUtil::tz);
			$startY = floor(floor($centerY) - $height / MapUtil::tz);

			//Calculate end xy
			$endX = ceil(ceil($centerX) + $width / MapUtil::tz);
			$endY = ceil(ceil($centerY) + $height / MapUtil::tz);

			for($x = $startX; $x <= $endX; $x++) {
				for($y = $startY; $y <= $endY; $y++) {
					//Set cache path
					$cache = $this->cache.'/'.$zoom.'/'.$x.'/'.$y.'.png';

					//Without cache image
					if (!is_file($cache)) {
						//Set tile url
						$tileUri = str_replace(['{Z}', '{X}', '{Y}'], [$zoom, $x, $y], $this->url);

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

						//Store tile in cache
						file_put_contents($cache, file_get_contents($tileUri, false, $this->ctx));
					}

					//Set dest x
					$destX = intval(floor($width / 2 - MapUtil::tz * ($centerX - $x)));

					//Set dest y
					$destY = intval(floor($height / 2 - MapUtil::tz * ($centerY - $y)));

					//Read tile from cache
					$tile->readImage($cache);

					//Compose image
					$image->compositeImage($tile, \Imagick::COMPOSITE_OVER, $destX, $destY);

					//Clear tile
					$tile->clear();
				}
			}

			//Add imagick draw instance
			//XXX: see https://www.php.net/manual/fr/imagick.examples-1.php#example-3916
			$draw = new \ImagickDraw();

			//Set text antialias
			$draw->setTextAntialias(true);

			//Set stroke antialias
			$draw->setStrokeAntialias(true);

			//Set text alignment
			$draw->setTextAlignment(\Imagick::ALIGN_CENTER);

			//Set gravity
			$draw->setGravity(\Imagick::GRAVITY_CENTER);

			//Set fill color
			$draw->setFillColor('#cff');

			//Set stroke color
			$draw->setStrokeColor('#00c3f9');

			//Set stroke width
			$draw->setStrokeWidth(2);

			//Draw circle
			$draw->circle($width/2 - 5, $height/2 - 5, $width/2 + 5, $height/2 + 5);

			//Draw on image
			$image->drawImage($draw);

			//Strip image exif data and properties
			$image->stripImage();

			//Add latitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLatitude', $this->map->latitudeToSexagesimal($latitude));

			//Add longitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLongitude', $this->map->longitudeToSexagesimal($longitude));

			//Add description
			//XXX: not supported by imagick :'(
			#$image->setImageProperty('exif:Description', $caption);

			//Set progressive jpeg
			$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

			//Set compression quality
			//TODO: ajust that
			$image->setImageCompressionQuality(70);

			//Save image
			if (!$image->writeImage($map)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $path));
			}

			//Set mtime
			$mtime = stat($map)['mtime'];
		}

		//Read map from cache
		$response = new BinaryFileResponse($map);

		//Set file name
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'map-'.$latitude.','.$longitude.'-'.$zoom.'-'.$width.'x'.$height.'.jpeg');

		//Set etag
		$response->setEtag(md5(serialize([$updated, $latitude, $longitude, $zoom, $width, $height])));

		//Set last modified
		$response->setLastModified(\DateTime::createFromFormat('U', strval($mtime)));

		//Set as public
		$response->setPublic();

		//Return 304 response if not modified
		$response->isNotModified($request);

		//Return response
		return $response;
	}

	/**
	 * Return multi map image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param int $updated The updated timestamp
	 * @param float $latitude The latitude
	 * @param float $longitude The longitude
	 * @param string $coordinates The coordinates
	 * @param int $zoom The zoom
	 * @param int $width The width
	 * @param int $height The height
	 * @return Response The rendered image
	 */
	public function multiMap(Request $request, string $hash, int $updated, float $latitude, float $longitude, string $coordinates, int $zoom, int $width, int $height): Response {
		//Without matching hash
		if ($hash !== $this->slugger->hash([$updated, $latitude, $longitude, $coordinate = $this->slugger->hash($coordinates), $zoom, $width, $height])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match multi map hash: %s', $hash));
		}

		//Set multi
		$map = $this->path.'/'.$zoom.'/'.$latitude.'/'.$longitude.'/'.$coordinate.'/'.$width.'x'.$height.'.jpeg';

		//Without multi up to date file
		if (!is_file($map) || !($mtime = stat($map)['mtime']) || $mtime < $updated) {
			//Without existing multi path
			if (!is_dir($dir = dirname($map))) {
				//Create filesystem object
				$filesystem = new Filesystem();

				try {
					//Create path
					//XXX: set as 0775, symfony umask (0022) will reduce rights (0755)
					//XXX: on CoW filesystems execute a chattr +C before filling
					$filesystem->mkdir($dir, 0775);
				} catch (IOExceptionInterface $e) {
					//Throw error
					throw new \Exception(sprintf('Output path "%s" do not exists and unable to create it', $dir), 0, $e);
				}
			}

			//Create image instance
			$image = new \Imagick();

			//Add new image
			$image->newImage($width, $height, new \ImagickPixel('transparent'), 'jpeg');

			//Create tile instance
			$tile = new \Imagick();

			//Get tile xy
			$centerX = $this->map->longitudeToX($longitude, $zoom);
			$centerY = $this->map->latitudeToY($latitude, $zoom);

			//Calculate start xy
			$startX = floor(floor($centerX) - $width / MapUtil::tz);
			$startY = floor(floor($centerY) - $height / MapUtil::tz);

			//Calculate end xy
			$endX = ceil(ceil($centerX) + $width / MapUtil::tz);
			$endY = ceil(ceil($centerY) + $height / MapUtil::tz);

			for($x = $startX; $x <= $endX; $x++) {
				for($y = $startY; $y <= $endY; $y++) {
					//Set cache path
					$cache = $this->cache.'/'.$zoom.'/'.$x.'/'.$y.'.png';

					//Without cache image
					if (!is_file($cache)) {
						//Set tile url
						$tileUri = str_replace(['{Z}', '{X}', '{Y}'], [$zoom, $x, $y], $this->url);

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

						//Store tile in cache
						file_put_contents($cache, file_get_contents($tileUri, false, $this->ctx));
					}

					//Set dest x
					$destX = intval(floor($width / 2 - MapUtil::tz * ($centerX - $x)));

					//Set dest y
					$destY = intval(floor($height / 2 - MapUtil::tz * ($centerY - $y)));

					//Read tile from cache
					$tile->readImage($cache);

					//Compose image
					$image->compositeImage($tile, \Imagick::COMPOSITE_OVER, $destX, $destY);

					//Clear tile
					$tile->clear();
				}
			}

			//Add imagick draw instance
			//XXX: see https://www.php.net/manual/fr/imagick.examples-1.php#example-3916
			$draw = new \ImagickDraw();

			//Set text antialias
			$draw->setTextAntialias(true);

			//Set stroke antialias
			$draw->setStrokeAntialias(true);

			//Set text alignment
			$draw->setTextAlignment(\Imagick::ALIGN_CENTER);

			//Set gravity
			$draw->setGravity(\Imagick::GRAVITY_CENTER);

			//Convert to array	
			$coordinates = array_reverse(array_map(function ($v) { $p = strpos($v, ','); return ['latitude' => floatval(substr($v, 0, $p)), 'longitude' => floatval(substr($v, $p + 1))]; }, explode('-', $coordinates)), true);

			//Iterate on locations
			foreach($coordinates as $id => $coordinate) {
				//Set dest x
				$destX = intval(floor($width / 2 - MapUtil::tz * ($centerX - $this->map->longitudeToX(floatval($coordinate['longitude']), $zoom))));

				//Set dest y
				$destY = intval(floor($height / 2 - MapUtil::tz * ($centerY - $this->map->latitudeToY(floatval($coordinate['latitude']), $zoom))));

				//Set fill color
				$draw->setFillColor($this->map->fill);

				//Set font size
				$draw->setFontSize($this->map->fontSize);

				//Set stroke color
				$draw->setStrokeColor($this->map->stroke);

				//Set circle radius
				$radius = $this->map->radius;

				//Set stroke width
				$stroke = $this->map->strokeWidth;

				//With matching position
				if ($coordinate['latitude'] === $latitude && $coordinate['longitude'] == $longitude) {
					//Set fill color
					$draw->setFillColor($this->map->highFill);

					//Set font size
					$draw->setFontSize($this->map->highFontSize);

					//Set stroke color
					$draw->setStrokeColor($this->map->highStroke);

					//Set circle radius
					$radius = $this->map->highRadius;

					//Set stroke width
					$stroke = $this->map->highStrokeWidth;
				}

				//Set stroke width
				$draw->setStrokeWidth($stroke);

				//Draw circle
				$draw->circle($destX - $radius, $destY - $radius, $destX + $radius, $destY + $radius);

				//Set fill color
				$draw->setFillColor($draw->getStrokeColor());

				//Set stroke width
				$draw->setStrokeWidth($stroke / 4);

				//Get font metrics
				$metrics = $image->queryFontMetrics($draw, strval($id));

				//Add annotation
				$draw->annotation($destX - $radius, $destY + $stroke, strval($id));
			}

			//Draw on image
			$image->drawImage($draw);

			//Strip image exif data and properties
			$image->stripImage();

			//Add latitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLatitude', $this->map->latitudeToSexagesimal($latitude));

			//Add longitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLongitude', $this->map->longitudeToSexagesimal($longitude));

			//Add description
			//XXX: not supported by imagick :'(
			#$image->setImageProperty('exif:Description', $caption);

			//Set progressive jpeg
			$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

			//Set compression quality
			//TODO: ajust that
			$image->setImageCompressionQuality(70);

			//Save image
			if (!$image->writeImage($map)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $path));
			}

			//Set mtime
			$mtime = stat($map)['mtime'];
		}

		//Read map from cache
		$response = new BinaryFileResponse($map);

		//Set file name
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'multimap-'.$latitude.','.$longitude.'-'.$zoom.'-'.$width.'x'.$height.'.jpeg');

		//Set etag
		$response->setEtag(md5(serialize([$updated, $latitude, $longitude, $zoom, $width, $height])));

		//Set last modified
		$response->setLastModified(\DateTime::createFromFormat('U', strval($mtime)));

		//Set as public
		$response->setPublic();

		//Return 304 response if not modified
		$response->isNotModified($request);

		//Return response
		return $response;
	}
}
