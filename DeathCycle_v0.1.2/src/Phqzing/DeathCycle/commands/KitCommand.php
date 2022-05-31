<?php

namespace Phqzing\DeathCycle\commands;

use pocketmine\command\{Command, CommandSender};
use pocketmine\player\Player;
use Phqzing\DeathCycle\Loader;
use pocketmine\lang\Translatable;

class KitCommand extends Command {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("kit", "Command for creating/deleting kits", "§eUsage: /kit [crate|delete|list] <name>", []);
        parent::setPermission("deathcycle.kit.command");
    }


    public function execute(CommandSender $sender, string $label, array $args)
    {
        if(!$this->testPermission($sender)) return;
        if(!isset($args[0]))
        {
            $sender->sendMessage("§cPlease specify whether you wanna create or delete a kit\n".$this->getUsage());
            return;
        }

        if(!isset($args[1]) and strtolower($args[0]) != "list")
        {
            $sender->sendMessage("§cPlease specify the name of the kit\n".$this->getUsage());
            return;
        }


        switch(strtolower($args[0]))
        {
            case "create":
                if(!($sender instanceof Player))
                {
                    $sender->sendMessage("§cYou can only create kits while in-game");
                    return;
                }
                
                $contents = json_encode($sender->getInventory()->getContents());
                $armor = json_encode($sender->getArmorInventory()->getContents());
                $gamemode = (int)$sender->getGameMode()->getAliases()[0];
                $effects = [];
                $i = 0;
                foreach($sender->getEffects()->all() as $effect)
                {
                    $name = $effect->getType()->getName();
                    if($name instanceof Translatable)
                        $name = $effect->getType()->getName()->getText();
                    $effects[$i] = [$name, $effect->getDuration(), $effect->getAmplifier()];
                    $i++;
                }
                $effects = json_encode($effects);

                $prep = $this->plugin->db->prepare("INSERT OR REPLACE INTO kits (kit, contents, armor, gamemode, effects) VALUES (:kit, :contents, :armor, :gamemode, :effects);");
                $prep->bindValue(":kit", $args[1]);
                $prep->bindValue(":contents", $contents);
                $prep->bindValue(":armor", $armor);
                $prep->bindValue(":gamemode", $gamemode);
                $prep->bindValue(":effects", $effects);

                $res = $this->plugin->db->query("SELECT kit FROM kits WHERE lower(kit)='".strtolower($args[1])."';")->fetchArray(SQLITE3_ASSOC);
                if(!empty($res))
                {
                    $sender->sendMessage("§aKit has been successfully §6OVERWRITTEN");
                }else{
                    $sender->sendMessage("§aKit has been successfully §eCREATED");
                }

                $prep->execute();
            break;

            case "delete":
                $res = $this->plugin->db->query("SELECT kit FROM kits WHERE lower(kit)='".strtolower($args[1])."';")->fetchArray(SQLITE3_ASSOC);
                if(empty($res))
                {
                    $sender->sendMessage("§7".$args[1]." §ckit does not exist!");
                    return;
                }
                $this->plugin->db->exec("DELETE FROM kits WHERE lower(kit)='".strtolower($args[1])."';");
                $sender->sendMessage("§aKit has been successfully §cDELETED");
            break;

            case "list":
                $kits = [];
                $q = $this->plugin->db->query("SELECT * FROM kits");
                while($table = $q->fetchArray(SQLITE3_ASSOC))
                {
                    $kits[] = $table["kit"];
                }
                if(empty($kits))
                {
                    $kits = "\n§l§8-§r§7 There are no available kits. Use the command /kit create to make one";
                }else{
                    $kits = implode("\n§r§l§8-§r§6 ", $kits);
                }
                $sender->sendMessage("§2[§aAll Available Kits§2]\n§r§l§8-§r§6 ".$kits);
            break;

            default:
                $sender->sendMessage("§cPlease specify whether you wanna create or delete a kit\n".$this->getUsage());
                return;
        }
    }
}