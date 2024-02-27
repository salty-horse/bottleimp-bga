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
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');


class BottleImp extends Table {

    function __construct() {


        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.json.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue

        parent::__construct();
        self::initGameStateLabels([
            'roundNumber' => 10,
            'ledSuit' => 11,
            'firstPlayer' => 12,
            'numberOfBottles' => 13,
            'roundsPerPlayer' => 100,
            '4playerMode' => 104,
            '5playerMode' => 105,
            '6playerMode' => 106,
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
        $default_colors = $gameinfos['player_colors'];

        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = 'INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ';
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes($player['player_name'])."','".addslashes($player['player_avatar'])."')";

            // Player statistics - TODO
            // $this->initStat('player', 'tricks_won', 0, $player_id);
            // $this->initStat('player', 'average_points_per_trick', 0, $player_id);
            // $this->initStat('player', 'number_of_trumps_played', 0, $player_id);
        }
        self::DbQuery($sql . implode(',', $values));

        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values

        self::setGameStateInitialValue('roundNumber', 0);

        $number_of_bottles = 1;
        $player_count = count($players);
        if ($player_count == 5 || $player_count == 6) {
            if (self::getGameStateValue("${player_count}playerMode") == 1) {
                $number_of_bottles = 2;
            }
        }

        self::setGameStateInitialValue('numberOfBottles', $number_of_bottles);

        // Init game statistics
        // (note: statistics are defined in your stats.json file)

        // Create cards
        $cards = [];
        foreach ($this->cards as $cardinfo) {
            if ($player_count <= 4 && !is_int($cardinfo['rank']))
                continue;
            // Turn float ranks to ints
            $cards[] = ['type' => $cardinfo['suit'], 'type_arg' => $cardinfo['rank'] * 10, 'nbr' => 1];
        }

        $this->deck->createCards($cards, 'deck');

        // Init bottles
        $sql = 'INSERT INTO bottles (id, owner, price) VALUES (1, NULL, 19)';
        if ($number_of_bottles == 2) {
            $sql .= ', (2, "", 19)';
        }
        self::DbQuery($sql);

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
        $state_name = $this->gamestate->state()['name'];
        $result = [
            'players' => [],
            'cards' => $this->cards,
            // Casting and +0 removes trailing zeroes
            'cards_by_id' => self::getCollectionFromDB("SELECT card_id id, (CAST(card_type_arg/10 AS CHAR)+0) FROM card", true ),
        ];

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return informations visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $players = self::getCollectionFromDb('SELECT player_id id, player_score score FROM player');
        $result['players'] = $players;
        $result['bottles'] = self::getCollectionFromDb('SELECT id, owner, price FROM bottles');

        // Cards in player hand
        $result['hand'] = $this->deck->getCardsInLocation('hand', $current_player_id);
        if (count($players) == 2 && $state_name == 'passCards') {
            // Usually this is sent in the public players array
            $result['visible_hand'] = $this->deck->getCardsInLocation('eye', $current_player_id);
        }

        // Cards played on the table
        $result['cardsontable'] = $this->deck->getCardsInLocation('cardsontable');

        $result['roundNumber'] = $this->getGameStateValue('roundNumber');
        $result['totalRounds'] = count($players) * $this->getGameStateValue('roundsPerPlayer');
        $result['firstPlayer'] = $this->getGameStateValue('firstPlayer');
        $result['dealer'] = $this->getDealer();
        $result['teams'] = $this->getTeams();

        $score_piles = $this->getScorePiles();

        foreach ($players as &$player) {
            $player_id = $player['id'];
            $player['tricks_won'] = $score_piles[$player_id]['tricks_won'];
            $player['hand_size'] = $this->deck->countCardInLocation('hand', $player_id);
            if (count($players) == 2 && $state_name != 'passCards') {
                $player['visible_hand'] = $this->deck->getCardsInLocation('eye', $player_id);
            }
        }

        if ($this->gamestate->state()['name'] == 'passCards') {
            $result['players_yet_to_pass_cards'] = $this->gamestate->getActivePlayerList();
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
        return 1;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////

    function getPlayerCount() {
        return self::getUniqueValueFromDB('select count(*) from player');
    }

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
            $result[$player_id]['points'] += $this->cards[$card['type_arg']/10]['points'];
            $pile_size_by_player[$player_id] += 1;
        }

        foreach ($players as $player_id => $player) {
            $result[$player_id]['tricks_won'] = $pile_size_by_player[$player_id] / 2;
        }

        return $result;
    }

    function getDealer() {
        return self::getPlayerBefore(self::getGameStateValue('firstPlayer'));
    }

    function getTeams() {
        $teams = self::getCollectionFromDb('SELECT player_id, team FROM player', true);
        if ($teams[array_key_first($teams)] == null)
            return null;
        return $teams;
    }

    function assignTeams() {
        $player_count = $this->getPlayerCount();
        $team_info = $this->teamModes[$player_count] ?? null;
        if (!$team_info)
            return null;
        $teams = $team_info[self::getGameStateValue($team_info['opt'])] ?? null;
        if (!$teams) {
            return null;
        }

        // 2-2-2 teams don't change after initial assignment
        if ($teams === [2,2,2] && self::getUniqueValueFromDB('SELECT team FROM player LIMIT 1')) {
            return $this->getTeams();
        }

        $current_player = $this->getGameStateValue('firstPlayer');
        $player_to_team = [];
        foreach ($teams as $team_id) {
            $player_to_team[$current_player] = $team_id;
            self::DbQuery("UPDATE player SET team=$team_id WHERE player_id=$current_player");
            $current_player = self::getPlayerAfter($current_player);
        }

        return $player_to_team;
    }

    const SUIT_SYMBOLS = ['♥', '♠', '♣'];
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
        $this->passCardsFromPlayer($player_id, $left, $right, $center, $center2);
    }

    function passCardsFromPlayer($player_id, $left, $right, $center, $center2) {
        self::checkAction('passCards');

        $player_count = $this->getPlayerCount();
        if ($player_count == 2) {
            if (!$center2)
                throw new BgaUserException('You must pass 4 cards');
        } else {
            if ($center2)
                throw new BgaUserException('You must pass 3 cards');
        }

        // TODO - support 2 players - check source of each card

        // In 5-player mode, the dealer doesn't pass to the center
        $pass_two = false;
        if ($player_count == 5 && $player_id == $this->getDealer()) {
            $pass_two = true;
            if ($center)
                throw new BgaUserException('You must not pass the center card');
        } else {
            if (!$center)
                throw new BgaUserException('You must pass the center card');
        }

        $passed_cards = [$left, $right];
        if ($center) {
            $passed_cards[] = $center;
            if ($center2) {
                $passed_cards[] = $center2;
            }
        }
        $cards_center = array_slice($passed_cards, 2);

        if (count(array_unique($passed_cards)) != count($passed_cards))
            throw new BgaUserException('You must unique cards');

        $cards_in_hand = $this->deck->getPlayerHand($player_id);
        if (array_diff($passed_cards, array_keys($cards_in_hand))) {
            // array_diff returns items from the first array that are not in the second array
            throw new BgaUserException('You do not have this card');
        }

        $pass_notify_args = [
            'cards' => $passed_cards,
            'card_left' => $cards_in_hand[$passed_cards[0]]['type_arg']/10,
            'card_right' => $cards_in_hand[$passed_cards[1]]['type_arg']/10,
        ];

        $left_player_id = self::getPlayerAfter($player_id);
        if ($player_count == 2) {
            $this->deck->moveCard($left, 'pass_to_visible', $left_player_id);
            $this->deck->moveCard($right, 'pass_to_hidden', $left_player_id);
        } else {
            $right_player_id = self::getPlayerBefore($player_id);
            $this->deck->moveCard($left, 'pass_from_right', $left_player_id);
            $this->deck->moveCard($right, 'pass_from_left', $right_player_id);
            $pass_notify_args['player_name1'] = self::getPlayerNameById($left_player_id);
            $pass_notify_args['player_name2'] = self::getPlayerNameById($right_player_id);
        }

        $this->deck->moveCard($center, 'center');
        if ($center2) {
            $this->deck->moveCard($center2, 'center');
        }

        if ($pass_two) {
            $notif_message = clienttranslate('You passed ${card_left} to ${player_name1}, and ${card_right} to ${player_name2}');
        } else {
            $notif_message = clienttranslate('You passed ${card_left} to ${player_name1}, ${card_right} to ${player_name2}, and ${card_center} to the Devil\'s Trick');
            if ($player_count == 2) {
                $notif_message = clienttranslate('You passed ${card_left} to the visible hand, ${card_right} to the hidden hand, and ${cards_center} to the Devil\'s Trick');
                $pass_notify_args['cards_center'] = implode(', ', array_slice($cards_center, 2));
            } else {
                $pass_notify_args['card_center'] = $cards_in_hand[$passed_cards[2]]['type_arg']/10;
            }
        }

        self::notifyPlayer($player_id, 'passCardsPrivate', $notif_message, $pass_notify_args);
        self::notifyAllPlayers('passCards', clienttranslate('${player_name} selected cards to pass'), [
            'player_id' => $player_id,
            'player_name' => self::getPlayerNameById($player_id),
        ]);
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    function playCard($card_id) {
        self::checkAction('playCard');
        $player_id = self::getActivePlayerId();
        $this->playCardFromPlayer($player_id, $card_id);

        // Next player
        $this->gamestate->nextState();
    }

    function playCardFromPlayer($player_id, $card_id) {
        $current_card = $this->deck->getCard($card_id);

        // Sanity check. A more thorough check is done later.
        if ($current_card['location'] == 'hand' && $current_card['location_arg'] != $player_id) {
            throw new BgaUserException('You do not have this card');
        }

        $playable_cards = $this->getPlayableCards($player_id);

        if (!array_key_exists($card_id, $playable_cards)) {
            throw new BgaUserException('You cannot play this card');
        }

        $this->deck->moveCard($card_id, 'cardsontable', $player_id);
        if (self::getGameStateValue('ledSuit') == 0)
            self::setGameStateValue('ledSuit', $current_card['type']);
        self::notifyAllPlayers('playCard', clienttranslate('${player_name} plays ${value} ${suit}'), [
            'card_id' => $card_id,
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'value' => $current_card['type_arg']/10,
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
        $round_number = $this->incGameStateValue('roundNumber', 1);

        // Shuffle deck
        $this->deck->moveAllCardsInLocation(null, 'deck');
        $this->deck->shuffle('deck');

        // Deal cards
        $players = self::loadPlayersBasicInfos();
        $player_count = count($players);
        $dealer = $this->getDealer();
        if ($player_count == 2) {
            $hand_size = 12;
        } else if ($player_count == 3 || $player_count == 4) {
            $hand_size = 36 / $player_count;
        } else if ($player_count == 5) {
            $hand_size = 55 / $player_count;
        } else {
            $hand_size = 54 / $player_count;
        }
        foreach ($players as $player_id => $player) {
            if ($player_count == 5 && $player_id == $dealer) {
                $real_hand_size = $hand_size - 1;
            } else {
                $real_hand_size = $hand_size;
            }
            $hand_cards = $this->deck->pickCards($real_hand_size, 'deck', $player_id);
            $args = ['hand_cards' => $hand_cards];
            if ($player_count == 2) {
                $eye_cards = $this->deck->pickCardsForLocation(6, 'deck', 'eye', $player_id);
                $args['visible_hand'] = $eye_cards;
            }
            self::notifyPlayer($player_id, 'newHand', '', $args);

            // Give time before multiactive state
            self::giveExtraTime($player_id);
        }

        $teams = $this->assignTeams();

        self::notifyAllPlayers('newHandPublic', '', [
            'hand_size' => $hand_size,  // 5-player exception handled in JS
            'round_number' => $round_number,
            'dealer' => $dealer,
            'teams' => $teams,
        ]);

        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->nextState();
    }

    function stMakeNextPlayerActive() {
        $player_id = $this->activeNextPlayer();
        self::giveExtraTime($player_id);
        $this->gamestate->nextState();
    }

    function stTakePassedCards() {
        $players = self::loadPlayersBasicInfos();

        $visible_hands = [];

        foreach ($players as $player_id => $player) {
            $left_player_id = self::getPlayerAfter($player_id);

            if (count($players == 2)) {
                $notif_message = clienttranslate('You received ${card_left} to your visible hand, and ${card_right} to your hidden hand');
                $card_from_left = $this->deck->getCardsInLocation('pass_to_visible', $player_id);
                $card_from_right = $this->deck->getCardsInLocation('pass_to_hidden', $player_id);
                $this->deck->moveAllCardsInLocation('pass_to_visible', 'eye', $player_id, $player_id);
                $this->deck->moveAllCardsInLocation('pass_to_hidden', 'hand', $player_id, $player_id);
            } else {
                $notif_message = clienttranslate('You received ${card_left} from ${player_name1}, and ${card_right} from ${player_name2}');
                $right_player_id = self::getPlayerBefore($player_id);
                $card_from_left = $this->deck->getCardsInLocation('pass_from_left', $player_id);
                $card_from_right = $this->deck->getCardsInLocation('pass_from_right', $player_id);
                $this->deck->moveAllCardsInLocation('pass_from_left', 'hand', $player_id, $player_id);
                $this->deck->moveAllCardsInLocation('pass_from_right', 'hand', $player_id, $player_id);
            }

            $visible_hands[$player_id] = $this->deck->getCardsInLocation('eye', $player_id);

            self::notifyPlayer($player_id, 'takePassedCards', $notif_message, [
                'card_id_left' => array_keys($card_from_left)[0],
                'card_id_right' => array_keys($card_from_right)[0],
                'card_left' => array_values($card_from_left)[0]['type_arg']/10,
                'card_right' => array_values($card_from_right)[0]['type_arg']/10,
                'player_name1' => self::getPlayerNameById($left_player_id),
                'player_name2' => self::getPlayerNameById($right_player_id),
            ]);
        }

        // TODO: Add visible passed cards of all players, for spectators
        self::notifyAllPlayers('visibleHandsPublic', '', [
            'visible_hands' => $visible_hands,
        ]);

        $this->gamestate->changeActivePlayer($this->getGameStateValue('firstPlayer'));
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
            $card['type_arg'] /= 10;
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
            $new_price = floatval($winning_card['type_arg']);
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
        self::notifyAllPlayers('trickWin', $win_message, [
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
            $this->playCardFromPlayer($player_id, $autoplay_card_id);
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
            $card['type_arg'] /= 10;
            $devil_points = $this->cards[$card['type_arg']]['points'];
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

        $individual_scores = [];

        // Calculate individual scores
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
                $score_piles[$player_id]['points'] = -$points;
                $individual_scores[$player_id] = -$points;
                self::notifyAllPlayers('endHand', $lose_message, [
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points_disp' => $points,
                    'points' => -$points,
                ]);
            } else {
                $points = $score_piles[$player_id]['points'];
                $individual_scores[$player_id] = $score_piles[$player_id]['points'];
                self::notifyAllPlayers('endHand', clienttranslate('${player_name} collected ${points} points'), [
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points' => $points,
                ]);
            }
        }

        $score_object = $individual_scores;

        // Calculate team scores
        $teams = $this->getTeams();
        if ($teams) {
            $team_scores = [];
            $player_team_scores = [];
            $score_object = $player_team_scores;

            foreach ($teams as $player_id => $team) {
                if (!array_key_exists($team, $team_scores)) {
                    $team_scores[$team] = 0;
                }
                $team_scores[$team] += $individual_scores[$player_id];
            }
            foreach ($teams as $player_id => $team) {
                $player_team_scores[$player_id] = $team_scores[$team];
            }
        }

        // Update scores in DB
        foreach ($score_object as $player_id => $points) {
            self::DbQuery("UPDATE player SET player_score=player_score+$points  WHERE player_id='$player_id'");
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

        $row = [clienttranslate('Round score')];
        foreach ($players as $player_id => $player) {
            $row[] = $score_object[$player_id];
        }
        $scoreTable[] = $row;

        // Add separator before current total score
        $row = [''];
        foreach ($players as $player_id => $player) {
            $row[] = '';
        }
        $scoreTable[] = $row;

        $row = [$end_of_game ? clienttranslate('Final Score') : clienttranslate('Cumulative score')];
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

        // Reset bottles
        self::DbQuery('UPDATE bottles SET owner="", price=19');

        // Change first player
        $new_first_player = self::getPlayerAfter(self::getGameStateValue('firstPlayer'));
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

        // TODO - modify for 2 players, and when the dealer doesn't discard to the devil's trick
        if ($state_name == 'passCards') {
            // Pass random cards
            $cards_in_hand = $this->deck->getPlayerHand($active_player);
            $player_count = $this->getPlayerCount();
            if ($player_count == 2) {
                // TODO 2 players
            } else {
                if ($player_count == 5 && $active_player == $this->getDealer()) {
                    $card_count = 2;
                } else {
                    $card_count = 3;
                }
                $random_keys = array_rand($cards_in_hand, $card_count);
                $cards_to_pass = [];
                foreach ($random_keys as $k) {
                    $cards_to_pass[] = $cards_in_hand[$k]['id'];
                }
                // Pad the array
                for ($i = count($cards_to_pass); $i < 4; $i++) {
                    $cards_to_pass[] = 0;
                }
                $this->passCardsFromPlayer($active_player, ...$cards_to_pass);
            }
        } else if ($state_name == 'playerTurn') {
            // Play a random card
            $playable_cards = $this->getPlayableCards($active_player);
            $random_key = array_rand($playable_cards);
            $card_id = $playable_cards[$random_key]['id'];
            $this->playCardFromPlayer($active_player, $card_id);

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


