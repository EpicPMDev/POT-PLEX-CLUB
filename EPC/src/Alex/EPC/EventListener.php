<?php

namespace Alex\EPC;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

class EventListener implements Listener{

    public function onQuit(PlayerQuitEvent $event): void{
        $player = $event->getPlayer();
        $main = Main::getInstance();
        if (isset($main->cooldowns[$player->getName()])) unset($main->cooldowns[$player->getName()]);
    }

    public function onLaunch(ProjectileLaunchEvent $event): void{
        $entity = $event->getEntity();
        $player = $entity->getOwningEntity();
            var_dump($player);
        if (!$player instanceof Player || !$entity instanceof EnderPearl) return;
        if ($player->hasPermission("ecp.bypass")) return;

        $main = Main::getInstance();
        if (isset($main->cooldowns[$player->getName()])) {
            if (time() >= $main->cooldowns[$player->getName()]) {
                unset($main->cooldowns[$player->getName()]);
                return;
            }
            $player->sendMessage("§cWait 15secs!");
            $event->cancel();
        } else {
            $main->cooldowns[$player->getName()] = time() + 15;
        }
    }
}