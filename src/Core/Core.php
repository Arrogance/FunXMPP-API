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

abstract class Core
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
    protected $getMessageCounter;

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
     * Create an identity string
     *
     * @param  mixed $identity_file IdentityFile (optional).
     * @return string               Correctly formatted identity
     *
     * @throws CustomException      Error when cannot write identity data to file.
     */
    protected function buildIdentity($identity_file = false)
    {
        if ($identity_file === false) {
            $identity_file = sprintf('%sid.%s.dat', Helpers::fileBuildPath($this->getDataPath(), Config::$DATA_FOLDER, ''), $this->phoneNumber);
        }

        if (is_readable($identity_file)) {
            $data = urldecode(file_get_contents($identity_file));
            $length = strlen($data);

            if ($length == 20 || $length == 16) {
                return $data;
            }
        }

        $bytes = strtolower(openssl_random_pseudo_bytes(20));

        if (@file_put_contents($identity_file, urlencode($bytes)) === false) {
            throw new CustomException('Unable to write identity file to ' . $identity_file);
        }

        return $bytes;
    }



    /**
     * Get a decoded JSON response from FunXMPP server
     *
     * @param  string $host  The host URL
     * @param  array  $query A associative array of keys and values to send to server.
     *
     * @return null|object   NULL if the json cannot be decoded or if the encoded data is deeper than the recursion limit
     */
    public function getResponse($host, $query)
    {
        // Build the url.
        $url = $host . '?' . http_build_query($query);

        // Open connection.
        $ch = curl_init();

        // Configure the connection.
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, Config::$FUNXMPP_USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/json'));
        // This makes CURL accept any peer!
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // Get the response.
        $response = curl_exec($ch);

        // Close the connection.
        curl_close($ch);

        return json_decode($response);
    }

    /**
     * @return Core
     */
    public function setChallengeFilename($number)
    {
        $this->challengeFilename = sprintf('%sid.%s.dat', 
            Helpers::fileBuildPath($this->getDataPath(), Config::$DATA_FOLDER, ''), 
            $this->phoneNumber);

        return $this;
    }

    /**
     * 
     */
    public function getChallengeFilename()
    {
        return $this->challengeFilename;
    }

    /**
     * Print a message to the debug console.
     *
     * @param  mixed $debugMsg The debug message.
     * @return bool
     */
    public function debugPrint($debugMsg)
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

    /**
     * Process number/jid and turn it into a JID if necessary
     *
     * @param string $number Number to process
     * @return string
     */
    public function getJID($number)
    {
        if (!stristr($number, '@')) {
            //check if group message
            if (stristr($number, '-')) {
                //to group
                $number .= "@" . Config::$FUNXMPP_GROUP_SERVER;
            } else {
                //to normal user
                $number .= "@" . Config::$FUNXMPP_SERVER;
            }
        }

        return $number;
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
        $this->getMessageCounter = $messageCounter;

        return $this;
    }

    /**
     * 
     */
    public function getMessageCounter()
    {
        return $this->getMessageCounter;
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

}