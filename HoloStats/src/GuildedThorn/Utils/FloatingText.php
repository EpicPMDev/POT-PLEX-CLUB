<?php

namespace GuildedThorn\Utils;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use GuildedThorn\Main;

final class FloatingText extends Entity {

    public $type = "";
    public $playername = "";

    public function __construct(Location $location, string $type, ?Player $player = null, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);
        $this->setScale(0.0001);
        $this->type = $type;
        if($player instanceof Player) $this->playername = $player->getName();
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0, 0);
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::PLAYER;
    }

    public function getType():string
    {
        return $this->type;
    }

    public function getPlayerName():string
    {
        return $this->playername;
    }

    public static function createFloatingText(string $text, Location $location, string $type = "kills", null|Player $player = null): self {
        $entity = new FloatingText($location, $type, $player);
        $entity->setNameTag($text);
        $entity->setNameTagVisible();
        $entity->setNameTagAlwaysVisible();
        
        if($type == "kills")
        {
            $entity->spawnToAll();
        }else{
            $entity->despawnFromAll();
            $entity->spawnTo($player);
        }

        return $entity;
    }

    public static function createText(string $title, string|array $lines): string {
        if(is_array($lines))
        {
            $lines = implode("\n", $lines);
        }
        return $title . "\n" . $lines;
    }
    
    public function entityBaseTick(int $tickDiff = 1) : bool{

		if($this->justCreated){
			$this->justCreated = false;
			if(!$this->isAlive()){
				$this->kill();
			}
		}

		$hasUpdate = false;

		if($this->noDamageTicks > 0){
			$this->noDamageTicks -= $tickDiff;
			if($this->noDamageTicks < 0){
				$this->noDamageTicks = 0;
			}
		}
		
		if($this->getType() == "kdr")
		{
		    $this->despawnFromAll();
		    if(!Main::getMain()->getServer()->getPlayerExact($this->getPlayerName()) instanceof Player)
		    {
		        $this->kill();
		        $hasUpdate = true;
		    }else{
		        $this->spawnTo(Main::getMain()->getServer()->getPlayerExact($this->getPlayerName()));
		        $hasUpdate = true;
		    }
		}

		$this->ticksLived += $tickDiff;

		return $hasUpdate;
	}
}