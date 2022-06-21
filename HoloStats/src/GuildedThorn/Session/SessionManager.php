<?php

namespace GuildedThorn\Session;

use GuildedThorn\Main;
use pocketmine\player\Player;

class SessionManager {

    private static array $sessions = [];

    public static function RegisterPlayer(string $xuid, $kills, $deaths, $tag) : Session {
        return self::$sessions[$xuid] = new Session($kills, $deaths, $tag);
    }

    public static function GetSession(string $xuid) : Session {
        return self::$sessions[$xuid];
    }

    public static function CloseSession(Player $player) : void {
        Main::getDatabaseConnector()->executeChange("Stats.Update", [
            "xuid" => $player->getXuid(),
            "username" => $player->getName(),
            "kills" => SessionManager::GetSession($player->getXuid())->getKills(),
            "deaths" => SessionManager::GetSession($player->getXuid())->getDeaths(),
            "tag" => SessionManager::GetSession($player->getXuid())->getTag()
        ]);
        unset(self::$sessions[$player->getXuid()]);
    }

    public static function update(Player $player):void
    {
        Main::getDatabaseConnector()->executeChange("Stats.UpdateKD", [
            "kills" => self::GetSession($player->getXuid())->getKills(),
            "deaths" => self::GetSession($player->getXuid())->getDeaths(),
            "tag" => self::GetSession($player->getXuid())->getTag(),
            "xuid" => $player->getXuid()
        ]);
    }
}