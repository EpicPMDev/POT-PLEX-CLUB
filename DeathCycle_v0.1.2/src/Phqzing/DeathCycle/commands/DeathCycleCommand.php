<?php

namespace Phqzing\DeathCycle\commands;

use pocketmine\command\{Command, CommandSender};
use Phqzing\DeathCycle\Loader;
use pocketmine\lang\Translatable;

class DeathCycleCommand extends Command {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("deathcycle", "Command used to enable or disable the plugin", "§r§eUsage: /deathcycle [enable|disable]", ["dc"]);
        parent::setPermission("deathcycle.toggle.command");
    }


    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!$this->testPermission($sender)) return;
        if(!isset($args[0]))
        {
            $sender->sendMessage("§cPlease specify whether you wanna disable or enable the plugin");
            $sender->sendMessage($this->getUsage());
            return;
        }
        switch(strtolower($args[0]))
        {
            case "enable":
                $this->plugin->getConfig()->set("enabled", true);
                $this->plugin->getConfig()->save();
                $sender->sendMessage("§aPlugin successfully enabled");
            break;

            case "disable":
                $this->plugin->getConfig()->set("enabled", false);
                $this->plugin->getConfig()->save();
                $sender->sendMessage("§aPlugin successfully disabled");
            break;

            default:
                $sender->sendMessage($this->getUsage());
        }
    }
}