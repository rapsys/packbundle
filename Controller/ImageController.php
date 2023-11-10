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

use Rapsys\PackBundle\Util\ImageUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

/**
 * {@inheritdoc}
 */
class ImageController extends AbstractController implements ServiceSubscriberInterface {
	/**
	 * The cache path
	 */
	protected string $cache;

	/**
	 * The ImageUtil instance
	 */
	protected ImageUtil $image;

	/**
	 * The public path
	 */
	protected string $path;

	/**
	 * The SluggerUtil instance
	 */
	protected SluggerUtil $slugger;

	/**
	 * Creates a new image controller
	 *
	 * @param ContainerInterface $container The ContainerInterface instance
	 * @param ImageUtil $image The MapUtil instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param string $cache The cache path
	 * @param string $path The public path
	 * @param string $prefix The prefix
	 */
	function __construct(ContainerInterface $container, ImageUtil $image, SluggerUtil $slugger, string $cache = '../var/cache', string $path = './bundles/rapsyspack', string $prefix = 'image') {
		//Set cache
		$this->cache = $cache.'/'.$prefix;

		//Set container
		$this->container = $container;

		//Set image
		$this->image = $image;

		//Set path
		$this->path = $path.'/'.$prefix;

		//Set slugger
		$this->slugger = $slugger;
	}

	/**
	 * Return captcha image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param int $updated The updated timestamp
	 * @param string $equation The shorted equation
	 * @param int $width The width
	 * @param int $height The height
	 * @return Response The rendered image
	 */
	public function captcha(Request $request, string $hash, int $updated, string $equation, int $width, int $height): Response {
		//Without matching hash
		if ($hash !== $this->slugger->serialize([$updated, $equation, $width, $height])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match captcha hash: %s', $hash));
		}

		//Set hashed tree
		$hashed = array_reverse(str_split(strval($updated)));

		//Set captcha
		$captcha = $this->path.'/'.$hashed[0].'/'.$hashed[1].'/'.$hashed[2].'/'.$updated.'/'.$equation.'/'.$width.'x'.$height.'.jpeg';

		//Unshort equation
		$equation = $this->slugger->unshort($equation);

		//Without captcha up to date file
		if (!is_file($captcha) || !($mtime = stat($captcha)['mtime']) || $mtime < $updated) {
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
			$draw->setFillColor($this->image->captchaFill);

			//Set stroke color
			$draw->setStrokeColor($this->image->captchaStroke);

			//Set font size
			$draw->setFontSize($this->image->captchaFontSize/1.5);

			//Set stroke width
			$draw->setStrokeWidth($this->image->captchaStrokeWidth / 3);

			//Set rotation
			$draw->rotate($rotate = (rand(25, 75)*(rand(0,1)?-.1:.1)));

			//Get font metrics
			$metrics2 = $image->queryFontMetrics($draw, strval('stop spam'));

			//Add annotation
			$draw->annotation($width / 2 - ceil(rand(intval(-$metrics2['textWidth']), intval($metrics2['textWidth'])) / 2) - abs($rotate), ceil($metrics2['textHeight'] + $metrics2['descender'] + $metrics2['ascender']) / 2 - $this->image->captchaStrokeWidth - $rotate, strval('stop spam'));

			//Set rotation
			$draw->rotate(-$rotate);

			//Set font size
			$draw->setFontSize($this->image->captchaFontSize);

			//Set stroke width
			$draw->setStrokeWidth($this->image->captchaStrokeWidth);

			//Set rotation
			$draw->rotate($rotate = (rand(25, 50)*(rand(0,1)?-.1:.1)));

			//Get font metrics
			$metrics = $image->queryFontMetrics($draw, strval($equation));

			//Add annotation
			$draw->annotation($width / 2, ceil($metrics['textHeight'] + $metrics['descender'] + $metrics['ascender']) / 2 - $this->image->captchaStrokeWidth, strval($equation));

			//Set rotation
			$draw->rotate(-$rotate);

			//Add new image
			#$image->newImage(intval(ceil($metrics['textWidth'])), intval(ceil($metrics['textHeight'] + $metrics['descender'])), new \ImagickPixel($this->image->captchaBackground), 'jpeg');
			$image->newImage($width, $height, new \ImagickPixel($this->image->captchaBackground), 'jpeg');

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
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'captcha-stop-spam-'.str_replace([' ', '*', '+'], ['-', 'mul', 'add'], $equation).'-'.$width.'x'.$height.'.jpeg');

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
	 * Return thumb image
	 *
	 * @param Request $request The Request instance
	 * @param string $hash The hash
	 * @param int $updated The updated timestamp
	 * @param string $path The image path
	 * @param int $width The width
	 * @param int $height The height
	 * @return Response The rendered image
	 */
	public function thumb(Request $request, string $hash, int $updated, string $path, int $width, int $height): Response {
		//Without matching hash
		if ($hash !== $this->slugger->serialize([$updated, $path, $width, $height])) {
			//Throw new exception
			throw new NotFoundHttpException(sprintf('Unable to match thumb hash: %s', $hash));
		}

		//Set hashed tree
		$hashed = array_reverse(str_split(strval($updated)));

		//Set thumb
		$thumb = $this->path.'/'.$hashed[0].'/'.$hashed[1].'/'.$hashed[2].'/'.$updated.'/'.$path.'/'.$width.'x'.$height.'.jpeg';

		//Unshort path
		$path = $this->slugger->unshort($path);

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
		$response->setContentDisposition(HeaderUtils::DISPOSITION_INLINE, 'thumb-'.str_replace('/', '_', $path).'-'.$width.'x'.$height.'.jpeg');

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
