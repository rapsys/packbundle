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

use Rapsys\PackBundle\RapsysPackBundle;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\DependencyInjection\Container;

/**
 * {@inheritdoc}
 */
class Command extends BaseCommand {
	/**
	 * Alias string
	 */
	protected string $alias = '';

	/**
	 * Bundle string
	 */
	protected string $bundle = '';

	/**
	 * {@inheritdoc}
	 */
	public function __construct(protected ?string $name = null) {
		//Get class
		$class = strrchr(static::class, '\\', true);

		//Without command name
		if (substr(static::class, -strlen('\\Command')) !== '\\Command') {
			$class = strrchr($class, '\\', true);
		}

		//Set bundle
		$this->bundle = strtolower($class);

		//With full class name
		if (class_exists($class .= '\\'.str_replace('\\', '', $class)) && method_exists($class, 'getAlias')) {
			//Set alias
			$this->alias = call_user_func([$class, 'getAlias']);
		}

		//Fix name
		$this->name = $this->name ?? static::getName($this->alias);

		//Call parent constructor
		parent::__construct($this->name);

		//With description
		if (!empty($this->description)) {
			//Set description
			$this->setDescription($this->description);
		}

		//With help
		if (!empty($this->help)) {
			//Set help
			$this->setHelp($this->help);
		}
	}

	/**
	 * Return the command name
	 *
	 * {@inheritdoc}
	 *
	 * @param ?string $alias The bundle alias
	 */
	public function getName(?string $alias = null): string {
		//With namespace
		if ($npos = strrpos(static::class, '\\')) {
			//Set name pos
			$npos++;
		//Without namespace
		} else {
			$npos = 0;
		}

		//Set bundle pos
		$bpos = strlen(static::class) - $npos;

		//With trailing command
		if (substr(static::class, -strlen('Command'), strlen('Command')) === 'Command') {
			//Fix bundle pos
			$bpos -= strlen('Command');
		}

		//Without alias
		if ($alias === null) {
			//Get class
			$class = strrchr(static::class, '\\', true);

			//Without command name
			if (substr(static::class, -strlen('\\Command')) !== '\\Command') {
				$class = strrchr($class, '\\', true);
			}

			//With full class name
			if (class_exists($class .= '\\'.str_replace('\\', '', $class)) && method_exists($class, 'getAlias')) {
				//Set alias
				$alias = call_user_func([$class, 'getAlias']);
			}
		}

		//Return command alias
		return ($alias?$alias.':':'').strtolower(substr(static::class, $npos, $bpos));
	}
}
