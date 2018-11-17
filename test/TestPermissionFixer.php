<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\PermissionsFixer;

class TestPermissionFixer extends PermissionsFixer
{
    /**
     * @param Filesystem $filesystem
     * @return PermissionsFixer
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;
        return $this;
    }
}
