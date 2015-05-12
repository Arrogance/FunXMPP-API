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
use FunXMPP\Core\Config;

use FunXMPP\Util\Helpers;

class Account
{

    /**
     * @var Core
     */
    protected $instance;

    /**
     * @var mixed
     */
    protected $phoneNumber;

    /**
     * @var mixed
     */
    protected $identity;

    public function __construct(Core &$instance)
    {
        $this->instance = $instance;
        $this->phoneNumber = $instance->getPhoneNumber();
        $this->identity = $instance->getIdentity();
    }

	/**
     * Request a registration code from WhatsApp.
     *
     * @param string $method Accepts only 'sms' or 'voice' as a value.
     * @param string $carrier
     *
     * @return object
     *   An object with server response.
     *   - status: Status of the request (sent/fail).
     *   - length: Registration code lenght.
     *   - method: Used method.
     *   - reason: Reason of the status (e.g. too_recent/missing_param/bad_param).
     *   - param: The missing_param/bad_param.
     *   - retry_after: Waiting time before requesting a new code.
     *
     * @throws CustomException
     */
    public function codeRequest($method = 'sms', $carrier = "T-Mobile5")
    {
        if (!$phone = $this->dissectPhone()) {
            throw new CustomException('The provided phone number is not valid.');
        }

        $countryCode = ($phone['ISO3166'] != '') ? $phone['ISO3166'] : 'US';
        $langCode    = ($phone['ISO639'] != '') ? $phone['ISO639'] : 'en';

        if ($carrier != null) {
            $mnc = $this->detectMnc(strtolower($countryCode), $carrier);
        } else {
            $mnc = $phone['mnc'];
        }

        // Build the token.
        $token = Helpers::generateRequestToken($phone['country'], $phone['phone'], Config::$RELEASE_TIME);

        // Build the url.
        $host = 'https://' . Config::$FUNXMPP_REQUEST_HOST;
        $query = array(
            'in' => $phone['phone'],
            'cc' => $phone['cc'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode,
            'sim_mcc' => $phone['mcc'],
            'sim_mnc' => $mnc,
            'method' => $method,
            'token' => $token,
        );

        $this->instance->debugPrint($query);

        $response = $this->instance->getResponse($host, $query);

        $this->instance->debugPrint($response);

        if ($response->status == 'ok') {
            $this->instance->eventManager()->fire("onCodeRegister",
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
        } else if ($response->status != 'sent') {
            if (isset($response->reason) && $response->reason == "too_recent") {
                $this->instance->eventManager()->fire("onCodeRequestFailedTooRecent",
                    array(
                        $this->phoneNumber,
                        $method,
                        $response->reason,
                        $response->retry_after
                    ));
                $minutes = round($response->retry_after / 60);
                throw new CustomException("Code already sent. Retry after $minutes minutes.");

            } else if (isset($response->reason) && $response->reason == "too_many_guesses") {
                $this->instance->eventManager()->fire("onCodeRequestFailedTooManyGuesses",
                    array(
                        $this->phoneNumber,
                        $method,
                        $response->reason,
                        $response->retry_after
                    ));
                $minutes = round($response->retry_after / 60);
                throw new CustomException("Too many guesses. Retry after $minutes minutes.");

            }  else {
                $this->instance->eventManager()->fire("onCodeRequestFailed",
                    array(
                        $this->phoneNumber,
                        $method,
                        $response->reason,
                        isset($response->param) ? $response->param : NULL
                    ));
                throw new CustomException('There was a problem trying to request the code.');
            }
        } else {
            $this->instance->eventManager()->fire("onCodeRequest",
                array(
                    $this->phoneNumber,
                    $method,
                    $response->length
                ));
        }

        return $response;
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
     * @throws Exception
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

        // Build the url.
        $host  = 'https://' . Config::$FUNXMPP_CHECK_HOST;
        $query = array(
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'lg' => $langCode,
            'lc' => $countryCode,
        );

        $response = $this->instance->getResponse($host, $query);

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

    public function update()
    {
        $WAData = json_decode(file_get_contents(Config::$WHATSAPP_VER_CHECKER), true);

        var_dump($WAData); die();

        if (Config::$WHATSAPP_VER != $WAData['e']) {
            Config::RELEASE_TIME($WAData['h']);
            Config::WHATSAPP_VER($WAData['e']);
            Config::WHATSAPP_VER('WhatsApp/' . trim($WAData['e']) . ' S40Version/14.26 Device/Nokia302');
            
            Config::updateConfig();
        }
    }

    /**
     * Register account on WhatsApp using the provided code.
     *
     * @param integer $code
     *   Numeric code value provided on requestCode().
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
    public function codeRegister($code)
    {
        if (!$phone = $this->dissectPhone()) {
            throw new CustomException('The provided phone number is not valid.');
        }

        // Build the url.
        $host = 'https://' . Config::$FUNXMPP_REGISTER_HOST;
        $query = array(
            'cc' => $phone['cc'],
            'in' => $phone['phone'],
            'id' => $this->identity,
            'code' => $code
        );

        $response = $this->instance->getResponse($host, $query);

        if ($response->status != 'ok') {
            $this->instance->eventManager()->fire("onCodeRegisterFailed",
                array(
                    $this->phoneNumber,
                    $response->status,
                    $response->reason,
                    isset($response->retry_after) ? $response->retry_after : null
                ));

            $this->instance->debugPrint($query);
            $this->instance->debugPrint($response);

            if ($response->reason == 'old_version')
                $this->update();

            throw new CustomException("An error occurred registering the registration code from FunXMPP network. Reason: $response->reason");
        } else {
            $this->instance->eventManager()->fire("onCodeRegister",
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
        if (($handle = fopen(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'countries.csv'), 'rb')) !== false) {
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

    /**
     * Detects mnc from specified carrier.
     *
     * @param string $lc          LangCode
     * @param string $carrierName Name of the carrier
     * @return string
     *
     * Returns mnc value
     */
    protected function detectMnc($lc, $carrierName)
    {
        $fp = fopen(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'networkinfo.csv'), 'r');
        $mnc = null;

        while ($data = fgetcsv($fp, 0, ',')) {
            if ($data[4] === $lc && $data[7] === $carrierName) {
                $mnc = $data[2];
                break;
            }
        }

        if ($mnc == null) {
            $mnc = '000';
        }

        fclose($fp);

        return $mnc;
    }

}