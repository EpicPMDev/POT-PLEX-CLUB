<?php

namespace Phqzing\FishingRod;

use pocketmine\item\{Durable, Item, ItemIds, ItemIdentifier, ItemUseResult};
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\entity\Entity;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\world\sound\ThrowSound;

class FishingRod extends Durable {

	public function __construct($meta = 0)
	{
		parent::__construct(new ItemIdentifier(ItemIds::FISHING_ROD, 0), "Fishing Rod");
	}


	public function getMaxStackSize():int
	{
		return 1;
	}

	public function getCooldownTicks():int
	{
		return 5;
	}

	public function getMaxDurability():int
	{
		return 355;
	}


	public function onClickAir(Player $player, Vector3 $dirVec):ItemUseResult
	{
		if (!$player->hasItemCooldown($this)) {
			$player->resetItemCooldown($this);

			if (Loader::getFishing($player) == null) {
				$hook = new FishingHook($player->getLocation(), $player);
				$player->getWorld()->addSound($player->getLocation()->asVector3(), new ThrowSound());
			} else {
				$hook = Loader::getFishing($player);
				if(!$hook->isFlaggedForDespawn())
					$hook->flagForDespawn();
				Loader::setFishing($player, null);
			}
			$player->broadcastAnimation(new ArmSwingAnimation($player));
			return ItemUseResult::SUCCESS();
		}
		return ItemUseResult::FAIL();
	}

	public function getProjectileEntityType():string
	{
		return "Fishing Hook";
	}

	public function getThrowForce():float
	{
		return 0.9;
	}
}