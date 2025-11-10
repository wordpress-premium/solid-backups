<?php

/**
 * This file is part of Krizalys' OneDrive SDK for PHP.
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @author    Christophe Vidal
 * @copyright 2008-2021 Christophe Vidal (http://www.krizalys.com)
 * @license   https://opensource.org/licenses/BSD-3-Clause 3-Clause BSD License
 * @link      https://github.com/krizalys/onedrive-php-sdk
 */

namespace Solid_Backups\Strauss\Krizalys\Onedrive\Proxy;

use Solid_Backups\Strauss\Microsoft\Graph\Graph;
use Solid_Backups\Strauss\Microsoft\Graph\Model\Package;

/**
 * A proxy to a \Microsoft\Graph\Model\Package instance.
 *
 * @property-read string type
 *                The type.
 *
 * @since 2.0.0
 *
 * @api
 *
 * @link https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/src/Model/Package.php
 */
class PackageProxy extends EntityProxy
{
    /**
     * Constructor.
     *
     * @param \Solid_Backups\Strauss\Microsoft\Graph\Graph $graph
     *        The Microsoft Graph.
     * @param \Solid_Backups\Strauss\Microsoft\Graph\Model\Package $package
     *        The package.
     *
     * @since 2.0.0
     */
    public function __construct(Graph $graph, Package $package)
    {
        parent::__construct($graph, $package);
    }

    /**
     * Getter.
     *
     * @param string $name
     *        The name.
     *
     * @return mixed
     *         The value.
     *
     * @since 2.5.0
     */
    public function __get($name)
    {
        $package = $this->entity;

        switch ($name) {
            case 'type':
                return $package->getType();

            default:
                return parent::__get($name);
        }
    }
}
