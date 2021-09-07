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

use Twig\Error\SyntaxError;
use Twig\Environment;

/**
 * Helps manage intl conversions
 */
class IntlUtil {
	/**
	 * Construct intl util
	 */
	public function __construct() {
	}

	public function date(Environment $env, $date, $dateFormat = 'medium', $timeFormat = 'medium', $locale = null, $timezone = null, $format = null, $calendar = 'gregorian') {
		$date = twig_date_converter($env, $date, $timezone);

		$formatValues = array(
			'none' => IntlDateFormatter::NONE,
			'short' => IntlDateFormatter::SHORT,
			'medium' => IntlDateFormatter::MEDIUM,
			'long' => IntlDateFormatter::LONG,
			'full' => IntlDateFormatter::FULL,
		);

		$formatter = IntlDateFormatter::create(
			$locale,
			$formatValues[$dateFormat],
			$formatValues[$timeFormat],
			IntlTimeZone::createTimeZone($date->getTimezone()->getName()),
			'gregorian' === $calendar ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL,
			$format
		);

		return $formatter->format($date->getTimestamp());
	}

	public function number($number, $style = 'decimal', $type = 'default', $locale = null) {
		static $typeValues = array(
			'default' => NumberFormatter::TYPE_DEFAULT,
			'int32' => NumberFormatter::TYPE_INT32,
			'int64' => NumberFormatter::TYPE_INT64,
			'double' => NumberFormatter::TYPE_DOUBLE,
			'currency' => NumberFormatter::TYPE_CURRENCY,
		);

		$formatter = $this->getNumberFormatter($locale, $style);

		if (!isset($typeValues[$type])) {
			throw new SyntaxError(sprintf('The type "%s" does not exist. Known types are: "%s"', $type, implode('", "', array_keys($typeValues))));
		}

		return $formatter->format($number, $typeValues[$type]);
	}

	public function currency($number, $currency = null, $locale = null) {
		$formatter = $this->getNumberFormatter($locale, 'currency');

		return $formatter->formatCurrency($number, $currency);
	}

	/**
	 * Gets a number formatter instance according to given locale and formatter.
	 *
	 * @param string $locale Locale in which the number would be formatted
	 * @param int    $style  Style of the formatting
	 *
	 * @return NumberFormatter A NumberFormatter instance
	 */
	protected function getNumberFormatter($locale, $style): NumberFormatter {
		static $formatter, $currentStyle;

		$locale = null !== $locale ? $locale : Locale::getDefault();

		if ($formatter && $formatter->getLocale() === $locale && $currentStyle === $style) {
			// Return same instance of NumberFormatter if parameters are the same
			// to those in previous call
			return $formatter;
		}

		static $styleValues = array(
			'decimal' => NumberFormatter::DECIMAL,
			'currency' => NumberFormatter::CURRENCY,
			'percent' => NumberFormatter::PERCENT,
			'scientific' => NumberFormatter::SCIENTIFIC,
			'spellout' => NumberFormatter::SPELLOUT,
			'ordinal' => NumberFormatter::ORDINAL,
			'duration' => NumberFormatter::DURATION,
		);

		if (!isset($styleValues[$style])) {
			throw new SyntaxError(sprintf('The style "%s" does not exist. Known styles are: "%s"', $style, implode('", "', array_keys($styleValues))));
		}

		$currentStyle = $style;

		$formatter = NumberFormatter::create($locale, $styleValues[$style]);

		return $formatter;
	}
}
