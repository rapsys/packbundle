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
