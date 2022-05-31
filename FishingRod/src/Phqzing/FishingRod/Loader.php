<?php

namespace Phqzing\FishingRod;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\item\{ItemFactory, ItemIdentifier, ItemIds};
use pocketmine\entity\{EntityFactory, EntityDataHelper};
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\data\bedrock\EntityLegacyIds;

class Loader extends PluginBase {

	private static $fishing = [];

	public function onEnable():void
	{
		ItemFactory::getInstance()->register(new FishingRod(new ItemIdentifier(ItemIds::FISHING_ROD, 0), "Fishing Rod"), true);

        EntityFactory::getInstance()->register(FishingHook::class, function(World $world, CompoundTag $nbt): FishingHook {
            return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ["Fishing Hook", "minecraft:fishinghook"], EntityLegacyIds::FISHING_HOOK);
	}

	public static function getFishing(Player $player):?FishingHook
	{
		return self::$fishing[$player->getName()] ?? null;
	}

	public static function setFishing(Player $player, ?FishingHook $hook = null):void
	{
		self::$fishing[$player->getName()] = $hook;
	}
}