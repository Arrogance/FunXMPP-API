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

use FunXMPP\FunXMPP;

use FunXMPP\Core\Exception\CustomException;
use FunXMPP\Core\Constants;
use FunXMPP\Core\Server;

class Account
{

    /**
     * @var FunXMPP
     */
    protected $instance;

    /**
     * @var string
     */
    protected $phoneNumber;

    public function __construct(FunXMPP &$instance)
    {
        $this->instance = $instance;
    }

	/**
     * Check if account credentials are valid.
     *
     * WARNING: WhatsApp now changes your password everytime you use this.
     * Make sure you update your config file if the output informs about
     * a password change.
     *
     * @return object
     *   An object with server response.
     *   - status: Account status.
     *   - login: Phone number with country code.
     *   - pw: Account password.
     *   - type: Type of account.
     *   - expiration: Expiration date in UNIX TimeStamp.
     *   - kind: Kind of account.
     *   - price: Formatted price of account.
     *   - cost: Decimal amount of account.
     *   - currency: Currency price of account.
     *   - price_expiration: Price expiration in UNIX TimeStamp.
     *
     * @throws CustomException
     */
    public function checkCredentials()
    {
        if (!$phone = $this->dissectPhone()) {
            throw new CustomException('The provided phone number is not valid.');
        }

        $countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
        $langCode    = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

        if ($phone['cc'] == '77' || $phone['cc'] == '79') {
            $phone['cc'] = '7';
        }

        $host  = 'https://' . Constants::WHATSAPP_CHECK_HOST;
        $query = array(
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode
        );

        $response = $this->instance->server()->getResponse($host, $query);

        if ($response->status != 'ok') {
            $this->instance->eventManager()->fire("onCredentialsBad",
                array(
                    $this->phoneNumber,
                    $response->status,
                    $response->reason
                ));

            $this->instance->debugPrint($query);
            $this->instance->debugPrint($response);

            throw new CustomException('There was a problem trying to request the code.');
        } else {
            $this->instance->eventManager()->fire("onCredentialsGood",
                array(
                    $this->phoneNumber,
                    $response->login,
                    $response->pw,
                    $response->type,
                    $response->expiration,
                    $response->kind,
                    $response->price,
                    $response->cost,
                    $response->currency,
                    $response->price_expiration
                ));
        }

        return $response;
    }

    /**
     * Dissect country code from phone number.
     *
     * @return array
     *   An associative array with country code and phone number.
     *   - country: The detected country name.
     *   - cc: The detected country code (phone prefix).
     *   - phone: The phone number.
     *   - ISO3166: 2-Letter country code
     *   - ISO639: 2-Letter language code
     *   Return false if country code is not found.
     */
    protected function dissectPhone()
    {
        if (($handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . Constants::RESOURCES_FOLDER . DIRECTORY_SEPARATOR .'countries.csv', 'rb')) !== false) {
            while (($data = fgetcsv($handle, 1000)) !== false) {
                if (strpos($this->phoneNumber, $data[1]) === 0) {
                    // Return the first appearance.
                    fclose($handle);

                    $mcc = explode("|", $data[2]);
                    $mcc = $mcc[0];

                    //hook:
                    //fix country code for North America
                    if ($data[1][0] == "1") {
                        $data[1] = "1";
                    }

                    $phone = array(
                        'country' => $data[0],
                        'cc' => $data[1],
                        'phone' => substr($this->phoneNumber, strlen($data[1]), strlen($this->phoneNumber)),
                        'mcc' => $mcc,
                        'ISO3166' => @$data[3],
                        'ISO639' => @$data[4],
                        'mnc' => $data[5]
                    );

                    $this->instance->eventManager()->fire("onDissectPhone",
                        array(
                            $this->phoneNumber,
                            $phone['country'],
                            $phone['cc'],
                            $phone['phone'],
                            $phone['mcc'],
                            $phone['ISO3166'],
                            $phone['ISO639'],
                            $phone['mnc']
                        )
                    );

                    return $phone;
                }
            }
            fclose($handle);
        }

        $this->instance->eventManager()->fire("onDissectPhoneFailed",
            array(
                $this->phoneNumber
            ));

        return false;
    }

}