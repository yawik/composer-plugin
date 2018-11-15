<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Yawik\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Plugin
 * @package Yawik\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @since   0.32.0
 * @TODO    Create more documentation for methods
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    const YAWIK_MODULE_TYPE = 'yawik-module';

    const SCAN_TYPE_INSTALL = 'install';
    const SCAN_TYPE_REMOVE  = 'remove';

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $output;

    /**
     * An array list of available modules
     *
     * @var array
     */
    private $installed = [];

    private $uninstalled = [];

    private $projectPath;

    /**
     * @var AssetsInstaller
     */
    private $assetsInstaller;

    /**
     * @var PermissionsFixer
     */
    private $permissionsFixer;

    public function __construct($projectPath=null)
    {
        $this->projectPath = is_null($projectPath) ? getcwd():$projectPath;
    }

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->output   = $io;
    }

    /**
     * Define AssetsInstaller to use
     * This very usefull during testing process
     * @param AssetsInstaller $installer
     */
    public function setAssetsInstaller(AssetsInstaller $installer)
    {
        if (!is_null($this->output)) {
            $installer->setOutputFromComposerIO($this->output);
        }
        $this->assetsInstaller = $installer;
    }

    /**
     * @return AssetsInstaller
     */
    public function getAssetsInstaller()
    {
        if (!is_object($this->assetsInstaller)) {
            $assetInstaller = new AssetsInstaller();
            $this->setAssetsInstaller($assetInstaller);
        }

        return $this->assetsInstaller;
    }

    /**
     * @return PermissionsFixer
     */
    public function getPermissionsFixer()
    {
        return $this->permissionsFixer;
    }

    /**
     * @param PermissionsFixer $permissionsFixer
     * @return Plugin
     */
    public function setPermissionsFixer($permissionsFixer)
    {
        if (!is_null($this->output)) {
            $permissionsFixer->setOutputFromComposerIO($this->output);
        }
        $this->permissionsFixer = $permissionsFixer;
        return $this;
    }

    /**
     * Provide composer event listeners.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump'     => 'onPostAutoloadDump',
            'post-package-install'   => 'onPostPackageInstall',
            'post-package-update'    => 'onPostPackageUpdate',
            'pre-package-uninstall'  => 'onPrePackageUninstall'
        ];
    }

    public function onPostAutoloadDump()
    {
        if (count($this->uninstalled) > 0) {
            $this->getAssetsInstaller()->uninstall($this->uninstalled);
        } else {
            $this->getAssetsInstaller()->install($this->installed);
        }
        $this->getPermissionsFixer()->fix();
    }

    public function onPostPackageInstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $this->scanModules($package, static::SCAN_TYPE_INSTALL);
    }

    public function onPostPackageUpdate(PackageEvent $event)
    {
        $package = $event->getOperation()->getTargetPackage();
        $this->scanModules($package, static::SCAN_TYPE_INSTALL);
    }

    public function onPrePackageUninstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $this->scanModules($package, static::SCAN_TYPE_REMOVE);
    }

    private function scanModules(PackageInterface $package, $scanType='install')
    {
        $installer      = $this->composer->getInstallationManager();
        $packagePath    = $installer->getInstallPath($package);
        $type           = $package->getType();
        $publicDir      = $packagePath.'/public';
        $extras         = $package->getExtra();

        if (file_exists($publicDir) && $type === static::YAWIK_MODULE_TYPE) {
            // we skip undefined zf module definition
            if (isset($extras['zf']['module'])) {
                // we register module class name
                $moduleName     = $extras['zf']['module'];
                if (self::SCAN_TYPE_INSTALL == $scanType) {
                    $this->installed[$moduleName] = realpath($publicDir);
                } else {
                    $this->uninstalled[] = $moduleName;
                }
            } else {
                $this->output->write('[warning] No module definition for: ' . $package->getName());
            }
        }
    }
}
