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
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');


class TheBottleImp extends Table {

    function __construct() {


        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue

        parent::__construct();
        self::initGameStateLabels([
            'roundNumber' => 10,
            'trumpRank' => 11,
            'trumpSuit' => 12,
            'ledSuit' => 13,
            'firstPlayer' => 14,
            'roundsPerPlayer' => 100,
            'numberOfBottles' => 101,
            // 'teamMode' => 102, # TODO options for team modes when playing with 1 bottle
        ]);

        $this->deck = self::getNew('module.common.deck');
        $this->deck->init('card');
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return 'bottleimp';
    }

    /*
        setupNewGame:

        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = [])
    {
        $gameinfos = $this->getGameinfos();
        if ($gameinfos['favorite_colors_support'])
            self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = 'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($gameinfos['player_colors']);
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes($player['player_name'])."','".addslashes($player['player_avatar'])."')";

            // Player statistics - TODO
            // $this->initStat('player', 'won_tricks', 0, $player_id);
            // $this->initStat('player', 'average_points_per_trick', 0, $player_id);
            // $this->initStat('player', 'number_of_trumps_played', 0, $player_id);
        }
        $sql .= implode($values, ',');
        self::DbQuery($sql);

        /************ Start the game initialization *****/

        // Init global values with their initial values

        self::setGameStateInitialValue('roundNumber', 0);
        self::setGameStateInitialValue('trumpRank', 0);
        self::setGameStateInitialValue('trumpSuit', 0);

        // Init game statistics
        // (note: statistics are defined in your stats.inc.php file)

        // Create cards
        $cards = [];
        foreach ($this->cards as $cardinfo) {
            if (count($players) <= 4 && !is_int($cardinfo['rank']))
                continue;
            $cards[] = ['type' => $cardinfo['suit'], 'type_arg' => $cardinfo['rank'], 'nbr' => 1];
        }

        $this->deck->createCards($cards, 'deck');

        // TODO: Init bottles

        // Activate first player (which is in general a good idea :))
        $this->activeNextPlayer();

        $player_id = self::getActivePlayerId();
        self::setGameStateInitialValue('firstPlayer', $player_id);

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas:

        Gather all informations about current game situation (visible by the current player).

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = [ 'players' => [] ];

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = 'SELECT player_id id, player_score score FROM player';
        $result['players'] = self::getCollectionFromDb($sql);

        // Cards in player hand
        $result['hand'] = $this->deck->getCardsInLocation('hand', $current_player_id);

        // Cards played on the table
        $result['cardsontable'] = $this->deck->getCardsInLocation('cardsontable');

        $result['roundNumber'] = $this->getGameStateValue('roundNumber');
        $result['totalRounds'] = count($result['players']) * $this->getGameStateValue('roundsPerPlayer');
        $result['firstPlayer'] = $this->getGameStateValue('firstPlayer');

        $score_piles = $this->getScorePiles();

        foreach ($result['players'] as &$player) {
            $player_id = $player['id'];
            if ($player_id != $current_player_id) {
                $result['opponent_id'] = $player_id;
            }
            $strawmen = $this->getPlayerStrawmen($player_id);
            $player['visible_strawmen'] = $strawmen['visible'];
            $player['more_strawmen'] = $strawmen['more'];
            $player['won_tricks'] = $score_piles[$player_id]['won_tricks'];
            $player['score_pile'] = $score_piles[$player_id]['points'];
            $player['hand_size'] = $this->deck->countCardInLocation('hand', $player_id);
        }

        return $result;
    }

    /*
        getGameProgression:

        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).

        This method is called each time we are in a game state with the "updateGameProgression" property set to true
        (see states.inc.php)
    */
    function getGameProgression() {
        // TODO
        if ($this->gamestate->state()['name'] == 'gameEnd') {
            return 100;
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

    function getBottlePriceAndOwner() {
        return self::getObjectFromDb(
            'SELECT id, owner, price FROM bottles ' .
            'WHERE price = (select max(price) from bottles) ' .
            'ORDER BY id LIMIT 1');
    }

    function getPlayableCards($player_id) {
        // TODO: Modify for 2 players
        // Collect all cards in hand
        $available_cards = $this->deck->getPlayerHand($player_id);
        $led_suit = self::getGameStateValue('ledSuit');
        if ($led_suit == 0) {
            return $available_cards;
        }

        $cards_of_led_suit = [];

        foreach ($available_cards as $available_card_id => $card) {
            if ($card['type'] == $led_suit) {
                $cards_of_led_suit[$card['id']] = $card;
            }
        }

        if ($cards_of_led_suit) {
            return $cards_of_led_suit;
        } else {
            return $available_cards;
        }
    }

    // A card can be autoplayed if it's the only one left, or if the hand is empty
    // and there's only one legal strawman
    function getAutoplayCard($player_id) {
        // TODO: Modify for 2 players
        $cards_in_hand = $this->deck->getPlayerHand($player_id);
        if (count($cards_in_hand) == 1) {
            return array_values($cards_in_hand)[0]['id'];
        } else if (!$cards_in_hand) {
            $playable_cards = $this->getPlayableCards($player_id);
            if (count($playable_cards) == 1) {
                return array_values($playable_cards)[0]['id'];
            }
        }

        return null;
    }

    function getScorePiles() {
        $players = self::loadPlayersBasicInfos();
        $result = [];
        $pile_size_by_player = [];
        foreach ($players as $player_id => $player) {
            $result[$player_id] = ['points' => 0];
            $pile_size_by_player[$player_id] = 0;
        }

        $cards = $this->deck->getCardsInLocation('scorepile');
        foreach ($cards as $card) {
            $player_id = $card['location_arg'];
            $result[$player_id]['points'] += $this->$cards[$card['type_arg']]['points'];
            $pile_size_by_player[$player_id] += 1;
        }

        foreach ($players as $player_id => $player) {
            $result[$player_id]['won_tricks'] = $pile_size_by_player[$player_id] / 2;
        }

        return $result;
    }

    const SUIT_SYMBOLS = ['♠', '♥', '♣'];
    function getSuitLogName($card) {
        return self::SUIT_SYMBOLS[$card['type'] - 1];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    ////////////
    /*
     * Each time a player is doing some game action, one of the methods below is called.
     * (note: each method below must match an input method in template.action.php)
     */
    function passCards($left, $right, $center, $center2) {
        $player_id = self::getCurrentPlayerId();
        $this->passCardsFromPlayer($card_id, $player_id);
    }

    function passCardsFromPlayer($left, $right, $center, $center2, $player_id) {
        self::checkAction('passCards');

        if ($center2 && count($players) != 2)
            throw new BgaUserException(self::_('You must pass 3 cards'));

        $passed_cards = [$left, $right, $center];
        if ($center2) {
            $passed_cards[] = [$center2];
        }

        if (count(array_unique($passed_cards)) != count($passed_cards))
            throw new BgaUserException(self::_('You must unique cards'));

        $cards_in_hand = $this->deck->getPlayerHand($player_id);
        if (!in_array($left, array_keys($cards_in_hand)) ||
            !in_array($right, array_keys($cards_in_hand)) ||
            !in_array($center, array_keys($cards_in_hand)) ||
            ($center2 && !in_array($center2, array_keys($cards_in_hand)))) {
            throw new BgaUserException(self::_('You do not have this card'));
        }

        $this->deck->moveCard($left, 'pass', self::getPlayerAfter($player_id));
        $this->deck->moveCard($right, 'pass', self::getPlayerBefore($player_id));
        $this->deck->moveCard($center, 'center');
        if ($center2) {
            $this->deck->moveCard($center2, 'center');
        }

        self::notifyPlayer($player_id, 'passCardsPrivate', '', ['cards' => $passed_cards]);
        self::notifyAllPlayers('passCards', clienttranslate('${player_name} selected cards to pass'), [
            'player_id' => $player_id,
            'player_name' => self::getPlayerNameById($player_id) ]);
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    function playCard($card_id) {
        self::checkAction('playCard');
        $player_id = self::getActivePlayerId();
        $this->playCardFromPlayer($card_id, $player_id);

        // Next player
        $this->gamestate->nextState();
    }

    function playCardFromPlayer($card_id, $player_id) {
        $current_card = $this->deck->getCard($card_id);

        // Sanity check. A more thorough check is done later.
        if ($current_card['location'] == 'hand' && $current_card['location_arg'] != $player_id) {
            throw new BgaUserException(self::_('You do not have this card'));
        }

        $playable_cards = $this->getPlayableCards($player_id);

        if (!array_key_exists($card_id, $playable_cards)) {
            throw new BgaUserException(self::_('You cannot play this card'));
        }

        $this->deck->moveCard($card_id, 'cardsontable', $player_id);
        if (self::getGameStateValue('ledSuit') == 0)
            self::setGameStateValue('ledSuit', $current_card['type']);
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value} ${suit}'), [
            'card_id' => $card_id,
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'value' => $current_card['type_arg'],
            'suit_id' => $current_card['type'],
            'suit' => $this->getSuitLogName($current_card),
        ]);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////
    /*
    * Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
    * These methods function is to return some additional information that is specific to the current
    * game state.
    */
    function argPlayCard() {
        $playable_cards = $this->getPlayableCards(self::getActivePlayerId());
        return [
            '_private' => [
                'active' => [
                    'playable_cards' => array_keys($playable_cards),
                ],
            ],
        ];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    /*
     * Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
     * The action method of state X is called everytime the current game state is set to X.
     */
    function stNewHand() {
        $this->incGameStateValue('roundNumber', 1);
        self::setGameStateValue('trumpRank', 0);
        self::setGameStateValue('trumpSuit', 0);

        // Shuffle deck
        $this->deck->moveAllCardsInLocation(null, 'deck');
        $this->deck->shuffle('deck');

        // TODO: Modify for 2 players
        // Deal cards
        $players = self::loadPlayersBasicInfos();
        $player_count = count($players);
        if ($player_count == 2) {
            $hand_size = 12;
        } else if ($player_count == 3 || $player_count == 4) {
            $hand_size = 36 / $player_count;
        } else {
            $hand_size = 54 / $player_count;
        }
        foreach ($players as $player_id => $player) {
            $hand_cards = $this->deck->pickCards($hand_size, 'deck', $player_id);
            self::notifyPlayer($player_id, 'newHand', '', ['hand_cards' => $hand_cards]);
        }

        // Notify both players about the public strawmen, first player, and first picker
        self::notifyAllPlayers('newHandPublic', '', [
            'hand_size' => $hand_size,
        ]);

        self::giveExtraTime(self::getActivePlayerId());

        $this->gamestate->nextState();
    }

    function stMakeNextPlayerActive() {
        $player_id = $this->activeNextPlayer();
        self::giveExtraTime($player_id);
        $this->gamestate->nextState();
    }

    function stTakePassedCards() {
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            $cards = $this->cards->getCardsInLocation('pass', $player_id );
            $this->cards->moveAllCardsInLocation('pass', 'hand', $player_id, $player_id);

            self::notifyPlayer($player_id, 'takePassedCards', '', [
                'cards' => $cards
            ]);
        }

        $this->gamestate->nextState();
    }

    function stNewTrick() {
        self::setGameStateValue('ledSuit', 0);
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
        // Move to next player
        if ($this->deck->countCardInLocation('cardsontable') != $this->getPlayersNumber()) {
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
            return;
        }

        // Resolve the trick
        $bottle_info = $this->getBottlePriceAndOwner();
        $cards_on_table = array_values($this->deck->getCardsInLocation('cardsontable'));
        $winning_player = null;

        $max_card = null;
        $max_card_below_price = null;
        $points = 0;

        foreach ($cards_on_table as $card) {
            if ($card['type_arg'] < $bottle_info['price']) {
                if (!$max_card_below_price || $card['type_arg'] >= $max_card_below_price['type_arg']) {
                    $max_card_below_price = $card;
                }
            } else if (!$max_card_below_price) {
                if (!$max_card || $card['type_arg'] >= $max_card['type_arg']) {
                    $max_card = $card;
                }
            }
            $points += $this->cards[$card['type_arg']]['points'];
        }

        if ($max_card_below_price) {
            $winning_card = $max_card_below_price;
            $bottle_id = $bottle_info['id'];
        } else {
            $winning_card = $max_card;
            $bottle_id = 0;
        }
        $winning_player = $winning_card['location_arg'];

        $this->gamestate->changeActivePlayer($winning_player);

        // Move all cards to the winner's score pile
        $this->deck->moveAllCardsInLocation('cardsontable', 'scorepile', null, $winning_player);

        // Update bottle
        $new_price = 0;
        if ($bottle_id) {
            $new_price = floatval($card['type_arg']);
            self::DbQuery("UPDATE bottles SET owner='$winning_player', price=$new_price WHERE id=$bottle_id");
        }

        $players = self::loadPlayersBasicInfos();
        if ($max_card_below_price) {
            if ($bottle_info['owner'] == $winning_player) {
                $win_message = clienttranslate('${player_name} wins the trick worth ${points} points, and keeps the bottle');
            } else {
                if ($this->getGameStateValue('numberOfBottles') == 2) {
                    $win_message = clienttranslate('${player_name} wins the trick worth ${points} points, and takes a bottle');
                } else {
                    $win_message = clienttranslate('${player_name} wins the trick worth ${points} points, and takes the bottle');
                }
            }
        } else {
            $win_message = clienttranslate('${player_name} wins the trick worth ${points} points');
        }

        // Note: we use 2 notifications to pause the display during the first notification
        // before cards are collected by the winner
        self::notifyAllPlayers('trickWin', clienttranslate('${player_name} wins the trick worth ${points} points'), [
            'player_id' => $winning_player,
            'player_name' => $players[$winning_player]['player_name'],
            'points' => $points,
            'bottle_id' => $bottle_id,
            'price' => $new_price,
        ]);
        self::notifyAllPlayers('giveAllCardsToPlayer','', [
            'player_id' => $winning_player,
            'points' => $points,
        ]);

        // TODO: Modify for 2 players
        $remaining_card_count = self::getUniqueValueFromDB('select count(*) from card where card_location = "hand"');
        if ($remaining_card_count == 0) {
            // End of the hand
            $this->gamestate->nextState('endHand');
        } else {
            // End of the trick
            $this->gamestate->nextState('nextTrick');
        }
    }

    function stPlayerTurnTryAutoplay() {
        $player_id = $this->getActivePlayerId();
        $autoplay_card_id = $this->getAutoplayCard($player_id);
        if ($autoplay_card_id) {
            $this->playCardFromPlayer($autoplay_card_id, $player_id);
            $this->gamestate->nextState('nextPlayer');
        } else {
            $this->gamestate->nextState('playerTurn');
        }
    }

    function stEndHand() {
        // Reveal devil's trick
        // TODO: Sort cards for display?
        $center_cards = $this->deck->getCardsInLocation('center');
        $devil_points = 0;
        $devil_cards_display = [];
        foreach ($center_cards as $card) {
            $devil_points = $this->$cards[$card['type_arg']]['points'];
            $suit_display = $this->getSuitLogName($card);
            $devil_cards_display[] = "{$card['type_arg']}$suit_display";
        }
        $devil_cards = join(", ", $devil_cards_display);


        self::notifyAllPlayers('revealDevilsTrick', clienttranslate('Devil\'s trick had ${devil_cards}, worth ${points} points'), [
            'devil_cards' => $devil_cards,
            'points' => $devil_points,
        ]);

        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        $score_piles = $this->getScorePiles();

        $bottle_owners = self::getObjectListFromDB('SELECT owner FROM bottles', true);

        // Apply scores to player
        foreach ($players as $player_id => $player) {
            if (in_array($player_id, $bottle_owners)) {
                if (count($bottle_owners) == 2 && $bottle_owners[0] == $bottle_owners[1]) {
                    $points = 2 * $devil_points;
                    $lose_message = clienttranslate('${player_name} owns both bottles and loses ${points_disp} points');
                } else {
                    $points = $devil_points;
                    if ($this->getGameStateValue('numberOfBottles') == 2) {
                        $lose_message = clienttranslate('${player_name} owns a bottle and loses ${points_disp} points');
                    } else {
                        $lose_message = clienttranslate('${player_name} owns the bottle and loses ${points_disp} points');
                    }
                }
                self::notifyAllPlayers('endHand', $lose_message, [
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points_disp' => $points,
                    'points' => -$points,
                ]);
            } else {
                $points = $score_piles[$player_id]['points'];
                self::DbQuery("UPDATE player SET player_score=player_score+$points  WHERE player_id='$player_id'");
                self::notifyAllPlayers('endHand', clienttranslate('${player_name} collected ${points} points'), [
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points' => $points,
                ]);
            }
        }

        $new_scores = self::getCollectionFromDb('SELECT player_id, player_score FROM player', true);
        $flat_scores = array_values($new_scores);
        self::notifyAllPlayers('newScores', '', ['newScores' => $new_scores]);

        // Check if this is the end of the game
        $end_of_game = false;
        if ($this->getGameStateValue('roundNumber') == count($players) * $this->getGameStateValue('roundsPerPlayer')) {
            $end_of_game = true;
        }

        // Display a score table
        $scoreTable = [];
        $row = [''];
        foreach ($players as $player_id => $player) {
            $row[] = [
                'str' => '${player_name}',
                'args' => ['player_name' => $player['player_name']],
                'type' => 'header'
            ];
        }
        $scoreTable[] = $row;

        $row = [clienttranslate('Score pile')];
        foreach ($players as $player_id => $player) {
            $row[] = $score_piles[$player_id]['points'];
        }
        $scoreTable[] = $row;

        $row = [clienttranslate('Round score')];
        foreach ($players as $player_id => $player) {
            $row[] = $score_piles[$player_id]['points'] + $gift_cards_by_player[$player_id]['type_arg'];
        }
        $scoreTable[] = $row;

        // Add separator before current total score
        $row = [''];
        foreach ($players as $player_id => $player) {
            $row[] = '';
        }
        $scoreTable[] = $row;

        $row = [clienttranslate('Cumulative score')];
        foreach ($players as $player_id => $player) {
            $row[] = $new_scores[$player_id];
        }
        $scoreTable[] = $row;

        $this->notifyAllPlayers('tableWindow', '', [
            'id' => 'scoreView',
            'title' => $end_of_game ? clienttranslate('Final Score') : clienttranslate('End of Round Score'),
            'table' => $scoreTable,
            'closing' => clienttranslate('Continue')
        ]);

        if ($end_of_game) {
            $this->gamestate->nextState('endGame');
            return;
        }

        // Change first player
        $new_first_player = self::getPlayerAfter(self::getGameStateValue('firstPlayer'))
        self::setGameStateValue('firstPlayer', $new_first_player);
        $this->gamestate->changeActivePlayer($new_first_player);
        $this->gamestate->nextState('nextHand');
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:

        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn($state, $active_player)
    {
        $state_name = $state['name'];

        if ($state_name == 'selectTrump') {
            // Select a random trump
            $trump_rank = $this->getGameStateValue('trumpRank');
            $trump_suit = $this->getGameStateValue('trumpSuit');

            if ($trump_rank) {
                $this->selectTrumpForPlayer('suit', bga_rand(1, 4), $active_player);
            } else if ($trump_suit) {
                $this->selectTrumpForPlayer('rank', bga_rand(1, 9), $active_player);
            } else {
                if (bga_rand(0, 1)) {
                    $this->selectTrumpForPlayer('suit', bga_rand(1, 4), $active_player);
                } else {
                    $this->selectTrumpForPlayer('rank', bga_rand(1, 9), $active_player);
                }
            }
        } else if ($state_name == 'giftCard') {
            // Gift a random card
            $cards_in_hand = $this->deck->getPlayerHand($active_player);
            $random_key = array_rand($cards_in_hand);
            $card_id = $cards_in_hand[$random_key]['id'];
            $this->giftCardFromPlayer($card_id, $active_player);
        } else if ($state_name == 'playerTurn') {
            // Play a random card
            $playable_cards = $this->getPlayableCards($active_player);
            $random_key = array_rand($playable_cards);
            $card_id = $playable_cards[$random_key]['id'];
            $this->playCardFromPlayer($card_id, $active_player);

            // Next player
            $this->gamestate->nextState();
        }
    }

///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:

        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.

    */

    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345

        // Example:
//        if($from_version <= 1404301345)
//        {
//            $sql = "ALTER TABLE xxxxxxx ....";
//            self::DbQuery($sql);
//        }
//        if($from_version <= 1405061421)
//        {
//            $sql = "CREATE TABLE xxxxxxx ....";
//            self::DbQuery($sql);
//        }
//        // Please add your future database scheme changes here
//
//


    }
}


