<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer;

interface AssetProviderInterface
{
    /**
     * @return string
     */
    public function getPublicDir();
}
