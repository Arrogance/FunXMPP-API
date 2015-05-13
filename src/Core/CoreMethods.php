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

use FunXMPP\Events\EventManager;

use FunXMPP\Core\Config;
use FunXMPP\Core\BinTreeNodeReader;
use FunXMPP\Core\BinTreeNodeWriter;
use FunXMPP\Core\Exception\CustomException;
use FunXMPP\Core\MessageStore\SqliteMessageStore;

use FunXMPP\Util\Helpers;

abstract class CoreMethods implements CoreInterface
{

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var BinTreeNodeWriter
     */
    protected $writer;

    /**
     * @var BinTreeNodeReader
     */
    protected $reader;

    /**
     * @var Account
     */
    protected $account;

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @var mixed
     */
    protected $phoneNumber;

    /**
     * @var mixed
     */
    protected $dataPath;

    /**
     * @var mixed
     */
    protected $identityFile;

    /**
     * @var mixed
     */
    protected $identity;

    /**
     * 
     */
    protected $password;

    /**
     * 
     */
    protected $name;

    /**
     * 
     */
    protected $challengeFilename;

    /**
     * 
     */
    protected $challengeData; 

    /**
     * @var mixed
     */
    protected $socket;

    /**
     * @var mixed
     */
    protected $loginStatus;

    /**
     * @var mixed
     */
    protected $loginTime;

    /**
     * 
     */
    protected $messageStore;

    /**
     * 
     */
    protected $newMessageBind = false;

    /**
     * @var integer
     */
    protected $messageCounter = 1;

    /**
     * @var array
     */
    protected $messageQueue = array();

    /**
     * 
     */
    protected $mediaFileInfo = array();

    /**
     * 
     */
    protected $mediaQueue = array();

    /**
     * 
     */
    protected $outQueue;

    /**
     * 
     */
    protected $lastId;

    /**
     * Default class constructor.
     *
     * @param string $number
     *   The user phone number including the country code without '+' or '00'.
     * @param string $nickname
     *   The user name.
     * @param $debug
     *   Debug on or off, false by default.
     * @param mixed $identityFile
     *  Path to identity file, overrides default path
     */
    public function __construct($number, $nickname, $debug = false, $identityFile = false)
    {
        Config::generateConfig();
        if (!empty($this->dataPath)) {
            Config::DATA_PATH($this->dataPath);
        }
        Config::updateConfig();

        if (!Config::$DATA_PATH) {
            throw new CustomException('You need to set a DATA_PATH');
        }

        $this->writer = new BinTreeNodeWriter();
        $this->reader = new BinTreeNodeReader();
        $this->debug = $debug;

        if (!$number) {
            throw new CustomException('You need to set a phone number');
        }

        $this->phoneNumber = $number;

        $this->setChallengeFilename($number);
        $this->identity = $this->buildIdentity($identityFile);

        $this->name         = $nickname;
        $this->loginStatus  = Config::$DISCONNECTED_STATUS;
        $this->eventManager = new EventManager();

        return $this;
    }

    /**
     * @return EventManager
     */
    public function eventManager()
    {
        return $this->eventManager;
    }

    /**
     * @return Connection
     */
    public function connection()
    {
        return $this->connection;
    }

    public function setConnection(&$connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @param mixed $phoneNumber
     *
     * @return Core
     */
    public function setPhoneNumber($phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * @return $phoneNumber
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * @param mixed $identity
     *
     * @return Core
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;

        return $this;
    }

    /**
     * @return $identity
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * 
     */
    public function setDataPath($dataPath)
    {
        $this->dataPath = $dataPath;

        return $this;
    }

    /**
     * 
     */
    public function getDataPath()
    {
        return $this->dataPath;
    }

    /**
     * 
     */
    public function setLoginTime($loginTime)
    {
        $this->loginTime = $loginTime;

        return $this;
    }

    /**
     * 
     */
    public function getLoginTime()
    {
        return $this->loginTime;
    }

    /**
     * 
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;

        return $this;
    }

    /**
     * 
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * 
     */
    public function getWriter()
    {
        return $this->writer;
    }

    /**
     * 
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * 
     */
    public function setMessageStore(SqliteMessageStore $messageStore)
    {
        $this->messageStore = $messageStore;

        return $this;
    }

    /**
     * 
     */
    public function getMessageStore()
    {
        return $this->messageStore;
    }

    /**
     * Sets the bind of the new message.
     *
     * @param $bind
     */
    public function setNewMessageBind($bind)
    {
        $this->newMessageBind = $bind;

        return $this;
    }

    /**
     * 
     */
    public function getNewMessageBind()
    {
        return $this->newMessageBind;
    }

    /**
     * 
     */
    public function setMessageCounter($messageCounter)
    {
        $this->messageCounter = $messageCounter;

        return $this;
    }

    /**
     * 
     */
    public function getMessageCounter()
    {
        return $this->messageCounter;
    }

    /**
     * 
     */
    public function sumMessageCounter()
    {
        $this->messageCounter = $this->messageCounter+1;

        return $this;
    }

    /**
     * 
     */
    public function setMediaFileInfo($mediaFileInfo)
    {
        $this->mediaFileInfo = $mediaFileInfo;

        return $this;
    }

    /**
     * 
     */
    public function getMediaFileInfo()
    {
        return $this->mediaFileInfo;
    }

    /**
     * 
     */
    public function setMediaQueue($mediaQueue)
    {
        $this->mediaQueue = $mediaQueue;

        return $this;
    }

    /**
     * 
     */
    public function getMediaQueue()
    {
        return $this->mediaQueue;
    }

    /**
     *
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $password;
    }

    /**
     *
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     *
     */
    public function setLoginStatus($loginStatus)
    {
        $this->loginStatus = $loginStatus;

        return $loginStatus;
    }

    /**
     *
     */
    public function getLoginStatus()
    {
        return $this->loginStatus;
    }

    /**
     *
     */
    public function setChallengeData($challengeData)
    {
        $this->challengeData = $challengeData;

        return $challengeData;
    }

    /**
     *
     */
    public function getChallengeData()
    {
        return $this->challengeData;
    }

    /**
     *
     */
    public function setName($name)
    {
        $this->name = $name;

        return $name;
    }

    /**
     *
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 
     */
    public function setMessageQueue($messageQueue)
    {
        $this->messageQueue = $messageQueue;

        return $this;
    }

    /**
     *
     */
    public function getMessageQueue()
    {
        return $this->messageQueue;
    }

    /**
     * 
     */
    public function setOutQueue($outQueue)
    {
        $this->outQueue = $outQueue;

        return $this;
    }

    /**
     *
     */
    public function getOutQueue()
    {
        return $this->outQueue;
    }

    /**
     * 
     */
    public function setLastId($lastId)
    {
        $this->lastId = $lastId;

        return $this;
    }

    /**
     *
     */
    public function getLastId()
    {
        return $this->lastId;
    }

}