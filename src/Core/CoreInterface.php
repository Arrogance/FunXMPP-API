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

use FunXMPP\Core\MessageStore\SqliteMessageStore;

interface CoreInterface
{

    public function eventManager();

    public function setConnection(&$connection);
    public function connection();

    public function setPhoneNumber($phoneNumber);
    public function getPhoneNumber();

    public function setIdentity($identity);
    public function getIdentity();

    public function setDataPath($dataPath);
    public function getDataPath();

    public function setLoginTime($loginTime);
    public function getLoginTime();

    public function setSocket($socket);
    public function getSocket();

    public function getWriter();
    public function getReader();

    public function setMessageStore(SqliteMessageStore $messageStore);
    public function getMessageStore();

    public function setNewMessageBind($bind);
    public function getNewMessageBind();

    public function setMessageCounter($messageCounter);
    public function getMessageCounter();
    public function sumMessageCounter();

    public function setMediaFileInfo($mediaFileInfo);
    public function getMediaFileInfo();

    public function setMediaQueue($mediaQueue);
    public function getMediaQueue();

    public function setPassword($password);
    public function getPassword();

    public function setLoginStatus($loginStatus);
    public function getLoginStatus();

    public function setChallengeData($challengeData);
    public function getChallengeData();

    public function setName($name);
    public function getName();

    public function setMessageQueue($messageQueue);
    public function getMessageQueue();

    public function setOutQueue($outQueue);
    public function getOutQueue();

    public function setLastId($lastId);
    public function getLastId();

}