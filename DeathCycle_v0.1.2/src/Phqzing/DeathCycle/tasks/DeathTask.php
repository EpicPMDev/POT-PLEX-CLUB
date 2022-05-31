<?php

namespace Phqzing\DeathCycle\tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\{Player, GameMode};
use pocketmine\item\VanillaItems;
use Phqzing\DeathCycle\Loader;

class DeathTask extends Task {

    private $plugin;
    private $player;
    private $mode;
    private $time = 5;

    public function __construct(Loader $plugin, string $player, string $mode)
    {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->mode = $mode;
    }


    public function onRun():void
    {
        $player = $this->plugin->getServer()->getPlayerExact($this->player);
        if(!($player instanceof Player) or !$player->isConnected())
        {
            $this->getHandler()->cancel();
            return;
        }
        if(!isset($this->plugin->spectator[$player->getName()]))
        {
            $this->getHandler()->cancel();
            return;
        }

        $message = str_replace("{timer}", $this->time, $this->plugin->getConfig()->get("death-timer"));
        $player->sendTitle($message);

        if($this->time <= 0)
        {
            $conf = $this->plugin->getConfig()->get("death-items");
            $gdye = VanillaItems::LIME_DYE()->setCustomName($conf["green-dye"]["name"]);
            $gdye->getNamedTag()->setString("ditem", $this->mode);
            $player->getInventory()->setItem($conf["grey-dye"]["slot"], $gdye);
            $this->plugin->spectator[$player->getName()] = $player;
            $this->getHandler()->cancel();
            return;
        }
        $this->time--;
    }
}