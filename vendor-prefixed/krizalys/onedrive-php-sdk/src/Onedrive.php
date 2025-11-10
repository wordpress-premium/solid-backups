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

namespace Solid_Backups\Strauss\Krizalys\Onedrive;

use Solid_Backups\Strauss\GuzzleHttp\Client as GuzzleHttpClient;
use Solid_Backups\Strauss\Krizalys\Onedrive\Definition\OperationDefinition;
use Solid_Backups\Strauss\Krizalys\Onedrive\Definition\Parameter\BodyParameterDefinition;
use Solid_Backups\Strauss\Krizalys\Onedrive\Definition\Parameter\QueryStringParameterDefinition;
use Solid_Backups\Strauss\Krizalys\Onedrive\Definition\ResourceDefinition;
use Solid_Backups\Strauss\Krizalys\Onedrive\Definition\ServiceDefinition;
use Solid_Backups\Strauss\Krizalys\Onedrive\Parameter\Injector\FlatInjector;
use Solid_Backups\Strauss\Krizalys\Onedrive\Parameter\Injector\HierarchicalInjector;
use Solid_Backups\Strauss\Krizalys\Onedrive\Parameter\ParameterBuilder;
use Solid_Backups\Strauss\Krizalys\Onedrive\Parameter\ParameterDefinitionCollection;
use Solid_Backups\Strauss\Krizalys\Onedrive\Serializer\OrderBySerializer;
use Solid_Backups\Strauss\Krizalys\Onedrive\Serializer\ScalarSerializer;
use Solid_Backups\Strauss\Microsoft\Graph\Graph;

/**
 * A facade exposing main OneDrive functionality while hiding implementation
 * details.
 *
 * Currently, this class exposes only one function, static, with a limited
 * number of parameters. This allows users to create
 * {@see Client Client} instances with minimal knowledge of API internals.
 *
 * Getting started with a OneDrive client is as trivial as:
 *
 * ```php
 * $client = Onedrive::client('<YOUR_CLIENT_ID>');
 * ```
 *
 * @since 2.3.0
 *
 * @api
 */
class Onedrive
{
    /**
     * Creates a Client instance and its dependencies.
     *
     * @param string $clientId
     *        The client ID.
     * @param mixed[string] $options
     *        The options to use while creating this object. Supported options:
     *          - `'state'` *(object)*: the OneDrive client state, as returned
     *            by {@see Client::getState() getState()}. Default: `[]`.
     *
     * @return \Solid_Backups\Strauss\Krizalys\Onedrive\Client
     *         The client created.
     *
     * @api
     */
    public static function client($clientId, array $options = [])
    {
        $graph             = new Graph();
        $httpClient        = new GuzzleHttpClient();
        $serviceDefinition = self::buildServiceDefinition();

        return new Client(
            $clientId,
            $graph,
            $httpClient,
            $serviceDefinition,
            $options
        );
    }

    /**
     * Builds a service definition.
     *
     * @return \Solid_Backups\Strauss\Krizalys\Onedrive\Definition\ServiceDefinitionInterface
     *         The service definition.
     *
     * @since 2.5.0
     */
    public static function buildServiceDefinition()
    {
        $orderBySerializer = new OrderBySerializer();
        $scalarSerializer  = new ScalarSerializer();
        $parameterBuilder  = new ParameterBuilder();

        return new ServiceDefinition([
            'driveItem' => new ResourceDefinition([], [
                'children' => new ResourceDefinition([
                    'get' => new OperationDefinition(
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, [
                            'top' => new QueryStringParameterDefinition(
                                new FlatInjector('$top'),
                                $scalarSerializer
                            ),
                            'orderBy' => new QueryStringParameterDefinition(
                                new FlatInjector('$orderby'),
                                $orderBySerializer
                            ),
                        ])
                    ),
                    'post' => new OperationDefinition(
                        new ParameterDefinitionCollection($parameterBuilder, [
                            'conflictBehavior' => new BodyParameterDefinition(
                                new HierarchicalInjector(['@microsoft.graph.conflictBehavior']),
                                $scalarSerializer
                            ),
                            'description' => new BodyParameterDefinition(
                                new HierarchicalInjector(['description']),
                                $scalarSerializer
                            ),
                        ]),
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, [])
                    ),
                ], []),
                'content' => new ResourceDefinition([
                    'put' => new OperationDefinition(
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, [
                            'conflictBehavior' => new QueryStringParameterDefinition(
                                new FlatInjector('@microsoft.graph.conflictBehavior'),
                                $scalarSerializer
                            ),
                        ])
                    ),
                ], []),
                'createUploadSession' => new ResourceDefinition([
                    'post' => new OperationDefinition(
                        new ParameterDefinitionCollection($parameterBuilder, [
                            'conflictBehavior' => new BodyParameterDefinition(
                                new HierarchicalInjector(['item', '@microsoft.graph.conflictBehavior']),
                                $scalarSerializer
                            ),
                            'description' => new BodyParameterDefinition(
                                new HierarchicalInjector(['item', 'description']),
                                $scalarSerializer
                            ),
                        ]),
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, [])
                    ),
                ], []),
                'invite' => new ResourceDefinition([
                    'post' => new OperationDefinition(
                        new ParameterDefinitionCollection($parameterBuilder, [
                            'message' => new BodyParameterDefinition(
                                new HierarchicalInjector(['message']),
                                $scalarSerializer
                            ),
                            'requireSignIn' => new BodyParameterDefinition(
                                new HierarchicalInjector(['requireSignIn']),
                                $scalarSerializer
                            ),
                            'sendInvitation' => new BodyParameterDefinition(
                                new HierarchicalInjector(['sendInvitation']),
                                $scalarSerializer
                            ),
                        ]),
                        new ParameterDefinitionCollection($parameterBuilder, []),
                        new ParameterDefinitionCollection($parameterBuilder, [])
                    ),
                ], []),
            ]),
        ]);
    }
}
