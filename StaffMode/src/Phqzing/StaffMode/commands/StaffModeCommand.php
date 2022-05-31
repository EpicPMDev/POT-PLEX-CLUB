<?php

namespace Phqzing\StaffMode\commands;

use pocketmine\player\Player;
use pocketmine\command\{Command, CommandSender};

use Phqzing\StaffMode\Loader;

class StaffModeCommand extends Command {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        parent::__construct("staffmode", "", "", ["sm"]);
        parent::setPermission("staffmode.core.permission");
        $this->plugin = $plugin;
    }


    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!$this->testPermission($sender)) return;
        if(!($sender instanceof Player)) return;

        $this->plugin->toggleStaffMode($sender);
    }
}