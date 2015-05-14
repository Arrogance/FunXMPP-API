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

interface ConfigInterface
{

    public static function CONNECTED_STATUS($value);
    public static function DISCONNECTED_STATUS($value);
    public static function FUNXMPP_VER($value);
    public static function FUNXMPP_USER_AGENT($value);
    public static function FUNXMPP_CHECK_HOST($value);
    public static function FUNXMPP_GROUP_SERVER($value);
    public static function FUNXMPP_REQUEST_HOST($value);
    public static function FUNXMPP_REGISTER_HOST($value);
    public static function FUNXMPP_SERVER($value);
    public static function FUNXMPP_CONNECT_SERVER($value);
    public static function FUNXMPP_DEVICE($value);
    public static function FUNXMPP_VER_CHECKER($value);
    public static function PORT($value);
    public static function TIMEOUT_SEC($value);
    public static function TIMEOUT_USEC($value);
    public static function DATA_PATH($value);
    public static function MEDIA_FOLDER($value);
    public static function PICTURES_FOLDER($value);
    public static function DATA_FOLDER($value);
    public static function RESOURCES_FOLDER($value);
    public static function RELEASE_TIME($value);
    public static function STORE_MESSAGES($value);

}