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

namespace FunXMPP\Core\Config;

abstract class ConfigMethods implements ConfigInterface
{

    public static $DISCONNECTED_STATUS;
    public static $CONNECTED_STATUS;
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
    public static $STORE_MESSAGES;

    /**
     * Set CONNECTED_STATUS variable
     *
     * @param mixed $value
     */
    public static function CONNECTED_STATUS($value) 
    { 
        static::$CONNECTED_STATUS = $value; 
    }

    /**
     * Set DISCONNECTED_STATUS variable
     *
     * @param mixed $value
     */
    public static function DISCONNECTED_STATUS($value) 
    { 
        static::$DISCONNECTED_STATUS = $value; 
    }

    /**
     * Set FUNXMPP_VER variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_VER($value) 
    { 
        static::$FUNXMPP_VER = $value; 
    }
    
    /**
     * Set FUNXMPP_USER_AGENT variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_USER_AGENT($value) 
    { 
        static::$FUNXMPP_USER_AGENT = $value; 
    }

    /**
     * Set FUNXMPP_CHECK_HOST variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_CHECK_HOST($value) 
    { 
        static::$FUNXMPP_CHECK_HOST = $value; 
    }

    /**
     * Set FUNXMPP_GROUP_SERVER variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_GROUP_SERVER($value) 
    { 
        static::$FUNXMPP_GROUP_SERVER = $value; 
    }

    /**
     * Set FUNXMPP_REQUEST_HOST variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_REQUEST_HOST($value)
    {
        static::$FUNXMPP_REQUEST_HOST = $value;
    }

    /**
     * Set FUNXMPP_REGISTER_HOST variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_REGISTER_HOST($value) 
    { 
        static::$FUNXMPP_REGISTER_HOST = $value; 
    }

    /**
     * Set FUNXMPP_SERVER variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_SERVER($value) 
    { 
        static::$FUNXMPP_SERVER = $value; 
    }

    /**
     * Set FUNXMPP_CONNECT_SERVER variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_CONNECT_SERVER($value) 
    { 
        static::$FUNXMPP_CONNECT_SERVER = $value; 
    }

    /**
     * Set FUNXMPP_DEVICE variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_DEVICE($value) 
    { 
        static::$FUNXMPP_DEVICE = $value; 
    }

    /**
     * Set FUNXMPP_VER_CHECKER variable
     *
     * @param mixed $value
     */
    public static function FUNXMPP_VER_CHECKER($value) 
    { 
        static::$FUNXMPP_VER_CHECKER = $value; 
    }

    /**
     * Set PORT variable
     *
     * @param mixed $value
     */
    public static function PORT($value) 
    { 
        static::$PORT = $value; 
    }

    /**
     * Set TIMEOUT_SEC variable
     *
     * @param mixed $value
     */
    public static function TIMEOUT_SEC($value) 
    { 
        static::$TIMEOUT_SEC = $value; 
    }

    /**
     * Set TIMEOUT_USEC variable
     *
     * @param mixed $value
     */
    public static function TIMEOUT_USEC($value) 
    { 
        static::$TIMEOUT_USEC = $value; 
    }

    /**
     * Set DATA_PATH variable
     *
     * @param mixed $value
     */
    public static function DATA_PATH($value) 
    { 
        static::$DATA_PATH = $value; 
    }

    /**
     * Set MEDIA_FOLDER variable
     *
     * @param mixed $value
     */
    public static function MEDIA_FOLDER($value) 
    { 
        static::$MEDIA_FOLDER = $value; 
    }

    /**
     * Set PICTURES_FOLDER variable
     *
     * @param mixed $value
     */
    public static function PICTURES_FOLDER($value) 
    { 
        static::$PICTURES_FOLDER = $value; 
    }

    /**
     * Set DATA_FOLDER variable
     *
     * @param mixed $value
     */
    public static function DATA_FOLDER($value) 
    { 
        static::$DATA_FOLDER = $value; 
    }

    /**
     * Set RESOURCES_FOLDER variable
     *
     * @param mixed $value
     */
    public static function RESOURCES_FOLDER($value) 
    { 
        static::$RESOURCES_FOLDER = $value; 
    }

    /**
     * Set RELEASE_TIME variable
     *
     * @param mixed $value
     */
    public static function RELEASE_TIME($value) 
    { 
        static::$RELEASE_TIME = $value; 
    }

    /**
     * Set STORE_MESSAGES variable
     *
     * @param bool $value
     */
    public static function STORE_MESSAGES($value)
    {
        static::$STORE_MESSAGES = $value;
    }

}