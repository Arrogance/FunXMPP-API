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

namespace FunXMPP;

use FunXMPP\Events\EventManager;

use FunXMPP\Core\Core;
use FunXMPP\Core\Account;
use FunXMPP\Core\Server;

class FunXMPP extends Core
{

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var Account
     */
    protected $account;

    /**
     * @var Server
     */
    protected $server;

    public function __construct()
    {
        $this->eventManager = new EventManager();
        $this->account = new Account($this);
        $this->server = new Server($this);
    }

    /**
     * @return EventManager
     */
    public function eventManager()
    {
        return $this->eventManager;
    }

    /**
     * @return Account
     */
    public function account()
    {
        return $this->account;
    }

    /**
     * @return Server
     */
    public function server()
    {
        return $this->server;
    }

}