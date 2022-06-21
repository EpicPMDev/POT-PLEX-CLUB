<?php

namespace GuildedThorn\Session;

final class Session {

    public array $data = [
        "Kills" => 0,
        "Deaths" => 0,
        "Tag" => "§6Bronze§r"
    ];

    public function __construct(int $kills, int $deaths, string $tag)
    {
        $this->data["Kills"] = $kills;
        $this->data["Deaths"] = $deaths;
        $this->data["Tag"] = $tag;
    }

    public function addKill(): void {
        $this->data["Kills"] = $this->data["Kills"] + 1;
    }

    public function getKills() : int {
        return $this->data["Kills"];
    }

    public function addDeath() : void {
        $this->data["Deaths"] = $this->data["Deaths"] + 1;
    }

    public function getDeaths() : int {
        return $this->data["Deaths"];
    }

    public function setTag(string $tag) : void {
        $this->data["Tag"] = $tag;
    }

    public function getTag() : string {
        return $this->data["Tag"];
    }

    public function getKDR()
    {
        if($this->data["Kills"] == 0)
        {
            $kdr = "0.00";
        }else{
            if($this->data["Deaths"] == 0)
            {
                $kdr = $this->data["Kills"].".00";
            }else{
                $rawkdr = $this->data["Kills"] / $this->data["Deaths"];
                $kdr = round($rawkdr, 2);
                $kdr = number_format($kdr, 2);
            }
        }
        return $kdr;
    }
}