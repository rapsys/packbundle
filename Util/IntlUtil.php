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
	 * Format currency
	 */
	public function currency(int|float $number, string $currency, ?string $locale = null) {
		//Get formatter
		$formatter = $this->getNumberFormatter($locale, 'currency');

		//Return formatted currency
		return $formatter->formatCurrency($number, $currency);
	}

	/**
	 * Format date
	 */
	public function date(Environment $env, \DateTime $date, string $dateFormat = 'medium', string $timeFormat = 'medium', ?string $locale = null, \IntlTimeZone|\DateTimeZone|string|null $timezone = null, ?string $calendar = null, ?string $pattern = null) {
		//Get converted date
		$date = twig_date_converter($env, $date, $timezone);

		//Set date and time formatters
		$formatters = [
			'none' => \IntlDateFormatter::NONE,
			'short' => \IntlDateFormatter::SHORT,
			'medium' => \IntlDateFormatter::MEDIUM,
			'long' => \IntlDateFormatter::LONG,
			'full' => \IntlDateFormatter::FULL,
		];

		//Get formatter
		$formatter = \IntlDateFormatter::create(
			$locale,
			$formatters[$dateFormat],
			$formatters[$timeFormat],
			\IntlTimeZone::createTimeZone($date->getTimezone()->getName()),
			'traditional' === $calendar ? \IntlDateFormatter::TRADITIONAL : \IntlDateFormatter::GREGORIAN,
			$pattern
		);

		//Return formatted date
		return $formatter->format($date->getTimestamp());
	}

	/**
	 * Compute eastern for selected year
	 *
	 * @param string $year The eastern year
	 *
	 * @return DateTime The eastern date
	 */
	public function getEastern(string $year): \DateTime {
		//Set static results
		static $results = [];

		//Check if already computed
		if (isset($results[$year])) {
			//Return computed eastern
			return $results[$year];
		}

		$d = (19 * ($year % 19) + 24) % 30;

		$e = (2 * ($year % 4) + 4 * ($year % 7) + 6 * $d + 5) % 7;

		$day = 22 + $d + $e;

		$month = 3;

		if ($day > 31) {
			$day = $d + $e - 9;
			$month = 4;
		} elseif ($d == 29 && $e == 6) {
			$day = 10;
			$month = 4;
		} elseif ($d == 28 && $e == 6) {
			$day = 18;
			$month = 4;
		}

		//Store eastern in data
		return ($results[$year] = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day)));
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
		$locale = null !== $locale ? $locale : \Locale::getDefault();

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
			'duration' => \NumberFormatter::DURATION
		];

		//Without styles
		if (!isset($styles[$style])) {
			throw new SyntaxError(sprintf('The style "%s" does not exist. Known styles are: "%s"', $style, implode('", "', array_keys($styleValues))));
		}

		//Return number formatter
		return ($formatters[$locale][$style] = \NumberFormatter::create($locale, $styles[$style]));
	}

	/**
	 * Format number
	 */
	public function number(int|float $number, $style = 'decimal', $type = 'default', ?string $locale = null) {
		//Set types
		static $types = [
			'default' => \NumberFormatter::TYPE_DEFAULT,
			'int32' => \NumberFormatter::TYPE_INT32,
			'int64' => \NumberFormatter::TYPE_INT64,
			'double' => \NumberFormatter::TYPE_DOUBLE,
			'currency' => \NumberFormatter::TYPE_CURRENCY
		];

		//Get formatter
		$formatter = $this->getNumberFormatter($locale, $style);

		//Without type
		if (!isset($types[$type])) {
			throw new SyntaxError(sprintf('The type "%s" does not exist. Known types are: "%s"', $type, implode('", "', array_keys($types))));
		}

		//Return formatted number
		return $formatter->format($number, $types[$type]);
	}

	/**
	 * Format size
	 *
	 * @TODO: @XXX: add unit translation kB, MB, GiB, etc ?
	 */
	public function size(int|float $number, $si = true, $style = 'decimal', $type = 'default', ?string $locale = null) {
		//Set types
		static $types = [
			'default' => \NumberFormatter::TYPE_DEFAULT,
			'int32' => \NumberFormatter::TYPE_INT32,
			'int64' => \NumberFormatter::TYPE_INT64,
			'double' => \NumberFormatter::TYPE_DOUBLE,
			'currency' => \NumberFormatter::TYPE_CURRENCY
		];

		//Get formatter
		$formatter = $this->getNumberFormatter($locale, $style);

		//Without type
		if (!isset($types[$type])) {
			throw new SyntaxError(sprintf('The type "%s" does not exist. Known types are: "%s"', $type, implode('", "', array_keys($types))));
		}

		//Set unit
		$unit = $si ? 1000 : 1024;

		//Set index
		$index = [ '', $si ? 'k' : 'K', 'M', 'G', 'T', 'P', 'E' ];

		//Get exp
		$exp = intval((log($number) / log($unit)));

		//Rebase number
		$number = round($number / pow($unit, $exp), 2);

		//Return formatted number
		return $formatter->format($number, $types[$type]).' '.$index[$exp].($si ? '' : 'i').'B';
	}
}
