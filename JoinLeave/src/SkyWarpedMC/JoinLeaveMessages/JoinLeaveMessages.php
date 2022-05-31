<?php

/*
 * Copyright 2022 SkyWarpedMC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace SkyWarpedMC\JoinLeaveMessages;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class JoinLeaveMessages extends PluginBase {

    private static self $instance;

    private ?string $joinMessage = null;
    private ?string $privateJoinMessage = null;
    private ?string $leaveMessage = null;

    /**
     * @return JoinLeaveMessages
     */
    public static function getInstance(): JoinLeaveMessages {
        return self::$instance;
    }

    protected function onLoad(): void {
        self::$instance = $this;
    }

    protected function onEnable(): void {
        $this->saveDefaultConfig();

        // Get configured messages
        $this->getConfig()->exists("join-message") ? $this->joinMessage = $this->getConfig()->get("join-message") : $this->joinMessage = null;
        $this->getConfig()->exists("private-join-message") ? $this->privateJoinMessage = $this->getConfig()->get("private-join-message") : $this->joinMessage = null;
        $this->getConfig()->exists("leave-message") ? $this->leaveMessage = $this->getConfig()->get("leave-message")  : $this->joinMessage = null;

        // Register event handler to send messages.
        $this->getServer()->getPluginManager()->registerEvents(new class() implements Listener {
            public function onPlayerJoin(PlayerJoinEvent $event): void {
                $joinMessage = JoinLeaveMessages::getInstance()->getJoinMessage();
                $event->setJoinMessage(isset($joinMessage) && $joinMessage !== "" ? TextFormat::colorize(str_replace("{player}", $event->getPlayer()->getName(), $joinMessage)) : "");
                $privateJoinMessage = JoinLeaveMessages::getInstance()->getPrivateJoinMessage();
                if (isset($privateJoinMessage) && $privateJoinMessage !== "") $event->getPlayer()->sendMessage(TextFormat::colorize(str_replace("{player}", $event->getPlayer()->getName(), $privateJoinMessage)));
            }

            public function onPlayerQuit(PlayerQuitEvent $event): void {
                $leaveMessage = JoinLeaveMessages::getInstance()->getLeaveMessage();
                $event->setQuitMessage(isset($leaveMessage) && $leaveMessage !== "" ? TextFormat::colorize(str_replace("{player}", $event->getPlayer()->getName(), $leaveMessage)) : "");
            }
        }, $this);
    }


    // Api Methods:

    /**
     * @return string|null
     */
    public function getJoinMessage(): ?string {
        return $this->joinMessage;
    }

    /**
     * @param string|null $joinMessage
     */
    public function setJoinMessage(?string $joinMessage): void {
        $this->joinMessage = $joinMessage;
    }

    /**
     * @return string|null
     */
    public function getPrivateJoinMessage(): ?string {
        return $this->privateJoinMessage;
    }

    /**
     * @param string|null $privateJoinMessage
     */
    public function setPrivateJoinMessage(?string $privateJoinMessage): void {
        $this->privateJoinMessage = $privateJoinMessage;
    }

    /**
     * @return string|null
     */
    public function getLeaveMessage(): ?string {
        return $this->leaveMessage;
    }

    /**
     * @param string|null $leaveMessage
     */
    public function setLeaveMessage(?string $leaveMessage): void {
        $this->leaveMessage = $leaveMessage;
    }
}