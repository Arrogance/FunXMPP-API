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

use FunXMPP\Core\Config;

class MediaUploader
{

    /**
     * 
     */
    protected static function sendData($host, $POST, $HEAD, $filepath, $mediafile, $TAIL)
    {
        $sock = fsockopen("ssl://" . $host, 443);

        fwrite($sock, $POST);
        fwrite($sock, $HEAD);

        //write file data
        $buf       = 1024;
        $totalread = 0;
        $fp        = fopen($filepath, "r");
        while ($totalread < $mediafile['filesize']) {
            $buff = fread($fp, $buf);
            fwrite($sock, $buff, $buf);
            $totalread += $buf;
        }
        //echo $TAIL;
        fwrite($sock, $TAIL);
        sleep(1);

        $data = fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        $data .= fgets($sock, 8192);
        fclose($sock);

        list($header, $body) = preg_split("/\R\R/", $data, 2);

        $json = json_decode($body);
        if ( ! is_null($json)) {
            return $json;
        }
        return false;
    }

    /**
     * 
     */
    public static function pushFile($uploadResponseNode, $messageContainer, $mediafile, $selfJID)
    {
        //get vars
        $url      = $uploadResponseNode->getChild("media")->getAttribute("url");
        $filepath = $messageContainer["filePath"];
        $to       = $messageContainer["to"];
        return self::getPostString($filepath, $url, $mediafile, $to, $selfJID);
    }

    /**
     * 
     */
    protected static function getPostString($filepath, $url, $mediafile, $to, $from)
    {
        $host = parse_url($url, PHP_URL_HOST);

        //filename to md5 digest
        $cryptoname    = md5($filepath) . "." . $mediafile['fileextension'];
        $boundary      = "zzXXzzYYzzXXzzQQ";

        if (is_array($to)) {
            $to = implode(',', $to);
        }

        $hBAOS = "--" . $boundary . "\r\n";
        $hBAOS .= "Content-Disposition: form-data; name=\"to\"\r\n\r\n";
        $hBAOS .= $to . "\r\n";
        $hBAOS .= "--" . $boundary . "\r\n";
        $hBAOS .= "Content-Disposition: form-data; name=\"from\"\r\n\r\n";
        $hBAOS .= $from . "\r\n";
        $hBAOS .= "--" . $boundary . "\r\n";
        $hBAOS .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . $cryptoname . "\"\r\n";
        $hBAOS .= "Content-Type: " . $mediafile['filemimetype'] . "\r\n\r\n";

        $fBAOS = "\r\n--" . $boundary . "--\r\n";

        $contentlength = strlen($hBAOS) + strlen($fBAOS) + $mediafile['filesize'];

        $POST = "POST " . $url . "\r\n";
        $POST .= "Content-Type: multipart/form-data; boundary=" . $boundary . "\r\n";
        $POST .= "Host: " . $host . "\r\n";
        $POST .= "User-Agent: " . Config::$WHATSAPP_USER_AGENT . "\r\n";
        $POST .= "Content-Length: " . $contentlength . "\r\n\r\n";

        return self::sendData($host, $POST, $hBAOS, $filepath, $mediafile, $fBAOS);
    }

}