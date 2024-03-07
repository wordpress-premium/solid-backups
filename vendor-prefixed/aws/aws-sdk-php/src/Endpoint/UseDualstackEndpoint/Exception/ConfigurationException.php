<?php
namespace Solid_Backups\Strauss\Aws\Endpoint\UseDualstackEndpoint\Exception;

use Solid_Backups\Strauss\Aws\HasMonitoringEventsTrait;
use Solid_Backups\Strauss\Aws\MonitoringEventsInterface;

/**
 * Represents an error interacting with configuration for useDualstackRegion
 */
class ConfigurationException extends \RuntimeException implements
    MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
