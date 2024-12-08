<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Command;

use Rapsys\PackBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritdoc}
 *
 * Shuffle printable character range
 */
class RangeCommand extends Command {
	/**
	 * Set description
	 *
	 * Shown with bin/console list
	 */
	protected string $description = 'Generates a shuffled printable characters range';

	/**
	 * Set help
	 *
	 * Shown with bin/console --help rapsyspack:range
	 */
	protected string $help = 'This command generates a shuffled printable characters range';

	/**
	 * {@inheritdoc}
	 */
	public function __construct(protected string $file, protected ?string $name = null) {
		//Call parent constructor
		parent::__construct($this->name);

		//Add argument
		$this->addArgument('file', InputArgument::OPTIONAL, 'Environment file', $this->file);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Output a shuffled printable characters range
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		//Printable character range
		$ranges = range(' ', '~');

		//Range shuffled
		$shuffles = [];

		//Shuffle range array
		do {
			//Set start offset
			$offset = rand(0, ($count = count($ranges)) - 1);
			//Set length
			$length = rand(1, $count - $offset < ($ceil = (int)ceil(($count+count($shuffles))/rand(5,10))) ? $count - $offset : rand(2, $ceil));
			//Splice array
			$slices = array_splice($ranges, $offset, $length);
			//When reverse
			if (rand(0, 1)) {
				//Reverse sliced array
				$slices = array_reverse($slices);
			}
			//Append sliced array
			$shuffles = array_merge($shuffles, $slices);
		} while (!empty($ranges));

		//Set string
		$string = 'RAPSYSPACK_RANGE="'.strtr(implode($shuffles), ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';

		//With writeable file
		if (is_file($file = $input->getArgument('file')) && is_writeable($file)) {
			//Get file content
			if (($content = file_get_contents($file, false)) === false) {
				//Display error
				error_log(sprintf('Unable to get %s content', $file), 0);

				//Return failure
				return self::FAILURE;
			}

			//With match
			if (preg_match('/^RAPSYSPACK_RANGE=.*$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
				//Replace matches
				$content = preg_replace('/^(RAPSYSPACK_RANGE=.*)$/m', '#$1'."\n".strtr($string, ['\\' => '\\\\', '\\$' => '\\\\$']), $content);
			//Without match
			} else {
				//Append string
				$content .= (strlen($content)?"\n\n":'').'###> '.$this->bundle.' ###'."\n".$string."\n".'###< '.$this->bundle.' ###';
			}

			//Write file content
			if (file_put_contents($file, $content) === false) {
				//Display error
				error_log(sprintf('Unable to put %s content', $file), 0);

				//Return failure
				return self::FAILURE;
			}

			//Print content
			echo $content;
		//Without writeable file
		} else {
			//Print instruction
			echo '# Add to '.$file."\n";

			//Print rapsys pack range variable
			echo '###> '.$this->bundle.' ###'."\n".$string."\n".'###< '.$this->bundle.' ###';

			//Add trailing line
			echo "\n";
		}

		//Return success
		return self::SUCCESS;
	}
}
