-- #! sqlite
-- #{ staffmode
-- # { bannedinit
CREATE TABLE IF NOT EXISTS banned 
(
    name TEXT PRIMARY KEY,
    time INT,
    by TEXT,
    reason TEXT
);
-- # }

-- # { mutedinit
CREATE TABLE IF NOT EXISTS muted 
(
    name TEXT PRIMARY KEY,
    time INT,
    by TEXT,
    reason TEXT
);
-- # }

-- # { baninsert
-- # :name string
-- # :time int
-- # :by string
-- # :reason string
INSERT OR REPLACE INTO banned (name, time, by, reason) VALUES (:name, :time, :by, :reason);
-- # }

-- # { muteinsert
-- # :name string
-- # :time int
-- # :by string
-- # :reason string
INSERT OR REPLACE INTO muted (name, time, by, reason) VALUES (:name, :time, :by, :reason);
-- # }

-- # { unban
-- # :name string
DELETE FROM banned WHERE lower(name) = :name;
-- # }

-- # { unmute
-- # :name string
DELETE FROM muted WHERE lower(name) = :name;
-- # }

-- # { banselect
-- # :name string
SELECT * FROM banned WHERE lower(name) = :name;
-- # }

-- # { muteselect
-- # :name string
SELECT * FROM muted WHERE lower(name) = :name;
-- # }

-- #}