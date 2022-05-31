<?php

namespace Phqzing\DeathCycle\commands;

use pocketmine\command\{Command, CommandSender};
use pocketmine\player\Player;
use Phqzing\DeathCycle\Loader;
use pocketmine\world\World;

class GamemodeTPCommand extends Command {

    private $plugin;
    
    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("gmtp", "", "", []);
        parent::setPermission("deathcycle.gmtp.command");
    }
    
    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!($sender instanceof Player)) return;
        if(!isset($args[0])) return;
        if($sender->getWorld()->getFolderName() != $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName()) return;
        if($this->plugin->getServer()->getPluginManager()->getPlugin("StaffMode")->isInStaffMode($sender->getName())) return;
        
        if($args[0] != "iron-sword" and $args[0] != "golden-apple" and $args[0] != "health-pot" and $args[0] != "lava-bucket" and $args[0] != "bow") return;
        
        $config = $this->plugin->getConfig()->get("gamemode-items")[$args[0]];
        
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($config["arena"]);
        if(!($world instanceof World))
        {
            $sender->sendMessage("§cArena is offline");
            return;
        }
        $sender->teleport($world->getSpawnLocation());
        $this->plugin->giveKit($sender, $config["kit"]);
        return;
    }
}