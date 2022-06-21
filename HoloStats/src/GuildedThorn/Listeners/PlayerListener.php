<?php

namespace GuildedThorn\Listeners;

use GuildedThorn\Main;
use GuildedThorn\Session\SessionManager;
use GuildedThorn\Utils\FloatingText;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\entity\{EntityDamageByEntityEvent, EntityDamageEvent};
use pocketmine\player\Player;
use pocketmine\entity\Location;

class PlayerListener implements Listener {

    private array $leaders = [];

    public function onLogin(PlayerLoginEvent $ev) {
        $player = $ev->getPlayer();
        if(!$player->hasPlayedBefore())
            Main::getMain()->getDatabaseConnector()->executeInsert("Stats.Create", ["xuid" => $player->getXuid(), "username" => $player->getName(), "kills" => 0, "deaths" => 0, "tag" => "§6Bronze§r"]);

        Main::getMain()->getDatabaseConnector()->executeSelect("Stats.Get", ["xuid" => $player->getXuid()], function (array $rows)use($player):void{
            $kills = 0;
            $deaths = 0;
            $tag = "§6Bronze§r";
            foreach($rows as $result)
            {
                $kills = $result["kills"];
                $deaths = $result["deaths"];
                $tag = $result["tag"];
            }
            SessionManager::RegisterPlayer($player->getXuid(), $kills, $deaths, $tag);
        });
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
    }
    
    public function onDamager(EntityDamageEvent $ev)
    {
        $entity = $ev->getEntity();
        
        if($ev instanceof EntityDamageByEntityEvent)
        {
            if($entity instanceof FloatingText) $ev->cancel();
        }
    }

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        SessionManager::CloseSession($player);

        foreach(Main::getMain()->getServer()->getWorldManager()->getWorldByName(Main::getMain()->getConfig()->get("kdr_location")["world"])->getEntities() as $entities) {
            if($entities instanceof FloatingText) {
                if($entities->getType() == "kdr") {
                    if($entities->getPlayerName() == $player->getName())
                        $entities->kill();
                }
            }
        }
    }

    /**
     * @priority HIGHEST
     * @param PlayerDeathEvent $event
     * @return void
     */
    public function onDeath(PlayerDeathEvent $event) : void {
        $player = $event->getPlayer();
        $ev = $player->getLastDamageCause();
        if($ev instanceof EntityDamageByEntityEvent)
        {
            $damager = $ev->getDamager();
            if ($damager instanceof Player) {
                SessionManager::GetSession($player->getXuid())->addDeath();
                SessionManager::GetSession($damager->getXuid())->addKill();
                SessionManager::update($player);
                SessionManager::update($damager);
            }
        }
    }

    public function onWorldChange(EntityTeleportEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if (Main::getMain()->getServer()->getWorldManager()->getWorldByName(Main::getMain()->getConfig()->get("kills_location")["world"]) === $event->getTo()->getWorld()) {
                Main::getDatabaseConnector()->executeSelect("Stats.KillsTop", [], function (array $rows){
                    $array = [];
                    $i = 1;
                    foreach ($rows as $result) {
                        $array[] = Main::parseKillsLine($result["username"], $result["kills"], $i);
                        $i++;
                    }
                    foreach(Main::getMain()->getServer()->getWorldManager()->getWorldByName(Main::getMain()->getConfig()->get("kills_location")["world"])->getEntities() as $entities)
                    {
                        if($entities instanceof FloatingText)
                        {
                            if($entities->getType() == "kills")
                                $entities->kill();
                        }
                    }
                    FloatingText::createFloatingText(FloatingText::createText(Main::getKillsTitle(),
                    $array), Main::getMain()->getKillLocation(), "kills");
                });
            }
            if (Main::getMain()->getServer()->getWorldManager()->getWorldByName(Main::getMain()->getConfig()->get("kdr_location")["world"]) === $event->getTo()->getWorld()) {
                foreach(Main::getMain()->getServer()->getWorldManager()->getWorldByName(Main::getMain()->getConfig()->get("kdr_location")["world"])->getEntities() as $entities) {
                    if($entities instanceof FloatingText) {
                        if($entities->getType() == "kdr") {
                            if($entities->getPlayerName() == $player->getName())
                            {
                                $entities->kill();
                            }else{
                                $entities->despawnFrom($player);
                            }
                        }
                    }
                }
                $kdr = str_replace(["{username}", "{kdr}"], [$player->getName(), SessionManager::GetSession($player->getXuid())->getKDR()], Main::getMain()->getConfig()->get("kdr_line"));

                FloatingText::createFloatingText(FloatingText::createText(Main::getMain()->getConfig()->get("kdr_title"), $kdr), Main::getMain()->getKDRLocation(), "kdr", $player);
            }
        }
    }
}