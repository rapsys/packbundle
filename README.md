Contribute
==========

You may buy me a Beer, a Tea or help with Server fees with a paypal donation to
the address <paypal@rapsys.eu>.

Don't forget to show your love for this project, feel free to report bugs to
the author, issues which are security relevant should be disclosed privately
first.

Patches are welcomed and grant credit when requested.

Installation
============

Applications that use Symfony Flex
----------------------------------

Add bundle custom repository to your project's `composer.json` file:

```json
{
	...,
	"repositories": [
		{
			"type": "package",
			"package": {
				"name": "rapsys/packbundle",
				"version": "dev-master",
				"source": {
					"type": "git",
					"url": "https://git.rapsys.eu/packbundle",
					"reference": "master"
				},
				"autoload": {
					"psr-4": {
						"Rapsys\\PackBundle\\": ""
					}
				},
				"require": {
					"symfony/asset": "^4.0|^5.0|^6.0|^7.0",
					"symfony/flex": "^1.0|^2.0",
					"symfony/framework-bundle": "^4.0|^5.0|^6.0|^7.0",
					"symfony/process": "^4.0|^5.0|^6.0|^7.0",
					"symfony/twig-bundle": "^4.0|^5.0|^6.0|^7.0"
				}
			}
		}
	],
	...
}
```

Then open a command console, enter your project directory and execute:

```console
$ composer require rapsys/packbundle dev-master
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require rapsys/packbundle dev-master
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
	public function registerBundles()
	{
		$bundles = array(
			// ...
			new Rapsys\PackBundle\RapsysPackBundle(),
		);

		// ...
	}

	// ...
}
```

### Step 3: Configure the Bundle

Setup configuration file `config/packages/rapsys_pack.yaml` with the following
content available in `Resources/config/packages/rapsys_pack.yaml`:

```yaml
#Services configuration
services:
    #Replace assets.packages definition
    assets.packages:
        class: 'Symfony\Component\Asset\Packages'
        arguments: [ '@rapsys_pack.path_package' ]
    #Replace assets.context definition
    assets.context:
        class: 'Rapsys\PackBundle\Context\RequestStackContext'
        arguments: [ '@request_stack', '%asset.request_context.base_path%', '%asset.request_context.secure%' ]
    #Register assets pack package
    rapsys_pack.path_package:
        class: 'Rapsys\PackBundle\Package\PathPackage'
        arguments: [ '/', '@assets.empty_version_strategy', '@assets.context' ]
        public: true
    #Register twig pack extension
    rapsys_pack.pack_extension:
        class: 'Rapsys\PackBundle\Extension\PackExtension'
        arguments: [ '@service_container', '@rapsys_pack.intl_util', '@file_locator', '@rapsys_pack.path_package', '@rapsys_pack.slugger_util' ]
        tags: [ 'twig.extension' ]
    #Register intl util service
    rapsys_pack.intl_util:
        class: 'Rapsys\PackBundle\Util\IntlUtil'
        public: true
    #Register facebook event subscriber
    Rapsys\PackBundle\Subscriber\FacebookSubscriber:
        arguments: [ '@router', [] ]
        tags: [ 'kernel.event_subscriber' ]
    #Register intl util class alias
    Rapsys\PackBundle\Util\IntlUtil:
        alias: 'rapsys_pack.intl_util'
    #Register facebook util service
    rapsys_pack.facebook_util:
        class: 'Rapsys\PackBundle\Util\FacebookUtil'
        arguments: [ '@router', '%kernel.project_dir%/var/cache', '%rapsys_pack.path%' ]
        public: true
    #Register facebook util class alias
    Rapsys\PackBundle\Util\FacebookUtil:
        alias: 'rapsys_pack.facebook_util'
    #Register image util service
    rapsys_pack.image_util:
        class: 'Rapsys\PackBundle\Util\ImageUtil'
        arguments: [ '@router', '@rapsys_pack.slugger_util', '%kernel.project_dir%/var/cache', '%rapsys_pack.path%' ]
        public: true
    #Register image util class alias
    Rapsys\PackBundle\Util\ImageUtil:
        alias: 'rapsys_pack.image_util'
    #Register map util service
    rapsys_pack.map_util:
        class: 'Rapsys\PackBundle\Util\MapUtil'
        arguments: [ '@router', '@rapsys_pack.slugger_util' ]
        public: true
    #Register map util class alias
    Rapsys\PackBundle\Util\MapUtil:
        alias: 'rapsys_pack.map_util'
    #Register slugger util service
    rapsys_pack.slugger_util:
        class: 'Rapsys\PackBundle\Util\SluggerUtil'
        arguments: [ '%kernel.secret%' ]
        public: true
    #Register slugger util class alias
    Rapsys\PackBundle\Util\SluggerUtil:
        alias: 'rapsys_pack.slugger_util'
    #Register image controller
    Rapsys\PackBundle\Controller\ImageController:
        arguments: [ '@service_container', '@rapsys_pack.image_util', '@rapsys_pack.slugger_util', '%kernel.project_dir%/var/cache', '%rapsys_pack.path%' ]
        tags: [ 'controller.service_arguments' ]
    #Register map controller
    Rapsys\PackBundle\Controller\MapController:
        arguments: [ '@service_container', '@rapsys_pack.map_util', '@rapsys_pack.slugger_util', '%kernel.project_dir%/var/cache', '%rapsys_pack.path%' ]
        tags: [ 'controller.service_arguments' ]
    Rapsys\PackBundle\Form\CaptchaType:
        arguments: [ '@rapsys_pack.image_util', '@rapsys_pack.slugger_util', '@translator' ]
        tags: [ 'form.type' ]
```

Setup configuration file `config/packages/myproject.yaml` with the following
content available in `Resources/config/packages/rapsys_pack.yaml`:

```yaml
#Services configuration
services:
    #Register facebook event subscriber
    Rapsys\PackBundle\Subscriber\FacebookSubscriber:
        arguments: [ '@router', [ 'en', 'en_gb', 'en_us', 'fr', 'fr_fr' ] ]
        tags: [ 'kernel.event_subscriber' ]
    #Register facebook util service
    rapsys_blog.facebook_util:
        class: 'Rapsys\PackBundle\Util\FacebookUtil'
        arguments: [ '@router',  '%kernel.project_dir%/var/cache', '%rapsys_pack.path%', 'facebook', '%kernel.project_dir%/public/png/facebook.png' ]
        public: true
```

Open a command console, enter your project directory and execute the following
command to see default bundle configuration:

```console
$ bin/console config:dump-reference RapsysPackBundle
```

Open a command console, enter your project directory and execute the following
command to see current bundle configuration:

```console
$ bin/console debug:config RapsysPackBundle
```

### Step 4: Use the twig extension in your Template

You can use a template like this to generate your first `rapsys_pack` enabled
template:

```twig
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>{% block title %}Welcome!{% endblock %}</title>
		{% stylesheet '//fonts.googleapis.com/css?family=Irish+Grover|La+Belle+Aurore' '@NamedBundle/Resources/public/css/{reset,screen}.css' '@Short/css/example.css' %}
			<link rel="stylesheet" type="text/css" href="{{ asset_url }}" />
		{% endstylesheet %}
	</head>
	<body>
		{% block body %}{% endblock %}
		{% javascript '@Short/js/*.js' %}
			<script type="text/javascript" src="{{ asset_url }}"></script>
		{% endjavascript %}
	</body>
</html>
```

### Step 5: Make sure you have local binary installed

You need to have cpack and jpack scripts from https://git.rapsys.eu/packer/ repository
set as executable and installed in /usr/local/bin.

To install cpack and jpack required packages open a root console and execute the
following command:

```console
# urpmi perl-base perl-CSS-Packer perl-JavaScript-Packer
```

or stone age distributions:

```console
# apt-get install libcss-packer-perl libjavascript-packer-perl
```

or other distributions through cpan:

```console
# cpan App::cpanminus
# cpanm CSS::Packer
# cpanm JavaScript::Packer
```

### Step 6: Create your own filter

You can create you own mypackfilter class which call a mypack binary:

```php
<?php

namespace Rapsys\PackBundle\Filter;

use Twig\Error\Error;

//This class will be defined in the parameter rapsys_pack.filters.(css|img|js).[x].class string
class MyPackFilter implements FilterInterface {
	//The constructor arguments ... will be replaced with values defined in the parameter rapsys_pack.filters.(css|img|js).[x].args array
	public function __construct(string $fileName, int $line, string $bin = 'mypack', ...) {
		//Set fileName
		$this->fileName = $fileName;

		//Set line
		$this->line = $line;

		//Set bin
		$this->bin = $bin;

		//Check argument presence
		if (!empty($this->...)) {
			//Append argument
			if ($this->... == '...') {
				$this->bin .= ' ...';
			} else {
				//Throw an error on ...
				throw new Error(sprintf('Got ... for %s: %s', $this->bin, $this->...), $this->line, $this->fileName);
			}
		}
	}

	//Pass merge of all inputs in content
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
```

The class must implements FilterInterface and get it's arguments through constructor.
