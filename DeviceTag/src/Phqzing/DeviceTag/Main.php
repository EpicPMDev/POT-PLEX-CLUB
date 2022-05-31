<?php

namespace Phqzing\DeviceTag;

use pocketmine\plugin\PluginBase;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\player\Player;

class Main extends PluginBase{

    public static function getPlayerDevice(Player $player):?string
    {
        if(!$player->isConnected()) return null;
        
        switch($player->getPlayerInfo()->getExtraData()["DeviceOS"])
        {
            case DeviceOS::ANDROID:
                return "Android";
            break;
            case DeviceOS::IOS:
                return "iOS";
            break;
            case DeviceOS::OSX:
                return "Mac";
            break;
            case DeviceOS::AMAZON:
                return "FireOS";
            break;
            case DeviceOS::GEAR_VR:
                return "GearVR";
            break;
            case DeviceOS::HOLOLENS:
                return "HoloLens";
            break;
            case DeviceOS::WINDOWS_10:
                return "Windows";
            break;
            case DeviceOS::WIN32:
                return "Windows";
            break;
            case DeviceOS::DEDICATED:
                return "Dedicated";
            break;
            case DeviceOS::TVOS:
                return "TvOS";
            break;
            case DeviceOS::PLAYSTATION:
                return "Playstation";
            break;
            case DeviceOS::NINTENDO:
                return "Switch";
            break;
            case DeviceOS::XBOX:
                return "XBOX";
            break;
            case DeviceOS::WINDOWS_PHONE:
                return "WinPhone";
            break;
            case DeviceOS::UNKNOWN:
                return "Linux";
            break;
            default:
                return "Unknown";
            
        }
    }
}