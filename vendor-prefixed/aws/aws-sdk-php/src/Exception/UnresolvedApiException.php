<?php
namespace Solid_Backups\Strauss\Aws\Exception;

use Solid_Backups\Strauss\Aws\HasMonitoringEventsTrait;
use Solid_Backups\Strauss\Aws\MonitoringEventsInterface;

class UnresolvedApiException extends \RuntimeException implements
    MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
