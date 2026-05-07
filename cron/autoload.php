<?php
    spl_autoload_register(function (string $class) {
        $map = [
            'CronLogger'               => __DIR__ . '/CronLogger.php',
            'TournamentStatusUpdater'  => __DIR__ . '/TournamentStatusUpdater.php',
            'TournamentEngine'         => __DIR__ . '/TournamentEngine.php',
            'BracketGenerator'         => __DIR__ . '/BracketGenerator.php',
            'SingleEliminationFormat'  => __DIR__ . '/formats/SingleEliminationFormat.php',
            'BracketFormatInterface'   => __DIR__ . '/TournamentEngine.php',
        ];
    
        if (isset($map[$class])) require_once $map[$class];
    });
?>