<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * This is your 'view' file.
 *
 * The method 'build_page' below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * 'build_page' method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in bottleimp_bottleimp.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */

require_once(APP_BASE_PATH.'view/common/game.view.php');

class view_bottleimp_bottleimp extends game_view {
    function getGameName() {
        return 'bottleimp';
    }

    function build_page($viewArgs) {
        // Get players & players number
        $players = $this->game->loadPlayersBasicInfos();
        $player_order = $this->game->getPlayerOrderFromCurrent();
        $template = self::getGameName() . '_' . self::getGameName();

        if (count($players) == 2) {
            global $g_user;
            if ($this->game->isSpectator()) {
                $player_ids = array_keys($players);
                $current_player_id = $player_ids[0];
                $this->tpl['TOP_PLAYER_ID'] = $player_ids[0];
                $this->tpl['BOTTOM_PLAYER_ID'] = $player_ids[1];
            } else {
                $current_player_id = $g_user->get_id();
                $this->tpl['BOTTOM_PLAYER_ID'] = $current_player_id;
            }
        }

        $this->page->begin_block($template, 'player');
        foreach ($player_order as $player_id) {
            $info = $players[$player_id];
            $this->page->insert_block('player', [
                'PLAYER_ID' => $player_id,
                'PLAYER_NAME' => $players[$player_id]['player_name'],
                'PLAYER_COLOR' => $players[$player_id]['player_color'],
                'PLAYER_COLOR_BACK' => '',
            ]);

            if (count($players) == 2 && !$this->game->isSpectator() && $player_id != $current_player_id) {
                $this->tpl['TOP_PLAYER_ID'] = $player_id;
            }
        }

        if (count($players) == 2) {
            $pass_types = ['next', 'center', 'center2', 'prev'];
        } else {
            $pass_types = ['next', 'center', 'prev'];
        }

        $this->page->begin_block($template, 'pass');
        foreach ($pass_types as $pass_type) {
            $this->page->insert_block('pass', [
                'PASS_TYPE' => $pass_type,
            ]);
        }

        $this->tpl['YOUR_HAND'] = self::_('Your hand');
        $this->tpl['YOUR_VISIBLE_HAND'] = self::_('Your visible hand');
        $this->tpl['TRUMP_RANK'] = self::_('Trump rank');
        $this->tpl['TRUMP_SUIT'] = self::_('Trump suit');
        $this->tpl['TRICKS_WON'] = self::_('Tricks won');
      /*********** Do not change anything below this line  ************/
    }
}
