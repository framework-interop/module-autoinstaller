<?php
namespace Interop\Framework\ModuleInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Composer\Package\PackageInterface;
use Composer\Package\AliasPackage;
use Composer\Package\Dumper\ArrayDumper;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;

/**
 * framework-interop automatic module installer for Composer.
 * (based on RobLoach's code for ComponentInstaller)
 */
class ModuleInstallerPlugin implements PluginInterface, EventSubscriberInterface
{
  protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
      $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => array(
                array('onPostAutoloadDump', 0)
            ),
        );
    }

    /**
     * Script callback; Acted on after the autoloader is dumped.
     */
    public function onPostAutoloadDump(Event $event)
    {
        // Retrieve basic information about the environment and present a
        // message to the user.
        $composer = $event->getComposer();
        $io = $event->getIO();
        $io->write('<info>Compiling modules list</info>');

        $packages = self::getPackagesList($composer);

        $factoryList = array();

        foreach ($packages as $package) {
            if (isset($package['extra']['framework-interop']['module-factory'])) {
                $factories = $package['extra']['framework-interop']['module-factory'];

                // Allowed values for module-factory can be one of:
                // String: the code of the factory
                // Array of strings: an array of factory code
                // Factory descriptor: like { "name"=>"", "description"=>"toto", "module"=>"code" }
                // Array of factory descriptor: like [{ "name"=>"", "description"=>"toto", "module"=>"code", "priority"=>100 }]
                if (!is_array($factories) || self::isAssoc($factories)) {
                    $factories = array($factories);
                }
                foreach ($factories as $key => $factory) {
                    if (!is_array($factory)) {
                        if (isset($package['name'])) {
                            $packageName = $package['name'];
                        } else {
                            $packageName = 'root';
                        }
                        if (count($factories) == 0) {
                            $moduleName = "Module for package ".$packageName;
                        } else {
                            $moduleName = "Module number $key for package ".$packageName;
                        }
                        $factory = [
                            "name" => $packageName.'_'.$key,
                            "description" => $moduleName,
                            "module" => $factory,
                            "priority" => 0,
                        ];
                        $factories[$key] = $factory;
                    }
                }
                $factoryList = array_merge($factoryList, $factories);
            }
        }

        usort($factoryList, function($module1, $module2) {
            $priority1 = isset($module1['priority'])?$module1['priority']:0;
            $priority2 = isset($module2['priority'])?$module2['priority']:0;
            return $priority1-$priority2;
        });

        // Now, we should merge this with the existing modules.php if it exists.
        // FIXME: this is actually not possible! What if the classes are in error, or have been uninstalled...
        // We need to start from scratch each time!
        //if (file_exists("modules.php")) {
            // FIXME: autoload problems here!
            // Maybe we should simply have a list of modules that is overwritten!
        //    $existingFactoryList = require 'modules.php';
        //} else {
            //$existingFactoryList = [];
        //}

        if ($factoryList) {
            // TODO: security checks
            $fp = fopen("modules.php", "w");
            fwrite($fp, "<?php\nreturn [\n");
            foreach ($factoryList as $factory) {
                // Let's see if the factory exists in the existing list:
                /*foreach ($existingFactoryList as $item) {
                    if ($factory['name'] == $item['name']) {
                        $factory = array_merge($item, $factory);
                        break;
                    }
                }
                if (!isset($factory['enable'])) {
                    $factory['enable'] = true;
                }*/

                fwrite($fp, "    [\n");
                foreach ($factory as $key => $value) {
                    if ($key == 'module') {
                        fwrite($fp, "        '$key' => ".$value.",\n");
                    } else {
                        fwrite($fp, "        '$key' => ".var_export($value, true).",\n");
                    }
                }
                fwrite($fp, "    ],\n");
            }
            fwrite($fp, "];\n");
        }
    }

    /**
     * Returns if an array is associative or not.
     *
     * @param  array   $arr
     * @return boolean
     */
    private static function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Returns the list of packages that contain modules.
     *
     * @param  Composer           $composer
     * @return PackageInterface[]
     */
    protected static function getPackagesList(Composer $composer)
    {
        // Get the available packages.
        $allPackages = array();
        $locker = $composer->getLocker();
        if (isset($locker)) {
            $lockData = $locker->getLockData();
            $allPackages = isset($lockData['packages']) ? $lockData['packages'] : array();

            // Also merge in any of the development packages.
            $dev = isset($lockData['packages-dev']) ? $lockData['packages-dev'] : array();
            foreach ($dev as $package) {
                $allPackages[] = $package;
            }
        }

        $packages = array();

        // Only add those packages that we can reasonably
        // assume are components into our packages list
        foreach ($allPackages as $package) {
            $extra = isset($package['extra']) ? $package['extra'] : array();
            if (isset($extra['framework-interop']) && is_array($extra['framework-interop'])) {
                $packages[] = $package;
            }
        }

        // Add the root package to the packages list.
        $root = $composer->getPackage();
        if ($root) {
            $dumper = new ArrayDumper();
            $package = $dumper->dump($root);
            $package['is-root'] = true;
            $packages[] = $package;
        }

        return $packages;
    }
}
