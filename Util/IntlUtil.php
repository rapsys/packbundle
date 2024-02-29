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
 * Manages intl conversions
 */
class IntlUtil {
	/**
	 * Format date
	 */
	public function date(Environment $env, \DateTime $date, string $dateFormat = 'medium', string $timeFormat = 'medium', ?string $locale = null, \IntlTimeZone|\DateTimeZone|string|null $timezone = null, ?string $calendar = null, ?string $pattern = null) {
		$date = twig_date_converter($env, $date, $timezone);

		//Set date and time formatters
		$formatters = [
			'none' => \IntlDateFormatter::NONE,
			'short' => \IntlDateFormatter::SHORT,
			'medium' => \IntlDateFormatter::MEDIUM,
			'long' => \IntlDateFormatter::LONG,
			'full' => \IntlDateFormatter::FULL,
		];

		$formatter = \IntlDateFormatter::create(
			$locale,
			$formatters[$dateFormat],
			$formatters[$timeFormat],
			\IntlTimeZone::createTimeZone($date->getTimezone()->getName()),
			'traditional' === $calendar ? \IntlDateFormatter::TRADITIONAL : \IntlDateFormatter::GREGORIAN,
			$pattern
		);

		return $formatter->format($date->getTimestamp());
	}

	/**
	 * Format number
	 */
	public function number(int|float $number, $style = 'decimal', $type = 'default', ?string $locale = null) {
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

	/**
	 * Format currency
	 */
	public function currency(int|float $number, string $currency, ?string $locale = null) {
		$formatter = $this->getNumberFormatter($locale, 'currency');

		return $formatter->formatCurrency($number, $currency);
	}

	/**
	 * Gets number formatter instance matching locale and style.
	 *
	 * @param ?string $locale Locale in which the number would be formatted
	 * @param string $style Style of the formatting
	 *
	 * @return NumberFormatter A NumberFormatter instance
	 */
	protected function getNumberFormatter(?string $locale, string $style): \NumberFormatter {
		//Set static formatters
		static $formatters = [];

		//Set locale
		$locale = null !== $locale ? $locale : Locale::getDefault();

		//With existing formatter
		if (isset($formatters[$locale][$style])) {
			//Return the instance from previous call
			return $formatters[$locale][$style];
		}

		//Set styles
		static $styles = [
			'decimal' => \NumberFormatter::DECIMAL,
			'currency' => \NumberFormatter::CURRENCY,
			'percent' => \NumberFormatter::PERCENT,
			'scientific' => \NumberFormatter::SCIENTIFIC,
			'spellout' => \NumberFormatter::SPELLOUT,
			'ordinal' => \NumberFormatter::ORDINAL,
			'duration' => \NumberFormatter::DURATION,
		];

		//Without styles
		if (!isset($styles[$style])) {
			throw new SyntaxError(sprintf('The style "%s" does not exist. Known styles are: "%s"', $style, implode('", "', array_keys($styleValues))));
		}

		//Return number formatter
		return ($formatters[$locale][$style] = \NumberFormatter::create($locale, $styles[$style]));
	}
}
