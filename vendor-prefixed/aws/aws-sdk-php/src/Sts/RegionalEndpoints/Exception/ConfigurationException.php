<?php
namespace Solid_Backups\Strauss\Aws\Sts\RegionalEndpoints\Exception;

use Solid_Backups\Strauss\Aws\HasMonitoringEventsTrait;
use Solid_Backups\Strauss\Aws\MonitoringEventsInterface;

/**
 * Represents an error interacting with configuration for sts regional endpoints
 */
class ConfigurationException extends \RuntimeException implements
    MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
