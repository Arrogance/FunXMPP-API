<?php
/**
 * Copyright 2015 Arrogance
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace FunXMPP\Core;

use FunXMPP\Core\Exception\CustomException;
use FunXMPP\Core\RC4;
use FunXMPP\Util\Helpers;

class KeyStream
{

    /**
     * 
     */
    const DROP = 768;

    /**
     * 
     */
    public static $AuthMethod = "WAUTH-2";

    /**
     * 
     */
    private $rc4;
    
    /**
     * 
     */
    private $seq;
    
    /**
     * 
     */
    private $macKey;

    /**
     * 
     */
    public function __construct($key, $macKey)
    {
        $this->rc4    = new RC4($key, self::DROP);
        $this->macKey = $macKey;
    }

    /**
     * 
     */
    public static function GenerateKeys($password, $nonce)
    {
        $array  = array(
            "key", //placeholders
            "key",
            "key",
            "key"
        );
        $array2 = array(1, 2, 3, 4);
        $nonce .= '0';
        for ($j = 0; $j < count($array); $j++) {
            $nonce[(strlen($nonce) - 1)] = chr($array2[$j]);
            $foo                         = Helpers::wa_pbkdf2("sha1", $password, $nonce, 2, 20, true);
            $array[$j]                   = $foo;
        }
        return $array;
    }

    /**
     * 
     */
    public function DecodeMessage($buffer, $macOffset, $offset, $length)
    {
        $mac = $this->computeMac($buffer, $offset, $length);
        //validate mac
        for ($i = 0; $i < 4; $i++) {
            $foo = ord($buffer[$macOffset + $i]);
            $bar = ord($mac[$i]);

            if ($foo !== $bar) {
                throw new CustomException("MAC mismatch: $foo != $bar");
            }
        }
        return $this->rc4->cipher($buffer, $offset, $length);
    }

    /**
     * 
     */
    public function EncodeMessage($buffer, $macOffset, $offset, $length)
    {
        $data = $this->rc4->cipher($buffer, $offset, $length);
        $mac  = $this->computeMac($data, $offset, $length);
        return substr($data, 0, $macOffset) . substr($mac, 0, 4) . substr($data, $macOffset + 4);
    }

    /**
     * 
     */
    private function computeMac($buffer, $offset, $length)
    {
        $hmac = hash_init("sha1", HASH_HMAC, $this->macKey);
        hash_update($hmac, substr($buffer, $offset, $length));
        $array = chr($this->seq >> 24)
            . chr($this->seq >> 16)
            . chr($this->seq >> 8)
            . chr($this->seq);
        hash_update($hmac, $array);
        $this->seq++;
        return hash_final($hmac, true);
    }

}