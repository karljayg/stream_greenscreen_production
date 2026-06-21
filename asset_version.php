<?php
/**
 * Cache-busting version for assets. Uses modification time of stream_production.js.
 * Exclude: production_files/audio/* and production_files/video/*
 */
$jsTime       = file_exists(__DIR__ . '/js/stream_production.js') ? filemtime(__DIR__ . '/js/stream_production.js') : 0;
$otherTime    = file_exists(__DIR__ . '/js/other_lists.js')       ? filemtime(__DIR__ . '/js/other_lists.js')       : 0;
$playerTime   = file_exists(__DIR__ . '/js/playerlist.js')        ? filemtime(__DIR__ . '/js/playerlist.js')        : 0;
$cssTime      = file_exists(__DIR__ . '/styles/styles.css')       ? filemtime(__DIR__ . '/styles/styles.css')       : 0;
$mainTime     = file_exists(__DIR__ . '/styles/main.css')         ? filemtime(__DIR__ . '/styles/main.css')         : 0;
$mxPlayerTime = file_exists(__DIR__ . '/js/music-player.js')      ? filemtime(__DIR__ . '/js/music-player.js')      : 0;
$mxAdminTime  = file_exists(__DIR__ . '/js/music-admin.js')       ? filemtime(__DIR__ . '/js/music-admin.js')       : 0;
$appCssTime   = file_exists(__DIR__ . '/styles/app.css')          ? filemtime(__DIR__ . '/styles/app.css')          : 0;
$v = max($jsTime, $otherTime, $playerTime, $cssTime, $mainTime, $mxPlayerTime, $mxAdminTime, $appCssTime) ?: time();
