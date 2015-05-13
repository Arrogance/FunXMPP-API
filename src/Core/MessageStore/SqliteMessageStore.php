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

namespace FunXMPP\Core\MessageStore;

use FunXMPP\Core\MessageStore\MessageStoreInterface;
use FunXMPP\Core\Config;

use FunXMPP\Util\Helpers;

class SqliteMessageStore implements MessageStoreInterface
{

    /**
     * @var PDO Object
     */
    private $db;

    /**
     * 
     */
    public function __construct($number)
    {
        $fileName = Helpers::fileBuildPath(Config::$DATA_PATH, Config::$DATA_FOLDER, 'msgstore-'.$number.'.db');
        $createTable = !file_exists($fileName);

        $this->db = new \PDO("sqlite:" . $fileName, null, null, array(\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
        if ($createTable)
        {
            $this->db->exec('CREATE TABLE messages (`from` TEXT, `to` TEXT, message TEXT, id TEXT, t TEXT)');
        }
    }

    /**
     * 
     */
    public function saveMessage($from, $to, $txt, $id, $t)
    {
        $sql = 'INSERT INTO messages (`from`, `to`, message, id, t) VALUES (:from, :to, :message, :messageId, :t)';
        $query = $this->db->prepare($sql);

        $query->execute(
            array(
                ':from' => $from,
                ':to' => $to,
                ':message' => $txt,
                ':messageId' => $id,
                ':t' => $t
            )
        );
    }

}
