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
	 * {@inheritdoc}
	 */
	public function __construct(protected ?string $name = null) {
		//Fix name
		$this->name = $this->name ?? static::getName();

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
	 * {@inheritdoc}
	 *
	 * Return the command name
	 */
	public function getName(): string {
		//With namespace
		if ($npos = strrpos(static::class, '\\')) {
			//Set name pos
			$npos++;
		//Without namespace
		} else {
			$npos = 0;
		}

		//With trailing command
		if (substr(static::class, -strlen('Command'), strlen('Command')) === 'Command') {
			//Set bundle pos
			$bpos = strlen(static::class) - $npos - strlen('Command');
		//Without bundle
		} else {
			//Set bundle pos
			$bpos = strlen(static::class) - $npos;
		}

		//Return command alias
		return RapsysPackBundle::getAlias().':'.strtolower(substr(static::class, $npos, $bpos));
	}
}
