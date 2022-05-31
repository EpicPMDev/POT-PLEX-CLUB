<?php

namespace Phqzing\StaffMode;

use pocketmine\plugin\PluginBase;
use pocketmine\player\{Player, GameMode};
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

use Phqzing\StaffMode\commands\{StaffModeCommand, StaffChatCommand};
use poggit\libasynql\libasynql;
use jojoe77777\FormAPI\{SimpleForm, CustomForm, ModalForm};
use webhook\{Embed, Message, Webhook};

class Loader extends PluginBase {

    public $database;
    public $banwebhook;
    public $mutewebhook;
    public $staffmode = [];
    public $staffchat = [];
    public $frozen = [];
    public $muted = [];

    public function onEnable():void
    {
        $this->saveDefaultConfig();
        $this->database = libasynql::create($this, $this->getConfig()->get("database"), [
            "sqlite" => "staffmode.sql"
        ]);
        $this->database->executeGeneric("staffmode.bannedinit");
        $this->database->executeGeneric("staffmode.mutedinit");
        $this->banwebhook = $this->getConfig()->get("ban-webhook");
        $this->mutewebhook = $this->getConfig()->get("mute-webhook");
        $this->getServer()->getPluginManager()->registerEvents(new EListener($this), $this);
        $this->getServer()->getCommandMap()->register("/staffmode", new StaffModeCommand($this));
        $this->getServer()->getCommandMap()->register("/staffmode", new StaffChatCommand($this));

        //just making sure arrays are cleared
        $this->staffmode = [];
        $this->staffchat = [];
        $this->frozen = [];
        $this->muted = [];
    }

    public function onDisable():void
    {
        if(isset($this->database))
            $this->database->close();
    }

    
    public function isInStaffMode(string $name):bool
    {
        return isset($this->staffmode[$name]);
    }

    public function isInStaffChat(string $name):bool
    {
        return isset($this->staffchat[$name]);
    }

    public function toggleStaffMode(Player $player):void
    {
        if($this->isInStaffMode($player->getName()))
        {
            $player->getEffects()->clear();
            $arr = $this->staffmode[$player->getName()];

            if(!($arr["location"]->getWorld() instanceof World))
            {
                $player->teleport($this->getServer()->getWorldManager()->getDefaultWorld());
            }else{
                $player->teleport($arr["location"]);
                $player->getInventory()->setContents($arr["contents"]["inventory"]);
                $player->getArmorInventory()->setContents($arr["contents"]["armor"]);
                $player->setHealth($arr["health"]);
                $player->setGameMode($arr["gamemode"]);

                foreach($arr["effects"] as $effect)
                {
                    $player->getEffects()->add($effect);
                }
            }

            foreach($this->getServer()->getOnlinePlayers() as $players)
            {
                $players->showPlayer($player);
            }

            unset($this->staffmode[$player->getName()]);
            $player->sendMessage("§cStaff mode Disabled");
            return;
        }else{
            $this->staffmode[$player->getName()] = [
                "location" => $player->getLocation(),
                "contents" => [
                    "inventory" => $player->getInventory()->getContents(),
                    "armor" => $player->getArmorInventory()->getContents()
                ],
                "effects" => $player->getEffects()->all(),
                "health" => $player->getHealth(),
                "gamemode" => $player->getGameMode()
            ];

            $player->getEffects()->clear();
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setGameMode(GameMode::CREATIVE());
            $player->getInventory()->setContents($this->getStaffModeItems());
            $player->sendMessage("§cStaff mode Enabled");

            foreach($this->getServer()->getOnlinePlayers() as $players)
            {
                $players->hidePlayer($player);
            }
            return;
        }
    }

    public function toggleStaffChat(Player $player):void
    {
        if($this->isInStaffChat($player->getName()))
        {
            unset($this->staffchat[$player->getName()]);
            $player->sendMessage("§cStaff chat disabled");
        }else{
            $this->staffchat[$player->getName()] = $player;
            $player->sendMessage("§aStaff chat enabled");
        }
    }


    public function getStaffModeItems():array
    {
        $freeze = VanillaBlocks::ICE()->asItem()->setCustomName("§r§bFreeze Tool §7(Hit Player)")->setLore(["§r§8- §7Hit a player with this tool to Freeze/Unfreeze them \n  or stop them from being able to move or hit a player. \n  If the frozen player leaves/quits the server the \n  player will automatically be banned for 30 days."]);
        $teleport = VanillaItems::ENDER_PEARL()->setCustomName("§r§3Teleport Tool §7(Right Click)")->setLore(["§r§8- §7Right Click/Use the tool to teleport to a random \n  player that is online on the server or to a specific player."]);
        $punishment = VanillaItems::STICK()->setCustomName("§r§cPunishment Tool §7(Hit Player/Right Click)")->setLore(["§r§8- §7Hitting a player with this tool \n  will open a form to ban/kick/mute/unmute/unban the player.", "§r§8- §7Right Clicking this tool will open a form to \n  either ban/kick/mute/unmute/unban a specified player."]);

        $items = [
            3 => $freeze,
            4 => $teleport,
            5 => $punishment
        ];

        return $items;
    }

    public function findMultiple(int $data):int
    {
        switch($data)
        {
            case 0:
                return 86400;
            break;
            
            case 1:
                return 3600;
            break;

            case 2:
                return 60;
            break;

            case 3:
                return 1;
            break;
        }
    }

    public function teleportForm(Player $player):void
    {
        $form = new SimpleForm(function(Player $player, $data = null):void
        {
            if(is_null($data)) return;

            switch($data)
            {
                case "random":
                    $playersArr = $this->getServer()->getOnlinePlayers();
                    $selected = $playersArr[array_rand($playersArr)];

                    if($selected->getName() != $player->getName() and !$this->isInStaffMode($selected->getName()))
                        $player->teleport($selected->getLocation());
                break;

                case "browse":
                    $form = new SimpleForm(function(Player $player, $data = null):void
                    {
                        if(is_null($data)) return;
                        $target = $this->getServer()->getPlayerExact($data);
                        
                        if($target instanceof Player)
                            $player->teleport($target->getLocation());
                    });
                    $form->setTitle("§l§3Teleport Form: §r§bBrowse");
                    foreach($this->getServer()->getOnlinePlayers() as $players)
                    {
                        if($players->getName() != $player->getName())
                            $form->addButton($players->getName(), -1, "", $players->getName());
                    }
                    $player->sendForm($form);
                break;
            }
        });
        $form->setTitle("§l§3Teleport Form");
        $form->addButton("Browse", -1, "", "browse");
        $form->addButton("Random", -1, "", "random");
        $player->sendForm($form);
    }

    public function punishmentForm(Player $player, $target = null):void
    {
        $form = new SimpleForm(function(Player $player, $data = null)use($target):void
        {
            if(is_null($data)) return;

            switch($data)
            {
                case "ban":
                    $form = new CustomForm(function(Player $player, null|array $data):void
                    {
                        if(is_null($data))
                        {
                            $player->sendMessage("§cCouldn't ban player due too missing information.");
                            return;
                        }
                        if(count($data) < 4)
                        {
                            $player->sendMessage("§cCouldn't ban player due too missing information.");
                            return;
                        }
                        if($data[0] == "") return;

                        $targetPlayer = $this->getServer()->getPlayerByPrefix($data[0]);
                        if(!($targetPlayer instanceof Player))
                        {
                            if(!$this->getServer()->hasOfflinePlayerData($data[0]))
                            {
                                $player->sendMessage("§cThe player named §7{$data[0]} §cdoes not exist.");
                                return;
                            }
                            $targetPlayer = $data[0];
                        }

                        if(!is_int((int)$data[2]))
                        {
                            $player->sendMessage("§cInvalid time given.");
                            return;
                        }

                        $multiple = $this->findMultiple($data[3]);
                        $duration = (int)$data[2] * $multiple + time();
                        $time = gmdate("d/m/Y h:ia T", $duration);

                        if($targetPlayer instanceof Player)
                        {
                            if($targetPlayer->getName() == $player->getName()) return;
                            $msg = str_replace(["{unbandate}", "{bannedby}", "{reason}"], [$time, $player->getName(), $data[1]], $this->getConfig()->get("ban-message"));
                            $targetPlayer->kick($msg, "");
                            $targetPlayer = $targetPlayer->getName();
                        }

                        $this->database->executeInsert("staffmode.baninsert", ["name" => $targetPlayer, "time" => $duration, "by" => $player->getName(), "reason" => $data[1]]);
                        $this->logBan($targetPlayer, $time, $data[1], $player->getName());
                        $player->sendMessage("§aSuccessfully banned the player §e{$targetPlayer} §auntil §7{$time}");
                    });
                    $form->setTitle("§l§4Punishment Form: §r§cBan");
                    if(!($target instanceof Player))
                    {
                        $form->addInput("Player Name", "Player Name Here", "");
                    }else{
                        $form->addInput("Player Name", "Player Name Here", $target->getName());
                    }
                    $form->addInput("Reason", "Reason Here", "");
                    $form->addInput("Time", "Time Here (must be a number)", "");
                    $form->addDropdown("Time Unit", ["day(s)", "hour(s)", "minute(s)", "second(s)"]);
                    $player->sendForm($form);
                break;

                case "unban":
                    $form = new CustomForm(function(Player $player, null|array $data):void
                    {
                        if(is_null($data))
                        {
                            $player->sendMessage("§cCouldn't unban player due too missing information.");
                            return;
                        }
                        if(count($data) < 2)
                        {
                            $player->sendMessage("§cCouldn't unban player due too missing information.");
                            return;
                        }

                        $target = $data[0];
                        if($target == "") return;
                        if(!$this->getServer()->hasOfflinePlayerData($target))
                        {
                            $player->sendMessage("§cThe player named §7{$target} §cdoes not exist.");
                            return;
                        }

                        if(strtolower($target) == strtolower($player->getName())) return;

                        $this->database->executeSelect("staffmode.banselect", ["name" => strtolower($target)], function(array $row)use($player, $target, $data):void
                        {
                            if(isset($row[0]) and !empty($row))
                            {
                                $this->database->executeChange("staffmode.unban", ["name" => strtolower($target)]);
                                $this->logUnban($target, $data[1], $player->getName());
                                $player->sendMessage("§aPlayer named §e{$target} §ahas been unbanned");
                            }else{
                                $player->sendMessage("§cPlayer named §7{$target} §cwas never banned to begin with");
                            }
                        });
                    });
                    $form->setTitle("§l§4Punishment Form: §r§cUnban");
                    $form->addInput("Player Name", "Player Name Here", "");
                    $form->addInput("Reason", "Reason Here", "");
                    $player->sendForm($form);
                break;

                case "mute":
                    $form = new CustomForm(function(Player $player, null|array $data):void
                    {
                        if(is_null($data))
                        {
                            $player->sendMessage("§cCouldn't mute player due too missing information.");
                            return;
                        }
                        if(count($data) < 4)
                        {
                            $player->sendMessage("§cCouldn't mute player due too missing information.");
                            return;
                        }
                        if($data[0] == "") return;

                        $targetPlayer = $this->getServer()->getPlayerByPrefix($data[0]);
                        if(!($targetPlayer instanceof Player))
                        {
                            if(!$this->getServer()->hasOfflinePlayerData($data[0]))
                            {
                                $player->sendMessage("§cThe player named §7{$data[0]} §cdoes not exist.");
                                return;
                            }
                            $targetPlayer = $data[0];
                        }

                        if(!is_int((int)$data[2]))
                        {
                            $player->sendMessage("§cInvalid time given.");
                            return;
                        }

                        $multiple = $this->findMultiple($data[3]);
                        $duration = (int)$data[2] * $multiple + time();
                        $time = gmdate("d/m/Y h:ia T", $duration);

                        if($targetPlayer instanceof Player)
                        {
                            if($targetPlayer->getName() == $player->getName()) return;
                            $msg = str_replace(["{unmutedate}", "{mutedby}", "{reason}"], [$time, $player->getName(), $data[1]], $this->getConfig()->get("mute-message"));
                            $targetPlayer->sendMessage($msg);
                            $this->muted[$targetPlayer->getName()] = $targetPlayer;
                            $targetPlayer = $targetPlayer->getName();
                        }

                        $this->database->executeInsert("staffmode.muteinsert", ["name" => $targetPlayer, "time" => $duration, "by" => $player->getName(), "reason" => $data[1]]); 
                        $this->logMute($targetPlayer, $time, $data[1], $player->getName());
                        $player->sendMessage("§aSuccessfully muted the player §e{$targetPlayer} §auntil §7{$time}");
                    });
                    $form->setTitle("§l§4Punishment Form: §r§cMute");
                    if(!($target instanceof Player))
                    {
                        $form->addInput("Player Name", "Player Name Here", "");
                    }else{
                        $form->addInput("Player Name", "Player Name Here", $target->getName());
                    }
                    $form->addInput("Reason", "Reason Here", "");
                    $form->addInput("Time", "Time Here (must be a number)", "");
                    $form->addDropdown("Time Unit", ["day(s)", "hour(s)", "minute(s)", "second(s)"]);
                    $player->sendForm($form);
                break;

                case "unmute":
                    $form = new CustomForm(function(Player $player, null|array $data):void
                    {
                        if(is_null($data))
                        {
                            $player->sendMessage("§cCouldn't unmute player due too missing information.");
                            return;
                        }
                        if(count($data) < 2)
                        {
                            $player->sendMessage("§cCouldn't unmute player due too missing information.");
                            return;
                        }

                        $target = $this->getServer()->getPlayerByPrefix($data[0]);
                        if(!($target instanceof Player))
                        {
                            if(!$this->getServer()->hasOfflinePlayerData($data[0]))
                            {
                                $player->sendMessage("§cThe player named §7{$data[0]} §cdoes not exist.");
                                return;
                            }
                            $target = $data[0];
                        }else{
                            $target = $target->getName();
                        }

                        $this->database->executeSelect("staffmode.muteselect", ["name" => strtolower($target)], function(array $row)use($player, $target, $data):void
                        {
                            if(isset($row[0]) and !empty($row))
                            {
                                if(isset($this->muted[$target]))
                                {   
                                    $this->muted[$target]->sendMessage("§aYou have been unmuted");
                                    unset($this->muted[$target]);
                                }
                                $this->database->executeChange("staffmode.unmute", ["name" => strtolower($target)]);
                                $this->logUnmute($target, $data[1], $player->getName());
                                $player->sendMessage("§aPlayer named §e{$target} §ahas been unmuted");
                            }else{
                                $player->sendMessage("§cPlayer named §7{$target} §cwas never muted to begin with");
                            }
                        });
                    });
                    $form->setTitle("§l§4Punishment Form: §r§cUnmute");
                    $form->addInput("Player Name", "Player Name Here", "");
                    $form->addInput("Reason", "Reason Here", "");
                    $player->sendForm($form);
                break;

                case "kick":
                    $form = new CustomForm(function(Player $player, null|array $data):void
                    {
                        if(is_null($data)) return;
                        if(count($data) < 2)
                        {
                            $player->sendMessage("§cCouldn't kick player due too missing information.");
                            return;
                        }

                        $targetPlayer = $this->getServer()->getPlayerByPrefix($data[0]);
                        if(!($targetPlayer instanceof Player))
                        {
                            $player->sendMessage("§cPlayer named §7{$data[0]} §cdoes not exist or is offline.");
                            return;
                        }

                        if($targetPlayer->getName() == $player->getName()) return;

                        $targetPlayer->kick("You have been kicked by {$player->getName()}\nReason: {$data[1]}", "");
                        $this->logKick($targetPlayer->getName(), $data[1], $player->getName());
                        $player->sendMessage("§aPlayer named §e{$targetPlayer->getName()} §ahas been kicked");
                    });
                    $form->setTitle("§l§4Punishment Form: §r§cKick");
                    if(!($target instanceof Player))
                    {
                        $form->addInput("Player Name", "Player Name Here", "");
                    }else{
                        $form->addInput("Player Name", "Player Name Here", $target->getName());
                    }
                    $form->addInput("Reason", "Reason Here", "");
                    $player->sendForm($form);
                break;
            }
        });
        $form->setTitle("§r§4Punishment Form");
        $form->addButton("Ban", -1, "", "ban");
        $form->addButton("Unban", -1, "", "unban");
        $form->addButton("Mute", -1, "", "mute");
        $form->addButton("Unmute", -1, "", "unmute");
        $form->addButton("Kick", -1, "", "kick");
        $player->sendForm($form);
    }


    public function logBan(string $banned, string $duration, string $reason, string $by):void
    {
        $webhook = new Webhook($this->banwebhook);
        $message = new Message();
        $embed = new Embed();

        $embed->setTitle("Player Ban");
        $embed->setFooter("by: ".$by);
        $embed->setDescription("Banned: {$banned}\nReason: {$reason}\nBanned Until: {$duration}");
        $message->addEmbed($embed);
        $webhook->send($message);
    }

    public function logUnban(string $unbanned, string $reason, string $by):void
    {
        $webhook = new Webhook($this->banwebhook);
        $message = new Message();
        $embed = new Embed();

        $embed->setTitle("Player Unban");
        $embed->setFooter("by: ".$by);
        $embed->setDescription("Unbanned: {$unbanned}\nReason: {$reason}");
        $message->addEmbed($embed);
        $webhook->send($message);
    }

    public function logMute(string $muted, string $duration, string $reason, string $by):void
    {
        $webhook = new Webhook($this->mutewebhook);
        $message = new Message();
        $embed = new Embed();

        $embed->setTitle("Player Mute");
        $embed->setFooter("by: ".$by);
        $embed->setDescription("Muted: {$muted}\nReason: {$reason}\nMuted Until: {$duration}");
        $message->addEmbed($embed);
        $webhook->send($message);
    }

    public function logUnmute(string $unmuted, string $reason, string $by):void
    {
        $webhook = new Webhook($this->mutewebhook);
        $message = new Message();
        $embed = new Embed();

        $embed->setTitle("Player Unmute");
        $embed->setFooter("by: ".$by);;
        $embed->setDescription("Banned: {$unmuted}\nReason: {$reason}");
        $message->addEmbed($embed);
        $webhook->send($message);
    }

    public function logKick(string $kicked, string $reason, string $by):void
    {
        $webhook = new Webhook($this->banwebhook);
        $message = new Message();
        $embed = new Embed();

        $embed->setTitle("Player Kick");
        $embed->setFooter("by: ".$by);
        $embed->setDescription("Kicked: {$kicked}\nReason: {$reason}");
        $message->addEmbed($embed);
        $webhook->send($message);
    }
}