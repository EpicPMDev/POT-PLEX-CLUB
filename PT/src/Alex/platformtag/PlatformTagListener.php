<?php

declare(strict_types=1);

/**
 *
 * @author Alex
 * @link https://github.com/pandaG5019
 *
 */

namespace Alex\platformtag;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

class PlatformTagListener implements Listener
{

    /**
     * @var PlatformTagPlugin
     */
    private PlatformTagPlugin $plugin;

    /**
     * @param PlatformTagPlugin $plugin
     */
    public function __construct(PlatformTagPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param PlayerJoinEvent $event
     * @return void
     */
    public function handlePlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();

        $player->setScoreTag(str_replace(
            "{PLATFORM}",
            $this->plugin->getPlayerPlatform($player),
            $this->plugin->getConfig()->getNested("scoretag")
        ));
    }
}