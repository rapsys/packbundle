<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Parser;

use Psr\Container\ContainerInterface;

use Rapsys\PackBundle\RapsysPackBundle;
use Rapsys\PackBundle\Util\SluggerUtil;

use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

use Twig\Error\Error;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\Node\TextNode;
use Twig\Source;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * {@inheritdoc}
 */
class TokenParser extends AbstractTokenParser {
	/**
	 * Filters array
	 */
	protected array $filters;

	/**
	 * Output string
	 */
	protected string $output;

	/**
	 * Route string
	 */
	protected string $route;

	/**
	 * Token string
	 */
	protected string $token;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface $container The ContainerInterface instance
	 * @param FileLocator $locator The FileLocator instance
	 * @param RouterInterface $router The RouterInterface instance
	 * @param SluggerUtil $slugger The SluggerUtil instance
	 * @param array $config The config
	 * @param mixed $ctx The context stream instance
	 * @param string $prefix The output prefix
	 * @param string $tag The tag name
	 */
	public function __construct(protected ContainerInterface $container, protected FileLocator $locator, protected RouterInterface $router, protected SluggerUtil $slugger, protected array $config, protected mixed $ctx, protected string $prefix, protected string $tag) {
		//Set filters
		$this->filters = $config['filters'][$prefix];

		//Set output
		$this->output = $config['public'].'/'.$config['prefixes']['pack'].'/'.$config['prefixes'][$prefix].'/*.'.$prefix;

		//Set route
		$this->route = $config['routes'][$prefix];

		//Set token
		$this->token = $config['tokens'][$prefix];
	}

	/**
	 * Get the tag name
	 *
	 * @return string This tag name
	 */
	public function getTag(): string {
		return $this->tag;
	}

	/**
	 * Parse the token
	 *
	 * @xxx Skip filter when debug mode is enabled is not possible
	 * @xxx This code is only run once when twig cache is enabled
	 * @xxx Twig cache value is not avaible in container parameters, maybe in twig env ?
	 *
	 * @param Token $token The \Twig\Token instance
	 * @return Node The PackNode
	 */
	public function parse(Token $token): Node {
		//Get parser
		$parser = $this->parser;

		//Get parser stream
		$stream = $this->parser->getStream();

		//Set inputs array
		$inputs = [];

		//Set content
		$content = '';

		//Process the token block until end
		while (!$stream->test(Token::BLOCK_END_TYPE)) {
			//The files to process
			if ($stream->test(Token::STRING_TYPE)) {
				//'somewhere/somefile.(css|img|js)' 'somewhere/*' '@jquery'
				$inputs[] = $stream->next()->getValue();
			//The filters token
			} elseif ($stream->test(Token::NAME_TYPE, 'filters')) {
				//filter='yui_js'
				$stream->next();
				$stream->expect(Token::OPERATOR_TYPE, '=');
				$this->filters = array_merge($this->filters, array_filter(array_map('trim', explode(',', $stream->expect(Token::STRING_TYPE)->getValue()))));
			//The route token
			} elseif ($stream->test(Token::NAME_TYPE, 'route')) {
				//output='rapsyspack_css' OR output='rapsyspack_js' OR output='rapsyspack_img'
				$stream->next();
				$stream->expect(Token::OPERATOR_TYPE, '=');
				$this->route = $stream->expect(Token::STRING_TYPE)->getValue();
			//The output token
			} elseif ($stream->test(Token::NAME_TYPE, 'output')) {
				//output='js/packed/*.js' OR output='js/core.js'
				$stream->next();
				$stream->expect(Token::OPERATOR_TYPE, '=');
				$this->output = $stream->expect(Token::STRING_TYPE)->getValue();
			//TODO: add format ? jpeg|png|gif|webp|webm ???
			//The token name
			} elseif ($stream->test(Token::NAME_TYPE, 'token')) {
				//name='core_js'
				$stream->next();
				$stream->expect(Token::OPERATOR_TYPE, '=');
				$this->token = $stream->expect(Token::STRING_TYPE)->getValue();
			//Unexpected token
			} else {
				$token = $stream->getCurrent();
				//Throw error
				throw new Error(sprintf('Unexpected token "%s" of value "%s"', Token::typeToEnglish($token->getType()), $token->getValue()), $token->getLine(), $stream->getSourceContext());
			}
		}

		//Process end block
		$stream->expect(Token::BLOCK_END_TYPE);

		//Process body
		$body = $this->parser->subparse([$this, 'testEndTag'], true);

		//Process end block
		$stream->expect(Token::BLOCK_END_TYPE);

		//Without valid output
		if (($pos = strpos($this->output, '*')) === false || $pos !== strrpos($this->output, '*')) {
			//Throw error
			throw new Error(sprintf('Invalid output "%s"', $this->output), $token->getLine(), $stream->getSourceContext());
		}

		//Without existing route
		if ($this->router->getRouteCollection()->get($this->route) === null) {
			//Throw error
			throw new Error(sprintf('Invalid route "%s"', $this->route), $token->getLine(), $stream->getSourceContext());
		}

		//Set file
		//XXX: assetic use substr(sha1(serialize($inputs).serialize($this->filters).serialize($this->output)), 0, 7)
		$file = $this->slugger->hash([$inputs, $this->filters, $this->output, $this->route, $this->token]);

		//Replace star by file
		$this->output = substr($this->output, 0, $pos).$file.substr($this->output, $pos + 1);

		//Process inputs
		for($k = 0; $k < count($inputs); $k++) {
			//Deal with generic url
			if (strpos($inputs[$k], '//') === 0) {
				//Fix url
				$inputs[$k] = ($_ENV['RAPSYSPACK_SCHEME'] ?? 'https').'://'.substr($inputs[$k], 2);
			//Deal with non url path
			} elseif (strpos($inputs[$k], '://') === false) {
				//Check if we have a bundle path
				if ($inputs[$k][0] == '@') {
					//Resolve it
					$inputs[$k] = $this->getLocated($inputs[$k], $token->getLine(), $stream->getSourceContext());
				}

				//Deal with globs
				if (strpos($inputs[$k], '*') !== false || (($a = strpos($inputs[$k], '{')) !== false && ($b = strpos($inputs[$k], ',', $a)) !== false && strpos($inputs[$k], '}', $b) !== false)) {
					//Get replacement
					$replacement = glob($inputs[$k], GLOB_NOSORT|GLOB_BRACE);

					//Check that these are working files
					foreach($replacement as $input) {
						//Check that it's a file
						if (!is_file($input)) {
							//Throw error
							throw new Error(sprintf('Input path "%s" from "%s" is not a file', $input, $inputs[$k]), $token->getLine(), $stream->getSourceContext());
						}
					}

					//Replace with glob path
					array_splice($inputs, $k, 1, $replacement);

					//Fix current key
					$k += count($replacement) - 1;
				//Check that it's a file
				} elseif (!is_file($inputs[$k])) {
					//Throw error
					throw new Error(sprintf('Input path "%s" is not a file', $inputs[$k]), $token->getLine(), $stream->getSourceContext());
				}
			}
		}

		#TODO: move the inputs reading from here to inside the filters ?

		//Check inputs
		if (!empty($inputs)) {
			//Retrieve files content
			foreach($inputs as $input) {
				//Try to retrieve content
				if (($data = file_get_contents($input, false, $this->ctx)) === false) {
					//Throw error
					throw new Error(sprintf('Unable to retrieve input path "%s"', $input), $token->getLine(), $stream->getSourceContext());
				}

				//Append content
				$content .= $data;
			}
		} else {
			//Trigger error about empty inputs ?
			//XXX: There may be a legitimate case where we want an empty file or an error, feel free to contact the author in such case
			#throw new Error('Empty inputs token', $token->getLine(), $stream->getSourceContext());

			//Send an empty node without inputs
			return new Node();
		}

		//Check filters
		if (!empty($this->filters)) {
			//Apply all filters
			foreach($this->filters as $filter) {
				//Init args
				$args = [$stream->getSourceContext(), $token->getLine()];

				//Check if args is available
				if (!empty($filter['args'])) {
					//Append args if provided
					$args += $filter['args'];
				}

				//Init reflection
				$reflection = new \ReflectionClass($filter['class']);

				//Set instance args
				$tool = $reflection->newInstanceArgs($args);

				//Process content
				$content = $tool->process($content);

				//Remove object
				unset($tool, $reflection);
			}
		} else {
			//Trigger error about empty filters ?
			//XXX: There may be a legitimate case where we want only a merged file or an error, feel free to contact the author in such case
			#throw new Error('Empty filters token', $token->getLine(), $stream->getSourceContext());
		}

		//Check if we have a bundle path
		if ($this->output[0] == '@') {
			//Resolve it
			$this->output = $this->getLocated($this->output, $token->getLine(), $stream->getSourceContext());
		}

		//Get filesystem
		$filesystem = new Filesystem();

		//Create output dir if not present
		if (!is_dir($dir = dirname($this->output))) {
			try {
				//Create dir
				//XXX: set as 0775, symfony umask (0022) will reduce rights (0755)
				$filesystem->mkdir($dir, 0775);
			} catch (IOExceptionInterface $e) {
				//Throw error
				throw new Error(sprintf('Output directory "%s" do not exists and unable to create it', $dir), $token->getLine(), $stream->getSourceContext(), $e);
			}
		}

		//Send file content
		try {
			//Write content to file
			//XXX: this call is (maybe) atomic
			//XXX: see https://symfony.com/doc/current/components/filesystem.html#dumpfile
			$filesystem->dumpFile($this->output, $content);
		} catch (IOExceptionInterface $e) {
			//Throw error
			throw new Error(sprintf('Unable to write "%s"', $this->output), $token->getLine(), $stream->getSourceContext(), $e);
		}

		//Without output file mtime
		if (($mtime = filemtime($this->output)) === false) {
			//Throw error
			throw new Error(sprintf('Unable to get "%s" mtime', $this->output), $token->getLine(), $stream->getSourceContext(), $e);
		}

		//TODO: get mimetype for images ? and set _format ?

		try {
			//Generate asset url
			$asset = $this->router->generate($this->route, [ 'file' => $file, 'u' => $mtime ]);
		//Catch router exceptions
		} catch (RouteNotFoundException|MissingMandatoryParametersException|InvalidParameterException $e) {
			//Throw error
			throw new Error(sprintf('Unable to generate asset route "%s"', $this->route), $token->getLine(), $stream->getSourceContext(), $e);
		}

		//Set name in context key
		$ref = new AssignNameExpression($this->token, $token->getLine());

		//Set output in context value
		$value = new TextNode($asset, $token->getLine());

		//Send body with context set
		return new Node([
			//This define name in twig template by prepending $context['<name>'] = '<output>';
			new SetNode(true, $ref, $value, $token->getLine(), $this->getTag()),
			//The tag captured body
			$body
		]);
	}

	/**
	 * Test for tag end
	 *
	 * @param Token $token The \Twig\Token instance
	 * @return bool The token end test result
	 */
	public function testEndTag(Token $token): bool {
		return $token->test(['end'.$this->getTag()]);
	}

	/**
	 * Get path from bundled file
	 *
	 * @see https://symfony.com/doc/current/bundles.html#overridding-the-bundle-directory-structure
	 *
	 * @param string $file The bundled file path
	 * @param int $lineno The template line where the error occurred
	 * @param Source $source The source context where the error occurred
	 * @param Exception $prev The previous exception
	 * @return string The resolved file path
	 */
	public function getLocated(string $file, int $lineno = 0, ?Source $source = null, ?\Exception $prev = null): string {
		/*TODO: add a @jquery magic feature ?
		if ($file == '@jquery') {
			#header('Content-Type: text/plain');
			#var_dump($inputs);
			#exit;
			return $this->config['jquery'];
		}*/

		//Extract bundle
		if (($bundle = strstr($file, '/', true)) === false) {
			throw new Error(sprintf('Invalid bundle "%s"', $file), $lineno, $source);
		}

		//Extract path
		if (($path = strstr($file, '/')) === false) {
			throw new Error(sprintf('Invalid path "%s"', $file), $lineno, $source);
		}

		//Extract alias
		$alias = strtolower(substr($bundle, 1));

		//With public parameter
		if ($this->container->hasParameter($alias.'.public')) {
			//Set prefix
			$prefix = $this->container->getParameter($alias.'.public');
		//Without public parameter
		} else {
			//Without bundle suffix presence
			//XXX: use "bundle templates automatic namespace" mimicked behaviour to find intended bundle and/or path
			//XXX: see https://symfony.com/doc/4.3/templates.html#bundle-templates
			if (strlen($bundle) < strlen('@Bundle') || substr($bundle, -strlen('Bundle')) !== 'Bundle') {
				//Append Bundle
				$bundle .= 'Bundle';
			}

			//Try to resolve bundle prefix
			try {
				$prefix = $this->locator->locate($bundle);
			//Catch bundle does not exist or is not enabled exception
			} catch(\InvalidArgumentException $e) {
				throw new Error(sprintf('Unlocatable bundle "%s"', $bundle), $lineno, $source, $e);
			}

			//With Resources/public subdirectory
			if (is_dir($prefix.'Resources/public')) {
				$prefix .= 'Resources/public';
			//With public subdirectory
			} elseif (is_dir($prefix.'public')) {
				$prefix .= 'public';
			//Without any public subdirectory
			} else {
				throw new Error(sprintf('Bundle "%s" lacks a public subdirectory', $bundle), $lineno, $source, $e);
			}
		}

		//Return solved bundle prefix and path
		return $prefix.$path;
	}
}
