<?php

namespace Solid_Backups\Strauss\Aws;

use Solid_Backups\Strauss\Psr\Http\Message\ResponseInterface;

interface ResponseContainerInterface
{
    /**
     * Get the received HTTP response if any.
     *
     * @return ResponseInterface|null
     */
    public function getResponse();
}