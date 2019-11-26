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

Verify that you have the configuration file `config/packages/rapsys_pack.yaml`
with the following content:

```yaml
#Services configuration
services:
    #Register assets pack package
    assets.pack_package:
        class: Rapsys\PackBundle\Asset\PathPackage
        arguments: [ '/', '@assets.empty_version_strategy', '@assets.context' ]
    #Register twig pack extension
    rapsys_pack.twig.pack_extension:
        class: Rapsys\PackBundle\Twig\PackExtension
        arguments: [ '@file_locator', '@service_container', '@assets.pack_package' ]
        tags: [ twig.extension ]
```

Open a command console, enter your project directory and execute the
following command to see default bundle configuration:

```console
$ php bin/console config:dump-reference RapsysPackBundle
```

Open a command console, enter your project directory and execute the
following command to see current bundle configuration:

```console
$ php bin/console debug:config RapsysPackBundle
```

### Step 4: Use the twig extension in your Template

You can use a template like this to generate your first `rapsys_pack` enabled template:

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

namespace Rapsys\PackBundle\Twig\Filter;

use Rapsys\PackBundle\Twig\Filter\FilterInterface;
use Twig\Error\Error;

//This class will be defined in the parameter rapsys_pack.filters.(css|img|js).[x].class string
class MyPackFilter implements FilterInterface {
	//The constructor arguments ... will be replaced defined in the parameter rapsys_pack.filters.(css|img|js).[x].args array
	public function __construct($fileName, $line, $bin = 'mypack', ...) {
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
	public function process($content) {
		//Create descriptors
		$descriptorSpec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		);

		//Open process
		if (is_resource($proc = proc_open($this->bin, $descriptorSpec, $pipes))) {
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

The class is required to get it's arguments through constructor and have a process method.
