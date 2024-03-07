<?php
/**
 * The API provided by each builder.
 *
 * @package lucatume\DI52
 */

namespace Solid_Backups\Strauss\lucatume\DI52\Builders;

/**
 * Interface BuilderInterface
 *
 * @package Solid_Backups\Strauss\lucatume\DI52\Builders
 */
interface BuilderInterface
{
    /**
     * Builds and returns the implementation handled by the builder.
     *
     * @return mixed The implementation provided by the builder.
     */
    public function build();
}
