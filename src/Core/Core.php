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

class Core
{

    /**
     * 
     */
    protected $debug;

    /**
     * Print a message to the debug console.
     *
     * @param  mixed $debugMsg The debug message.
     * @return bool
     */
    protected function debugPrint($debugMsg)
    {
        if ($this->debug) {
            if (is_array($debugMsg) || is_object($debugMsg)) {
                print_r($debugMsg);
            }
            else {
                echo $debugMsg;
            }
            return true;
        }

        return false;
    }

}