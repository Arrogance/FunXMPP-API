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
use FunXMPP\Core\Exception\LoginFailureException;
use FunXMPP\Core\Exception\ConnectionException;

use FunXMPP\Core\Config;
use FunXMPP\Core\KeyStream;
use FunXMPP\Core\SyncResult;
use FunXMPP\Core\ProtocolNode;
use FunXMPP\Core\MediaUploader;

use FunXMPP\Util\Helpers;

class Connection
{

    /**
     * @var Core
     */
    protected $instance;

    /**
     * 
     */
    protected $inputKey;

    /**
     * 
     */
    protected $outputKey;

    /**
     * 
     */
    protected $serverReceivedId = false;

    public function __construct(Core &$instance)
    {
        $this->instance = $instance;
        $instance->setConnection($this);
    }

    /**
     * Connect (create a socket) to the FunXMPP network.
     *
     * @return bool
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return true;
        }

        /* Create a TCP/IP socket. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket !== false) {
            $result = socket_connect($socket, "e" . rand(1, 16) . Config::$FUNXMPP_CONNECT_SERVER, Config::$PORT);
            if ($result === false) {
                $socket = false;
            }
        }

        $this->instance->setSocket($socket);

        if ($socket !== false) {
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => Config::$TIMEOUT_SEC, 'usec' => Config::$TIMEOUT_USEC));
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => Config::$TIMEOUT_SEC, 'usec' => Config::$TIMEOUT_USEC));

            $this->instance->setSocket($socket);
            $this->instance->eventManager()->fire("onConnect",
                array(
                    $this->instance->getPhoneNumber(),
                    $this->instance->getSocket()
                )
            );
            return true;
        } else {
            $this->instance->eventManager()->fire("onConnectError",
                array(
                    $this->instance->getPhoneNumber(),
                    $this->instance->getSocket()
                )
            );
            return false;
        }
    }

    /**
     * Login to the FunXMPP server with your password
     *
     * If you already know your password you can log into the Whatsapp server
     * using this method.
     *
     * @param  string  $password         Your funxmpp network password. You must already know this!
     */
    public function loginWithPassword($password)
    {
        $this->instance->setPassword($password);
        if (is_readable($this->instance->getChallengeFilename())) {
            $challengeData = file_get_contents($this->instance->getChallengeFilename());
            if ($challengeData) {
                $this->instance->setChallengeFilename($challengeData);
            }
        }
        $this->doLogin();
    }

    /**
     * Send the nodes to the FunXMPP server to log in.
     *
     * @throws CustomException
     */
    protected function doLogin()
    {
        if ($this->isLoggedIn()) {
            return true;
        }

        $this->instance->getWriter()->resetKey();
        $this->instance->getReader()->resetKey();

        $resource = Config::$FUNXMPP_DEVICE . '-' . Config::$FUNXMPP_VER . '-' . Config::$PORT;
        $data = $this->instance->getWriter()->StartStream(Config::$FUNXMPP_SERVER, $resource);
        $feat = $this->createFeaturesNode();
        $auth = $this->createAuthNode();

        $this->sendData($data);

        $this->sendNode($feat);
        $this->sendNode($auth);

        $this->pollMessage();
        $this->pollMessage();
        $this->pollMessage();

        if ($this->instance->getChallengeData() != null) {
            $data = $this->createAuthResponseNode();
            $this->sendNode($data);
            $this->instance->getReader()->setKey($this->inputKey);
            $this->instance->getWriter()->setKey($this->outputKey);
            $this->pollMessage();
        }

        if ($this->instance->getLoginStatus() === Config::$DISCONNECTED_STATUS) {
            throw new LoginFailureException();
        }

        $this->instance->eventManager()->fire("onLogin",
            array(
                $this->instance->getPhoneNumber()
            ));
        $this->sendAvailableForChat();
        $this->instance->setLoginTime(time());

        return $this;
    }

    /**
     * Send a ping to the server.
     */
    public function sendPing()
    {
        $msgId = $this->createMsgId();
        $pingNode = new ProtocolNode("ping", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:p",
                "type" => "get",
                "to" => Config::$FUNXMPP_SERVER
            ), array($pingNode), null);

        $this->sendNode($node);
    }

    /**
     * Add the auth response to protocoltreenode.
     *
     * @return ProtocolNode Returns a response node.
     */
    protected function createAuthResponseNode()
    {
        return new ProtocolNode("response", null, null, $this->authenticate());
    }

    /**
     * Authenticate with the WhatsApp Server.
     *
     * @return string Returns binary string
     */
    protected function authenticate()
    {
        $keys = KeyStream::GenerateKeys(base64_decode($this->instance->getPassword()), $this->instance->getChallengeData());
        $this->inputKey = new KeyStream($keys[2], $keys[3]);
        $this->outputKey = new KeyStream($keys[0], $keys[1]);
        $array = "\0\0\0\0" . $this->instance->getPhoneNumber() . $this->instance->getChallengeData();
        $response = $this->outputKey->EncodeMessage($array, 0, 4, strlen($array) - 4);
        return $response;
    }

    public function sendAvailableForChat($nickname = null)
    {
        $presence = array();
        if ($nickname) {
            //update nickname
            $this->instance->setName($nickname);
        }

        $presence['name'] = $this->instance->getName();
        $node = new ProtocolNode("presence", $presence, null, "");
        $this->sendNode($node);
    }

    /**
     * Send data to the WhatsApp server.
     * @param string $data
     *
     * @throws Exception
     */
    protected function sendData($data)
    {
        if ($this->instance->getSocket() != null) {
            if (socket_write($this->instance->getSocket(), $data, strlen($data)) === false) {
                $this->disconnect();
                throw new ConnectionException('Connection Closed!');
            }
        }
    }

    /**
     * Send node to the WhatsApp server.
     * @param ProtocolNode $node
     * @param bool         $encrypt
     */
    protected function sendNode($node, $encrypt = true)
    {
        $this->instance->debugPrint($node->nodeString("tx  ") . "\n");
        $this->sendData($this->instance->getWriter()->write($node, $encrypt));
    }

    /**
     * Do we have an active socket connection to FunXMPP network?
     *
     * @return bool
     */
    public function isConnected()
    {
        return ($this->instance->getSocket() !== null);
    }

    /**
     * Have we an active connection with WhatsAPP AND a valid login already?
     *
     * @return bool
     */
    protected function isLoggedIn(){
        //If you aren't connected you can't be logged in! ($this->isConnected())
        //We are connected - but are we logged in? (the rest)
        return ($this->isConnected() && !empty($this->instance->getLoginStatus()) && $this->instance->getLoginStatus() === Config::$CONNECTED_STATUS);
    }

    /**
     * Disconnect from the WhatsApp network.
     */
    public function disconnect()
    {
        if (is_resource($this->socket)) {
            @socket_shutdown($this->socket, 2);
            @socket_close($this->socket);
            $this->socket = null;
            $this->loginStatus  = Config::$DISCONNECTED_STATUS;
            $this->eventManager()->fire("onDisconnect",
                array(
                    $this->phoneNumber,
                    $this->socket
                )
            );
        }
    }

    /**
     * Add the authentication nodes.
     *
     * @return ProtocolNode Returns an authentication node.
     */
    protected function createAuthNode()
    {
        $data = $this->createAuthBlob();
        $node = new ProtocolNode("auth", array(
            'mechanism' => 'WAUTH-2',
            'user'      => $this->instance->getPhoneNumber()
        ), null, $data);

        return $node;
    }

    protected function createAuthBlob()
    {
        if ($this->instance->getChallengeFilename()) {
            $key = Helpers::wa_pbkdf2('sha1', base64_decode($this->instance->getPassword()), $this->instance->getChallengeFilename(), 16, 20, true);
            $this->inputKey = new KeyStream($key[2], $key[3]);
            $this->outputKey = new KeyStream($key[0], $key[1]);
            $this->instance->getReader()->setKey($this->inputKey);

            $array = "\0\0\0\0" . $this->instance->getPhoneNumber() . $this->instance->getChallengeFilename() . time();
            $this->challengeData = null;
            return $this->outputKey->EncodeMessage($array, 0, strlen($array), false);
        }
        return null;
    }

    /**
     * Add stream features.
     *
     * @return ProtocolNode Return itself.
     */
    protected function createFeaturesNode()
    {
        $readreceipts = new ProtocolNode("readreceipts", null, null, null);
        $groupsv2 = new ProtocolNode("groups_v2", null, null, null);
        $privacy = new ProtocolNode("privacy", null, null, null);
        $presencev2 = new ProtocolNode("presence", null, null, null);
        $parent = new ProtocolNode("stream:features", null, array($readreceipts, $groupsv2, $privacy, $presencev2), null);

        return $parent;
    }

    /**
     * Fetch a single message node
     * @param  bool   $autoReceipt
     * @param  string $type
     * @return bool
     *
     * @throws ConnectionException
     */
    public function pollMessage($autoReceipt = true, $type = "read")
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Connection Closed!');
        }

        $r = array($this->instance->getSocket());
        $w = array();
        $e = array();

        if (socket_select($r, $w, $e, Config::$TIMEOUT_SEC, Config::$TIMEOUT_USEC)) {
            // Something to read
            if ($stanza = $this->readStanza()) {
                $this->processInboundData($stanza, $autoReceipt, $type);
                return true;
            }
        }

        return false;
    }

    /**
     * Read 1024 bytes from the whatsapp server.
     *
     * @throws Exception
     */
    public function readStanza()
    {
        $buff = '';
        if ($this->instance->getSocket() != null) {
            $header = @socket_read($this->instance->getSocket(), 3);//read stanza header
            if ($header === false) {
                $error = "socket EOF, closing socket...";
                socket_close($this->instance->getSocket());
                $this->instance->setSocket(null);
                $this->eventManager()->fire("onClose",
                    array(
                        $this->getPhoneNumber(),
                        $error
                    )
                );
            }

            if (strlen($header) == 0) {
                return;
            }

            if (strlen($header) != 3) {
                throw new ConnectionException("Failed to read stanza header");
            }

            $treeLength = (ord($header[0]) & 0x0F) << 16;
            $treeLength |= ord($header[1]) << 8;
            $treeLength |= ord($header[2]) << 0;

            //read full length
            $buff = socket_read($this->instance->getSocket(), $treeLength);
            //$trlen = $treeLength;
            $len = strlen($buff);
            //$prev = 0;
            while (strlen($buff) < $treeLength) {
                $toRead = $treeLength - strlen($buff);
                $buff .= socket_read($this->instance->getSocket(), $toRead);
                if ($len == strlen($buff)) {
                    //no new data read, fuck it
                    break;
                }
                $len = strlen($buff);
            }

            if (strlen($buff) != $treeLength) {
                throw new ConnectionException("Tree length did not match received length (buff = " . strlen($buff) . " & treeLength = $treeLength)");
            }
            $buff = $header . $buff;
        } else {
            $this->instance->eventManager()->fire("onDisconnect",
                array(
                    $this->instance->getPhoneNumber(),
                    $this->instance->getSocket()
                ));
        }

        return $buff;
    }

    /**
     * Process inbound data.
     *
     * @param      $data
     * @param bool $autoReceipt
     * @param      $type
     *
     * @throws Exception
     */
    protected function processInboundData($data, $autoReceipt = true, $type = "read")
    {
        $node = $this->instance->getReader()->nextTree($data);
        if ($node != null) {
            $this->processInboundDataNode($node, $autoReceipt, $type);
        }
    }

    /**
     * Process the challenge.
     *
     * @param ProtocolNode $node The node that contains the challenge.
     */
    protected function processChallenge($node)
    {
        $this->instance->setChallengeData($node->getData());
    }

    /**
     * Tell the server we received the message.
     *
     * @param ProtocolNode $node The ProtocolTreeNode that contains the message.
     * @param string       $type
     * @param string       $participant
     * @param string       $callId
     */
    protected function sendReceipt($node, $type = "read", $participant = null, $callId = null)
    {
        $messageHash = array();
        if ($type == "read") {
            $messageHash["type"] = $type;
        }
        if ($participant != null) {
            $messageHash["participant"] = $participant;
        }
        $messageHash["to"] = $node->getAttribute("from");
        $messageHash["id"] = $node->getAttribute("id");

        if ($callId != null)
        {
            $offerNode = new ProtocolNode("offer", array("call-id" => $callId), null, null);
            $messageNode = new ProtocolNode("receipt", $messageHash, array($offerNode), null);
        }
        else
        {
            $messageNode = new ProtocolNode("receipt", $messageHash, null, null);
        }
        $this->sendNode($messageNode);
        $this->instance->eventManager()->fire("onSendMessageReceived",
            array(
                $this->instance->getPhoneNumber(),
                $node->getAttribute("id"),
                $node->getAttribute("from"),
                $type
            ));
    }

    /**
     * @param $node  ProtocolNode
     * @param $class string
     */
    protected function sendAck($node, $class)
    {
        $from = $node->getAttribute("from");
        $to = $node->getAttribute("to");
        $participant = $node->getAttribute("participant");
        $id = $node->getAttribute("id");
        $type = $node->getAttribute("type");

        $attributes = array();
        if ($to)
            $attributes["from"] = $to;
        if ($participant)
            $attributes["participant"] = $participant;
        $attributes["to"] = $from;
        $attributes["class"] = $class;
        $attributes["id"] = $id;
        if ($type != null)
            $attributes["type"] = $type;

        $ack = new ProtocolNode("ack", $attributes, null, null);

        $this->sendNode($ack);
    }

    /**
     * Clears the "dirty" status on your account
     *
     * @param  array $categories
     */
    protected function sendClearDirty($categories)
    {
        $msgId = $this->createMsgId();

        $catnodes = array();
        foreach ($categories as $category) {
            $catnode = new ProtocolNode("clean", array("type" => $category), null, null);
            $catnodes[] = $catnode;
        }
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "type" => "set",
                "to" => Config::$FUNXMPP_SERVER,
                "xmlns" => "urn:xmpp:whatsapp:dirty"
            ), $catnodes, null);

        $this->sendNode($node);
    }

    /**
     * Create a unique msg id.
     *
     * @return string
     *   A message id string.
     */
    protected function createMsgId()
    {
        $msgid = $messageCounter = $this->instance->getMessageCounter();
        $this->instance->setMessageCounter($messageCounter++);

        return $this->instance->getLoginTime() . "-" . $msgid;
    }

        /**
     * Process media upload response
     *
     * @param ProtocolNode $node Message node
     * @return bool
     */
    protected function processUploadResponse($node)
    {
        $id = $node->getAttribute("id");
        $messageNode = @$this->instance->getMediaQueue()[$id];
        if ($messageNode == null) {
            //message not found, can't send!
            $this->instance->eventManager()->fire("onMediaUploadFailed",
                array(
                    $this->instance->getPhoneNumber(),
                    $id,
                    $node,
                    $messageNode,
                    "Message node not found in queue"
                ));
            return false;
        }

        $duplicate = $node->getChild("duplicate");
        if ($duplicate != null) {
            //file already on funxmpp server
            $url = $duplicate->getAttribute("url");
            $filesize = $duplicate->getAttribute("size");
            $filehash = $duplicate->getAttribute("filehash");
            $filetype = $duplicate->getAttribute("type");
            $exploded = explode("/", $url);
            $filename = array_pop($exploded);
        } else {
            //upload new file
            $json = MediaUploader::pushFile($node, $messageNode, $this->instance->getMediaFileInfo(), $this->instance->getPhoneNumber());

            if (!$json) {
                //failed upload
                $this->instance->eventManager()->fire("onMediaUploadFailed",
                    array(
                        $this->instance->getPhoneNumber(),
                        $id,
                        $node,
                        $messageNode,
                        "Failed to push file to server"
                    ));
                return false;
            }

            $url = $json->url;
            $filesize = $json->size;
            $filehash = $json->filehash;
            $filetype = $json->type;
            $filename = $json->name;
        }

        $mediaAttribs = array();
        $mediaAttribs["type"] = $filetype;
        $mediaAttribs["url"] = $url;
        $mediaAttribs["encoding"] = "raw";
        $mediaAttribs["file"] = $filename;
        $mediaAttribs["size"] = $filesize;
        if ($this->instance->getMediaQueue()[$id]['caption'] != '') {
            $mediaAttribs["caption"] = $this->instance->getMediaQueue()[$id]['caption'];
        }

        $filepath = $this->instance->getMediaQueue()[$id]['filePath'];
        $to = $this->instance->getMediaQueue()[$id]['to'];

        switch ($filetype) {
            case "image":
                $caption = $this->instance->getMediaQueue()[$id]['caption'];
                $icon = createIcon($filepath);
                break;
            case "video":
                $caption = $this->instance->getMediaQueue()[$id]['caption'];
                $icon = createVideoIcon($filepath);
                break;
            default:
                $caption = '';
                $icon = '';
                break;
        }
        //Retrieve Message ID
        $message_id = $messageNode['message_id'];

        $mediaNode = new ProtocolNode("media", $mediaAttribs, null, $icon);
        if (is_array($to)) {
            $this->sendBroadcast($to, $mediaNode, "media");
        } else {
            $this->sendMessageNode($to, $mediaNode, $message_id);
        }
        $this->instance->eventManager()->fire("onMediaMessageSent",
            array(
                $this->instance->getPhoneNumber(),
                $to,
                $id,
                $filetype,
                $url,
                $filename,
                $filesize,
                $filehash,
                $caption,
                $icon
            ));

        return true;
    }

    /**
     * Send the active status. User will show up as "Online" (as long as socket is connected).
     */
    public function sendActiveStatus()
    {
        $messageNode = new ProtocolNode("presence", array("type" => "active"), null, "");
        $this->sendNode($messageNode);
    }

    /**
     * Send a request to return a list of groups user is currently participating in.
     *
     * To capture this list you will need to bind the "onGetGroups" event.
     */
    public function sendGetGroups()
    {
        $this->sendGetGroupsFiltered("participating");
    }

    public function sendChatState($to, $state)
    {
        $node = new ProtocolNode("chatstate",
            array(
                "to" => $this->instance->getJID($to)
            ), array(new ProtocolNode($state, null, null, null)), null);

        $this->sendNode($node);
    }

    /**
     * Send the getGroupList request to WhatsApp
     * @param  string $type Type of list of groups to retrieve. "owning" or "participating"
     */
    protected function sendGetGroupsFiltered($type)
    {
        $msgID = $this->nodeId['getgroups'] = $this->createMsgId();
        $child = new ProtocolNode($type, null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgID,
                "type" => "get",
                "xmlns" => "w:g2",
                "to" => Config::$FUNXMPP_GROUP_SERVER
            ), array($child), null);

        $this->sendNode($node);
        $this->waitForServer($msgID);
    }

    public function sendSync(array $numbers, array $deletedNumbers = null, $syncType = 4, $index = 0, $last = true)
    {
        $users = array();
        for ($i=0; $i<count($numbers); $i++) { // number must start with '+' if international contact
            $users[$i] = new ProtocolNode("user", null, null, (substr($numbers[$i], 0, 1) != '+')?('+' . $numbers[$i]):($numbers[$i]));
        }

        if ($deletedNumbers != null || count($deletedNumbers)) {
            for ($j=0; $j<count($deletedNumbers); $j++, $i++) {
                $users[$i] = new ProtocolNode("user", array("jid" => $this->instance->getJID($deletedNumbers[$j]), "type" => "delete"), null, null);
            }
        }

        switch($syncType)
        {
            case 0:
                $mode = "full";
                $context = "registration";
                break;
            case 1:
                $mode = "full";
                $context = "interactive";
                break;
            case 2:
                $mode = "full";
                $context = "background";
                break;
            case 3:
                $mode = "delta";
                $context = "interactive";
                break;
            case 4:
                $mode = "delta";
                $context = "background";
                break;
            case 5:
                $mode = "query";
                $context = "interactive";
                break;
            case 6:
                $mode = "chunked";
                $context = "registration";
                break;
            case 7:
                $mode = "chunked";
                $context = "interactive";
                break;
            case 8:
                $mode = "chunked";
                $context = "background";
                break;
            default:
                $mode = "delta";
                $context = "background";
        }

        $id = $this->createMsgId();

        $node = new ProtocolNode("iq",
            array(
                "id" => $id,
                "xmlns" => "urn:xmpp:whatsapp:sync",
                "type" => "get"
            ), array(
                new ProtocolNode("sync",
                    array(
                        "mode" => $mode,
                        "context" => $context,
                        "sid" => "".((time() + 11644477200) * 10000000),
                        "index" => "".$index,
                        "last" => $last ? "true" : "false"
                    ), $users, null)
            ), null);

        $this->sendNode($node);
        $this->waitForServer($id);

        return $id;
    }

    /**
     * Send a Broadcast Message with audio.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets       An array of numbers to send to.
     * @param string $path          URL or local path to the audio file to send
     * @param bool   $storeURLmedia Keep a copy of the audio file on your server
     * @param int    $fsize
     * @param string $fhash
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendBroadcastAudio($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = "")
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        // Return message ID. Make pull request for this.
        return  $this->sendMessageAudio($targets, $path, $storeURLmedia, $fsize, $fhash);
    }

    /**
     * Send a Broadcast Message with an image.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array  $targets       An array of numbers to send to.
     * @param string $path          URL or local path to the image file to send
     * @param bool   $storeURLmedia Keep a copy of the audio file on your server
     * @param int    $fsize
     * @param string $fhash
     * @param string $caption
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendBroadcastImage($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        // Return message ID. Make pull request for this.
        return  $this->sendMessageImage($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Send a Broadcast Message with location data.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * If no name is supplied , receiver will see large sized google map
     * thumbnail of entered Lat/Long but NO name/url for location.
     *
     * With name supplied, a combined map thumbnail/name box is displayed
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param  array  $targets  An array of numbers to send to.
     * @param  float $long      The longitude of the location eg 54.31652
     * @param  float $lat       The latitude if the location eg -6.833496
     * @param  string $name     (Optional) A name to describe the location
     * @param  string $url      (Optional) A URL to link location to web resource
     * @return string           Message ID
     */
    public function sendBroadcastLocation($targets, $long, $lat, $name = null, $url = null)
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        // Return message ID. Make pull request for this.
        return $this->sendMessageLocation($targets, $long, $lat, $name, $url);
    }

    /**
     * Send a Broadcast Message
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param  array  $targets      An array of numbers to send to.
     * @param  string $message      Your message
     * @return string               Message ID
     */
    public function sendBroadcastMessage($targets, $message)
    {
        $bodyNode = new ProtocolNode("body", null, null, $message);
        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $bodyNode, "text");
    }

    /**
     * Send a Broadcast Message with a video.
     *
     * The recipients MUST have your number (synced) and in their contact list
     * otherwise the message will not deliver to that person.
     *
     * Approx 20 (unverified) is the maximum number of targets
     *
     * @param array   $targets       An array of numbers to send to.
     * @param string  $path          URL or local path to the video file to send
     * @param bool    $storeURLmedia Keep a copy of the audio file on your server
     * @param int     $fsize
     * @param string  $fhash
     * @param string  $caption
     * @return string|null           Message ID if successfully, null if not.
     */
    public function sendBroadcastVideo($targets, $path, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }
        // Return message ID. Make pull request for this.
        return $this->sendMessageVideo($targets, $path, $storeURLmedia, $fsize, $fhash, $caption);
    }

    /**
     * Delete Broadcast lists
     *
     * @param  string array $lists
     * Contains the broadcast-id list
     */
    public function sendDeleteBroadcastLists($lists)
    {
        $msgId = $this->createMsgId();
        $listNode = array();
        if ($lists != null && count($lists) > 0) {
            for ($i = 0; $i < count($lists); $i++) {
                $listNode[$i] = new ProtocolNode("list", array("id" => $lists[$i]), null, null);
            }
        } else {
            $listNode = null;
        }
        $deleteNode = new ProtocolNode("delete", null, $listNode, null);
        $node       = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:b",
                "type" => "set",
                "to" => Constants::WHATSAPP_SERVER
            ), array($deleteNode), null);

        $this->sendNode($node);
    }

    /**
     * Send a broadcast
     * @param array  $targets Array of numbers to send to
     * @param object $node
     * @param        $type
     * @return string
     */
    protected function sendBroadcast($targets, $node, $type)
    {
        if (!is_array($targets)) {
            $targets = array($targets);
        }

        $toNodes = array();
        foreach ($targets as $target) {
            $jid = $this->getJID($target);
            $hash = array("jid" => $jid);
            $toNode = new ProtocolNode("to", $hash, null, null);
            $toNodes[] = $toNode;
        }

        $broadcastNode = new ProtocolNode("broadcast", null, $toNodes, null);

        $msgId = $this->createMsgId();

        $messageNode = new ProtocolNode("message",
            array(
                "to" => time()."@broadcast",
                "type" => $type,
                "id" => $msgId
            ), array($node, $broadcastNode), null);

        $this->sendNode($messageNode);
        $this->waitForServer($msgId);
        //listen for response
        $this->instance->eventManager()->fire("onSendMessage",
            array(
                $this->instance->getPhoneNumber(),
                $targets,
                $msgId,
                $node
            ));

        return $msgId;
    }

    /**
     * Wait for WhatsApp server to acknowledge *it* has received message.
     * @param string $id The id of the node sent that we are awaiting acknowledgement of.
     * @param int    $timeout
     */
    public function waitForServer($id, $timeout = 5)
    {
        $time = time();
        $this->serverReceivedId = false;
        do {
            $this->pollMessage();
        } while ($this->serverReceivedId !== $id && time() - $time < $timeout);
    }

    /**
     * Send node to the servers.
     *
     * @param              $to
     * @param ProtocolNode $node
     * @param null         $id
     *
     * @return string            Message ID.
     */
    public function sendMessageNode($to, $node, $id = null)
    {
        $msgId = ($id == null) ? $this->createMsgId() : $id;
        $to = $this->instance->getJID($to);

        $messageNode = new ProtocolNode("message", array(
            'to'   => $to,
            'type' => ($node->getTag() == "body") ? 'text' : 'media',
            'id'   => $msgId,
            't'    => time()
        ), array($node), "");

        $this->sendNode($messageNode);

        $this->instance->eventManager()->fire("onSendMessage",
            array(
                $this->instance->getPhoneNumber(),
                $to,
                $msgId,
                $node
            ));

        $this->waitForServer($msgId);

        return $msgId;
    }

    /**
     * @param ProtocolNode $groupNode
     * @param string       $fromGetGroups
     */
    protected function handleGroupV2InfoResponse(ProtocolNode $groupNode, $fromGetGroups = false)
    {
        $creator = $groupNode->getAttribute('creator');
        $creation = $groupNode->getAttribute('creation');
        $subject = $groupNode->getAttribute('subject');
        $groupID = $groupNode->getAttribute('id');
        $participants = array();
        $admins = array();
        if ($groupNode->getChild(0) != null) {
            foreach ($groupNode->getChildren() as $child) {
                $participants[] = $child->getAttribute('jid');
                if ($child->getAttribute('type') == "admin")
                    $admins[] = $child->getAttribute('jid');
            }
        }
        $this->instance->eventManager()->fire("onGetGroupV2Info",
            array(
                $this->instance->getPhoneNumber(),
                $groupID,
                $creator,
                $creation,
                $subject,
                $participants,
                $admins,
                $fromGetGroups
            )
        );
    }

    /**
     * Will process the data from the server after it's been decrypted and parsed.
     *
     * This also provides a convenient method to use to unit test the event framework.
     * @param ProtocolNode $node
     * @param bool         $autoReceipt
     * @param              $type
     *
     * @throws Exception
     */
    protected function processInboundDataNode(ProtocolNode $node, $autoReceipt = true, $type = "read") {
        $this->instance->debugPrint($node->nodeString("rx  ") . "\n");
        $this->serverReceivedId = $node->getAttribute('id');

        if ($node->getTag() == "challenge") {
            $this->processChallenge($node);
        } elseif ($node->getTag() == "failure") {
            $this->instance->setLoginStatus(Config::$DISCONNECTED_STATUS);
            $this->instance->eventManager()->fire("onLoginFailed",
                array(
                    $this->instance->getPhoneNumber(),
                    $node->getChild(0)->getTag()
                ));
        } elseif ($node->getTag() == "success") {
            if ($node->getAttribute("status") == "active") {
                $this->instance->setLoginStatus(Config::$CONNECTED_STATUS);
                $challengeData = $node->getData();
                file_put_contents($this->instance->getChallengeFilename(), $challengeData);
                $this->instance->getWriter()->setKey($this->outputKey);

                $this->instance->eventManager()->fire("onLoginSuccess",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute("kind"),
                        $node->getAttribute("status"),
                        $node->getAttribute("creation"),
                        $node->getAttribute("expiration")
                    ));
            } elseif ($node->getAttribute("status") == "expired") {
                $this->instance->eventManager()->fire("onAccountExpired",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute("kind"),
                        $node->getAttribute("status"),
                        $node->getAttribute("creation"),
                        $node->getAttribute("expiration")
                    ));
            }
        } elseif ($node->getTag() == 'ack' && $node->getAttribute("class") == "message") {
            $this->instance->eventManager()->fire("onMessageReceivedServer",
                array(
                    $this->instance->getPhoneNumber(),
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('class'),
                    $node->getAttribute('t')
                ));
        } elseif ($node->getTag() == 'receipt') {
            if ($node->hasChild("list")) {
                foreach ($node->getChild("list")->getChildren() as $child) {
                    $this->instance->eventManager()->fire("onMessageReceivedClient",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute('from'),
                            $child->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('participant')
                        ));
                }
            }

            $this->instance->eventManager()->fire("onMessageReceivedClient",
                array(
                    $this->instance->getPhoneNumber(),
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('type'),
                    $node->getAttribute('t'),
                    $node->getAttribute('participant')
                ));

            $this->sendAck($node, 'receipt');
        }
        if ($node->getTag() == "message") {
            $this->instance->setMessageQueue(array_push($this->instance->getMessageQueue(), $node));

            if ($node->hasChild('x') && $this->lastId == $node->getAttribute('id')) {
                $this->sendNextMessage();
            }
            if ($this->instance->getNewMessageBind() && ($node->getChild('body') || $node->getChild('media'))) {
                $this->instance->getNewMessageBind()->process($node);
            }
            if ($node->getAttribute("type") == "text" && $node->getChild('body') != null) {
                $author = $node->getAttribute("participant");
                if ($author == "") {
                    //private chat message
                    $this->instance->eventManager()->fire("onGetMessage",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute("notify"),
                            $node->getChild("body")->getData()
                        ));

                    if ($this->instance->getMessageStore() !== null) {
                        $this->instance->getMessageStore()->saveMessage($node->getAttribute('from'), $this->instance->getPhoneNumber(), $node->getChild("body")->getData(), $node->getAttribute('id'), $node->getAttribute('t'));
                    }
                } else {
                    //group chat message
                    $this->instance->eventManager()->fire("onGetGroupMessage",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute('from'),
                            $author,
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute("notify"),
                            $node->getChild("body")->getData()
                        ));
                }

                if ($autoReceipt) {
                    $this->sendReceipt($node, $type, $author);
                }
            }
            if ($node->getAttribute("type") == "text" && $node->getChild(0)->getTag() == 'enc') {
                // TODO
                if ($autoReceipt) {
                    $this->sendReceipt($node, $type);
                }
            }
            if ($node->getAttribute("type") == "media" && $node->getChild('media') != null) {
                if ($node->getChild("media")->getAttribute('type') == 'image') {

                    if ($node->getAttribute("participant") == null) {
                        $this->instance->eventManager()->fire("onGetImage",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('width'),
                                $node->getChild("media")->getAttribute('height'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption')
                            ));
                    } else {
                        $this->instance->eventManager()->fire("onGetGroupImage",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('width'),
                                $node->getChild("media")->getAttribute('height'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption')
                            ));
                    }
                } elseif ($node->getChild("media")->getAttribute('type') == 'video') {
                    if ($node->getAttribute("participant") == null) {
                        $this->instance->eventManager()->fire("onGetVideo",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('duration'),
                                $node->getChild("media")->getAttribute('vcodec'),
                                $node->getChild("media")->getAttribute('acodec'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption')
                            ));
                    } else {
                        $this->instance->eventManager()->fire("onGetGroupVideo",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('id'),
                                $node->getAttribute('type'),
                                $node->getAttribute('t'),
                                $node->getAttribute('notify'),
                                $node->getChild("media")->getAttribute('url'),
                                $node->getChild("media")->getAttribute('file'),
                                $node->getChild("media")->getAttribute('size'),
                                $node->getChild("media")->getAttribute('mimetype'),
                                $node->getChild("media")->getAttribute('filehash'),
                                $node->getChild("media")->getAttribute('duration'),
                                $node->getChild("media")->getAttribute('vcodec'),
                                $node->getChild("media")->getAttribute('acodec'),
                                $node->getChild("media")->getData(),
                                $node->getChild("media")->getAttribute('caption')
                            ));
                    }
                } elseif ($node->getChild("media")->getAttribute('type') == 'audio') {
                    $author = $node->getAttribute("participant");
                    $this->instance->eventManager()->fire("onGetAudio",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $node->getChild("media")->getAttribute('size'),
                            $node->getChild("media")->getAttribute('url'),
                            $node->getChild("media")->getAttribute('file'),
                            $node->getChild("media")->getAttribute('mimetype'),
                            $node->getChild("media")->getAttribute('filehash'),
                            $node->getChild("media")->getAttribute('seconds'),
                            $node->getChild("media")->getAttribute('acodec'),
                            $author,
                        ));
                } elseif ($node->getChild("media")->getAttribute('type') == 'vcard') {
                    if ($node->getChild("media")->hasChild('vcard')) {
                        $name = $node->getChild("media")->getChild("vcard")->getAttribute('name');
                        $data = $node->getChild("media")->getChild("vcard")->getData();
                    } else {
                        $name = "NO_NAME";
                        $data = $node->getChild("media")->getData();
                    }
                    $author = $node->getAttribute("participant");

                    $this->instance->eventManager()->fire("onGetvCard",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $name,
                            $data,
                            $author
                        ));
                } elseif ($node->getChild("media")->getAttribute('type') == 'location') {
                    $url = $node->getChild("media")->getAttribute('url');
                    $name = $node->getChild("media")->getAttribute('name');
                    $author = $node->getAttribute("participant");

                    $this->instance->eventManager()->fire("onGetLocation",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute('from'),
                            $node->getAttribute('id'),
                            $node->getAttribute('type'),
                            $node->getAttribute('t'),
                            $node->getAttribute('notify'),
                            $name,
                            $node->getChild("media")->getAttribute('longitude'),
                            $node->getChild("media")->getAttribute('latitude'),
                            $url,
                            $node->getChild("media")->getData(),
                            $author
                        ));
                }

                if ($autoReceipt) {
                    $this->sendReceipt($node, $type);
                }
            }
            if ($node->getChild('received') != null) {
                $this->instance->eventManager()->fire("onMessageReceivedClient",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getAttribute('type'),
                        $node->getAttribute('t'),
                        $node->getAttribute('participant')
                    ));
            }
        }
        if ($node->getTag() == "presence" && $node->getAttribute("status") == "dirty") {
            //clear dirty
            $categories = array();
            if (count($node->getChildren()) > 0) {
                foreach ($node->getChildren() as $child) {
                    if ($child->getTag() == "category") {
                        $categories[] = $child->getAttribute("name");
                    }
                }
            }
            $this->sendClearDirty($categories);
        }
        if (strcmp($node->getTag(), "presence") == 0
            && strncmp($node->getAttribute('from'), $this->instance->getPhoneNumber(), strlen($this->instance->getPhoneNumber())) != 0
            && strpos($node->getAttribute('from'), "-") === false) {
            $presence = array();
            if ($node->getAttribute('type') == null) {
                $this->instance->eventManager()->fire("onPresenceAvailable",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute('from'),
                    ));
            } else {
                $this->instance->eventManager()->fire("onPresenceUnavailable",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute('from'),
                        $node->getAttribute('last')
                    ));
            }
        }
        if ($node->getTag() == "presence"
            && strncmp($node->getAttribute('from'), $this->instance->getPhoneNumber(), strlen($this->instance->getPhoneNumber())) != 0
            && strpos($node->getAttribute('from'), "-") !== false
            && $node->getAttribute('type') != null) {
            $groupId = Helpers::parseJID($node->getAttribute('from'));
            if ($node->getAttribute('add') != null) {
                $this->instance->eventManager()->fire("onGroupsParticipantsAdd",
                    array(
                        $this->instance->getPhoneNumber(),
                        $groupId,
                        Helpers::parseJID($node->getAttribute('add'))
                    ));
            } elseif ($node->getAttribute('remove') != null) {
                $this->instance->eventManager()->fire("onGroupsParticipantsRemove",
                    array(
                        $this->phoneNumber,
                        $groupId,
                        Helpers::parseJID($node->getAttribute('remove'))
                    ));
            }
        }
        if (strcmp($node->getTag(), "chatstate") == 0
            && strncmp($node->getAttribute('from'), $this->instance->getPhoneNumber(), strlen($this->instance->getPhoneNumber())) != 0
            && strpos($node->getAttribute('from'), "-") === false) {
            if($node->getChild(0)->getTag() == "composing"){
                $this->instance->eventManager()->fire("onMessageComposing",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        "composing",
                        $node->getAttribute('t')
                    ));
            } else {
                $this->instance->eventManager()->fire("onMessagePaused",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        "paused",
                        $node->getAttribute('t')
                    ));
            }
        }
        if ($node->getTag() == "iq"
            && $node->getAttribute('type') == "get"
            && $node->getAttribute('xmlns') == "urn:xmpp:ping") {
            $this->instance->eventManager()->fire("onPing",
                array(
                    $this->instance->getPhoneNumber(),
                    $node->getAttribute('id')
                ));
            $this->sendPong($node->getAttribute('id'));
        }
        if ($node->getTag() == "iq"
            && $node->getChild("sync") != null) {

            //sync result
            $sync = $node->getChild('sync');
            $existing = $sync->getChild("in");
            $nonexisting = $sync->getChild("out");

            //process existing first
            $existingUsers = array();
            if (!empty($existing)) {
                foreach ($existing->getChildren() as $child) {
                    $existingUsers[$child->getData()] = $child->getAttribute("jid");
                }
            }

            //now process failed numbers
            $failedNumbers = array();
            if (!empty($nonexisting)) {
                foreach ($nonexisting->getChildren() as $child) {
                    $failedNumbers[] = str_replace('+', '', $child->getData());
                }
            }

            $index = $sync->getAttribute("index");

            $result = new SyncResult($index, $sync->getAttribute("sid"), $existingUsers, $failedNumbers);

            $this->instance->eventManager()->fire("onGetSyncResult",
                array(
                    $result
                ));
        }
        if ($node->getTag() == "receipt") {
            $this->instance->eventManager()->fire("onGetReceipt",
                array(
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getAttribute('offline'),
                    $node->getAttribute('retry')
                ));
        }
        if ($node->getTag() == "iq"
            && $node->getAttribute('type') == "result") {
            if ($node->getChild("query") != null) {
                if (isset($this->nodeId['privacy']) && ($this->nodeId['privacy'] == $node->getAttribute('id'))) {
                    $listChild = $node->getChild(0)->getChild(0);
                    foreach ($listChild->getChildren() as $child) {
                        $blockedJids[] = $child->getAttribute('value');
                    }
                    $this->instance->eventManager()->fire("onGetPrivacyBlockedList",
                        array(
                            $this->instance->getPhoneNumber(),
                            $blockedJids
                        ));
                }
                $this->instance->eventManager()->fire("onGetRequestLastSeen",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute('from'),
                        $node->getAttribute('id'),
                        $node->getChild(0)->getAttribute('seconds')
                    ));
                $this->instance->setMessageQueue(array_push($this->instance->getMessageQueue(), $node));
            }
            if ($node->getChild("props") != null) {
                //server properties
                $props = array();
                foreach($node->getChild(0)->getChildren() as $child) {
                    $props[$child->getAttribute("name")] = $child->getAttribute("value");
                }
                $this->instance->eventManager()->fire("onGetServerProperties",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getChild(0)->getAttribute("version"),
                        $props
                    ));
            }
            if ($node->getChild("picture") != null) {
                $this->instance->eventManager()->fire("onGetProfilePicture",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getAttribute("from"),
                        $node->getChild("picture")->getAttribute("type"),
                        $node->getChild("picture")->getData()
                    ));
            }
            if ($node->getChild("media") != null || $node->getChild("duplicate") != null) {
                $this->processUploadResponse($node);
            }
            if (strpos($node->getAttribute("from"), Config::$FUNXMPP_GROUP_SERVER) !== false)  {
                //There are multiple types of Group reponses. Also a valid group response can have NO children.
                //Events fired depend on text in the ID field.
                $groupList = array();
                $groupNodes = array();
                if ($node->getChild(0) != null && $node->getChild(0)->getChildren() != null) {
                    foreach ($node->getChild(0)->getChildren() as $child) {
                        $groupList[] = $child->getAttributes();
                        $groupNodes[] = $child;
                    }
                }
                if (isset($this->nodeId['groupcreate']) && ($this->nodeId['groupcreate'] == $node->getAttribute('id'))) {
                    $this->groupId = $node->getChild(0)->getAttribute('id');
                    $this->instance->eventManager()->fire("onGroupsChatCreate",
                        array(
                            $this->instance->getPhoneNumber(),
                            $this->groupId
                        ));
                }
                if (isset($this->nodeId['leavegroup']) && ($this->nodeId['leavegroup'] == $node->getAttribute('id'))) {
                    $this->groupId = $node->getChild(0)->getChild(0)->getAttribute('id');
                    $this->instance->eventManager()->fire("onGroupsChatEnd",
                        array(
                            $this->instance->getPhoneNumber(),
                            $this->groupId
                        ));
                }
                if (isset($this->nodeId['getgroups']) && ($this->nodeId['getgroups'] == $node->getAttribute('id'))) {
                    $this->instance->eventManager()->fire("onGetGroups",
                        array(
                            $this->instance->getPhoneNumber(),
                            $groupList
                        ));
                    //getGroups returns a array of nodes which are exactly the same as from getGroupV2Info
                    //so lets call this event, we have all data at hand, no need to call getGroupV2Info for every
                    //group we are interested
                    foreach ($groupNodes AS $groupNode) {
                        $this->handleGroupV2InfoResponse($groupNode, true);
                    }

                }
            if (isset($this->nodeId['get_groupv2_info']) && ($this->nodeId['get_groupv2_info'] == $node->getAttribute('id'))) {
                $groupChild = $node->getChild(0);
                if ($groupChild != null) {
                    $this->handleGroupV2InfoResponse($groupChild);
                }
            }
          }
            if (isset($this->nodeId['get_lists']) && ($this->nodeId['get_lists'] == $node->getAttribute('id'))) {
                $broadcastLists = array();
                if ($node->getChild(0) != null) {
                    $childArray = $node->getChildren();
                    foreach ($childArray as $list) {
                        if ($list->getChildren() != null) {
                            foreach ( $list->getChildren() as $sublist) {
                                $id = $sublist->getAttribute("id");
                                $name = $sublist->getAttribute("name");
                                $broadcastLists[$id]['name'] = $name;
                                $recipients = array();
                                foreach ($sublist->getChildren() as $recipient) {
                                    array_push($recipients, $recipient->getAttribute('jid'));
                                }
                                $broadcastLists[$id]['recipients'] = $recipients;
                            }
                        }
                    }
                }
                $this->instance->eventManager()->fire("onGetBroadcastLists",
                    array(
                        $this->instance->getPhoneNumber(),
                        $broadcastLists
                    ));
            }
            if ($node->getChild("pricing") != null) {
                $this->instance->eventManager()->fire("onGetServicePricing",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getChild(0)->getAttribute("price"),
                        $node->getChild(0)->getAttribute("cost"),
                        $node->getChild(0)->getAttribute("currency"),
                        $node->getChild(0)->getAttribute("expiration")
                    ));
            }
            if ($node->getChild("extend") != null) {
                $this->instance->eventManager()->fire("onGetExtendAccount",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getChild("account")->getAttribute("kind"),
                        $node->getChild("account")->getAttribute("status"),
                        $node->getChild("account")->getAttribute("creation"),
                        $node->getChild("account")->getAttribute("expiration")
                    ));
            }
            if ($node->getChild("normalize") != null) {
                $this->instance->eventManager()->fire("onGetNormalizedJid",
                    array(
                        $this->instance->getPhoneNumber(),
                        $node->getChild(0)->getAttribute("result")
                    ));
            }
            if ($node->getChild("status") != null) {
                $child = $node->getChild("status");
                foreach($child->getChildren() as $status)
                {
                    $this->instance->eventManager()->fire("onGetStatus",
                        array(
                            $this->instance->getPhoneNumber(),
                            $status->getAttribute("jid"),
                            "requested",
                            $node->getAttribute("id"),
                            $status->getAttribute("t"),
                            $status->getData()
                        ));
                }
            }
        }
        if ($node->getTag() == "iq" && $node->getAttribute('type') == "error") {
            $this->instance->eventManager()->fire("onGetError",
                array(
                    $this->instance->getPhoneNumber(),
                    $node->getAttribute('from'),
                    $node->getAttribute('id'),
                    $node->getChild(0)
                ));
        }

        if ($node->getTag() == "message" && $node->getAttribute('type') == "media" && $node->getChild(0)->getAttribute('type') == "image" ) {
            $msgId = $this->createMsgId();

            $ackNode = new ProtocolNode("ack",
                array(
                    "url" => $node->getChild(0)->getAttribute('url')
                ), null, null);

            $iqNode = new ProtocolNode("iq",
                array(
                    "id" => $msgId,
                    "xmlns" => "w:m",
                    "type" => "set",
                    "to" => Config::$FUNXMPP_SERVER
                ), array($ackNode), null);

            $this->sendNode($iqNode);
        }

        $children = $node->getChild(0);
        if ($node->getTag() == "stream:error" && !empty($children) && $node->getChild(0)->getTag() == "system-shutdown")
        {
            $this->instance->eventManager()->fire("onStreamError",
                array(
                    $node->getChild(0)->getTag()
                ));
        }

        if ($node->getTag() == "stream:error") {
            $this->eventManager()->fire("onStreamError",
                array(
                    $node->getChild(0)->getTag()
                ));
        }

        if ($node->getTag() == "notification") {
            $name = $node->getAttribute("notify");
            $type = $node->getAttribute("type");
            switch($type)
            {
                case "status":
                    $this->instance->eventManager()->fire("onGetStatus",
                        array(
                            $this->instance->getPhoneNumber(),
                            $node->getAttribute("from"),
                            $node->getChild(0)->getTag(),
                            $node->getAttribute("id"),
                            $node->getAttribute("t"),
                            $node->getChild(0)->getData()
                        ));
                    break;
                case "picture":
                    if ($node->hasChild('set')) {
                        $this->instance->eventManager()->fire("onProfilePictureChanged",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('t')
                            ));
                    } else if ($node->hasChild('delete')) {
                        $this->instance->eventManager()->fire("onProfilePictureDeleted",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('id'),
                                $node->getAttribute('t')
                            ));
                    }
                    //TODO
                    break;
                case "contacts":
                    $notification = $node->getChild(0)->getTag();
                    if ($notification == 'add')
                    {
                        $this->instance->eventManager()->fire("onNumberWasAdded",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getChild(0)->getAttribute('jid')
                        ));
                    }
                    elseif ($notification == 'remove')
                    {
                        $this->instance->eventManager()->fire("onNumberWasRemoved",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getChild(0)->getAttribute('jid')
                        ));
                    }
                    elseif ($notification == 'update')
                    {
                        $this->instance->eventManager()->fire("onNumberWasUpdated",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getChild(0)->getAttribute('jid')
                        ));
                    }
                    break;
                case "encrypt":
                    $value = $node->getChild(0)->getAttribute('value');
                    if (is_numeric($value)) {
                        $this->instance->eventManager()->fire("onGetKeysLeft",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getChild(0)->getAttribute('value')
                            ));
                    }
                    else {
                        echo "Corrupt Stream: value " . $value . "is not numeric";
                    }
                    break;
                case "w:gp2":
                    if ($node->hasChild('remove')) {
                        if ($node->getChild(0)->hasChild('participant'))
                            $this->instance->eventManager()->fire("onGroupsParticipantsRemove",
                                array(
                                    $this->instance->getPhoneNumber(),
                                    $node->getAttribute('from'),
                                    $node->getChild(0)->getChild(0)->getAttribute('jid')
                                ));
                    } else if ($node->hasChild('add')) {
                        $this->instance->eventManager()->fire("onGroupsParticipantsAdd",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getChild(0)->getChild(0)->getAttribute('jid')
                            ));
                    }
                    else if ($node->hasChild('create')) {
                        $groupMembers = array();
                        foreach ($node->getChild(0)->getChild(0)->getChildren() AS $cn) {
                            $groupMembers[] = $cn->getAttribute('jid');
                        }
                        $this->instance->eventManager()->fire("onGroupisCreated",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getChild(0)->getChild(0)->getAttribute('creator'),
                                $node->getChild(0)->getChild(0)->getAttribute('id'),
                                $node->getChild(0)->getChild(0)->getAttribute('subject'),
                                $node->getAttribute('participant'),
                                $node->getChild(0)->getChild(0)->getAttribute('creation'),
                                $groupMembers
                            ));
                    }
                    else if ($node->hasChild('subject')) {
                        $this->instance->eventManager()->fire("onGetGroupsSubject",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getAttribute('t'),
                                $node->getAttribute('participant'),
                                $node->getAttribute('notify'),
                                $node->getChild(0)->getAttribute('subject')
                            ));
                    }
                    else if ($node->hasChild('promote')) {
                        $promotedJIDs = array();
                        foreach ($node->getChild(0)->getChildren() AS $cn) {
                            $promotedJIDs[] = $cn->getAttribute('jid');
                        }
                        $this->instance->eventManager()->fire("onGroupsParticipantsPromote",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),        //Group-JID
                                $node->getAttribute('t'),           //Time
                                $node->getAttribute('participant'), //Issuer-JID
                                $node->getAttribute('notify'),      //Issuer-Name
                                $promotedJIDs,
                            )
                        );
                    }
                    break;
                case "account":
                    if (($node->getChild(0)->getAttribute('author')) == "")
                        $author = "Paypal";
                    else
                        $author = $node->getChild(0)->getAttribute('author');
                    $this->instance->eventManager()->fire("onPaidAccount",
                        array(
                            $this->instance->getPhoneNumber(),
                            $author,
                            $node->getChild(0)->getChild(0)->getAttribute('kind'),
                            $node->getChild(0)->getChild(0)->getAttribute('status'),
                            $node->getChild(0)->getChild(0)->getAttribute('creation'),
                            $node->getChild(0)->getChild(0)->getAttribute('expiration')
                        ));
                    break;
                case "features":
                    if ($node->getChild(0)->getChild(0) == "encrypt") {
                        $this->instance->eventManager()->fire("onGetFeature",
                            array(
                                $this->instance->getPhoneNumber(),
                                $node->getAttribute('from'),
                                $node->getChild(0)->getChild(0)->getAttribute('value'),
                            ));
                    }
                    break;
                case "web":
                      if (($node->getChild(0)->getTag() == 'action') && ($node->getChild(0)->getAttribute('type') == 'sync'))
                      {
                            $data = $node->getChild(0)->getChildren();
                            $this->instance->eventManager()->fire("onWebSync",
                                array(
                                    $this->instance->getPhoneNumber(),
                                    $node->getAttribute('from'),
                                    $node->getAttribute('id'),
                                    $data[0]->getData(),
                                    $data[1]->getData(),
                                    $data[2]->getData()
                            ));
                      }
                    break;
                default:
                    throw new CustomException("Method $type not implemented");
            }
            $this->sendAck($node, 'notification');
        }
        if ($node->getTag() == "call")
        {
            if ($node->getChild(0)->getTag() == "offer")
            {
                $callId = $node->getChild(0)->getAttribute("call-id");
                $this->sendReceipt($node, null, null, $callId);

                $this->instance->eventManager()->fire("onCallReceived",
                array(
                    $this->instance->getPhoneNumber(),
                    $node->getAttribute("from"),
                    $node->getAttribute("id"),
                    $node->getAttribute("notify"),
                    $node->getAttribute("t"),
                    $node->getChild(0)->getAttribute("call-id")
                ));
            }
            else
            {
                $this->sendAck($node, 'call');
            }

        }
        if ($node->getTag() == "ib")
        {
            foreach($node->getChildren() as $child)
            {
                switch($child->getTag())
                {
                    case "dirty":
                        $this->sendClearDirty(array($child->getAttribute("type")));
                        break;
                    case "account":
                        $this->instance->eventManager()->fire("onPaymentRecieved",
                        array(
                            $this->instance->getPhoneNumber(),
                            $child->getAttribute("kind"),
                            $child->getAttribute("status"),
                            $child->getAttribute("creation"),
                            $child->getAttribute("expiration")
                        ));
                        break;
                    case "offline":

                        break;
                    default:
                        throw new CustomException("ib handler for " . $child->getTag() . " not implemented");
                }
            }
        }

        // Disconnect socket on stream error.
        if ($node->getTag() == "stream:error")
        {
            $this->disconnect();
        }
    }

}