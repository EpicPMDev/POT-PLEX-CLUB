<?php

namespace Phqzing\TagSystem;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\{Listener, EventPriority};
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use GuildedThorn\Session\{Session, SessionManager};
use _64FF00\PurePerms\EventManager\PPRankChangedEvent;

class Loader extends PluginBase implements Listener {

    public static $instance;

    public function onEnable():void
    {
        self::$instance = $this;
        $this->saveDefaultConfig();

        $this->getServer()->getPluginManager()->registerEvent(PlayerDeathEvent::class, static function (PlayerDeathEvent $ev):void
        {
            $player = $ev->getPlayer();
            $cause = $player->getLastDamageCause();
            $pureperms = Loader::getInstance()->getServer()->getPluginManager()->getPlugin("PurePerms");

            if(!($cause instanceof EntityDamageByEntityEvent)) return;
            $damager = $cause->getDamager();
            if(!($damager instanceof Player)) return;
            if(!$damager->isConnected()) return;

            $session = SessionManager::GetSession($damager->getXuid());
            $kills = $session->getKills();
            $deaths = $session->getDeaths();
            $kdr = $session->getKDR();

            $tier = Loader::calculateTier($kills, $kdr);

            if($tier != $session->getTag())
            {
                $WorldName = $damager->getWorld()->getFolderName();
                
                if(Loader::isDemoted($tier, $session->getTag()))
                {
                   $msg = str_replace("{tier}", $tier, Loader::getInstance()->getConfig()->get("tier-downgrade-message"));
                }else{
                   $msg = str_replace("{tier}", $tier, Loader::getInstance()->getConfig()->get("tier-upgrade-message"));
                }

                $msg = str_replace("{tier}", $tier, Loader::getInstance()->getConfig()->get("tier-upgrade-message"));
                $damager->sendMessage($msg);
                $session->setTag($tier);
                $event = new PPRankChangedEvent($pureperms, $damager, $pureperms->getUserDataMgr()->getGroup($damager, $WorldName), $WorldName);
                $event->call();
            }


            $session = SessionManager::GetSession($player->getXuid());
            $kills = $session->getKills();
            $deaths = $session->getDeaths();
            $kdr = $session->getKDR();
            
            $tier = Loader::calculateTier($kills, $kdr);

            if($tier != $session->getTag())
            {
                $WorldName = $player->getWorld()->getFolderName();

                if(Loader::isDemoted($tier, $session->getTag()))
                {
                   $msg = str_replace("{tier}", $tier, Loader::getInstance()->getConfig()->get("tier-downgrade-message"));
                }else{
                   $msg = str_replace("{tier}", $tier, Loader::getInstance()->getConfig()->get("tier-upgrade-message"));
                }
                $player->sendMessage($msg);
                $session->setTag($tier);
                $event = new PPRankChangedEvent($pureperms, $player, $pureperms->getUserDataMgr()->getGroup($player, $WorldName), $WorldName);
                $event->call();
            }

        }, EventPriority::NORMAL, $this);
    }

    public static function getInstance():Loader
    {
        return self::$instance;
    }

    public static function calculateTier($kills, $kdr):string
    {
        $tier = "§6Bronze§r";

        foreach(self::$instance->getConfig()->get("tiers") as $name => $arr)
        {
            if($kills >= $arr["kills"] and $kdr >= $arr["kdr"])
            {
                $tier = $name;
            }
        }
        return $tier;
    }
    
    public static function isDemoted(string $tier1, string $tier2):bool
    {
        if($tier1 == "§6Bronze§r") return true;
        if($tier2 == "§6Bronze§r") return false;
    
        $t1k = self::$instance->getConfig()->get("tiers")[$tier1]["kills"];
        $t1kdr = self::$instance->getConfig()->get("tiers")[$tier1]["kdr"];
        $t2k = self::$instance->getConfig()->get("tiers")[$tier2]["kills"];
        $t2kdr = self::$instance->getConfig()->get("tiers")[$tier2]["kdr"];
        
        if($t1k >= $t2k and $t1kdr >= $t2kdr)
           return false;
        return true;
    }
}