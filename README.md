About framework-interop's Module-Autoinstaller
==============================================

This project is a test project developed as a proof of concept while working on the [FrameworkInterop](https://github.com/framework-interop/framework-interop-demo) project.

The big picture
---------------

The ultimate goal is to allow framework agnostic modules to work together in a common application.
A module can contain a container (compatible with container-interop) and a HTTP router (compatible
with StackPHP middlewares).

Compared to the classical way of thinking about a web application, this is a paradigm shift.

**In a "classical" application**, packages added to the application may add new instances to the main and only DI container.
This is what SF2 bundles, ZF2 modules or Mouf2 packages are doing.

**Using this approach**, each package provides its own DI container that contains instances and its own router.
DI containers are added to a global container that is queried.

About this package
------------------

The goal of this package is simply to provide an easy way to **automatically detect modules** that might
be declared in Composer packages they use.

**Important:** use of this package is optional. You can use framework-interop without having module autodetection running.

This project adds an additional step to Composer "install", just after Composer dumps the autoloader.
The Module-Autoinstaller will scan all packages *composer.json* files and will look for a section like:

```json
{
	"extra": {
		"framework-interop": {
			"module-factory": "My\\ModuleFactory::getModule"
		}
	}
}
```

This section actually declares a module factory that can be bundled in the package.

The "module-factory" parameter must point to a function or a static method that returns the module.

Here is a sample implementation:

```php
class ModuleFactory {
	/**
	 * This method is returning a configured module
	 *
	 * @return ModuleInterface
	 */
	public static function getModule() {
		return new MyModule();
	}
}
```

Note: your package does not have to require the `mouf/module-autoinstaller` package. This is sweet because if
other module detectors follow the same convention (referencing factory code in `composer.json` extra section),
there can easily be many different implementations.


How to use a the detected modules in your project?
==================================================

This package will simply create a `modules.php` file at the root of your project.
This `modules.php` file will contain a list of modules:

Sample **modules.php** file:
```php
<?php
return [
    [
        'name' => '__root___0',
        'description' => 'Module number 0 for package __root__',
        'factory' => My\ModuleFactory::getModule,
        'enable' => true
    ],
];
```

Please note that the developer can enable or disable modules manually, using the 'enable' attribute.

From there it is up to the application developer to use that file.

Using framework-interop base application implementation, a typical use would look like this:

**app.php**
```php
use Interop\Framework\Application;

require_once __DIR__ . '/../vendor/autoload.php';

// Let's load modules from generated modules.php file.
$modules = require '../modules.php';

// Let's create a new application.
$app = new Application(
    $modules
);
```

Allowed syntax
--------------
Those syntaxes are all valid to declare module factories in **composer.json**:

Simply declaring a module-factory **via callback**:

```json
{
	"extra": {
		"framework-interop": {
			"module-factory": "My\\ModuleFactory::getModule"
		}
	}
}
```

Declaring an **array of module-factories via callback**:

```json
{
	"extra": {
		"framework-interop": {
			"module-factory": [
				"My\\ModuleFactory1::getModule",
				"My\\ModuleFactory2::getModule",
				"My\\ModuleFactory3::getModule"
			]
		}
	}
}
```

Declaring a module-factory **descriptor** (it contains additional data about the factory):

```json
{
	"extra": {
		"framework-interop": {
			"module-factory": {
				"name": "a unique name for the factory",
				"description": "a description of what the factory does",
				"factory": "My\\ModuleFactory::getModule"
			}
		}
	}
}
```

Note: all parameters of a descriptor are optional, except for the "factory" part.

Declaring an **array of module-factory descriptors**:

```json
{
	"extra": {
		"framework-interop": {
			"module-factory":
			[{
				"name": "a unique name for the factory",
				"description": "a description of what the factory does",
				"factory": "My\\ModuleFactory::getModule"
			},
			{
				"name": "a unique name for another factory",
				"description": "a description of what the factory does",
				"factory": "My\\ModuleFactory2::getModule"
			}]
		}
	}
}
```


Benefits
--------
Each package provides its module. The module is not dependent on the framework used in the application.
This way, we can provide packages that are framework agnostic.

Downsides
---------
The classical implementation of the composite controller and of StackPHP middlewares might imply a performance hit. We will need to think of a way to
improve the performance of the composite container (maybe by doing entries maps, mapping entries to their associated container...)
