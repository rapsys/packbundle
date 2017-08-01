<?php
// src/Rapsys/PackBundle/Twig/PackTokenParser.php
namespace Rapsys\PackBundle\Twig;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PackTokenParser extends \Twig_TokenParser {
	private $tag;

	/**
	 * Constructor.
	 *
	 * @param class		$fileLocator		The FileLocator instance
	 * @param class		$assetsPackages		The Assets Packages instance
	 * @param string	$prefix			The prefix path
	 * @param string	$tag			The tag name
	 * @param string	$output			The default output string
	 * @param string	$tool			The tool path
	 */
	public function __construct(FileLocator $fileLocator, ContainerInterface $containerInterface, $prefix, $tag, $output, $tool = null) {
		$this->fileLocator		= $fileLocator;
		$this->containerInterface	= $containerInterface;
		$this->prefix			= $prefix;
		$this->tag			= $tag;
		$this->output			= $output;
		$this->tool			= $tool;
	}

	public function getTag() {
		return $this->tag;
	}

	public function parse(\Twig_Token $token) {
		$parser = $this->parser;
		$stream = $this->parser->getStream();

		$inputs = array();
		$filters = array();
		$name = 'asset_url';
		$output = $this->output;

		$content = '';

		while (!$stream->test(\Twig_Token::BLOCK_END_TYPE)) {
			if ($stream->test(\Twig_Token::STRING_TYPE)) {
				// '@jquery', 'js/src/core/*', 'js/src/extra.js'
				$inputs[] = $stream->next()->getValue();
			} elseif ($stream->test(\Twig_Token::NAME_TYPE, 'filter')) {
				// filter='yui_js'
				$stream->next();
				$stream->expect(\Twig_Token::OPERATOR_TYPE, '=');
				$filters = array_merge($filters, array_filter(array_map('trim', explode(',', $stream->expect(\Twig_Token::STRING_TYPE)->getValue()))));
			} elseif ($stream->test(\Twig_Token::NAME_TYPE, 'output')) {
				// output='js/packed/*.js' OR output='js/core.js'
				$stream->next();
				$stream->expect(\Twig_Token::OPERATOR_TYPE, '=');
				$output = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();
			} elseif ($stream->test(\Twig_Token::NAME_TYPE, 'name')) {
				// name='core_js'
				$stream->next();
				$stream->expect(\Twig_Token::OPERATOR_TYPE, '=');
				$name = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();
			} else {
				$token = $stream->getCurrent();
				throw new \Twig_Error_Syntax(sprintf('Unexpected token "%s" of value "%s"', \Twig_Token::typeToEnglish($token->getType()), $token->getValue()), $token->getLine(), $stream->getFilename());
			}
		}

		$stream->expect(\Twig_Token::BLOCK_END_TYPE);

		$body = $this->parser->subparse(array($this, 'testEndTag'), true);

		$stream->expect(\Twig_Token::BLOCK_END_TYPE);

		//Replace star with sha1
		if (($pos = strpos($output, '*')) !== false) {
			#XXX: assetic code : substr(sha1(serialize($inputs).serialize($filters).serialize($options)), 0, 7)
			$output = substr($output, 0, $pos).sha1(serialize($inputs).serialize($filters)).substr($output, $pos + 1);
		}

		//Deal with inputs
		for($k = 0; $k < count($inputs); $k++) {
			//Deal with generic url
			if (strpos($inputs[$k], '//') === 0) {
				//TODO: set this as a parameter (scheme)
				$inputs[$k] = 'https:'.$inputs[$k];
			//Deal with non url path
			} elseif (strpos($inputs[$k], '://') === false) {
				//Check if we have a bundle path
				if ($inputs[$k][0] == '@') {
					if (($pos = strpos($inputs[$k], '/')) === false) {
						throw new \Twig_Error_Syntax(sprintf('Invalid input path "%s"', $inputs[$k]), $token->getLine(), $stream->getFilename());
					}
					//Extract prefix
					#$inputs[$k] = $this->kernel->locateResource(substr($inputs[$k], 0, $pos)).substr($inputs[$k], $pos + 1);
					$inputs[$k] = $this->fileLocator->locate(substr($inputs[$k], 0, $pos)).substr($inputs[$k], $pos + 1);
				}
				//Deal with globs
				if (strpos($inputs[$k], '*') !== false || (($a = strpos($inputs[$k], '{')) !== false && ($b = strpos($inputs[$k], ',', $a)) !== false && strpos($inputs[$k], '}', $b) !== false)) {
					//Get replacement
					$replacement = glob($inputs[$k], GLOB_NOSORT|GLOB_BRACE);
					//Check that these are working files
					foreach($replacement as $input) {
						if (!is_file($input)) {
							throw new \Twig_Error_Syntax(sprintf('Input path "%s" from "%s" is not a file', $input, $inputs[$k]), $token->getLine(), $stream->getFilename());
						}
					}
					//Replace with glob path
					array_splice($inputs, $k, 1, $replacement);
					//Fix current key
					$k += count($replacement) - 1;
				//Check that it's a file
				} elseif (!is_file($inputs[$k])) {
					throw new \Twig_Error_Syntax(sprintf('Input path "%s" is not a file', $inputs[$k]), $token->getLine(), $stream->getFilename());
				}
			}
		}

		//Retrieve files content
		foreach($inputs as $input) {
			//Set timeout
			$ctx = stream_context_create(
				array(
					'http' => array(
						'timeout' => 5
					)
				)
			);
			//Try to retrieve content
			if (($data = file_get_contents($input, false, $ctx)) === false) {
				throw new \Twig_Error_Syntax(sprintf('Unable to retrieve input path "%s"', $input), $token->getLine(), $stream->getFilename());
			}
			//Append content
			$content .= $data;
		}

		//Use tool
		if (!empty($this->tool) && is_executable($this->tool)) {
			$descriptorSpec = array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w')
			);
			if (is_resource($proc = proc_open($this->tool, $descriptorSpec, $pipes))) {
				//Set stderr as non blocking
				stream_set_blocking($pipes[2], 0);
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
				if ($stderr = stream_get_contents($pipes[2])) {
					throw new \Twig_Error_Syntax(sprintf('Got unexpected strerr for %s: %s', $this->tool, $stderr), $token->getLine(), $stream->getFilename());
				}
				//Close stderr
				fclose($pipes[2]);
				//Close process
				if ($ret = proc_close($proc)) {
					throw new \Twig_Error_Syntax(sprintf('Got unexpected non zero return code %s: %d', $this->tool, $ret), $token->getLine(), $stream->getFilename());
				}
			}
		}

		//Create output dir on demand
		if (!is_dir($parent = $dir = dirname($this->prefix.$output))) {
			//XXX: set as 0777, symfony umask (0022) will reduce rights (0755)
			mkdir($dir, 0777, true);
		}

		//Send file content
		//TODO: see if atomic rotation is really necessary ?
		//XXX: version management is done via rapsys_pack configuration atomic should be useless
		//TODO: implement asset versionning or re-use internal functions
		if (file_put_contents($this->prefix.$output.'.new', $content) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to write to: %s', $prefix.$output.'.new'), $token->getLine(), $stream->getFilename());
		}

		//Remove old file
		if (is_file($this->prefix.$output) && unlink($this->prefix.$output) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to unlink: %s', $prefix.$output), $token->getLine(), $stream->getFilename());
		}

		//Rename it
		if (rename($this->prefix.$output.'.new', $this->prefix.$output) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to rename: %s to %s', $prefix.$output.'.new', $prefix.$output), $token->getLine(), $stream->getFilename());
		}

		//Retrieve asset uri
		if (($output = $this->containerInterface->get('assets.packages')->getUrl($output, 'rapsys_pack')) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to get url for asset: %s with package %s', $output, 'rapsys_pack'), $token->getLine(), $stream->getFilename());
		}

		//Send pack node
		return new PackNode(array('value' => $body), array('inputs' => $inputs, 'filters' => $filters, 'name' => $name, 'output' => $output), $token->getLine(), $this->getTag());
	}

	public function testEndTag(\Twig_Token $token) {
		return $token->test(array('end'.$this->getTag()));
	}
}
