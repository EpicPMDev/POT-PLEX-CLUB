<?php

namespace Phqzing\DeathCycle\commands;

use pocketmine\command\{Command, CommandSender};
use pocketmine\player\{Player, GameMode};
use Phqzing\DeathCycle\Loader;
use Phqzing\DeathCycle\tasks\DeathTask;
use pocketmine\event\player\PlayerDeathEvent;

class ForfeitCommand extends Command {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("forfeit", "", "", ["ff"]);
        parent::setPermission("deathcycle.ff.command");
    }


    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!($sender instanceof Player)) return;
        if(isset($this->plugin->spectator[$sender->getName()]))
        {
            $sender->sendMessage("§cYou are already in the death screen");
            return;
        }
        if($this->plugin->getConfig()->get("enabled"))
        {
            $wname = $sender->getWorld()->getFolderName();
            $mode = "none";
            foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
            {
                if($wname == $arr["arena"])
                {
                    $mode = $key;
                }
            }
            if($mode == "none")
            {
                $sender->sendMessage("§cDeathCycle is not available in this world");
                return;
            }
            $sender->setGameMode(GameMode::ADVENTURE());
            $sender->teleport($sender->getLocation()->asVector3()->add(0, 2, 0));
            $sender->setAllowFlight(true);
            $sender->setFlying(true);
            $this->plugin->giveDeathItems($sender, $mode);
            $this->plugin->getScheduler()->scheduleRepeatingTask(new DeathTask($this->plugin, $sender->getName(), $mode), 20);
            $dEv = new PlayerDeathEvent($sender, $sender->getInventory()->getContents(), $sender->getXpManager()->getXpLevel(), null);
            $dEv->call();
        }else{
            $sender->sendMessage("§cYou can't use this command while the plugin is disabled");
        }
    }
}