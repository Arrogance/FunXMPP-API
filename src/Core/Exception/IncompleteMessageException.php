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

use FunXMPP\Core\Exception\CustomException;

class IncompleteMessageException extends CustomException
{
    
    /**
     * @var mixed
     */
    private $input;

    /**
     * @param string $message 
     * @param mixed  $code 
     */
    public function __construct($message = null, $code = 0)
    {
        parent::__construct($message, $code);
    }

    /**
     * Set input
     *
     * @param mixed $input 
     * @return IncompleteMessageException
     */
    public function setInput($input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Get input
     *
     * @return mixed
     */
    public function getInput()
    {
        return $this->input;
    }

}