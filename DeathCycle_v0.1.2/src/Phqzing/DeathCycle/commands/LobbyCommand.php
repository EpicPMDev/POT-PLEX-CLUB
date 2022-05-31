<?php

namespace Phqzing\DeathCycle\commands;

use pocketmine\command\{Command, CommandSender};
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\Server;
use Phqzing\DeathCycle\Loader;

class LobbyCommand extends Command {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("lobby", "Command used to teleport back to the lobby", "", []);
        parent::setPermission("deathcycle.lobby.command");
    }


    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!($sender instanceof Player)) return;

        if(!$this->testPermission($sender)) return;

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if(!($world instanceof World))
        {
            $sender->sendMessage("§cLobby world does not exist or is not loaded");
            return;
        }

        $sender->teleport($world->getSpawnLocation());
        $this->plugin->giveLobbyItems($sender);
    }
}