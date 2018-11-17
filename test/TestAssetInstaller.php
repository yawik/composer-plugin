<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\AssetsInstaller;

class TestAssetInstaller extends AssetsInstaller
{
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }
}
