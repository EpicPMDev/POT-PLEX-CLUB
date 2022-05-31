<?php

namespace Phqzing\CpsCounter;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerJoinEvent, PlayerQuitEvent};
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\event\server\DataPacketReceiveEvent;

class Loader extends PluginBase implements Listener {

    public $clicks = [];

    public function onEnable():void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function addClick(Player $player)
    {
        if(!isset($this->clicks[$player->getName()])) return;
        array_unshift($this->clicks[$player->getName()], microtime(true));
		if (count($this->clicks[$player->getName()]) > 20)
			array_pop($this->clicks[$player->getName()]);

		$player->sendTip("§cCPS: §f".abs($this->getCps($player)));
    }

    public function getCps(Player $player, float $deltaTime = 1.0, int $roundPrecision = 1):int
    {
		$mt = microtime(true);
		return round(count(array_filter($this->clicks[$player->getName()], static function (float $t) use ($deltaTime, $mt): bool {
				return ($mt - $t) <= $deltaTime;
			})) / $deltaTime, $roundPrecision);
    }

    


    public function onJoin(PlayerJoinEvent $ev):void
    {
        $this->clicks[$ev->getPlayer()->getName()] = [];
    }

    public function onQuit(PlayerQuitEvent $ev):void
    {
        if(isset($this->clicks[$ev->getPlayer()->getName()]))
            unset($this->clicks[$ev->getPlayer()->getName()]);
    }

    public function onDataReceive(DataPacketReceiveEvent $event):void
	{
		$packet = $event->getPacket();
		if ($packet::NETWORK_ID === LevelSoundEventPacket::NETWORK_ID and $packet instanceof LevelSoundEventPacket) 
        {
			$player = $event->getOrigin()->getPlayer();
            if($player instanceof Player)
            {
                if ($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE or $packet->sound === LevelSoundEvent::ATTACK_STRONG)
                {
                    $this->addClick($player);
                }
            }
		}
	}
}