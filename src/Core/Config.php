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

use FunXMPP\Core\Config\ConfigMethods;

use FunXMPP\Core\Exception\CustomException;
use FunXMPP\Util\Helpers;

class Config extends ConfigMethods
{

    /**
     * Read and save into static variables the settings stored in serverinfo.json
     * file. But first call checkConfigFile() method to verify if files are ok.
     */
    public static function generateConfig()
    {
        Config::checkConfigFile();

        $config = file_get_contents(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json'));
        $configArray = json_decode($config, true);

        foreach($configArray as $key => $value) {
            static::$key($value);
        }
    }

    /**
     * Save this class static variables into the serverinfo.json file.
     */
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

    /**
     * Check of the serverinfo.json file exists. If not, then copy all settings 
     * from the serverinfo.json.dist
     */
    public static function checkConfigFile()
    {
        if (@file_exists((Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json')))) {
            return;
        }
        else if (!@file_exists((Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json.dist')))) {
            throw new CustomException('Unable to open serverinfo.json.dist file.');
        }

        $config = file_get_contents(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json.dist'));
        file_put_contents(Helpers::fileBuildPath(__DIR__, '..', 'Resources', 'serverinfo.json'), $config);
    }

}