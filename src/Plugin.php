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
use Composer\Script\Event as ScriptEvent;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

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

    private $packages = [];

    /**
     * @var AssetsInstaller
     */
    private $assetsInstaller;

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
     * @param $installer
     */
    public function setAssetsInstaller($installer)
    {
        $this->assetsInstaller = $installer;
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
            'pre-package-uninstall'  => 'onPrePackageUninstall',
            'post-package-uninstall' => 'onPostPackageUninstall'
        ];
    }

    public function onPostAutoloadDump()
    {
        if (count($this->installed) > 0) {
            $this->getAssetsInstaller()->install($this->installed);
        }
        if (count($this->uninstalled) > 0) {
            $this->getAssetsInstaller()->uninstall($this->uninstalled);
        }
    }

    public function onPostPackageInstall(PackageEvent $event)
    {
        $this->scanModules($event, static::SCAN_TYPE_INSTALL);
    }

    public function onPostPackageUpdate(PackageEvent $event)
    {
        $this->scanModules($event, static::SCAN_TYPE_INSTALL);
    }

    public function onPostPackageUninstall(PackageEvent $event)
    {
        $this->scanModules($event, static::SCAN_TYPE_REMOVE);
    }

    /**
     * @return AssetsInstaller
     */
    private function getAssetsInstaller()
    {
        if (!is_object($this->assetsInstaller)) {
            $assetInstaller = new AssetsInstaller();
            if (php_sapi_name()==='cli') {
                $assetInstaller->setInput(new ArgvInput())->setOutput(new ConsoleOutput());
                $assetInstaller->getOutput()->setDecorated(true);
            }
            $this->assetsInstaller = $assetInstaller;
        }
        return $this->assetsInstaller;
    }

    private function scanModules(PackageEvent $event, $scanType='install')
    {
        $package    = $event->getOperation()->getPackage();
        $installer = $this->composer->getInstallationManager();
        $packagePath = $installer->getInstallPath($package);

        $type       = $package->getType();
        $publicDir  = $packagePath.'/public';
        $extras     = $package->getExtra();
        if (file_exists($publicDir) && $type === static::YAWIK_MODULE_TYPE) {
            // we skip undefined zf module definition
            if (isset($extras['zf']['module'])) {
                // we register module class name
                $moduleName     = $extras['zf']['module'];
                if ($scanType === 'install') {
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
