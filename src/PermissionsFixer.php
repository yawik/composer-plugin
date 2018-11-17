<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer;

use Composer\EventDispatcher\EventSubscriberInterface;
use Core\Application;
use Core\Options\ModuleOptions as CoreOptions;
use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\Event\ConfigureEvent;

/**
 * Class        PermissionsFixer
 * @package     Yawik\Composer
 * @author      Anthonius Munthi <http://itstoni.com>
 * @since       0.32.0
 */
class PermissionsFixer implements EventSubscriberInterface
{
    use LogTrait;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public static function getSubscribedEvents()
    {
        return [
            Plugin::YAWIK_ACTIVATE_EVENT => 'onActivateEvent',
            Plugin::YAWIK_CONFIGURE_EVENT => 'onConfigureEvent'
        ];
    }

    public function onConfigureEvent(ConfigureEvent $event)
    {
        $modules        = $event->getModules();
        $files          = [];
        $directories    = [];

        foreach ($modules as $module) {
            if ($module instanceof PermissionsFixerModuleInterface) {
                $modDirLists    = $module->getDirectoryPermissionLists();
                $modFileLists   = $module->getFilePermissionLists();
                if (is_array($modDirLists)) {
                    $directories    = array_merge($directories, $modDirLists);
                } else {
                    $this->logError(sprintf(
                        '<comment>%s::getDirectoryPermissionList()</comment> should return an array.',
                        get_class($module)
                    ));
                }

                if (is_array($modFileLists)) {
                    $files = array_merge($files, $modFileLists);
                } else {
                    $this->logError(sprintf(
                        '<comment>%s::getFilePermissionList()</comment> should return an array.',
                        get_class($module)
                    ));
                }
            }
        }

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $this->mkdir($directory);
            }
            $this->chmod($directory);
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                $this->touch($file);
            }
            $this->chmod($file, 0666);
        }
    }

    public function touch($file)
    {
        try {
            $this->filesystem->touch($file);
            $this->log('created <info>'.$file.'</info>', 'touch');
        } catch (\Exception $exception) {
            $this->logError($exception->getMessage(), 'touch');
        }
    }

    public function chmod($dir, $mode = 0777)
    {
        try {
            $this->filesystem->chmod($dir, $mode);
            $fileperms  = decoct(@fileperms($dir) & 0777);
            $message    = sprintf('<comment>%s</comment> with %s', $dir, $fileperms);
            $this->log($message, 'chmod');
        } catch (\Exception $exception) {
            $this->logError($exception->getMessage(), 'chmod');
        }
    }

    public function mkdir($dir, $mode = 0777)
    {
        try {
            $this->filesystem->mkdir($dir, $mode);
            $this->log(sprintf('<comment>%s</comment>', $dir), 'mkdir');
        } catch (\Exception $e) {
            $this->logError($e->getMessage(), 'mkdir');
        }
    }
}
