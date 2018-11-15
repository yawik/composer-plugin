<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer;

use Core\Application;
use Core\Options\ModuleOptions as CoreOptions;
use Symfony\Component\Filesystem\Filesystem;

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
    private $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param Filesystem $filesystem
     * @return PermissionsFixer
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;
        return $this;
    }

    /**
     *
     */
    public function fix()
    {
        /* @var CoreOptions $options */
        $app        = Application::init();
        $options    = $app->getServiceManager()->get('Core/Options');

        $logDir     = $options->getLogDir();
        $cacheDir   = $options->getCacheDir();
        $configDir  = realpath(Application::getConfigDir());

        $dirs = [
            $configDir.'/autoload',
            $cacheDir,
            $logDir,
            $logDir.'/tracy',
        ];
        foreach ($dirs as $dir) {
            try {
                if (!is_dir($dir)) {
                    $this->mkdir($dir);
                }
                $this->chmod($dir);
            } catch (\Exception $exception) {
                $this->logError($exception->getMessage());
            }
        }

        if (!is_file($logFile = $logDir.'/yawik.log')) {
            touch($logFile);
        }
        $this->chmod($logFile, 0666);
    }

    private function chmod($dir, $mode = 0777)
    {
        if (is_dir($dir) || is_file($dir)) {
            $this->filesystem->chmod($dir, $mode);
            $this->log(sprintf(
                '<info>chmod: <comment>%s</comment> with %s</info>',
                $dir,
                decoct(fileperms($dir) & 0777)
            ));
        }
    }

    private function mkdir($dir)
    {
        $this->filesystem->mkdir($dir, 0777);
        $this->log(sprintf('<info>mkdir: </info><comment>%s</comment>', $dir));
    }
}
