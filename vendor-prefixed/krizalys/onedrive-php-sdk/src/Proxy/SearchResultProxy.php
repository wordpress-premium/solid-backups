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
use Solid_Backups\Strauss\Microsoft\Graph\Model\SearchResult;

/**
 * A proxy to a \Microsoft\Graph\Model\SearchResult instance.
 *
 * @since 2.0.0
 *
 * @api
 *
 * @link https://github.com/microsoftgraph/msgraph-sdk-php/blob/dev/src/Model/SearchResult.php
 */
class SearchResultProxy extends EntityProxy
{
    /**
     * Constructor.
     *
     * @param \Solid_Backups\Strauss\Microsoft\Graph\Graph\Graph $graph
     *        The Microsoft Graph.
     * @param \Solid_Backups\Strauss\Microsoft\Graph\Model\SearchResult $searchResult
     *        The search result.
     *
     * @since 2.0.0
     */
    public function __construct(Graph $graph, SearchResult $searchResult)
    {
        parent::__construct($graph, $searchResult);
    }
}
