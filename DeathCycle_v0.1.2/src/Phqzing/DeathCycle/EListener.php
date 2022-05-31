<?php

namespace Phqzing\DeathCycle;

use pocketmine\event\Listener;
use pocketmine\player\{Player, GameMode};
use pocketmine\event\player\{PlayerDeathEvent,
    PlayerItemUseEvent,
    PlayerJoinEvent,
    PlayerInteractEvent,
    PlayerItemConsumeEvent,
    PlayerQuitEvent,
    PlayerDropItemEvent};
use pocketmine\event\entity\{EntityTeleportEvent, EntityDamageEvent, EntityDamageByEntityEvent};
use pocketmine\item\{VanillaItems, Item};
use pocketmine\item\enchantment\{VanillaEnchantments, EnchantmentInstance};
use pocketmine\world\World;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use Phqzing\DeathCycle\tasks\DeathTask;
use dktapps\pmforms\{MenuForm, MenuOption, FormIcon};
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;

class EListener implements Listener {

    private $plugin;

    public function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $ev):void
    {
        $player = $ev->getPlayer();
        $player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
        $this->plugin->giveLobbyItems($player);
    }

    public function onQuit(PlayerQuitEvent $ev):void
    {
        $player = $ev->getPlayer();
        if(isset($this->plugin->spectator[$player->getName()]))
            unset($this->plugin->spectator[$player->getName()]);
    }

    public function onTeleport(EntityTeleportEvent $ev):void
    {
        $player = $ev->getEntity();
        if(!($player instanceof Player) or !$player->isConnected()) return;
        
        if($this->plugin->getServer()->getPluginManager()->getPlugin("StaffMode")->isInStaffMode($player->getName())) return;

        if($ev->getTo()->getWorld()->getFolderName() == $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName())
            $this->plugin->giveLobbyItems($player);
    }


    public function onDamage(EntityDamageEvent $ev):void
    {
        $player = $ev->getEntity();
        if(!($player instanceof Player)) return;
        if(!$player->isConnected()) return;

        if(isset($this->plugin->spectator[$player->getName()])) $ev->cancel();

        if($ev instanceof EntityDamageByEntityEvent)
        {
            $damager = $ev->getDamager();
            if (!($damager instanceof Player) or !$damager->isConnected()) {
                $damager = $this->plugin->getServer()->getPluginManager()->getPlugin("Anti-Interrupt")->getEnemy($player);
                $damager = $this->plugin->getServer()->getPlayerExact($damager);
                if(!($damager instanceof Player)) $damager = "none";
            }else{
                if(isset($this->plugin->spectator[$damager->getName()]) or isset($this->plugin->spectator[$player->getName()])) $ev->cancel();   
            }
        }else{
            $damager = $this->plugin->getServer()->getPluginManager()->getPlugin("Anti-Interrupt")->getEnemy($player);
            $damager = $this->plugin->getServer()->getPlayerExact($damager);
            if(!($damager instanceof Player)) $damager = "none";
        }
        
        if($ev->getFinalDamage() >= $player->getHealth() and in_array($player->getWorld()->getFolderName(), $this->plugin->getConfig()->get("deathcycle-worlds")) and $this->plugin->getConfig()->get("enabled"))
         {
             $wname = $player->getWorld()->getFolderName();
             $mode = "none";
             $kit = "none";
             foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
             {
                 if($wname == $arr["arena"])
                 {
                     $mode = $key;
                     $kit = $arr["kit"];
                 }
             }
             if($mode == "none") return;
             $player->setGameMode(GameMode::ADVENTURE());
             $this->plugin->giveDeathItems($player, $mode);
             $this->plugin->getScheduler()->scheduleRepeatingTask(new DeathTask($this->plugin, $player->getName(), $mode), 20);
             $ev->cancel();
             $dEv = new PlayerDeathEvent($player, $player->getInventory()->getContents(), $player->getXpManager()->getXpLevel(), null);
             $dEv->call();

             if($kit == "none" or $damager == "none") return;
             if(!($damager instanceof Player) or !$damager->isConnected()) return;
             $this->plugin->giveKit($damager, $kit);
             $this->plugin->getServer()->broadcastMessage("§c{$player->getName()} §fwas killed by §c{$damager->getName()}");
         }
    }


    public function onItemUse(PlayerItemUseEvent $ev):void
    {
        $player = $ev->getPlayer();
        $item = $ev->getItem();

        if($item->equals(VanillaItems::DIAMOND_SWORD(), true, false) and $item->getName() == $this->plugin->getConfig()->get("lobby-items")["diamond-sword"]["name"])
        {
            if($player->getWorld()->getFolderName() == $this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getFolderName())
            {
                $this->sendGamemodesGUI($player);
                return;
            }
            $this->plugin->giveGamemodeItems($player);
            return;
        }

        if($item->equals(VanillaItems::BOOK(), true, false) and $item->getName() == $this->plugin->getConfig()->get("lobby-items")["book"]["name"]) {
            $player->sendForm($this->serverInfoForm());
            return;
        }

        foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
        {

            if(!is_null($item->getNamedTag()->getTag("gitem")) and $item->getNamedTag()->getString("gitem") == $key)
            {
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($arr["arena"]);
                if(!($world instanceof World)) return;
                $player->teleport($world->getSpawnLocation());
                $this->plugin->giveKit($player, $arr["kit"]);
                $ev->cancel();
                return;
            }
        }

        $conf = $this->plugin->getConfig()->get("death-items");
        if($item->equals(VanillaItems::COMPASS(), true, false) and $item->getName() == $conf["compass"]["name"])
        {
            if(isset($this->plugin->spectator[$player->getName()]) and $this->plugin->spectator[$player->getName()] == "waiting") return;
            $this->plugin->giveGamemodeItems($player);
            return;
        }

        if($item->equals(VanillaItems::LIME_DYE(), true, false) and $item->getName() == $conf["green-dye"]["name"] and !is_null($item->getNamedTag()->getTag("ditem")))
        {
            if(!isset($this->plugin->getConfig()->get("gamemode-items")[$item->getNamedTag()->getString("ditem")])) return;
            $mode = $this->plugin->getConfig()->get("gamemode-items")[$item->getNamedTag()->getString("ditem")];
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($mode["arena"]);
            if(!($world instanceof World)) return;

            $player->teleport($world->getSpawnLocation());
            $this->plugin->giveKit($player, $mode["kit"]);
            return;
        }

        if($item->equals(VanillaItems::ENDER_PEARL(), true, false) and $item->getName() == $conf["ender-pearl"]["name"] and !is_null($item->getNamedTag()->getTag("ditem")))
        {
            if(!isset($this->plugin->getConfig()->get("gamemode-items")[$item->getNamedTag()->getString("ditem")])) return;
            $mode = $this->plugin->getConfig()->get("gamemode-items")[$item->getNamedTag()->getString("ditem")];
            $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($mode["arena"]);
            if(!($world instanceof World)) return;

            $player->sendForm($this->spectatePlayerForm($world));
            $ev->cancel();
            return;
        }

        if($item->equals(VanillaItems::GHAST_TEAR(), true, false) and $item->getName() == "§r§7Go Back")
        {
            $wname = $player->getWorld()->getFolderName();
            $mode = "none";
            foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
            {
                if($wname == $arr["arena"])
                {
                    $mode = $key;
                }
            }
            $this->plugin->giveDeathItems($player, $mode);
            $ev->cancel();
            return;
        }

        if($item->equals(VanillaItems::REDSTONE_DUST(), true, false) and $item->getName() == "§r§cLobby")
        {
            $player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSpawnLocation());
            $this->plugin->giveLobbyItems($player);
            $ev->cancel();
            return;
        }
        return;
    }

    public function onInteract(PlayerInteractEvent $ev):void
    {
        $item = $ev->getItem();
        $player = $ev->getPlayer();

        if($item->equals(VanillaItems::LAVA_BUCKET(), true, false) and !is_null($item->getNamedTag()->getTag("gitem")) or isset($this->plugin->spectator[$player->getName()]))
        {
            $ev->cancel();
        }
        return;
    }

    public function onItemDrop(PlayerDropItemEvent $ev):void
    {
        if(isset($this->plugin->spectator[$ev->getPlayer()->getName()]))
            $ev->cancel();
    }

    public function onConsume(PlayerItemConsumeEvent $ev):void
    {
        $item = $ev->getItem();
        $player = $ev->getPlayer();

        if($item->equals(VanillaItems::GOLDEN_APPLE(), true, false) and !is_null($item->getNamedTag()->getTag("gitem")) or $item->equals(VanillaItems::HEALING_POTION(), true, false) and !is_null($item->getNamedTag()->getTag("gitem")) or isset($this->plugin->spectator[$player->getName()]))
        {
            $ev->cancel();
        }
        return;
    }



    //forms
    public function serverInfoForm():MenuForm
    {
        return new MenuForm(
            "§r§lServer Info",
            $this->plugin->getConfig()->get("lobby-items")["book"]["message"],
            [],
            function():void{return;}
        );
    }

    public function spectatePlayerForm(World $world):MenuForm
    {
        $buttons = [];
        $players = [];
        foreach($world->getPlayers() as $player)
        {
            if(!isset($this->plugin->spectator[$player->getName()]))
            {
                $buttons[] = new MenuOption($player->getName());
                $players[] = $player;
            }
        }
        return new MenuForm(
            "§l§rSpectate Player",
            "§7Choose a player",
            $buttons,
            function(Player $player, int $data)use($players, $world):void
            {
                $target = $players[$data];
                if(!($target instanceof Player) or !$target->isConnected())
                {
                    $player->sendMessage("§cPlayer is offline");
                    return;
                }
                if($target->getWorld()->getFolderName() != $world->getFolderName())
                {
                    $player->sendMessage("§cPlayer has left the arena");
                    return;
                }
                $player->teleport($target->getPosition());
            }
        );
    }

    public function sendGamemodesGUI(Player $player):void
    {
        if(!$player->isConnected()) return;

        if($player->getPlayerInfo()->getExtraData()["DeviceOS"] === DeviceOS::ANDROID or $player->getPlayerInfo()->getExtraData()["DeviceOS"] === DeviceOS::IOS)
        {
            $config = $this->plugin->getConfig()->get("gamemode-items");

            $potWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($config["health-pot"]["arena"]);
            $sumoWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($config["iron-sword"]["arena"]);
            $gappleWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($config["golden-apple"]["arena"]);
            $buhcWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($config["lava-bucket"]["arena"]);
            $kbWorld = $this->plugin->getServer()->getWorldManager()->getWorldByName($config["bow"]["arena"]);

            if(!($potWorld instanceof World))
            {
                $potCount = "§c[§4Arena Offline§c]";
            }else{
                $potCount = "§r§aPlayers: ".count($potWorld->getPlayers());
            }

            if(!($sumoWorld instanceof World))
            {
                $sumoCount = "§c[§4Arena Offline§c]";
            }else{
                $sumoCount = "§r§aPlayers: ".count($sumoWorld->getPlayers());
            }

            if(!($gappleWorld instanceof World))
            {
                $gappleCount = "§c[§4Arena Offline§c]";
            }else{
                $gappleCount = "§r§aPlayers: ".count($gappleWorld->getPlayers());
            }

            if(!($buhcWorld instanceof World))
            {
                $buhcCount = "§c[§4Arena Offline§c]";
            }else{
                $buhcCount = "§r§aPlayers: ".count($buhcWorld->getPlayers());
            }

            if(!($kbWorld instanceof World))
            {
                $kbCount = "§eComing Soon";
            }else{
                $kbCount = "§r§aPlayers: ".count($kbWorld->getPlayers());
            }

            $potName = str_replace("{playercount}", $potCount, $config["health-pot"]["form-name"]);
            $sumoName = str_replace("{playercount}", $sumoCount, $config["iron-sword"]["form-name"]);
            $gappleName = str_replace("{playercount}", $gappleCount, $config["golden-apple"]["form-name"]);
            $buhcName = str_replace("{playercount}", $buhcCount, $config["lava-bucket"]["form-name"]);
            $kbName = str_replace("{playercount}", $kbCount, $config["bow"]["form-name"]);

            $form = new MenuForm(
                "Choose a gamemode",
                "",
                [
                    new MenuOption($potName, new FormIcon("textures/items/potion_bottle_heal.png", FormIcon::IMAGE_TYPE_PATH)),
                    new MenuOption($sumoName, new FormIcon("textures/items/iron_sword.png", FormIcon::IMAGE_TYPE_PATH)),
                    new MenuOption($gappleName, new FormIcon("textures/items/apple_golden.png", FormIcon::IMAGE_TYPE_PATH)),
                    new MenuOption($buhcName, new FormIcon("textures/items/bucket_lava.png", FormIcon::IMAGE_TYPE_PATH)),
                    new MenuOption($kbName, new FormIcon("textures/items/bow_pulling_0.png", FormIcon::IMAGE_TYPE_PATH))
                ],
                function(Player $player, int $data)use($potWorld, $sumoWorld, $gappleWorld, $buhcWorld, $kbWorld, $config):void
                {
                    switch($data)
                    {
                        case 0:
                            if(!($potWorld instanceof World))
                                return;
                            $player->teleport($potWorld->getSpawnLocation());
                            $this->plugin->giveKit($player, $config["health-pot"]["kit"]);
                        break;

                        case 1:
                            if(!($sumoWorld instanceof World))
                                return;
                            $player->teleport($sumoWorld->getSpawnLocation());
                            $this->plugin->giveKit($player, $config["iron-sword"]["kit"]);
                        break;

                        case 2:
                            if(!($gappleWorld instanceof World))
                                return;
                            $player->teleport($gappleWorld->getSpawnLocation());
                            $this->plugin->giveKit($player, $config["golden-apple"]["kit"]);
                        break;

                        case 3:
                            if(!($buhcWorld instanceof World))
                                return;
                            $player->teleport($buhcWorld->getSpawnLocation());
                            $this->plugin->giveKit($player, $config["lava-bucket"]["kit"]);
                        break;

                        case 4:
                            if(!($kbWorld instanceof World))
                                return;
                            $player->teleport($kbWorld->getSpawnLocation());
                            $this->plugin->giveKit($player, $config["bow"]["kit"]);
                        break;
                    }
                }
            );
            $player->sendForm($form);
            return;
        }

        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("Choose a gamemode");

        foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
        {
            $item = $this->plugin->stringToItem($key);
            if($item instanceof Item)
            {
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($arr["arena"]);

                if(!($world instanceof World))
                {
                    if($key == "bow")
                    {
                        $playerCount = "N/A\n§eComing Soon";
                    }else{
                        $playerCount = "N/A\n§c[§4Arena Offline§c]";
                    }
                }else {
                    $playerCount = count($world->getPlayers());
                }
                $itemName = str_replace("{playercount}", $playerCount, $arr["name"]);
                $item->setCustomName($itemName);
                $item->getNamedTag()->setString("gitem", $key);
                if($item->equals(VanillaItems::BOW(), true, false))
                {
                    $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK(), 1));
                }
                $menu->getInventory()->setItem($arr["gui-slot"], $item);
            }
        }

        $menu->setListener(function(InvMenuTransaction $transaction):InvMenuTransactionResult
        {
            $player = $transaction->getPlayer();
            $clicked = $transaction->getItemClicked();

            foreach($this->plugin->getConfig()->get("gamemode-items") as $key => $arr)
            {
                if(!is_null($clicked->getNamedTag()->getTag("gitem")) and $clicked->getNamedTag()->getString("gitem") == $key)
                {
                    $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($arr["arena"]);
                    if(!($world instanceof World)) return $transaction->discard();
                    $player->teleport($world->getSpawnLocation());
                    $this->plugin->giveKit($player, $arr["kit"]);
                    return $transaction->discard();
                }
            }
            return $transaction->discard();
        });
        $menu->send($player);
    }
}