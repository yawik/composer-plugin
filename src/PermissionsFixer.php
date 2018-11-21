<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer;

use Core\Options\ModuleOptions as CoreOptions;
use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\Event\ConfigureEvent;

/**
 * Class        PermissionsFixer
 * @package     Yawik\Composer
 * @author      Anthonius Munthi <http://itstoni.com>
 * @since       0.32.0
 */
class PermissionsFixer
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

    public function onConfigureEvent(ConfigureEvent $event)
    {
        $modules        = $event->getModules();
        $options        = $event->getOptions();

        $this->fix($options, $modules);
    }

    public function fix(CoreOptions $options, array $modules)
    {
        $files          = [];
        $directories    = [];

        foreach ($modules as $module) {
            if ($module instanceof RequireFilePermissionInterface) {
                $modFileLists   = $module->getRequiredFileLists($options);
                if (!is_null($modFileLists) && !is_array($modFileLists)) {
                    $this->logError(sprintf(
                        '<comment>%s::getRequiredFileList()</comment> should return an array.',
                        get_class($module)
                    ));
                } else {
                    foreach ($modFileLists as $file) {
                        if (!in_array($file, $files)) {
                            $files[] = $file;
                        }
                    }
                }
            }

            if ($module instanceof RequireDirectoryPermissionInterface) {
                $modDirLists = $module->getRequiredDirectoryLists($options);
                if (!is_null($modDirLists) && !is_array($modDirLists)) {
                    $this->logError(sprintf(
                        '<comment>%s::getRequiredDirectoryList()</comment> should return an array.',
                        get_class($module)
                    ));
                } else {
                    foreach ($modDirLists as $directory) {
                        if (!in_array($directory, $directories)) {
                            $directories[] = $directory;
                        }
                    }
                }
            }
        }

        foreach ($directories as $directory) {
            try {
                if (!is_dir($directory)) {
                    $this->mkdir($directory);
                }
                $this->chmod($directory);
            } catch (\Exception $exception) {
                $this->logError($exception->getMessage());
            }
        }

        foreach ($files as $file) {
            try {
                if (!is_file($file)) {
                    $this->touch($file);
                }
                $this->chmod($file, 0666);
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
            }
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
