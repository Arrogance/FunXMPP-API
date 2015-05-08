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

namespace FunXMPP\Core\Exception;

use FunXMPP\Core\Exception\ExceptionInterface;

class CustomException extends \Exception implements ExceptionInterface
{

    /**
     * Exception message
     *
     * @var string
     */
    protected $message = 'Unknown exception';

    /**
     * User-defined exception code
     *
     * @var mixed
     */
    protected $code = 0;

    /**
     * Source filename of exception
     *
     * @var mixed
     */
    protected $file;

    /**
     * Source line of exception
     */
    protected $line;

    /**
     * @param string $message 
     * @param mixed  $code
     */
    public function __construct($message = null, $code = 0)
    {
        if (!$message) {
            throw new $this('Unknown ' . get_class($this));
        }
        
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n"
        . "{$this->getTraceAsString()}";
    }

}