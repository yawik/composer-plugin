<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Yawik\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    const YAWIK_MODULE_TYPE = 'yawik-module';

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * An array list of available modules
     *
     * @var array
     */
    private $modules;

    private $projectPath;


    public function __construct()
    {
        $this->projectPath = getcwd();
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer     = $composer;
        $this->io           = $io;
    }

    /**
     * Provide composer event listeners.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump'    => 'onPostAutoloadDump',
            'post-package-install'  => 'onPostPackageInstall',
            'post-package-update'   => 'onPostPackageUpdate',
            'pre-package-uninstall' => 'onPrePackageUninstall',
        ];
    }

    public function onPostAutoloadDump()
    {
        $this->installAssets();
    }

    public function onPostPackageInstall(PackageEvent $event)
    {
        $this->scanModules($event);
    }

    public function onPostPackageUpdate(PackageEvent $event)
    {
        $this->scanModules($event);
    }

    public function onPrePackageUninstall(PackageEvent $event)
    {
        $this->scanModules($event);
    }

    public function onPostPackageUninstall(PackageEvent $event)
    {
        $this->scanModules($event);
    }

    private function installAssets()
    {
        if (count($this->modules) > 0) {
            $assetInstaller = new AssetsInstaller($this->modules);
            $assetInstaller->install();
        }
    }

    private function scanModules(PackageEvent $event)
    {
        $type       = $event->getComposer()->getPackage()->getType();
        $publicDir  = $event->getComposer()->getPackage()->getTargetDir().'/public';
        $extras     = $event->getComposer()->getPackage()->getExtra();
        if (is_dir($publicDir) && $type === static::YAWIK_MODULE_TYPE) {
            // we skip undefined zf module definition
            if (isset($extras['zf']['module'])) {
                // we register module class name
                $moduleName     = $extras['zf']['module'];
                $this->modules[$moduleName] = $publicDir;
            }
        }
    }
}
