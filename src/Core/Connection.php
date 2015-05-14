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

use FunXMPP\Core\Connection\ConnectionMethods;
use FunXMPP\Core\Config;
use FunXMPP\Core\KeyStream;
use FunXMPP\Core\SyncResult;
use FunXMPP\Core\ProtocolNode;
use FunXMPP\Core\MediaUploader;

use FunXMPP\Util\Helpers;

class Connection extends ConnectionMethods
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
     * If you already know your password you can log into the FunXMPP server
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
     * Send a request to get new Groups V2 info.
     *
     * @param $groupID
     *    The group JID
     */
    public function sendGetGroupV2Info($groupID)
    {
        $msgId = $this->nodeId['get_groupv2_info'] = $this->createMsgId();

        $queryNode = new ProtocolNode("query",
            array(
                "request" => "interactive"
            ), null, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:g2",
                "type" => "get",
                "to" => $this->instance->getJID($groupID)
            ), array($queryNode), null);

        $this->sendNode($node);
    }

    /**
     * Send a request to get a list of people you have currently blocked.
     */
    public function sendGetPrivacyBlockedList()
    {
        $msgId = $this->nodeId['privacy'] = $this->createMsgId();
        $child = new ProtocolNode("list",
            array(
                "name" => "default"
            ), null, null);

        $child2 = new ProtocolNode("query", array(), array($child), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "jabber:iq:privacy",
                "type" => "get"
            ), array($child2), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
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
     * Do we have an active socket connection to FunXMPP network?
     *
     * @return bool
     */
    public function isConnected()
    {
        return ($this->instance->getSocket() !== null);
    }

    /**
     * Send presence status.
     *
     * @param string $type The presence status.
     */
    public function sendPresence($type = "active")
    {
        $node = new ProtocolNode("presence",
            array(
                "type" => $type
            ), null, "");

        $this->sendNode($node);
        $this->instance->eventManager()->fire("onSendPresence",
            array(
                $this->instance->getPhoneNumber(),
                $type,
                $this->instance->getName()
            ));
    }

    /**
     * Set the picture for the group.
     *
     * @param string $gjid The groupID
     * @param string $path The URL/URI of the image to use
     */
    public function sendSetGroupPicture($gjid, $path)
    {
        $this->sendSetPicture($gjid, $path);
    }

    /**
     * Disconnect from the FunXMPP network.
     */
    public function disconnect()
    {
        if (is_resource($this->instance->getSocket())) {
            @socket_shutdown($this->instance->getSocket(), 2);
            @socket_close($this->instance->getSocket());
            $this->instance->setSocket(null);
            $this->instance->setLoginStatus(Config::$DISCONNECTED_STATUS);
            $this->instance->eventManager()->fire("onDisconnect",
                array(
                    $this->instance->getPhoneNumber(),
                    $this->instance->getSocket()
                )
            );
        }
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
     * Send the next message.
     */
    public function sendNextMessage()
    {
        if (count($this->getOutQueue()) > 0) {
            $msgnode = array_shift($this->getOutQueue());
            $msgnode->refreshTimes();
            $this->setLastId($msgnode->getAttribute('id'));
            $this->sendNode($msgnode);
        } else {
            $this->setLastId(false);
        }
    }

    /**
     * Send the offline status. User will show up as "Offline".
     */
    public function sendOfflineStatus()
    {
        $messageNode = new ProtocolNode("presence", array("type" => "inactive"), null, "");
        $this->sendNode($messageNode);
    }

    /**
     * Send the 'paused composing message' status.
     *
     * @param string $to The recipient number or ID.
     */
    public function sendMessagePaused($to)
    {
        $this->sendChatState($to, "paused");
    }

    /**
     * Send the composing message status. When typing a message.
     *
     * @param string $to The recipient to send status to.
     */
    public function sendMessageComposing($to)
    {
        $this->sendChatState($to, "composing");
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

    public function sendClientConfig()
    {
        $attr = array();
        $attr["platform"] = Config::$FUNXMPP_DEVICE;
        $attr["version"] = Config::$FUNXMPP_VER;
        $child = new ProtocolNode("config", $attr, null, "");
        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createMsgId(),
                "type" => "set",
                "xmlns" => "urn:xmpp:whatsapp:push",
                "to" => Config::$FUNXMPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
    }

    /**
     * Request to retrieve the last online time of specific user.
     *
     * @param string $to Number or JID of user
     */
    public function sendGetRequestLastSeen($to)
    {
        $msgId = $this->createMsgId();

        $queryNode = new ProtocolNode("query", null, null, null);

        $messageNode = new ProtocolNode("iq",
            array(
                "to" => $this->instance->getJID($to),
                "type" => "get",
                "id" => $msgId,
                "xmlns" => "jabber:iq:last"
            ), array($queryNode), "");

        $this->sendNode($messageNode);
        $this->waitForServer($msgId);
    }

    /**
     * Get profile picture of specified user.
     *
     * @param string $number
     *  Number or JID of user
     * @param bool $large
     *  Request large picture
     */
    public function sendGetProfilePicture($number, $large = false)
    {
        $msgId = $this->createMsgId();

        $hash = array();
        $hash["type"] = "image";
        if (!$large) {
            $hash["type"] = "preview";
        }
        $picture = new ProtocolNode("picture", $hash, null, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "type" => "get",
                "xmlns" => "w:profile:picture",
                "to" => $this->instance->getJID($number)
            ), array($picture), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Send a request to get the normalized mobile number representing the JID.
     *
     *  @param string $countryCode Country Code
     *  @param string $number      Mobile Number
     */
    public function sendGetNormalizedJid($countryCode, $number)
    {
        $msgId = $this->createMsgId();
        $ccNode = new ProtocolNode("cc", null, null, $countryCode);
        $inNode = new ProtocolNode("in", null, null, $number);
        $normalizeNode = new ProtocolNode("normalize", null, array($ccNode, $inNode), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:account",
                "type" => "get",
                "to" => Config::$FUNXMPP_SERVER
            ), array($normalizeNode), null);

        $this->sendNode($node);
    }

    /**
     * Gets all the broadcast lists for an account.
     */
    public function sendGetBroadcastLists()
    {
        $msgId = $this->nodeId['get_lists'] = $this->createMsgId();
        $listsNode = new ProtocolNode("lists", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:b",
                "type" => "get",
                "to" => Config::$FUNXMPP_SERVER
            ), array($listsNode), null);

        $this->sendNode($node);
    }

    /**
     * 
     */
    public function sendGetClientConfig()
    {
        $msgId = $this->createMsgId();
        $child = new ProtocolNode("config", null, null, null);
        $node  = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:push",
                "type" => "get",
                "to" => Config::$FUNXMPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
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
                "to" => Config::$FUNXMPP_SERVER
            ), array($deleteNode), null);

        $this->sendNode($node);
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
     * Send audio to the user/group.
     *
     * @param string $to            The recipient.
     * @param string $filepath      The url/uri to the audio file.
     * @param bool   $storeURLmedia Keep copy of file
     * @param int    $fsize
     * @param string $fhash         *
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageAudio($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = "")
    {
        if ($fsize == 0 || $fhash == "") {
            $allowedExtensions = array('3gp', 'caf', 'wav', 'mp3', 'wma', 'ogg', 'aif', 'aac', 'm4a');
            $size = 10 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'audio', $allowedExtensions, $storeURLmedia);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'audio', $fsize, $filepath, $to);
        }
    }

    /**
     * Send an image file to group/user.
     *
     * @param string $to            Recipient number
     * @param string $filepath      The url/uri to the image file.
     * @param bool   $storeURLmedia Keep copy of file
     * @param int    $fsize         size of the media file
     * @param string $fhash         base64 hash of the media file
     * @param string $caption
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageImage($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if ($fsize == 0 || $fhash == "") {
            $allowedExtensions = array('jpg', 'jpeg', 'gif', 'png');
            $size = 5 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'image', $allowedExtensions, $storeURLmedia, $caption);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'image', $fsize, $filepath, $to, $caption);
        }
    }

     /**
     * Send a video to the user/group.
     *
     * @param string $to            The recipient to send.
     * @param string $filepath      A URL/URI to the MP4/MOV video.
     * @param bool   $storeURLmedia Keep a copy of media file.
     * @param int    $fsize         Size of the media file
     * @param string $fhash         base64 hash of the media file
     * @param string $caption       *
     * @return string|null          Message ID if successfully, null if not.
     */
    public function sendMessageVideo($to, $filepath, $storeURLmedia = false, $fsize = 0, $fhash = "", $caption = "")
    {
        if ($fsize == 0 || $fhash == "") {
            $allowedExtensions = array('3gp', 'mp4', 'mov', 'avi');
            $size = 20 * 1024 * 1024; // Easy way to set maximum file size for this media type.
            // Return message ID. Make pull request for this.
            return $this->sendCheckAndSendMedia($filepath, $size, $to, 'video', $allowedExtensions, $storeURLmedia, $caption);
        } else {
            // Return message ID. Make pull request for this.
            return $this->sendRequestFileUpload($fhash, 'video', $fsize, $filepath, $to, $caption);
        }
    }

    /**
     * Set your profile picture.
     *
     * @param string $path URL/URI of image
     */
    public function sendSetProfilePicture($path)
    {
        $this->sendSetPicture($this->instance->getPhoneNumber(), $path);
    }

    /**
     * Set the recovery token for your account to allow you to retrieve your password at a later stage.
     *
     * @param  string $token A user generated token.
     */
    public function sendSetRecoveryToken($token)
    {
        $child = new ProtocolNode("pin",
            array(
                "xmlns" => "w:ch:p"
            ), null, $token);

        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createMsgId(),
                "type" => "set",
                "to" => Config::$FUNXMPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
    }

    /**
     * Update the user status.
     *
     * @param string $txt The text of the message status to send.
     */
    public function sendStatusUpdate($txt)
    {
        $child = new ProtocolNode("status", null, null, $txt);
        $node = new ProtocolNode("iq",
            array(
                "to" => Config::$FUNXMPP_SERVER,
                "type" => "set",
                "id" => $this->createMsgId(),
                "xmlns" => "status"
            ), array($child), null);

        $this->sendNode($node);
        $this->instance->eventManager()->fire("onSendStatusUpdate",
            array(
                $this->getPhoneNumber(),
                $txt
            ));
    }

    /**
     * Send a vCard to the user/group.
     *
     * @param string $to    The recipient to send.
     * @param string $name  The contact name.
     * @param object $vCard The contact vCard to send.
     * @return string       Message ID
     */
    public function sendVcard($to, $name, $vCard)
    {
        $vCardNode = new ProtocolNode("vcard",
            array(
                "name" => $name
            ), null, $vCard);

        $mediaNode = new ProtocolNode("media",
            array(
                "type" => "vcard"
            ), array($vCardNode), "");

        // Return message ID. Make pull request for this.
        return $this->sendMessageNode($to, $mediaNode);
    }

    /**
     * Send a vCard to the user/group as Broadcast.
     *
     * @param array  $targets An array of recipients to send to.
     * @param string $name    The vCard contact name.
     * @param object $vCard   The contact vCard to send.
     * @return string         Message ID
     */
    public function sendBroadcastVcard($targets, $name, $vCard)
    {
        $vCardNode = new ProtocolNode("vcard",
            array(
                "name" => $name
            ), null, $vCard);

        $mediaNode = new ProtocolNode("media",
            array(
                "type" => "vcard"
            ), array($vCardNode), "");

        // Return message ID. Make pull request for this.
        return $this->sendBroadcast($targets, $mediaNode, "media");
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
     * Send a request to get the current server properties.
     */
    public function sendGetServerProperties()
    {
        $id = $this->createMsgId();
        $child = new ProtocolNode("props", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $id,
                "type" => "get",
                "xmlns" => "w",
                "to" => Config::$FUNXMPP_SERVER
            ), array($child), null);

        $this->sendNode($node);
    }

    /**
     * Send a request to get the current service pricing.
     *
     *  @param string $lg
     *   Language
     *  @param string $lc
     *   Country
     */
    public function sendGetServicePricing($lg, $lc)
    {
        $msgId = $this->createMsgId();
        $pricingNode = new ProtocolNode("pricing",
            array(
                "lg" => $lg,
                "lc" => $lc
            ), null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "urn:xmpp:whatsapp:account",
                "type" => "get",
                "to" => Config::$FUNXMPP_SERVER
            ), array($pricingNode), null);

        $this->sendNode($node);
    }

    /**
     * Get the current status message of a specific user.
     *
     * @param mixed $jids The users' JIDs
     */
    public function sendGetStatuses($jids)
    {
        if (!is_array($jids)) {
            $jids = array($jids);
        }

        $children = array();
        foreach ($jids as $jid) {
            $children[] = new ProtocolNode("user", array("jid" => $this->instance->getJID($jid)), null, null);
        }

        $node = new ProtocolNode("iq",
            array(
                "to" => Config::$FUNXMPP_SERVER,
                "type" => "get",
                "xmlns" => "status",
                "id" => $this->createMsgId()
            ), array(
                new ProtocolNode("status", null, $children, null)
            ), null);

        $this->sendNode($node);
    }

    /**
     * Create a group chat.
     *
     * @param string $subject
     *   The group Subject
     * @param array $participants
     *   An array with the participants numbers.
     *
     * @return string
     *   The group ID.
     */
    public function sendGroupsChatCreate($subject, $participants)
    {
        if (!is_array($participants)) {
            $participants = array($participants);
        }

        $participantNode = array();
        foreach ($participants as $participant) {
            $participantNode[] = new ProtocolNode("participant", array(
                "jid" => $this->instance->getJID($participant)
            ), null, null);
        }

        $id = $this->nodeId['groupcreate'] = $this->createMsgId();

        $createNode = new ProtocolNode("create",
            array(
                "subject" => $subject
            ), $participantNode, null);

        $iqNode = new ProtocolNode("iq",
            array(
                "xmlns" => "w:g2",
                "id" => $id,
                "type" => "set",
                "to" => Config::$FUNXMPP_GROUP_SERVER
            ), array($createNode), null);

        $this->sendNode($iqNode);
        $this->waitForServer($id);
        $groupId = $this->groupId;

        $this->eventManager()->fire("onGroupCreate",
            array(
                $this->getPhoneNumber(),
                $groupId
            ));

        return $groupId;
    }

    /**
     * Change group's subject.
     *
     * @param string $gjid    The group id
     * @param string $subject The subject
     */
    public function sendSetGroupSubject($gjid, $subject)
    {
        $child = new ProtocolNode("subject", null, null, $subject);
        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createMsgId(),
                "type" => "set",
                "to" => $this->instance->getJID($gjid),
                "xmlns" => "w:g2"
            ), array($child), null);

        $this->sendNode($node);
    }

    /**
     * Leave a group chat.
     *
     * @param mixed $gjids Group or group's ID(s)
     */
    public function sendGroupsLeave($gjids)
    {
        $msgId = $this->nodeId['leavegroup'] = $this->createMsgId();

        if (!is_array($gjids)) {
            $gjids = array($this->instance->getJID($gjids));
        }

        $nodes = array();
        foreach ($gjids as $gjid) {
            $nodes[] = new ProtocolNode("group",
                array(
                    "id" => $this->instance->getJID($gjid)
                ), null, null);
        }

        $leave = new ProtocolNode("leave",
            array(
                'action'=>'delete'
            ), $nodes, null);

        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "to" => Config::$FUNXMPP_GROUP_SERVER,
                "type" => "set",
                "xmlns" => "w:g2"
            ), array($leave), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
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
        $id = $this->sendMessageNode($to, $bodyNode, $id);
        $this->waitForServer($id);

        if ($this->instance->getMessageStore() !== null) {
            $this->instance->getMessageStore()->saveMessage($this->instannce->getPhoneNumber(), $to, $txt, $id, time());
        }

        return $id;
    }

    /**
     * Add participant(s) to a group.
     *
     * @param string $groupId      The group ID.
     * @param mixed  $participants An array with the participants numbers to add
     */
    public function sendGroupsParticipantsAdd($groupId, $participants)
    {
        $msgId = $this->createMsgId();
        if (!is_array($participants)) {
            $participants = array($participants);
        }
        $this->sendGroupsChangeParticipants($groupId, $participants, 'add', $msgId);
    }

    /**
     * Remove participant(s) from a group.
     *
     * @param string $groupId      The group ID.
     * @param mixed  $participants An array with the participants numbers to remove
     */
    public function sendGroupsParticipantsRemove($groupId, $participants)
    {
        $msgId = $this->createMsgId();
        if (!is_array($participants)) {
            $participants = array($participants);
        }
        $this->sendGroupsChangeParticipants($groupId, $participants, 'remove', $msgId);
    }

    /**
     * Promote participant(s) of a group; Make a participant an admin of a group.
     *
     * @param string $gId          The group ID.
     * @param mixed  $participants An array with the participants numbers to promote
     */
    public function sendPromoteParticipants($gId, $participants)
    {
        $msgId = $this->createMsgId();
        if (!is_array($participants)) {
            $participants = array($participants);
        }
        $this->sendGroupsChangeParticipants($gId, $participants, "promote", $msgId);
    }

    /**
     * Demote participant(s) of a group; remove participant of being admin of a group.
     *
     * @param string $gId          The group ID.
     * @param array  $participants An array with the participants numbers to demote
     */
    public function sendDemoteParticipants($gId, $participants)
    {
        $msgId = $this->createMsgId();
        if (!is_array($participants)) {
            $participants = array($participants);
        }
        $this->sendGroupsChangeParticipants($gId, $participants, "demote", $msgId);
    }

    /**
     * Lock group: participants cant change group subject or profile picture except admin.
     *
     * @param string $gId The group ID.
     */
    public function sendLockGroup($gId)
    {
        $msgId = $this->createMsgId();
        $lockedNode = new ProtocolNode("locked", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:g2",
                "type" => "set",
                "to" => $this->getJID($gId)
            ), array($lockedNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Unlock group: Any participant can change group subject or profile picture.
     *
     *
     * @param string $gId The group ID.
     */
    public function sendUnlockGroup($gId)
    {
        $msgId = $this->createMsgId();
        $unlockedNode = new ProtocolNode("unlocked", null, null, null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $msgId,
                "xmlns" => "w:g2",
                "type" => "set",
                "to" => $this->getJID($gId)
            ), array($unlockedNode), null);

        $this->sendNode($node);
        $this->waitForServer($msgId);
    }

    /**
     * Set the list of numbers you wish to block receiving from.
     *
     * @param mixed $blockedJids One or more numbers to block messages from.
     */
    public function sendSetPrivacyBlockedList($blockedJids = array())
    {
        if (!is_array($blockedJids)) {
            $blockedJids = array($blockedJids);
        }

        $items = array();
        foreach ($blockedJids as $index => $jid) {
            $item = new ProtocolNode("item",
                array(
                    "type" => "jid",
                    "value" => $this->instance->getJID($jid),
                    "action" => "deny",
                    "order" => $index + 1
                ), null, null);
            $items[] = $item;
        }

        $child = new ProtocolNode("list",
            array(
                "name" => "default"
            ), $items, null);

        $child2 = new ProtocolNode("query", null, array($child), null);
        $node = new ProtocolNode("iq",
            array(
                "id" => $this->createMsgId(),
                "xmlns" => "jabber:iq:privacy",
                "type" => "set"
            ), array($child2), null);

        $this->sendNode($node);
    }

}