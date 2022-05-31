<?php

namespace Phqzing\StaffMode;

use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\event\player\{PlayerJoinEvent, PlayerQuitEvent, PlayerItemUseEvent, PlayerChatEvent, PlayerMoveEvent, PlayerDropItemEvent};
use pocketmine\event\block\{BlockPlaceEvent, BlockBreakEvent};
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\entity\{EntityDamageByEntityEvent, EntityItemPickupEvent};
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;

class EListener implements Listener {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
    }   

    public function onJoin(PlayerJoinEvent $ev):void
    {
        $player = $ev->getPlayer();

        $this->plugin->database->executeSelect("staffmode.banselect", ["name" => strtolower($player->getName())], function(array $row)use($player):void
        {
            if(isset($row[0]) and !empty($row))
            {
                $time = $row[0]["time"];

                if($time > time())
                {
                    $by = $row[0]["by"];
                    $reason = $row[0]["reason"];
                    $time = gmdate("d/m/Y h:ia T", $time);
                    $msg = str_replace(["{unbandate}", "{bannedby}", "{reason}"], [$time, $by, $reason], $this->plugin->getConfig()->get("ban-message"));
                    $player->kick($msg, "");
                }else{
                    $this->plugin->database->executeChange("staffmode.unban", ["name" => strtolower($player->getName())]);
                }
            }else{
                $this->plugin->database->executeChange("staffmode.unban", ["name" => strtolower($player->getName())]);
            }
        });
        $this->plugin->database->executeSelect("staffmode.muteselect", ["name" => strtolower($player->getName())], function(array $row)use($player):void
        {
            if(isset($row[0]) and !empty($row))
            {
                $time = $row[0]["time"];

                if($time > time())
                {
                    $by = $row[0]["by"];
                    $reason = $row[0]["reason"];
                    $time = gmdate("d/m/Y h:ia T", $time);
                    $msg = str_replace(["{unmutedate}", "{mutedby}", "{reason}"], [$time, $by, $reason], $this->plugin->getConfig()->get("mute-message"));
                    $player->sendMessage($msg);
                    $this->plugin->muted[$player->getName()] = $player;
                }else{
                    $this->plugin->database->executeChange("staffmode.unmute", ["name" => strtolower($player->getName())]);
                    if(isset($this->plugin->muted[$player->getName()])) unset($this->plugin->muted[$player->getName()]);
                }
            }else{
                $this->plugin->database->executeChange("staffmode.unmute", ["name" => strtolower($player->getName())]);
                if(isset($this->plugin->muted[$player->getName()])) unset($this->plugin->muted[$player->getName()]);
            }
        });
        
        foreach($this->plugin->getServer()->getOnlinePlayers() as $players)
        {
            if($this->plugin->isInStaffMode($players->getName()))
               $player->hidePlayer($players);
        }

        if($player->hasPermission("staffmode.core.permission") and $this->plugin->getConfig()->get("silent-join")) $ev->setJoinMessage("");
    }

    public function onQuit(PlayerQuitEvent $ev):void
    {
        $player = $ev->getPlayer();

        if($this->plugin->isInStaffMode($player->getName())) $this->plugin->toggleStaffMode($player);
        if($this->plugin->isInStaffChat($player->getName())) $this->plugin->toggleStaffChat($player);
        if($player->hasPermission("staffmode.core.permission") and $this->plugin->getConfig()->get("silent-leave")) $ev->setQuitMessage("");
        if(isset($this->plugin->muted[$player->getName()])) unset($this->plugin->muted[$player->getName()]);
        if(isset($this->plugin->frozen[$player->getName()]))
        {
            $duration = 30 * 86400 + time();
            $this->plugin->database->executeInsert("staffmode.baninsert", ["name" => $player->getName(), "time" => $duration, "by" => "Console", "reason" => "Left while frozen"]); 
            $this->plugin->logBan($player->getName(), gmdate("d/m/Y h:ia T", $duration), "Left while frozen", "Console");
            unset($this->plugin->frozen[$player->getName()]);
        }
    }

    
    public function onHit(EntityDamageByEntityEvent $ev):void
    {
        $damager = $ev->getDamager();
        $victim = $ev->getEntity();

        if(!($damager instanceof Player) or !($victim instanceof Player)) return;

        $tool = $damager->getInventory()->getItemInHand();

        if(isset($this->plugin->frozen[$damager->getName()]) or isset($this->plugin->frozen[$victim->getName()])) $ev->cancel();

        if($this->plugin->isInStaffMode($damager->getName()))
        {
            $ev->cancel();

            if($tool->equals(VanillaBlocks::ICE()->asItem(), true, false) and $tool->getName() == "§r§bFreeze Tool §7(Hit Player)")
            {
                if(isset($this->plugin->frozen[$victim->getName()]))
                {
                    unset($this->plugin->frozen[$victim->getName()]);
                    $victim->sendMessage("§r§aYou have been unfrozen by: §7{$damager->getName()}");
                    $damager->sendMessage("§r§aYou have unfrozen §7{$victim->getName()}");
                }else{
                    $this->plugin->frozen[$victim->getName()] = $victim;
                    $victim->sendMessage("§r§cYou have been frozen by: §7{$damager->getName()}");
                    $damager->sendMessage("§r§aYou have frozen §7{$victim->getName()}");
                }
                return;
            }

            if($tool->equals(VanillaItems::STICK(), true, false) and $tool->getName() == "§r§cPunishment Tool §7(Hit Player/Right Click)")
            {
                $ev->cancel();
                $this->plugin->punishmentForm($damager, $victim);
                return;
            }
        }
    }

    public function onItemUse(PlayerItemUseEvent $ev):void
    {
        $player = $ev->getPlayer();
        $tool = $ev->getItem();

        if($this->plugin->isInStaffMode($player->getName()))
        {
            $ev->cancel();
            if($tool->equals(VanillaItems::ENDER_PEARL(), true, false) and $tool->getName() == "§r§3Teleport Tool §7(Right Click)")
            {
                $this->plugin->teleportForm($player);
                return;
            }

            if($tool->equals(VanillaItems::STICK(), true, false) and $tool->getName() == "§r§cPunishment Tool §7(Hit Player/Right Click)")
            {
                $this->plugin->punishmentForm($player);
                return;
            }
        }
    }
    

    public function onDrop(PlayerDropItemEvent $ev):void
    {
        if($this->plugin->isInStaffMode($ev->getPlayer()->getName()))
            $ev->cancel();
    }

    public function onPickUp(EntityItemPickupEvent $ev):void
    {
        $player = $ev->getEntity();
        if($player instanceof Player)
        {
            if($this->plugin->isInStaffMode($player->getName()))
                $ev->cancel();
        }
    }

    public function onBlockPlace(BlockPlaceEvent $ev):void
    {
        if($this->plugin->isInStaffMode($ev->getPlayer()->getName()))
            $ev->cancel();
    }

    public function onBlockBreak(BlockBreakEvent $ev):void
    {
        if($this->plugin->isInStaffMode($ev->getPlayer()->getName()))
            $ev->cancel();
    }

    public function onTransaction(InventoryTransactionEvent $ev):void
    {
        $transaction = $ev->getTransaction();
        $player = $transaction->getSource();

        if($this->plugin->isInStaffMode($player->getName()))
            $ev->cancel();
    }

    public function onMove(PlayerMoveEvent $ev)
    {
        $player = $ev->getPlayer();

        if(isset($this->plugin->frozen[$player->getName()]))
        {
            $player->sendTitle("§cYou have been frozen!");
            $ev->cancel();
        }
    }

    public function onChat(PlayerChatEvent $ev):void
    {
        $player = $ev->getPlayer();
        $chat = $ev->getMessage();
        
        if($this->plugin->isInStaffChat($player->getName()))
        {
            foreach($this->plugin->getServer()->getOnlinePlayers() as $players)
            {
                if($players->hasPermission("staffmode.core.permission"))
                   $players->sendMessage("§c[Staff] §7{$player->getName()}: {$chat}");
            }
            $ev->cancel();
        }

        if(isset($this->plugin->muted[$player->getName()]))
        {
            $this->plugin->database->executeSelect("staffmode.muteselect", ["name" => strtolower($player->getName())], function(array $row)use($player, $chat):void
            {
                if(isset($row[0]) and !empty($row))
                {
                    $time = $row[0]["time"];

                    if($time > time())
                    {
                        $by = $row[0]["by"];
                        $reason = $row[0]["reason"];
                        $time = gmdate("d/m/Y h:ia T", $time);
                        $msg = str_replace(["{unmutedate}", "{mutedby}", "{reason}"], [$time, $by, $reason], $this->plugin->getConfig()->get("mute-message"));
                        $player->sendMessage($msg);
                    }else{
                        $this->plugin->database->executeChange("staffmode.unmute", ["name" => strtolower($player->getName())]);
                        if(isset($this->plugin->muted[$player->getName()])) unset($this->plugin->muted[$player->getName()]);
                        $player->chat($chat);
                    }
                }else{
                    $this->plugin->database->executeChange("staffmode.unmute", ["name" => strtolower($player->getName())]);
                    if(isset($this->plugin->muted[$player->getName()])) unset($this->plugin->muted[$player->getName()]);
                    $player->chat($chat);
                }
            });
            $ev->cancel();
        }
    }
    

    public function onPluginDisable(PluginDisableEvent $ev):void 
    {
        foreach($this->plugin->getServer()->getOnlinePlayers() as $players)
        {
            if($this->plugin->isInStaffMode($players->getName()))
                $this->plugin->toggleStaffMode($players);
        }
    }
}