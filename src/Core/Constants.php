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

class Constants
{

    /**
     * The relative folder to store received media files
     */
    const MEDIA_FOLDER = 'media';

    /**
     * The relative folder to store picture files
     */
    const PICTURES_FOLDER = 'pictures';

    /**
     * The relative folder to store cache files.
     */
    const DATA_FOLDER = 'wadata';

    /**
     * The relative folder to resources folder
     */
    const RESOURCES_FOLDER = 'Resources'

    /**
     * Describes the connection status with the server
     */
    const CONNECTED_STATUS = 'connected';

    /**
     * Describes the connection status with the server
     */
    const DISCONNECTED_STATUS = 'disconnected';

    /**
     * The port of the server
     */
    const PORT = 443;

    /**
     * The timeout for the connection
     */
    const TIMEOUT_SEC = 2;
    const TIMEOUT_USEC = 0;

    /**
     * The check credentials host
     */
    const FUNXMPP_CHECK_HOST = 'v.whatsapp.net/v2/exist';

    /**
     * The group server hostname
     */
    const FUNXMPP_GROUP_SERVER = 'g.us';

    /**
     * The register code host
     */
    const FUNXMPP_REGISTER_HOST = 'v.whatsapp.net/v2/register';

    /**
     * The request code host
     */
    const FUNXMPP_REQUEST_HOST = 'v.whatsapp.net/v2/code';

    /**
     * The hostname used to login/send messages
     */
    const FUNXMPP_SERVER = 's.whatsapp.net';

    /**
     * The device name
     */
    const FUNXMPP_DEVICE = 'S40';

    /**
     * The FunXMPP Client version
     */
    const FUNXMPP_VER = '2.12.81';

    /**
     * The user agent used in request/registration code
     */
    const FUNXMPP_USER_AGENT = 'WhatsApp/2.12.81 S40Version/14.26 Device/Nokia302';

    /**
     * Check FunXMPP Client version (WhatsApp in this case)
     */
    const FUNXMPP_VER_CHECKER = 'https://coderus.openrepos.net/whitesoft/whatsapp_scratch';

}