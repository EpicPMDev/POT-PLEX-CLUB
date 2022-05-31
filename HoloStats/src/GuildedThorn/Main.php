<?php

namespace GuildedThorn;

use GuildedThorn\Listeners\PlayerListener;
use GuildedThorn\Utils\FloatingText;
use pocketmine\entity\{Location, EntityFactory, EntityDataHelper};
use pocketmine\plugin\PluginBase;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use pocketmine\world\World;
use pocketmine\nbt\tag\CompoundTag;

class Main extends PluginBase {

    private static Main $main;
    private static DataConnector $database;
    private static string $KillsTitle;
    private static string $KillsLine;
    public $killsloc;
    public $kdrloc;

    private static array $KillArray;

    protected function onLoad(): void {
        self::$main = $this;
    }

    protected function onEnable(): void {
        $this->saveDefaultConfig();

        self::$database = libasynql::create($this, $this->getConfig()->get("database"), [
            "sqlite" => "stats.sql"
        ]);

        EntityFactory::getInstance()->register(FloatingText::class, function(World $world, CompoundTag $nbt):FloatingText{
            return new FloatingText(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ["FloatingText"]);

        $this->getDatabaseConnector()->executeGeneric("Stats.Init");
        $kdrWorld = $this->getConfig()->get("kdr_location")["world"];
        $kdrX = $this->getConfig()->get("kdr_location")["x"];
        $kdrY = $this->getConfig()->get("kdr_location")["y"];
        $kdrZ = $this->getConfig()->get("kdr_location")["z"];
        $killsWorld = $this->getConfig()->get("kills_location")["world"];
        $killsX = $this->getConfig()->get("kills_location")["x"];
        $killsY = $this->getConfig()->get("kills_location")["y"];
        $killsZ = $this->getConfig()->get("kills_location")["z"];
        $this->kdrloc = new Location($kdrX, $kdrY, $kdrZ, $this->getServer()->getWorldManager()->getWorldByName($kdrWorld), 0, 0);
        $this->killsloc = new Location($killsX, $killsY, $killsZ, $this->getServer()->getWorldManager()->getWorldByName($killsWorld), 0, 0);
        
        foreach($this->getServer()->getWorldManager()->getWorldByName($killsWorld)->getEntities() as $entities)
        {
            if($entities instanceof FloatingText)
               $entities->kill();
        }
        foreach($this->getServer()->getWorldManager()->getWorldByName($kdrWorld)->getEntities() as $entities)
        {
            if($entities instanceof FloatingText)
               $entities->kill();
        }


        $this->getDatabaseConnector()->executeSelect("Stats.KillsTop", [], function (array $rows) {
            $array = [];
            $i = 1;
            foreach ($rows as $result) {
                $array[] = Main::parseKillsLine($result["username"], $result["kills"], $i);
                $i++;
            }
            FloatingText::createFloatingText(FloatingText::createText(Main::getKillsTitle(),
            $array), Main::getMain()->getKillLocation(), "kills");
        });

        self::$KillsTitle = $this->getConfig()->get("kills_title");
        self::$KillsLine = $this->getConfig()->get("kills_line");

        $this->getServer()->getPluginManager()->registerEvents(new PlayerListener(), $this);
    }

    protected function onDisable() : void {
        if (isset($this->database))
            $this->database->close();
    }

    public static function getMain() : Main {
        return self::$main;
    }

    public static function getDatabaseConnector() : DataConnector {
        return self::$database;
    }

    public static function getKillsTitle() : string {
        return self::$KillsTitle;
    }

    public static function parseKillsLine(string $username, string $kills, $rank) : string {
        $line = self::$KillsLine;
        $line = str_replace(["{kills}", "{username}", "{rank}"], [$kills, $username, $rank], $line);
        return $line;
    }

    public function getKDRLocation():Location
    {
        return $this->kdrloc;
    }

    public function getKillLocation():Location
    {
        return $this->killsloc;
    }
}