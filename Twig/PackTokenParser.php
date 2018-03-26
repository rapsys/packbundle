<?php
// src/Rapsys/PackBundle/Twig/PackTokenParser.php
namespace Rapsys\PackBundle\Twig;

use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Asset\Packages;

class PackTokenParser extends \Twig_TokenParser {
	private $tag;

	/**
	 * Constructor.
	 *
	 * @param class		$fileLocator		The FileLocator instance
	 * @param class		$containerInterface	The Container Interface instance
	 * @param class		$assetsPackages		The Assets Packages instance
	 * @param string	$prefix			The prefix path
	 * @param string	$tag			The tag name
	 * @param string	$output			The default output string
	 * @param array		$filters		The default filters array
	 */
	public function __construct(FileLocator $fileLocator, ContainerInterface $containerInterface, Packages $assetsPackages, $prefix, $tag, $output, $filters) {
		$this->fileLocator		= $fileLocator;
		$this->containerInterface	= $containerInterface;
		$this->assetsPackages		= $assetsPackages;
		$this->prefix			= $prefix;
		$this->tag			= $tag;
		$this->output			= $output;
		$this->filters			= $filters;

		if ($this->containerInterface->hasParameter('rapsys_pack')) {
			if ($parameters = $this->containerInterface->getParameter('rapsys_pack')) {
				if (isset($parameters['timeout'])) {
					$timeout = $parameters['timeout'];
				} elseif (isset($parameters['user_agent'])) {
					$userAgent = $parameters['user_agent'];
				} elseif (isset($parameters['redirect'])) {
					$redirect = $parameters['redirect'];
				}
			}
		}

		//Set http default timeout
		$this->timeout = ini_get('default_socket_timeout');
		//Set http default user agent
		$this->userAgent = ini_get('user_agent');
		//Set http default redirect
		$this->redirect = 20;

		//Try to load service defaults
		if ($this->containerInterface->hasParameter('rapsys_pack')) {
			if ($parameters = $this->containerInterface->getParameter('rapsys_pack')) {
				if (!empty($parameters['timeout'])) {
					$this->timeout = $parameters['timeout'];
				}
				if (!empty($parameters['user_agent'])) {
					$this->userAgent = $parameters['user_agent'];
				}
				if (!empty($parameters['redirect'])) {
					$this->redirect = $parameters['redirect'];
				}
			}
		}
	}

	public function getTag() {
		return $this->tag;
	}

	public function parse(\Twig_Token $token) {
		$parser = $this->parser;
		$stream = $this->parser->getStream();

		$inputs = array();
		$name = 'asset_url';
		$output = $this->output;
		$filters = $this->filters;

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
				throw new \Twig_Error_Syntax(sprintf('Unexpected token "%s" of value "%s"', \Twig_Token::typeToEnglish($token->getType()), $token->getValue()), $token->getLine(), $stream->getSourceContext());
			}
		}

		$stream->expect(\Twig_Token::BLOCK_END_TYPE);

		$body = $this->parser->subparse(array($this, 'testEndTag'), true);

		$stream->expect(\Twig_Token::BLOCK_END_TYPE);

		//Replace star with sha1
		if (($pos = strpos($output, '*')) !== false) {
			#XXX: assetic code: substr(sha1(serialize($inputs).serialize($filters).serialize($options)), 0, 7)
			$output = substr($output, 0, $pos).sha1(serialize($inputs).serialize($filters)).substr($output, $pos + 1);
		}

		//Deal with inputs
		for($k = 0; $k < count($inputs); $k++) {
			//Deal with generic url
			if (strpos($inputs[$k], '//') === 0) {
				//Default scheme
				$scheme = 'https://';
				//Try to load service scheme
				if ($this->containerInterface->hasParameter('rapsys_pack')) {
					if ($parameters = $this->containerInterface->getParameter('rapsys_pack')) {
						if (isset($parameters['scheme'])) {
							$scheme = $parameters['scheme'];
						}
					}
				}
				//Fix url
				$inputs[$k] = $scheme.substr($inputs[$k], 2);
			//Deal with non url path
			} elseif (strpos($inputs[$k], '://') === false) {
				//Check if we have a bundle path
				if ($inputs[$k][0] == '@') {
					//Check that we don't have only a path
					if (($pos = strpos($inputs[$k], '/')) === false) {
						#TODO: @jquery support (if we really want it)
						#header('Content-Type: text/plain');
						#var_dump($inputs);
						#if ($inputs[0] == '@jquery') {
						#	exit;
						#}
						throw new \Twig_Error_Syntax(sprintf('Invalid input path "%s"', $inputs[$k]), $token->getLine(), $stream->getSourceContext());
					}
					//Resolve bundle prefix
					$inputs[$k] = $this->fileLocator->locate(substr($inputs[$k], 0, $pos)).substr($inputs[$k], $pos + 1);
				}
				//Deal with globs
				if (strpos($inputs[$k], '*') !== false || (($a = strpos($inputs[$k], '{')) !== false && ($b = strpos($inputs[$k], ',', $a)) !== false && strpos($inputs[$k], '}', $b) !== false)) {
					//Get replacement
					$replacement = glob($inputs[$k], GLOB_NOSORT|GLOB_BRACE);
					//Check that these are working files
					foreach($replacement as $input) {
						if (!is_file($input)) {
							throw new \Twig_Error_Syntax(sprintf('Input path "%s" from "%s" is not a file', $input, $inputs[$k]), $token->getLine(), $stream->getSourceContext());
						}
					}
					//Replace with glob path
					array_splice($inputs, $k, 1, $replacement);
					//Fix current key
					$k += count($replacement) - 1;
				//Check that it's a file
				} elseif (!is_file($inputs[$k])) {
					throw new \Twig_Error_Syntax(sprintf('Input path "%s" is not a file', $inputs[$k]), $token->getLine(), $stream->getSourceContext());
				}
			}
		}

		//Init context
		$ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => $this->timeout,
					'user_agent' => $this->userAgent,
					'redirect' => $this->redirect,
				)
			)
		);

		//Check inputs
		if (!empty($inputs)) {
			//Retrieve files content
			foreach($inputs as $input) {
				//Try to retrieve content
				if (($data = file_get_contents($input, false, $ctx)) === false) {
					throw new \Twig_Error_Syntax(sprintf('Unable to retrieve input path "%s"', $input), $token->getLine(), $stream->getSourceContext());
				}
				//Append content
				$content .= $data;
			}
		} else {
			#TODO: trigger error about empty inputs ?
		}

		//Check filters
		if (!empty($filters)) {
			//Apply all filters
			foreach($filters as $filter) {
				//Prefix with filter
				$filter = __NAMESPACE__.'\\Filter\\'.$filter;
				//Init tool object
				$tool = new $filter($this->containerInterface, $stream->getSourceContext(), $token->getLine());
				//Process content
				$content = $tool->process($content);
				//Remove object
				unset($tool);
			}
		} else {
			#TODO: trigger error about empty filters ?
		}

		//Create output dir on demand
		if (!is_dir($parent = $dir = dirname($this->prefix.$output))) {
			//XXX: set as 0777, symfony umask (0022) will reduce rights (0755)
			try {
				mkdir($dir, 0777, true);
			} catch (\Exception $e) {
				throw new \Twig_Error_Syntax(sprintf('Unable to create directory: %s', $dir), $token->getLine(), $stream->getSourceContext());
			}
		}

		//Send file content
		//XXX: atomic rotation is required to avoid partial content in reverse cache
		if (file_put_contents($this->prefix.$output.'.new', $content) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to write to: %s', $prefix.$output.'.new'), $token->getLine(), $stream->getSourceContext());
		}

		//Remove old file
		if (is_file($this->prefix.$output) && unlink($this->prefix.$output) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to unlink: %s', $prefix.$output), $token->getLine(), $stream->getSourceContext());
		}

		//Rename it
		if (rename($this->prefix.$output.'.new', $this->prefix.$output) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to rename: %s to %s', $prefix.$output.'.new', $prefix.$output), $token->getLine(), $stream->getSourceContext());
		}

		//Retrieve asset uri
		if (($output = $this->assetsPackages->getUrl($output, 'rapsys_pack')) === false) {
			throw new \Twig_Error_Syntax(sprintf('Unable to get url for asset: %s with package %s', $output, 'rapsys_pack'), $token->getLine(), $stream->getSourceContext());
		}

		//Send pack node
		return new PackNode(array('value' => $body), array('inputs' => $inputs, 'filters' => $filters, 'name' => $name, 'output' => $output), $token->getLine(), $this->getTag());
	}

	public function testEndTag(\Twig_Token $token) {
		return $token->test(array('end'.$this->getTag()));
	}
}
