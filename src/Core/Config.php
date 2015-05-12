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
use FunXMPP\Util\Helpers;

class Config
{

    public static $DISCONNECTED_STATUS = 'disconnected';
    public static $CONNECTED_STATUS = 'connected';
    public static $FUNXMPP_VER;
    public static $FUNXMPP_USER_AGENT;
    public static $FUNXMPP_CHECK_HOST;
    public static $FUNXMPP_GROUP_SERVER;
    public static $FUNXMPP_REGISTER_HOST;
    public static $FUNXMPP_REQUEST_HOST;
    public static $FUNXMPP_SERVER;
    public static $FUNXMPP_CONNECT_SERVER;
    public static $FUNXMPP_DEVICE;
    public static $FUNXMPP_VER_CHECKER;
    public static $PORT;
    public static $TIMEOUT_SEC;
    public static $TIMEOUT_USEC;
    public static $DATA_PATH;
    public static $MEDIA_FOLDER;
    public static $PICTURES_FOLDER;
    public static $DATA_FOLDER;
    public static $RESOURCES_FOLDER;
    public static $RELEASE_TIME;

    /**
     * 
     */
    public static function generateConfig()
    {
        $config = file_get_contents(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json'));
        $configArray = json_decode($config, true);

        foreach($configArray as $key => $value) {
            static::$key($value);
        }
    }

    public static function updateConfig()
    {
        $class = new \ReflectionClass('FunXMPP\\Core\\Config');
        $staticProperties = $class->getStaticProperties();

        if (!$configFile = @fopen(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json'), 'w')) {
            throw new CustomException('Unable to open serverinfo.json file.');
        }

        $data = json_encode($staticProperties, JSON_PRETTY_PRINT);
        $text = $data;
        fwrite($configFile, $text);

        fclose($configFile);
    }

    public static function CONNECTED_STATUS($value) 
    { 
        static::$CONNECTED_STATUS = $value; 
    }

    public static function DISCONNECTED_STATUS($value) 
    { 
        static::$DISCONNECTED_STATUS = $value; 
    }

    public static function FUNXMPP_VER($value) 
    { 
        static::$FUNXMPP_VER = $value; 
    }
    
    public static function FUNXMPP_USER_AGENT($value) 
    { 
        static::$FUNXMPP_USER_AGENT = $value; 
    }

    public static function FUNXMPP_CHECK_HOST($value) 
    { 
        static::$FUNXMPP_CHECK_HOST = $value; 
    }

    public static function FUNXMPP_GROUP_SERVER($value) 
    { 
        static::$FUNXMPP_GROUP_SERVER = $value; 
    }

    public static function FUNXMPP_REQUEST_HOST($value)
    {
        static::$FUNXMPP_REQUEST_HOST = $value;
    }

    public static function FUNXMPP_REGISTER_HOST($value) 
    { 
        static::$FUNXMPP_REGISTER_HOST = $value; 
    }

    public static function FUNXMPP_SERVER($value) 
    { 
        static::$FUNXMPP_SERVER = $value; 
    }

    public static function FUNXMPP_CONNECT_SERVER($value) 
    { 
        static::$FUNXMPP_CONNECT_SERVER = $value; 
    }

    public static function FUNXMPP_DEVICE($value) 
    { 
        static::$FUNXMPP_DEVICE = $value; 
    }

    public static function FUNXMPP_VER_CHECKER($value) 
    { 
        static::$FUNXMPP_VER_CHECKER = $value; 
    }

    public static function PORT($value) 
    { 
        static::$PORT = $value; 
    }

    public static function TIMEOUT_SEC($value) 
    { 
        static::$TIMEOUT_SEC = $value; 
    }

    public static function TIMEOUT_USEC($value) 
    { 
        static::$TIMEOUT_USEC = $value; 
    }

    public static function DATA_PATH($value) 
    { 
        static::$DATA_PATH = $value; 
    }

    public static function MEDIA_FOLDER($value) 
    { 
        static::$MEDIA_FOLDER = $value; 
    }

    public static function PICTURES_FOLDER($value) 
    { 
        static::$PICTURES_FOLDER = $value; 
    }

    public static function DATA_FOLDER($value) 
    { 
        static::$DATA_FOLDER = $value; 
    }

    public static function RESOURCES_FOLDER($value) 
    { 
        static::$RESOURCES_FOLDER = $value; 
    }

    public static function RELEASE_TIME($value) 
    { 
        static::$RELEASE_TIME = $value; 
    }

}