<?php

namespace Phqzing\FishingRod;

use pocketmine\entity\{Location, Entity, EntitySizeInfo};
use pocketmine\entity\projectile\Projectile;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Random;
use pocketmine\event\entity\{EntityDamageByChildEntityEvent, EntityDamageByEntityEvent, EntityDamageEvent, ProjectileHitEntityEvent};
use pocketmine\block\Block;
use pocketmine\world\World;
use pocketmine\math\RayTraceResult;
use pocketmine\player\Player;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class FishingHook extends Projectile {

	public $height = 0.25;
	public $width = 0.25;
	protected $gravity = 0.1;

	public function __construct(Location $loc, ?Entity $owner, ?CompoundTag $nbt = null)
	{
		parent::__construct($loc, $owner, $nbt);

		if($owner instanceof Player)
		{
			$this->setPosition($loc->asVector3()->add(0, $owner->getEyeHeight() - 0.1, 0));
			$this->setMotion($owner->getDirectionVector()->multiply(0.4));
			Loader::setFishing($owner, $this);
			$this->handleHookMotion($this->motion->x, $this->motion->y, $this->motion->z, 2.5, 1.0);
			$this->spawnToAll();
		}
	}

	public function getInitialSizeInfo():EntitySizeInfo
	{
		return new EntitySizeInfo($this->height, $this->width);
	}

	public static function getNetworkTypeId():string
	{
		return EntityIds::FISHING_HOOK;
	}

	public function handleHookMotion(float $x, float $y, float $z, float $f1, float $f2)
	{
		$rand = new Random();
		$f = sqrt($x * $x + $y * $y + $z * $z);
		$x = $x / (float)$f;
		$y = $y / (float)$f;
		$z = $z / (float)$f;
		$x = $x + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
		$y = $y + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
		$z = $z + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
		$x = $x * (float)$f1;
		$y = $y * (float)$f1;
		$z = $z * (float)$f1;
		$this->motion->x += $x;
		$this->motion->y += $y;
		$this->motion->z += $z;
	}

	public function onHitEntity(Entity $entityHit, RayTraceResult $hitResult):void
	{
		$event = new ProjectileHitEntityEvent($this, $hitResult, $entityHit);
		//$event->call();
		$damage = $this->getResultDamage();

		if($this->getOwningEntity() instanceof Player)
		{
			$ev = new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			$entityHit->attack($ev);
			$entityHit->setMotion($this->getOwningEntity()->getDirectionVector()->multiply(0.3)->add(0.4, 0.4, 0.4));
		}
		$this->isCollided = true;
		$this->flagForDespawn();
	}

	protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult):void
	{
		parent::onHitBlock($blockHit, $hitResult);
	}

	public function entityBaseTick(int $tickDiff = 1):bool
	{
		$hasUpdate = parent::entityBaseTick($tickDiff);
		$owner = $this->getOwningEntity();
		if($owner instanceof Player)
		{
			if (!($owner->getInventory()->getItemInHand() instanceof FishingRod) or !$owner->isAlive() or $owner->isClosed())
			{
				if(!$this->isClosed() or !$this->isFlaggedForDespawn()) $this->flagForDespawn();
			}
		} else if(!$this->isClosed() or !$this->isFlaggedForDespawn()) {
			$this->flagForDespawn();
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function getGrapplingSpeed(float $dist):float
	{
		if ($dist > 600):
			$motion = 0.26;
		elseif ($dist > 500):
			$motion = 0.24;
		elseif ($dist > 300):
			$motion = 0.23;
		elseif ($dist > 200):
			$motion = 0.201;
		elseif ($dist > 100):
			$motion = 0.17;
		elseif ($dist > 40):
			$motion = 0.11;
		else:
			$motion = 0.8;
		endif;

		return $motion;
	}

	public function applyGravity():void
	{
		if ($this->isUnderwater())
			$this->motion->y += $this->gravity; else parent::applyGravity();
	}
}