<?php

namespace Solid_Backups\Strauss\Aws\ClientSideMonitoring;

use Solid_Backups\Strauss\Aws\CommandInterface;
use Solid_Backups\Strauss\Aws\Exception\AwsException;
use Solid_Backups\Strauss\Aws\ResultInterface;
use Solid_Backups\Strauss\GuzzleHttp\Psr7\Request;
use Solid_Backups\Strauss\Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
interface MonitoringMiddlewareInterface
{

    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param RequestInterface $request
     * @return array
     */
    public static function getRequestData(RequestInterface $request);


    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param ResultInterface|AwsException|\Exception $klass
     * @return array
     */
    public static function getResponseData($klass);

    public function __invoke(CommandInterface $cmd, RequestInterface $request);
}