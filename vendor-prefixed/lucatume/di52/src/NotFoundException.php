<?php
/**
 * An exception used to signal no binding was found for container ID.
 *
 * @package lucatume\DI52
 */

namespace Solid_Backups\Strauss\lucatume\DI52;

use Solid_Backups\Strauss\Psr\Container\NotFoundExceptionInterface;

/**
 * Class NotFoundException
 *
 * @package lucatume\DI52
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
