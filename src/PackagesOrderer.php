<?php
namespace Interop\Framework\ModuleInstaller;

use Composer\Package\PackageInterface;

/**
 * This class is dedicated at reordering packages.
 *
 * @author David Negrier
 */
class PackagesOrderer
{
    /**
     * Method: go through the tree, loading child first.
     * Each time we go through a package, lets ensure the package is not already part of the packages to install.
     * If so, ignore.
     *
     * @param array<array> $unorderedPackagesList
     */
    public static function reorderPackages(array $unorderedPackagesList)
    {
        // The very first step is to reorder the packages alphabetically.
        // This is to ensure the same order every time, even between packages that are unrelated.
        usort($unorderedPackagesList, function (PackageInterface $packageA, PackageInterface $packageB) {
            return strcmp($packageA->getName(), $packageB->getName());
        });

        $orderedPackagesList = array();
        foreach ($unorderedPackagesList as $package) {
            $orderedPackagesList = self::walkPackagesList($package, $orderedPackagesList, $unorderedPackagesList);
        }

        return $orderedPackagesList;
    }

    /**
     * Function used to sort packages by dependencies (packages depending from no other package in front of others)
     * Invariant hypothesis for this function: $orderedPackagesList is already ordered and the package we add
     * has all its dependencies already accounted for. If not, we add the dependencies first.
     *
     * @param  array        $package
     * @param  array<array> $orderedPackagesList The list of sorted packages
     * @param  array<array> $availablePackages   The list of all packages not yet sorted
     * @return array<array>
     */
    private static function walkPackagesList(array $package, array $orderedPackagesList, array &$availablePackages)
    {
        // First, let's check that the package we want to add is not already in our list.
        foreach ($orderedPackagesList as $includedPackage) {
            if ($includedPackage->equals($package)) {
                return $orderedPackagesList;
            }
        }

        // We need to make sure there is no loop (if a package A requires a package B that requires the package A)...
        // We do that by removing the package from the list of all available packages.
        $key = array_search($package, $availablePackages);
        unset($availablePackages[$key]);

        // Now, let's see if there are dependencies.
        if (isset($package['require'])) {
            foreach ($package['require'] as $require => $version) {
                foreach ($availablePackages as $iterPackage) {
                    if ($iterPackage['name'] == $require) {
                        $orderedPackagesList = self::walkPackagesList($iterPackage, $orderedPackagesList, $availablePackages);
                        break;
                    }
                }
            }
        }

        // FIXME: manage dev-requires and "provides"

        // Finally, let's add the package once all dependencies have been added.
        $orderedPackagesList[] = $package;

        return $orderedPackagesList;
    }
}
