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
use FunXMPP\Core\Config;
use FunXMPP\Core\ProtocolNode;

use FunXMPP\Util\Helpers;

class Message
{

	/**
     * @var Core
     */
    protected $instance;

    /**
     * 
     */
    protected $connection;

    public function __construct(Core &$instance, Connection &$connection)
    {
        $this->instance = $instance;
        $this->connection = $connection;
    }

    /**
     * Send a text message to the user/group.
     *
     * @param string $to  The recipient.
     * @param string $txt The text message.
     * @param $id
     *
     * @return string     Message ID.
     */
    public function sendMessage($to, $txt, $id = null)
    {
        $bodyNode = new ProtocolNode("body", null, null, $txt);
        $id = $this->connection->sendMessageNode($to, $bodyNode, $id);
        $this->connection->waitForServer($id);

        if ($this->instance->getMessageStore() !== null) {
            $this->messageStore->saveMessage($this->instannce->getPhoneNumber(), $to, $txt, $id, time());
        }

        return $id;
    }

    /**
     * Send the composing message status. When typing a message.
     *
     * @param string $to The recipient to send status to.
     */
    public function sendMessageComposing($to)
    {
        $this->connection->sendChatState($to, "composing");
    }

}
