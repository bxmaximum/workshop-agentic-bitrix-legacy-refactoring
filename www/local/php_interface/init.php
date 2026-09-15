<?php

// OMUT_COMPOSER_AUTOLOAD — portable Composer vendor (site root)
$omutAutoloadCandidates = array(
    dirname(__DIR__, 3) . '/vendor/autoload.php', // layout B: www/local/php_interface
    dirname(__DIR__, 2) . '/vendor/autoload.php', // legacy flat
);
foreach ($omutAutoloadCandidates as $omutAutoload) {
    if (is_file($omutAutoload)) {
        require_once $omutAutoload;
        break;
    }
}
