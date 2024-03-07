<?php

/**
 * CertificateSerialNumber
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace Solid_Backups\Strauss\phpseclib3\File\ASN1\Maps;

use Solid_Backups\Strauss\phpseclib3\File\ASN1;

/**
 * CertificateSerialNumber
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class CertificateSerialNumber
{
    const MAP = ['type' => ASN1::TYPE_INTEGER];
}
