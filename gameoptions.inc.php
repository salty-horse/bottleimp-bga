<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * The Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * The Bottle Imp game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in bottleimp.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = [
    100 => [
        'name' => totranslate('Rounds per player'),
        'values' => [
            2 => ['name' => totranslate('Standard game (2 rounds per player)')],
            3 => ['name' => totranslate('Long game (3 rounds per player)')],
        ],
        'default' => 2
    ],
    101 => [
        'name' => totranslate('Number of bottles'),
        'values' => [
            1 => ['name' => totranslate('One bottle (Team mode)')],
            2 => ['name' => totranslate('Two bottles (No teams)')],
        ],
        'default' => 1,
        'displaycondition' => [
            [
                'type' => 'minplayers',
                'value' => 5,
            ]
        ],
    ],
];
