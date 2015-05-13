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

use FunXMPP\Core\CoreMethods;

use FunXMPP\Core\Config;
use FunXMPP\Core\Exception\CustomException;

use FunXMPP\Util\Helpers;

abstract class Core extends CoreMethods
{

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
     * Drain the message queue for application processing.
     *
     * @return ProtocolNode[]
     *   Return the message queue list.
     */
    public function getMessages()
    {
        $ret = $this->messageQueue;
        $this->messageQueue = array();

        return $ret;
    }

    /**
     * @return Core
     */
    public function setChallengeFilename($number)
    {
        $this->challengeFilename = sprintf('%sid.%s.dat', 
            Helpers::fileBuildPath(Config::$DATA_PATH, Config::$DATA_FOLDER, ''), 
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
            $identity_file = sprintf('%sid.%s.dat', Helpers::fileBuildPath(Config::$DATA_PATH, Config::$DATA_FOLDER, ''), $this->phoneNumber);
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

}