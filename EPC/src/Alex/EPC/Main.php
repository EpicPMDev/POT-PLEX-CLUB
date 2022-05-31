<?php

namespace Alex\EPC;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

class Main extends PluginBase{
    use SingletonTrait;

    public array $cooldowns = [];

    public function onLoad(): void{
        self::setInstance($this);
    }


    public function onEnable(): void{
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
    }
}
