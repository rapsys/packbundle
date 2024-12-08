<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle;

use Rapsys\PackBundle\Util\ImageUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

use Psr\Container\ContainerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * {@inheritdoc}
 */
class Controller extends AbstractController implements ServiceSubscriberInterface {
	/**
	 * Alias string
	 */
	protected string $alias;

	/**
	 * Config array
	 */
	protected array $config;

	/**
	 * Stream context
	 */
	protected mixed $ctx;

	/**
	 * Version string
	 */
	protected string $version;

	/**
	 * Creates a new image controller
	 *
	 * @param ContainerInterface $container The ContainerInterface instance
	 * @param ImageUtil $image The MapUtil instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 */
	function __construct(protected ContainerInterface $container, protected ImageUtil $image, protected SluggerUtil $slugger) {
		//Retrieve config
		$this->config = $container->getParameter($this->alias = RapsysPackBundle::getAlias());

		//Set ctx
		$this->ctx = stream_context_create(
			[
				'http' => [
					'max_redirects' => $_ENV['RAPSYSPACK_REDIRECT'] ?? 20,
					'timeout' => $_ENV['RAPSYSPACK_TIMEOUT'] ?? (($timeout = ini_get('default_socket_timeout')) !== false && $timeout !== '' ? (float)$timeout : 60),
					'user_agent' => $_ENV['RAPSYSPACK_AGENT'] ?? (($agent = ini_get('user_agent')) !== false && $agent !== '' ? (string)$agent : $this->alias.'/'.($this->version = RapsysPackBundle::getVersion()))
				]
			]
		);
	}

	/**
	 * Return captcha image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param string $equation The shorted equation
	 * @param int $height The height
	 * @param int $width The width
	 * @return Response The rendered image
	 */
	public function captcha(Request $request, string $hash, string $equation, int $height, int $width, string $_format): Response {
		//Without matching hash
		if ($hash !== $this->slugger->serialize([$equation, $height, $width])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match captcha hash: %s', $hash));
		//Without valid format
		} elseif ($_format !== 'jpeg' && $_format !== 'png' && $_format !== 'webp') {
			//Throw new exception
			throw new NotFoundHttpException('Invalid thumb format');
		}

		//Unshort equation
		$equation = $this->slugger->unshort($short = $equation);

		//Set hashed tree
		$hashed = str_split(strval($equation));

		//Set captcha
		$captcha = $this->config['cache'].'/'.$this->config['prefixes']['captcha'].'/'.$hashed[0].'/'.$hashed[4].'/'.$hashed[8].'/'.$short.'.'.$_format;

		//Without captcha up to date file
		if (!is_file($captcha) || !($mtime = stat($captcha)['mtime']) || $mtime < (new \DateTime('-1 hour'))->getTimestamp()) {
			//Without existing captcha path
			if (!is_dir($dir = dirname($captcha))) {
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
			$draw->setFillColor($this->config['captcha']['fill']);

			//Set stroke color
			$draw->setStrokeColor($this->config['captcha']['border']);

			//Set font size
			$draw->setFontSize($this->config['captcha']['size'] / 1.5);

			//Set stroke width
			$draw->setStrokeWidth($this->config['captcha']['thickness'] / 3);

			//Set rotation
			$draw->rotate($rotate = (rand(25, 75)*(rand(0,1)?-.1:.1)));

			//Get font metrics
			$metrics2 = $image->queryFontMetrics($draw, strval('stop spam'));

			//Add annotation
			$draw->annotation($width / 2 - ceil(rand(intval(-$metrics2['textWidth']), intval($metrics2['textWidth'])) / 2) - abs($rotate), ceil($metrics2['textHeight'] + $metrics2['descender'] + $metrics2['ascender']) / 2 - $this->config['captcha']['thickness'] - $rotate, strval('stop spam'));

			//Set rotation
			$draw->rotate(-$rotate);

			//Set font size
			$draw->setFontSize($this->config['captcha']['size']);

			//Set stroke width
			$draw->setStrokeWidth($this->config['captcha']['thickness']);

			//Set rotation
			$draw->rotate($rotate = (rand(25, 50)*(rand(0,1)?-.1:.1)));

			//Get font metrics
			$metrics = $image->queryFontMetrics($draw, strval($equation));

			//Add annotation
			$draw->annotation($width / 2, ceil($metrics['textHeight'] + $metrics['descender'] + $metrics['ascender']) / 2 - $this->config['captcha']['thickness'], strval($equation));

			//Set rotation
			$draw->rotate(-$rotate);

			//Add new image
			#$image->newImage(intval(ceil($metrics['textWidth'])), intval(ceil($metrics['textHeight'] + $metrics['descender'])), new \ImagickPixel($this->config['captcha']['background']), 'jpeg');
			$image->newImage($width, $height, new \ImagickPixel($this->config['captcha']['background']), $_format);

			//Draw on image
			$image->drawImage($draw);

			//Strip image exif data and properties
			$image->stripImage();

			//Set compression quality
			$image->setImageCompressionQuality(70);

			//Save captcha
			if (!$image->writeImage($captcha)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $captcha));
			}

			//Set mtime
			$mtime = stat($captcha)['mtime'];
		}

		//Read captcha from cache
		$response = new BinaryFileResponse($captcha);

		//Set file name
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'captcha-stop-spam-'.str_replace([' ', '*', '+'], ['-', 'mul', 'add'], $equation).'.'.$_format);

		//Set etag
		$response->setEtag(md5($hash));

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
	 * Return facebook image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param string $path The image path
	 * @param int $height The height
	 * @param int $width The width
	 * @return Response The rendered image
	 */
	public function facebook(Request $request, string $hash, string $path, int $height, int $width, string $_format): Response {
		//Without matching hash
		if ($hash !== $this->slugger->serialize([$path, $height, $width])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match facebook hash: %s', $hash));
		//Without matching format
		} elseif ($_format !== 'jpeg' && $_format !== 'png' && $_format !== 'webp') {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Invalid facebook format: %s', $_format));
		}

		//Unshort path
		$path = $this->slugger->unshort($short = $path);

		//Without facebook file
		if (!is_file($facebook = $this->config['cache'].'/'.$this->config['prefixes']['facebook'].$path.'.'.$_format)) {
			//Throw new exception
			throw new NotFoundHttpException('Unable to get facebook file');
		}

		//Read facebook from cache
		$response = new BinaryFileResponse($facebook);

		//Set file name
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'facebook-'.$hash.'.'.$_format);

		//Set etag
		$response->setEtag(md5($hash));

		//Set last modified
		$response->setLastModified(\DateTime::createFromFormat('U', strval(stat($facebook)['mtime'])));

		//Set as public
		$response->setPublic();

		//Return 304 response if not modified
		$response->isNotModified($request);

		//Return response
		return $response;
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
	public function map(Request $request, string $hash, float $latitude, float $longitude, int $height, int $width, int $zoom, string $_format): Response {
		//Without matching hash
		if ($hash !== $this->slugger->hash([$height, $width, $zoom, $latitude, $longitude])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match map hash: %s', $hash));
		}

		//Set map
		$map = $this->config['cache'].'/'.$this->config['prefixes']['map'].'/'.$zoom.'/'.($latitude*1000000%10).'/'.($longitude*1000000%10).'/'.$latitude.','.$longitude.'-'.$zoom.'-'.$width.'x'.$height.'.'.$_format;

		//Without map file
		//TODO: refresh after config modification ?
		if (!is_file($map)) {
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
					throw new \Exception(sprintf('Output directory "%s" do not exists and unable to create it', $dir), 0, $e);
				}
			}

			//Create image instance
			$image = new \Imagick();

			//Add new image
			$image->newImage($width, $height, new \ImagickPixel('transparent'), $_format);

			//Create tile instance
			$tile = new \Imagick();

			//Get tile xy
			$centerX = $this->image->longitudeToX($longitude, $zoom);
			$centerY = $this->image->latitudeToY($latitude, $zoom);

			//Calculate start xy
			$startX = floor(floor($centerX) - $width / $this->config['map']['tz']);
			$startY = floor(floor($centerY) - $height / $this->config['map']['tz']);

			//Calculate end xy
			$endX = ceil(ceil($centerX) + $width / $this->config['map']['tz']);
			$endY = ceil(ceil($centerY) + $height / $this->config['map']['tz']);

			for($x = $startX; $x <= $endX; $x++) {
				for($y = $startY; $y <= $endY; $y++) {
					//Set cache path
					$cache = $this->config['cache'].'/'.$this->config['prefixes']['map'].'/'.$zoom.'/'.($x%10).'/'.($y%10).'/'.$x.','.$y.'.png';

					//Without cache image
					if (!is_file($cache)) {
						//Set tile url
						$tileUri = str_replace(['{Z}', '{X}', '{Y}'], [$zoom, $x, $y], $this->config['servers'][$this->config['map']['server']]);

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
					$destX = intval(floor($width / 2 - $this->config['map']['tz'] * ($centerX - $x)));

					//Set dest y
					$destY = intval(floor($height / 2 - $this->config['map']['tz'] * ($centerY - $y)));

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
			$draw->setFillColor($this->config['map']['fill']);

			//Set stroke color
			$draw->setStrokeColor($this->config['map']['border']);

			//Set stroke width
			$draw->setStrokeWidth($this->config['map']['thickness']);

			//Draw circle
			$draw->circle($width/2 - $this->config['map']['radius'], $height/2 - $this->config['map']['radius'], $width/2 + $this->config['map']['radius'], $height/2 + $this->config['map']['radius']);

			//Draw on image
			$image->drawImage($draw);

			//Strip image exif data and properties
			$image->stripImage();

			//Add latitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLatitude', $this->image->latitudeToSexagesimal($latitude));

			//Add longitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLongitude', $this->image->longitudeToSexagesimal($longitude));

			//Set progressive jpeg
			$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

			//Set compression quality
			$image->setImageCompressionQuality($this->config['map']['quality']);

			//Save image
			if (!$image->writeImage($map)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $map));
			}
		}

		//Read map from cache
		$response = new BinaryFileResponse($map);

		//Set file name
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, basename($map));

		//Set etag
		$response->setEtag(md5(serialize([$height, $width, $zoom, $latitude, $longitude])));

		//Set last modified
		$response->setLastModified(\DateTime::createFromFormat('U', strval(stat($map)['mtime'])));

		//Disable robot index
		$response->headers->set('X-Robots-Tag', 'noindex');

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
	public function multi(Request $request, string $hash, string $coordinate, int $height, int $width, int $zoom, string $_format): Response {
		//Without matching hash
		if ($hash !== $this->slugger->hash([$height, $width, $zoom, $coordinate])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match multi map hash: %s', $hash));
		}

		//Set latitudes and longitudes array
		$latitudes = $longitudes = [];

		//Set coordinates
		$coordinates = array_map(
			function ($v) use (&$latitudes, &$longitudes) {
				list($latitude, $longitude) = explode(',', $v);
				$latitudes[] = $latitude;
				$longitudes[] = $longitude;
				return [ $latitude, $longitude ];
			},
			explode('-', $coordinate)
		);

		//Set latitude
		$latitude = round((min($latitudes)+max($latitudes))/2, 6);

		//Set longitude
		$longitude = round((min($longitudes)+max($longitudes))/2, 6);

		//Set map
		$map = $this->config['cache'].'/'.$this->config['prefixes']['multi'].'/'.$zoom.'/'.($latitude*1000000%10).'/'.($longitude*1000000%10).'/'.$coordinate.'-'.$zoom.'-'.$width.'x'.$height.'.'.$_format;

		//Without map file
		if (!is_file($map)) {
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
					throw new \Exception(sprintf('Output directory "%s" do not exists and unable to create it', $dir), 0, $e);
				}
			}

			//Create image instance
			$image = new \Imagick();

			//Add new image
			$image->newImage($width, $height, new \ImagickPixel('transparent'), $_format);

			//Create tile instance
			$tile = new \Imagick();

			//Get tile xy
			$centerX = $this->image->longitudeToX($longitude, $zoom);
			$centerY = $this->image->latitudeToY($latitude, $zoom);

			//Calculate start xy
			$startX = floor(floor($centerX) - $width / $this->config['multi']['tz']);
			$startY = floor(floor($centerY) - $height / $this->config['multi']['tz']);

			//Calculate end xy
			$endX = ceil(ceil($centerX) + $width / $this->config['multi']['tz']);
			$endY = ceil(ceil($centerY) + $height / $this->config['multi']['tz']);

			for($x = $startX; $x <= $endX; $x++) {
				for($y = $startY; $y <= $endY; $y++) {
					//Set cache path
					$cache = $this->config['cache'].'/'.$this->config['prefixes']['multi'].'/'.$zoom.'/'.($x%10).'/'.($y%10).'/'.$x.','.$y.'.png';

					//Without cache image
					if (!is_file($cache)) {
						//Set tile url
						$tileUri = str_replace(['{Z}', '{X}', '{Y}'], [$zoom, $x, $y], $this->config['servers'][$this->config['multi']['server']]);

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
					$destX = intval(floor($width / 2 - $this->config['multi']['tz'] * ($centerX - $x)));

					//Set dest y
					$destY = intval(floor($height / 2 - $this->config['multi']['tz'] * ($centerY - $y)));

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

			//Iterate on locations
			foreach($coordinates as $id => $coordinate) {
				//Get coordinates
				list($clatitude, $clongitude) = $coordinate;

				//Set dest x
				$destX = intval(floor($width / 2 - $this->config['multi']['tz'] * ($centerX - $this->image->longitudeToX(floatval($clongitude), $zoom))));

				//Set dest y
				$destY = intval(floor($height / 2 - $this->config['multi']['tz'] * ($centerY - $this->image->latitudeToY(floatval($clatitude), $zoom))));

				//Set fill color
				$draw->setFillColor($this->config['multi']['fill']);

				//Set font size
				$draw->setFontSize($this->config['multi']['size']);

				//Set stroke color
				$draw->setStrokeColor($this->config['multi']['border']);

				//Set circle radius
				$radius = $this->config['multi']['radius'];

				//Set stroke width
				$stroke = $this->config['multi']['thickness'];

				//With matching position
				if ($clatitude === $latitude && $clongitude == $longitude) {
					//Set fill color
					$draw->setFillColor($this->config['multi']['highfill']);

					//Set font size
					$draw->setFontSize($this->config['multi']['highsize']);

					//Set stroke color
					$draw->setStrokeColor($this->config['multi']['highborder']);

					//Set circle radius
					$radius = $this->config['multi']['highradius'];

					//Set stroke width
					$stroke = $this->config['multi']['highthickness'];
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
				#$metrics = $image->queryFontMetrics($draw, strval($id));

				//Add annotation
				$draw->annotation($destX - $radius, $destY + $stroke, strval($id));
			}

			//Draw on image
			$image->drawImage($draw);

			//Strip image exif data and properties
			$image->stripImage();

			//Add latitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLatitude', $this->image->latitudeToSexagesimal($latitude));

			//Add longitude
			//XXX: not supported by imagick :'(
			$image->setImageProperty('exif:GPSLongitude', $this->image->longitudeToSexagesimal($longitude));

			//Add description
			//XXX: not supported by imagick :'(
			#$image->setImageProperty('exif:Description', $caption);

			//Set progressive jpeg
			$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);

			//Set compression quality
			$image->setImageCompressionQuality($this->config['multi']['quality']);

			//Save image
			if (!$image->writeImage($map)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $path));
			}
		}

		//Read map from cache
		$response = new BinaryFileResponse($map);

		//Set file name
		#$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'multimap-'.$latitude.','.$longitude.'-'.$zoom.'-'.$width.'x'.$height.'.jpeg');
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, basename($map));

		//Set etag
		$response->setEtag(md5(serialize([$height, $width, $zoom, $coordinate])));

		//Set last modified
		$response->setLastModified(\DateTime::createFromFormat('U', strval(stat($map)['mtime'])));

		//Disable robot index
		$response->headers->set('X-Robots-Tag', 'noindex');

		//Set as public
		$response->setPublic();

		//Return 304 response if not modified
		$response->isNotModified($request);

		//Return response
		return $response;
	}

	/**
	 * Return thumb image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param string $path The image path
	 * @param int $height The height
	 * @param int $width The width
	 * @return Response The rendered image
	 */
	public function thumb(Request $request, string $hash, string $path, int $height, int $width, string $_format): Response {
		//Without matching hash
		if ($hash !== $this->slugger->serialize([$path, $height, $width])) {
			//Throw new exception
			throw new NotFoundHttpException('Invalid thumb hash');
		//Without valid format
		} elseif ($_format !== 'jpeg' && $_format !== 'png' && $_format !== 'webp') {
			//Throw new exception
			throw new NotFoundHttpException('Invalid thumb format');
		}

		//Unshort path
		$path = $this->slugger->unshort($short = $path);

		//Set thumb
		$thumb = $this->config['cache'].'/'.$this->config['prefixes']['thumb'].$path.'.'.$_format;

		//Without file
		if (!is_file($path) || !($updated = stat($path)['mtime'])) {
			//Throw new exception
			throw new NotFoundHttpException('Unable to get thumb file');
		}

		//Without thumb up to date file
		if (!is_file($thumb) || !($mtime = stat($thumb)['mtime']) || $mtime < $updated) {
			//Without existing thumb path
			if (!is_dir($dir = dirname($thumb))) {
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

			//Read image
			$image->readImage(realpath($path));

			//Crop using aspect ratio
			//XXX: for better result upload image directly in aspect ratio :)
			$image->cropThumbnailImage($width, $height);

			//Strip image exif data and properties
			$image->stripImage();

			//Set compression quality
			//TODO: ajust that
			$image->setImageCompressionQuality(70);

			//Set image format
			#$image->setImageFormat($_format);

			//Save thumb
			if (!$image->writeImage($thumb)) {
				//Throw error
				throw new \Exception(sprintf('Unable to write image "%s"', $thumb));
			}

			//Set mtime
			$mtime = stat($thumb)['mtime'];
		}

		//Read thumb from cache
		$response = new BinaryFileResponse($thumb);

		//Set file name
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'thumb-'.$hash.'.'.$_format);

		//Set etag
		$response->setEtag(md5($hash));

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
