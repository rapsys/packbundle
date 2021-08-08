<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Filter;

use Twig\Error\Error;
use Twig\Source;

/**
 * {@inheritdoc}
 */
class JPackFilter implements FilterInterface {
	//Default bin
	private $bin;

	//Default compress type
	private $compress;

	//Twig template filename
	private $fileName;

	//Twig template line
	private $line;

	/**
	 * Setup jpack filter
	 *
	 * @xxx compress can be clean, shrink, obfuscate or best
	 */
	public function __construct(Source $fileName, int $line, string $bin = 'jpack', string $compress = 'best') {
		//Set fileName
		$this->fileName = $fileName;

		//Set line
		$this->line = $line;

		//Set bin
		$this->bin = $bin;

		//Set compress
		$this->compress = $compress;

		//Deal with compress
		if (!empty($this->compress)) {
			//Append clean parameter
			if ($this->compress == 'clean') {
				$this->bin .= ' --clean';
			//Append shrink parameter
			} elseif ($this->compress == 'shrink') {
				$this->bin .= ' --shrink';
			//Append obfuscate parameter
			} elseif ($this->compress == 'obfuscate') {
				$this->bin .= ' --obfuscate';
			//Unknown compress type
			//XXX: default compression is best
			} elseif ($this->compress !== 'best') {
				//Throw an error on unknown compress
				throw new Error(sprintf('Got unexpected compress for %s: %s', $this->bin, $this->compress), $this->line, $this->fileName);
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function process(string $content): string {
		//Create descriptors
		$descriptorSpec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		);

		//Open process
		if (is_resource($proc = proc_open($this->bin, $descriptorSpec, $pipes))) {
			//Set stderr as non blocking
			stream_set_blocking($pipes[2], false);

			//Send content to stdin
			fwrite($pipes[0], $content);

			//Close stdin
			fclose($pipes[0]);

			//Read content from stdout
			if ($stdout = stream_get_contents($pipes[1])) {
				$content = $stdout;
			}

			//Close stdout
			fclose($pipes[1]);

			//Read content from stderr
			if (($stderr = stream_get_contents($pipes[2]))) {
				throw new Error(sprintf('Got unexpected strerr for %s: %s', $this->bin, $stderr), $this->line, $this->fileName);
			}

			//Close stderr
			fclose($pipes[2]);

			//Close process
			if (($ret = proc_close($proc))) {
				throw new Error(sprintf('Got unexpected non zero return code %s: %d', $this->bin, $ret), $this->line, $this->fileName);
			}
		}

		//Return content
		return $content;
	}
}
