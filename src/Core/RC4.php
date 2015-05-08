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

class RC4
{

    /**
     * @var mixed
     */
    private $s;

    /**
     * @var mixed
     */
    private $i;

    /**
     * @var mixed
     */
    private $j;

    /**
     * 
     */
    public function __construct($key, $drop)
    {
        $this->s = range(0, 255);
        for ($i = 0, $j = 0; $i < 256; $i++) {
            $k = ord($key{$i % strlen($key)});
            $j = ($j + $k + $this->s[$i]) & 255;
            $this->swap($i, $j);
        }

        $this->i = 0;
        $this->j = 0;
        $this->cipher(range(0, $drop), 0, $drop);
    }

    /**
     * 
     */
    public function cipher($data, $offset, $length)
    {
        $out = $data;
        for ($n = $length; $n > 0; $n--) {
            $this->i = ($this->i + 1) & 0xff;
            $this->j = ($this->j + $this->s[$this->i]) & 0xff;
            $this->swap($this->i, $this->j);
            $d            = ord($data{$offset});
            $out[$offset] = chr($d ^ $this->s[($this->s[$this->i] + $this->s[$this->j]) & 0xff]);
            $offset++;
        }

        return $out;
    }

    /**
     * 
     */
    protected function swap($i, $j)
    {
        $c           = $this->s[$i];
        $this->s[$i] = $this->s[$j];
        $this->s[$j] = $c;
    }

}