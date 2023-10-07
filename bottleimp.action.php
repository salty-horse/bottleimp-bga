<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * The Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * The Bottle Imp main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method 'myAction' here, then you can call it from your javascript code with:
 * this.ajaxcall('/bottleimp/bottleimp/myAction.html', ...)
 *
 */


class action_bottleimp extends APP_GameAction {

    // Constructor: please do not modify
    public function __default() {
        if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs ['table'] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "bottleimp_bottleimp";
            self::trace("Complete reinitialization of board game");
        }
    }

    public function selectTrump() {
        self::setAjaxMode();
        $trump_type = self::getArg('trump_type', AT_enum, true, null, ['rank', 'suit']);
        $trump_id = self::getArg('id', AT_posint, true);
        if ($trump_type == 'rank' && $trump_id > 9 || $trump_type == 'suit' && $trump_id > 4)
            throw new BgaUserException(self::_('Invalid trump value'));

        $this->game->selectTrump($trump_type, $trump_id);
        self::ajaxResponse();
    }

    public function giftCard() {
        self::setAjaxMode();
        $card_id = self::getArg('id', AT_posint, true);
        $this->game->giftCard($card_id);
        self::ajaxResponse();
    }

    public function playCard() {
        self::setAjaxMode();
        $card_id = self::getArg('id', AT_posint, true);
        $this->game->playCard($card_id);
        self::ajaxResponse();
    }
}
