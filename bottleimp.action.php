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

    public function passCards() {
        self::setAjaxMode();
        $left = self::getArg('left', AT_float, true);
        $right = self::getArg('right', AT_float, true);
        $center = self::getArg('center', AT_float, true);
        $center2 = self::getArg('center2', AT_float, false);
        if (!array_key_exists($left, $this->cards))
            throw new BgaUserException(self::_('Invalid card value'));
        if (!array_key_exists($right, $this->cards))
            throw new BgaUserException(self::_('Invalid card value'));
        if (!array_key_exists($center, $this->cards))
            throw new BgaUserException(self::_('Invalid card value'));
        if ($center2 && !array_key_exists($center2, $this->cards))
            throw new BgaUserException(self::_('Invalid card value'));
        $this->game->passCards($left, $right, $center, $center2);
        self::ajaxResponse();
    }

    public function playCard() {
        self::setAjaxMode();
        $card_id = self::getArg('id', AT_float, true);
        if (!array_key_exists($card_id, $this->cards))
            throw new BgaUserException(self::_('Invalid card value'));
        $this->game->playCard($card_id);
        self::ajaxResponse();
    }
}
