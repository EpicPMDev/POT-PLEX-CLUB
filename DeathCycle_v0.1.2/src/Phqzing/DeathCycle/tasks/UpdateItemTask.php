<?php

namespace Phqzing\DeathCycle\tasks;

use pocketmine\scheduler\Task;
use pocketmine\world\World;
use Phqzing\DeathCycle\Loader;

class UpdateItemTask extends Task {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
    }


    public function onRun():void
    {
        foreach($this->plugin->getServer()->getOnlinePlayers() as $players)
        {
            foreach($players->getInventory()->getContents() as $slot => $item)
            {
                foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
                {
                    if(!is_null($item->getNamedTag()->getTag("gitem")) and $item->getNamedTag()->getString("gitem") == $key)
                    {
                        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($arr["arena"]);
                        if(!($world instanceof World))
                        {
                            $playerCount = "N/A\n§c[§4Arena Offline§c]";
                        }else{
                            $playerCount = 0;
                            foreach($world->getPlayers() as $player)
                            {
                                if(!isset($this->plugin->spectator[$player->getName()])) $playerCount++;
                            }
                        }
                        $itemName = str_replace("{playercount}", $playerCount, $arr["name"]);
                        
                        if($item->getName() != $itemName)
                        {
                            $item->setCustomName($itemName);
                            if(!isset($this->plugin->spectator[$players->getName()]))
                            {
                                $players->getInventory()->setItem($slot, $item);
                            }
                        }
                    }
                }
            }
        }
    }
}