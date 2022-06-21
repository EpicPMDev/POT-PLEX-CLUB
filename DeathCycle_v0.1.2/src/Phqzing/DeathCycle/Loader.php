<?php

namespace Phqzing\DeathCycle;

use pocketmine\plugin\PluginBase;
use pocketmine\player\{Player, GameMode};
use pocketmine\world\World;
use pocketmine\item\{Item, VanillaItems};
use pocketmine\item\enchantment\{EnchantmentInstance, VanillaEnchantments};
use pocketmine\entity\effect\{VanillaEffects, EffectInstance, Effect};
use muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ContainerClosePacket;
use muqsit\invmenu\InvMenuHandler;
use Phqzing\DeathCycle\commands\{DeathCycleCommand, ForfeitCommand, KitCommand, LobbyCommand, RekitCommand};

class Loader extends PluginBase {

    public $db;
    public $spectator = [];

    public function onEnable():void
    {
        @mkdir($this->getDataFolder());
        $this->saveResource("config.yml");
        $this->saveDefaultConfig();
        
        $this->getServer()->getPluginManager()->registerEvents(new EListener($this), $this);
        $this->getServer()->getCommandMap()->register("kit", new KitCommand($this));
        $this->getServer()->getCommandMap()->register("lobby", new LobbyCommand($this));
        $this->getServer()->getCommandMap()->register("forfeit", new ForfeitCommand($this));
        $this->getServer()->getCommandMap()->register("deathcycle", new DeathCycleCommand($this));
        $this->getServer()->getCommandMap()->register("rekit", new RekitCommand($this));

        $this->db = new \Sqlite3($this->getDataFolder()."DeathCycle.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS kits(
            kit TEXT PRIMARY KEY,
            contents TEXT,
            armor TEXT,
            gamemode INT,
            effects TEXT
        );");

        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }

        static $send = false;
        SimplePacketHandler::createInterceptor($this)
            ->interceptIncoming(static function(ContainerClosePacket $packet, NetworkSession $session) use(&$send) : bool{
                $send = true;
                $session->sendDataPacket($packet);
                $send = false;
                return true;
            })
            ->interceptOutgoing(static function(ContainerClosePacket $packet, NetworkSession $session) use(&$send) : bool{
                return $send;
            });

//        $this->getScheduler()->scheduleRepeatingTask(new UpdateItemTask($this), 20); <--- enable this if you like to update the items name whenever there is a change in playercount. NOTE: the reason i disabled it is because one time when i was testing it it was bugging it and when i restarted the server it was back to normal, im not taking risks.
    }


    public function onDisable():void
    {
        if(isset($this->db)) $this->db->close();
    }


    public function giveKit(Player $player, string $kit):void
    {
        if(!$player->isConnected()) return;

        if(isset($this->spectator[$player->getName()])) unset($this->spectator[$player->getName()]);
        $player->getInventory()->clearAll();
        $player->getHungerManager()->setFood(20);
        $player->setMaxHealth(20);
        $player->setHealth(20);
        $player->setAllowFlight(false);
        $player->setFlying(false);
        foreach($this->getServer()->getOnlinePlayers() as $players)
        {
            $players->showPlayer($player);
        }
        $res = $this->db->query("SELECT * FROM kits WHERE lower(kit)='".strtolower($kit)."';")->fetchArray(SQLITE3_ASSOC);
        if(empty($res))
        {
            $player->sendMessage("§7".$kit." §ckit does not exist!");
            return;
        }

        $tmpContents = json_decode($res["contents"], true);
        $contents = [];
        foreach($tmpContents as $slot => $arr)
        {
            $contents[$slot] = Item::jsonDeserialize($arr);
        }
        $tmpArmor = json_decode($res["armor"], true);
        $armor = [];
        foreach($tmpArmor as $slot => $arr)
        {
            $armor[$slot] = Item::jsonDeserialize($arr);
        }
        
        $player->getEffects()->clear();
        $effects = json_decode($res["effects"]);
        foreach($effects as $effectArr)
        {
            $effect = $this->stringToEffect($effectArr[0]);
            if($effect instanceof Effect)
            {
                $player->getEffects()->add(new EffectInstance($effect, $effectArr[1], $effectArr[2], false));
            }
        }

        $player->setGameMode(GameMode::fromString($res["gamemode"]));
        $player->getArmorInventory()->setContents($armor);
        $player->getInventory()->setContents($contents);
    }


    public function giveGamemodeItems(Player $player):void
    {
        if(!$player->isConnected()) return;
        $player->getInventory()->clearAll();
        foreach($this->getConfig()->get("gamemode-items") as $key => $arr)
        {
            $item = $this->stringToItem($key);
            if($item instanceof Item)
            {
                $world = $this->getServer()->getWorldManager()->getWorldByName($arr["arena"]);
                    
                if(!($world instanceof World))
                {
                    $playerCount = "N/A\n§c[§4Arena Offline§c]";
                }else {
                    $playerCount = 0;
                    foreach($world->getPlayers() as $players)
                    {
                        if(!isset($this->spectator[$players->getName()])) $playerCount++;
                    }
                }
                $itemName = str_replace("{playercount}", $playerCount, $arr["name"]);
                $item->setCustomName($itemName);
                $item->getNamedTag()->setString("gitem", $key);
                if($item->equals(VanillaItems::BOW(), true, false))
                {
                    $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::KNOCKBACK(), 1));
                }
                $player->getInventory()->setItem($arr["slot"], $item);
            }
        }
        $tear = VanillaItems::GHAST_TEAR()->setCustomName("§r§7Go Back");
        $lobby = VanillaItems::REDSTONE_DUST()->setCustomName("§r§cLobby");

        $player->getInventory()->setItem(0, $lobby);
        $player->getInventory()->setItem(8, $tear);
    }


    public function giveLobbyItems(Player $player):void
    {
        if(!$player->isConnected()) return;

        if(isset($this->spectator[$player->getName()])) unset($this->spectator[$player->getName()]);
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEffects()->clear();
        $player->getHungerManager()->setFood(20);
        $player->setMaxHealth(20);
        $player->setHealth(20);
        $player->setGamemode(GameMode::SURVIVAL());
        $player->setAllowFlight(false);
        $player->setFlying(false);
        foreach($this->getServer()->getOnlinePlayers() as $players)
        {
            $players->showPlayer($player);
        }

        $book = VanillaItems::BOOK()->setCustomName($this->getConfig()->get("lobby-items")["book"]["name"]);
        $sword = VanillaItems::DIAMOND_SWORD()->setCustomName($this->getConfig()->get("lobby-items")["diamond-sword"]["name"]);

        $player->getInventory()->setItem($this->getConfig()->get("lobby-items")["book"]["slot"], $book);
        $player->getInventory()->setItem($this->getConfig()->get("lobby-items")["diamond-sword"]["slot"], $sword);
    }


    public function giveDeathItems(Player $player, string $mode):void
    {
        if(!$player->isConnected()) return;

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEffects()->clear();
        $player->getHungerManager()->setFood(20);
        $player->setMaxHealth(20);
        $player->setHealth(20);
        foreach($this->getServer()->getOnlinePlayers() as $players)
        {
            $players->hidePlayer($player);
        }
        $player->setAllowFlight(true);
        $player->setFlying(true);

        $conf = $this->getConfig()->get("death-items");
        if(!isset($this->spectator[$player->getName()]))
        {
            $this->spectator[$player->getName()] = "waiting";
            $dye = VanillaItems::GRAY_DYE()->setCustomName($conf["grey-dye"]["name"]);
        }else{
            $dye = VanillaItems::LIME_DYE()->setCustomName($conf["green-dye"]["name"]);
            $dye->getNamedTag()->setString("ditem", $mode);
        }
        $ender = VanillaItems::ENDER_PEARL()->setCustomName($conf["ender-pearl"]["name"]);
        $compass = VanillaItems::COMPASS()->setCustomName($conf["compass"]["name"]);

        $ender->getNamedTag()->setString("ditem", $mode);

        $player->getInventory()->setITem($conf["grey-dye"]["slot"], $dye);
        $player->getInventory()->setItem($conf["compass"]["slot"], $compass);
        $player->getInventory()->setITem($conf["ender-pearl"]["slot"], $ender);
    }


    public function stringToEffect(string $string)
    {
        $arr1 = [
            "potion.nightVision" => 0,
            "potion.absorption" => 1,
            "potion.blindness" => 2,
            "potion.conduitPower" => 3,
            "potion.poison" => 4,
            "potion.regeneration" => 5,
            "potion.fireResistance" => 6,
            "potion.digSpeed" => 7,
            "potion.healthBoost" => 8,
            "potion.hunger" => 9,
            "potion.invisibility" => 10,
            "potion.jump" => 11,
            "potion.levitation" => 12,
            "potion.digSlowDown" => 13,
            "potion.confusion" => 14,
            "potion.resistance" => 15,
            "potion.saturation" => 16,
            "potion.moveSlowDown" => 17,
            "potion.moveSpeed" => 18,
            "potion.damageBoost" => 19,
            "potion.waterBreathing" => 20,
            "potion.weakness" => 21,
            "potion.wither" => 22
        ];
        $arr2 = [
            VanillaEffects::NIGHT_VISION(),
            VanillaEffects::ABSORPTION(),
            VanillaEffects::BLINDNESS(),
            VanillaEffects::CONDUIT_POWER(),
            VanillaEffects::POISON(),
            VanillaEffects::REGENERATION(),
            VanillaEffects::FIRE_RESISTANCE(),
            VanillaEffects::HASTE(),
            VanillaEffects::HEALTH_BOOST(),
            VanillaEffects::HUNGER(),
            VanillaEffects::INVISIBILITY(),
            VanillaEffects::JUMP_BOOST(),
            VanillaEffects::LEVITATION(),
            VanillaEffects::MINING_FATIGUE(),
            VanillaEffects::NAUSEA(),
            VanillaEffects::RESISTANCE(),
            VanillaEffects::SATURATION(),
            VanillaEffects::SLOWNESS(),
            VanillaEffects::SPEED(),
            VanillaEffects::STRENGTH(),
            VanillaEffects::WATER_BREATHING(),
            VanillaEffects::WEAKNESS(),
            VanillaEffects::WITHER()
        ];
        if(!isset($arr1[$string])) return null;
        return $arr2[$arr1[$string]];
    }

    public function stringToItem(string $string)
    {
        $arr1 = [
            "iron-sword" => 0,
            "golden-apple" => 1,
            "health-pot" => 2,
            "lava-bucket" => 3,
            "bow" => 4,
        ];
        $arr2 = [
            VanillaItems::IRON_SWORD(),
            VanillaItems::GOLDEN_APPLE(),
            VanillaItems::HEALING_POTION(),
            VanillaItems::LAVA_BUCKET(),
            VanillaItems::BOW(),
        ];
        if(!isset($arr1[$string])) return null;
        return $arr2[$arr1[$string]];
    }
}