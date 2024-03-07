<?php

/**
 * DH Private Key
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */

namespace Solid_Backups\Strauss\phpseclib3\Crypt\DH;

use Solid_Backups\Strauss\phpseclib3\Crypt\Common;
use Solid_Backups\Strauss\phpseclib3\Crypt\DH;

/**
 * DH Private Key
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
final class PrivateKey extends DH
{
    use Common\Traits\PasswordProtected;

    /**
     * Private Key
     *
     * @var \Solid_Backups\Strauss\phpseclib3\Math\BigInteger
     */
    protected $privateKey;

    /**
     * Public Key
     *
     * @var \Solid_Backups\Strauss\phpseclib3\Math\BigInteger
     */
    protected $publicKey;

    /**
     * Returns the public key
     *
     * @return DH\PublicKey
     */
    public function getPublicKey()
    {
        $type = self::validatePlugin('Keys', 'PKCS8', 'savePublicKey');

        if (!isset($this->publicKey)) {
            $this->publicKey = $this->base->powMod($this->privateKey, $this->prime);
        }

        $key = $type::savePublicKey($this->prime, $this->base, $this->publicKey);

        return DH::loadFormat('PKCS8', $key);
    }

    /**
     * Returns the private key
     *
     * @param string $type
     * @param array $options optional
     * @return string
     */
    public function toString($type, array $options = [])
    {
        $type = self::validatePlugin('Keys', $type, 'savePrivateKey');

        if (!isset($this->publicKey)) {
            $this->publicKey = $this->base->powMod($this->privateKey, $this->prime);
        }

        return $type::savePrivateKey($this->prime, $this->base, $this->privateKey, $this->publicKey, $this->password, $options);
    }
}
