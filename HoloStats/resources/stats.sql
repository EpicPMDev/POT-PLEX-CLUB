-- #! sqlite

-- #{ Stats

-- # { Init
CREATE TABLE IF NOT EXISTS Players
(
    xuid TEXT NOT NULL  PRIMARY KEY,
    username TEXT NOT NULL,
    kills INT NOT NULL DEFAULT 0,
    deaths INT NOT NULL DEFAULT 0,
    tag TEXT DEFAULT NULL
);
-- # }

-- # { Create
-- # :xuid string
-- # :username string
-- # :tag string
INSERT OR REPLACE INTO Players
(
    xuid, username, kills, deaths, tag) VALUES (
    :xuid, :username, 0,     0,   :tag
);
-- # }

-- # { Get
-- # :xuid string
SELECT *
FROM Players
WHERE xuid = :xuid;
-- # }

-- # { Update
-- #   :xuid string
-- #   :username string
-- #   :kills int
-- #   :deaths int
-- #   :tag string
UPDATE Players
SET kills = :kills, username = :username, deaths = :deaths, tag = :tag
WHERE xuid = :xuid;
-- # }

-- # { UpdateKD
-- #    :kills int
-- #    :deaths int
-- #    :tag string
-- #    :xuid string
UPDATE Players
SET kills = :kills, deaths = :deaths, tag = :tag WHERE xuid = :xuid;
-- # }

-- # { KillsTop
SELECT kills, username FROM Players
ORDER BY kills DESC LIMIT 10;
-- # }

-- #}
