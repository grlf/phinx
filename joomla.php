<?php

require_once "configuration.php";

$joom = new JConfig();

return array(
    "paths" => array(
        "migrations" => "%%PHINX_CONFIG_DIR%%/db/migrations",
        "seeds" => "%%PHINX_CONFIG_DIR%%/db/seeds"
    ),
    "environments" => array(
        "default_migration_table" => "phinxlog",
        "default_database" => "production",
        "production" => array(
            "adapter" => "mysql",
            "host" => $joom->host,
            "name" => $joom->db,
            "user" => $joom->user,
            "pass" => $joom->password,
            "table_prefix" => $joom->dbprefix
        )
    )
);