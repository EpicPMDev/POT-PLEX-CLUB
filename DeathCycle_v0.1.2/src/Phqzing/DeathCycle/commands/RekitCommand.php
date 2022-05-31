<?php

namespace Phqzing\DeathCycle\commands;

use pocketmine\command\{Command, CommandSender};
use pocketmine\player\Player;
use Phqzing\DeathCycle\Loader;

class RekitCommand extends Command {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("rekit", "", "", ["refill"]);
        parent::setPermission("deathcycle.rekit.command");
    }

    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!($sender instanceof Player)) return;

        $wname = $sender->getWorld()->getFolderName();
        $kit = "none";
        foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
        {
            if($wname == $arr["arena"])
            {
                $kit = $arr["kit"];
            }
        }
        if($kit == "none") return;
        $this->plugin->giveKit($sender, $kit);
    }
}