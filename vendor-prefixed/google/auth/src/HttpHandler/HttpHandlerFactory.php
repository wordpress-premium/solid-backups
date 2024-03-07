<?php
/**
 * Copyright 2015 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Solid_Backups\Strauss\Google\Auth\HttpHandler;

use Solid_Backups\Strauss\GuzzleHttp\BodySummarizer;
use Solid_Backups\Strauss\GuzzleHttp\Client;
use Solid_Backups\Strauss\GuzzleHttp\ClientInterface;
use Solid_Backups\Strauss\GuzzleHttp\HandlerStack;
use Solid_Backups\Strauss\GuzzleHttp\Middleware;

class HttpHandlerFactory
{
    /**
     * Builds out a default http handler for the installed version of guzzle.
     *
     * @param ClientInterface $client
     * @return Guzzle5HttpHandler|Guzzle6HttpHandler|Guzzle7HttpHandler
     * @throws \Exception
     */
    public static function build(ClientInterface $client = null)
    {
        if (is_null($client)) {
            $stack = null;
            if (class_exists(BodySummarizer::class)) {
                // double the # of characters before truncation by default
                $bodySummarizer = new BodySummarizer(240);
                $stack = HandlerStack::create();
                $stack->remove('http_errors');
                $stack->unshift(Middleware::httpErrors($bodySummarizer), 'http_errors');
            }
            $client = new Client(['handler' => $stack]);
        }

        $version = null;
        if (defined('Solid_Backups\Strauss\GuzzleHttp\ClientInterface::MAJOR_VERSION')) {
            $version = ClientInterface::MAJOR_VERSION;
        } elseif (defined('Solid_Backups\Strauss\GuzzleHttp\ClientInterface::VERSION')) {
            $version = (int) substr(ClientInterface::VERSION, 0, 1);
        }

        switch ($version) {
            case 5:
                return new Guzzle5HttpHandler($client);
            case 6:
                return new Guzzle6HttpHandler($client);
            case 7:
                return new Guzzle7HttpHandler($client);
            default:
                throw new \Exception('Version not supported');
        }
    }
}
