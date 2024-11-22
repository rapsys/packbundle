<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

//Create image object
$image = new Imagick();

//Create draw object
$draw = new ImagickDraw();

//Create pixel object
$pixel = new ImagickPixel('white');

//Create new image
$image->newImage(1200, 630, $pixel);

//Set fill color
$draw->setFillColor('black');

//Set font properties
$draw->setFont('../woff2/droidsans.regular.woff2');
$draw->setFontSize(30);

//Add texts
$image->annotateImage($draw, 10, 35, 0, 'RP');
$image->annotateImage($draw, 10, 615, 0, 'RP');
$image->annotateImage($draw, 1155, 35, 0, 'RP');
$image->annotateImage($draw, 1155, 615, 0, 'RP');

//Set image format
$image->setImageFormat('png');

//Output image header
header('Content-type: image/png');

//Output image
echo $image;
