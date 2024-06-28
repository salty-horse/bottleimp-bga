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
            'cardsPlayed' => 14,
            'cardsToPlay' => 15,
            'roundsPerPlayer' => 100,
            '4playerMode' => 104,
            '5playerMode' => 105,
            '6playerMode' => 106,
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
        self::setGameStateInitialValue('cardsPlayed', 0);

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

        $player_count = count($players);
        if ($player_count == 2) {
            $cards_per_hand = 17 * 2;
        } else if ($player_count == 3)  {
            $cards_per_hand = 11 * 3;
        } else if ($player_count == 4)  {
            $cards_per_hand = 8 * 4;
        } else if ($player_count == 5) {
            $cards_per_hand = 10 * 5;
        } else {
            $cards_per_hand = 8 * 6;
        }
        self::setGameStateInitialValue('cardsToPlay', count($players) * $this->getGameStateValue('roundsPerPlayer') * $cards_per_hand);

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
        $result['players'] = &$players;
        $result['bottles'] = self::getCollectionFromDb('SELECT id, owner, price FROM bottles');

        // Cards in player hand
        $result['hand'] = $this->deck->getCardsInLocation('hand', $current_player_id);
        if (count($players) == 2 && $state_name == 'passCards') {
            // Usually this is sent in the public players array
            $result['visible_hand'] = $this->deck->getCardsInLocation('hand_eye', $current_player_id);
        }

        // Cards played on the table
        $result['cardsontable'] = $this->getCardsOnTable();

        $result['roundNumber'] = $this->getGameStateValue('roundNumber');
        $result['totalRounds'] = count($players) * $this->getGameStateValue('roundsPerPlayer');
        $result['firstPlayer'] = $this->getGameStateValue('firstPlayer');
        $result['dealer'] = $this->getDealer();
        $result['teams'] = $this->getTeams();

        $score_piles = $this->getScorePiles();

        foreach ($players as &$player) {
            $player_id = $player['id'];
            $player['tricks_won'] = $score_piles[$player_id]['tricks_won'];
            $player['hand_size'] = $this->deck->countCardInLocation('hand', $player_id);  // Only used in 2-player game
            if (count($players) == 2 && $state_name != 'passCards') {
                $player['visible_hand'] = $this->deck->getCardsInLocation('hand_eye', $player_id);
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
        if ($this->gamestate->state()['name'] == 'gameEnd') {
            return 100;
        }
        if ($this->getGameStateValue('cardsToPlay') == 0) {
            return 0;
        }
        return floor($this->getGameStateValue('cardsPlayed') * 100 / $this->getGameStateValue('cardsToPlay'));
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utilities
    ////////////


    function getPlayerOrderFromCurrent() {
        $player_order = self::getObjectListFromDB("SELECT player_id FROM player ORDER BY player_no", true);
        if (self::isSpectator()) {
            return $player_order;
        }
        $player_id = self::getCurrentPlayerId();
        $i = 0;
        while ($player_order[0] != $player_id) {
            $i += 1;
            if ($i > count($player_order)) {
                throw new BgaVisibleSystemException('Error creating player list');
            }
            $popped = array_shift($player_order);
            $player_order[] = $popped;
        }
        return $player_order;
    }

    function getBottlePriceAndOwner() {
        return self::getObjectFromDb(
            'SELECT id, owner, price FROM bottles ' .
            'WHERE price = (select max(price) from bottles) ' .
            'ORDER BY id LIMIT 1');
    }

    function getCardsOnTable() {
        return self::getObjectListFromDB(
            'select card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg from card where card_location like "cardsontable%"');
    }

    function getPlayableCards($player_id) {
        $cards_hand = $this->getPlayableCardsForHand($player_id, 'hand');
        if ($this->getPlayersNumber() != 2) {
            return $cards_hand;
        }
        $cards_eye = $this->getPlayableCardsForHand($player_id, 'hand_eye');
        return $cards_hand + $cards_eye;
    }

    function getPlayableCardsForHand($player_id, $hand_name) {
        // Collect all cards in hand
        $available_cards = $this->deck->getCardsInLocation($hand_name, $player_id);
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
        $cards_in_hand = self::getObjectListFromDB("SELECT card_id FROM card where card_location_arg='$player_id' and card_location='hand'", true);
        if ($this->getPlayersNumber() == 2) {
            $cards_in_visible_hand = self::getObjectListFromDB("SELECT card_id FROM card where card_location_arg='$player_id' and card_location='hand_eye'", true);
            if (count($cards_in_hand) == 1 && count($cards_in_visible_hand) == 0) {
                return $cards_in_hand[0];
            } else if (count($cards_in_hand) == 0) {
                $playable_cards = $this->getPlayableCards($player_id);
                if (count($playable_cards) == 1) {
                    return array_values($playable_cards)[0]['id'];
                }
            }
        } else {
            if (count($cards_in_hand) == 1) {
                return $cards_in_hand[0];
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
        if ($teams[array_key_first($teams)] == 0)
            return null;
        return $teams;
    }

    function assignTeams() {
        $player_count = $this->getPlayersNumber();
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
    function passCards($next, $prev, $center, $center2) {
        $player_id = self::getCurrentPlayerId();
        $this->passCardsFromPlayer($player_id, $next, $prev, $center, $center2);
    }

    function passCardsFromPlayer($player_id, $next, $prev, $center, $center2) {
        self::checkAction('passCards');

        $player_count = $this->getPlayersNumber();
        if ($player_count == 2) {
            if (!$center2)
                throw new BgaUserException('You must pass 4 cards');
        } else {
            if ($center2)
                throw new BgaUserException('You must pass 3 cards');
        }

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

        $passed_cards = [$next, $prev];
        if ($center) {
            $passed_cards[] = $center;
            if ($center2) {
                $passed_cards[] = $center2;
            }
        }

        if (count(array_unique($passed_cards)) != count($passed_cards))
            throw new BgaUserException('You must unique cards');

        $cards_in_hand = $this->deck->getPlayerHand($player_id);
        if (array_diff($passed_cards, array_keys($cards_in_hand))) {
            // array_diff returns items from the first array that are not in the second array
            throw new BgaUserException('You do not have this card');
        }

        $pass_notify_args = [
            'cards' => $passed_cards,
            'card_next' => $cards_in_hand[$passed_cards[0]]['type_arg']/10,
            'card_prev' => $cards_in_hand[$passed_cards[1]]['type_arg']/10,
        ];

        $next_player_id = self::getPlayerAfter($player_id);
        if ($player_count == 2) {
            $this->deck->moveCard($next, 'pass_to_visible', $next_player_id);
            $this->deck->moveCard($prev, 'pass_to_hidden', $next_player_id);
        } else {
            $prev_player_id = self::getPlayerBefore($player_id);
            $this->deck->moveCard($next, 'pass_from_prev', $next_player_id);
            $this->deck->moveCard($prev, 'pass_from_next', $prev_player_id);
            $pass_notify_args['player_name1'] = self::getPlayerNameById($next_player_id);
            $pass_notify_args['player_name2'] = self::getPlayerNameById($prev_player_id);
        }

        $this->deck->moveCard($center, 'center');
        if ($center2) {
            $this->deck->moveCard($center2, 'center');
        }

        if ($pass_two) {
            $notif_message = clienttranslate('You passed ${card_next} to ${player_name1}, and ${card_prev} to ${player_name2}');
        } else {
            $notif_message = clienttranslate('You passed ${card_next} to ${player_name1}, ${card_prev} to ${player_name2}, and ${card_center} to the Devil\'s Trick');
            if ($player_count == 2) {
                $notif_message = clienttranslate('You passed ${card_next} to the visible hand, ${card_prev} to the hidden hand, and ${cards_center} to the Devil\'s Trick');
                $pass_notify_args['cards_center'] = implode(', ', array_slice($passed_cards, 2));
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

        if ($this->getPlayersNumber() != 2) {
            $this->deck->moveCard($card_id, 'cardsontable', $player_id);
        } else {
            $target = ($this->deck->countCardInLocation('cardsontable', $player_id) == 0) ?
                'cardsontable' : 'cardsontable_2';
            $this->deck->moveCard($card_id, $target, $player_id);
        }

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
        $this->incGameStateValue('cardsPlayed', 1);
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
                $eye_cards = $this->deck->pickCardsForLocation(6, 'deck', 'hand_eye', $player_id);
                $args['visible_hand'] = $eye_cards;
            }
            self::notifyPlayer($player_id, 'newHand', '', $args);

            // Give time before multiactive state
            self::giveExtraTime($player_id);
        }

        $teams = $this->assignTeams();

        self::notifyAllPlayers('newHandPublic', '', [
            'hand_size' => $hand_size,  // Only used in 2-player game
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
        $player_count = $this->getPlayersNumber();

        foreach ($players as $player_id => $player) {
            $next_player_id = self::getPlayerAfter($player_id);

            $notify_args = [
                'player_name1' => self::getPlayerNameById($next_player_id),
            ];

            if (count($players) == 2) {
                $notif_message = clienttranslate('You received ${card_next} to your visible hand, and ${card_prev} to your hidden hand');
                $card_from_next = $this->deck->getCardsInLocation('pass_to_visible', $player_id);
                $card_from_prev = $this->deck->getCardsInLocation('pass_to_hidden', $player_id);
                $this->deck->moveAllCardsInLocation('pass_to_visible', 'hand_eye', $player_id, $player_id);
                $this->deck->moveAllCardsInLocation('pass_to_hidden', 'hand', $player_id, $player_id);
            } else {
                $notif_message = clienttranslate('You received ${card_next} from ${player_name1}, and ${card_prev} from ${player_name2}');
                $prev_player_id = self::getPlayerBefore($player_id);
                $card_from_next = $this->deck->getCardsInLocation('pass_from_next', $player_id);
                $card_from_prev = $this->deck->getCardsInLocation('pass_from_prev', $player_id);
                $this->deck->moveAllCardsInLocation('pass_from_next', 'hand', $player_id, $player_id);
                $this->deck->moveAllCardsInLocation('pass_from_prev', 'hand', $player_id, $player_id);
                $notify_args['player_name2'] = self::getPlayerNameById($prev_player_id);
            }

            $notify_args['card_id_next'] = array_keys($card_from_next)[0];
            $notify_args['card_id_prev'] = array_keys($card_from_prev)[0];
            $notify_args['card_next'] = array_values($card_from_next)[0]['type_arg']/10;
            $notify_args['card_prev'] = array_values($card_from_prev)[0]['type_arg']/10;

            if ($player_count == 2) {
                $visible_hands[$player_id] = $this->deck->getCardsInLocation('hand_eye', $player_id);
            }

            self::notifyPlayer($player_id, 'takePassedCards', $notif_message, $notify_args);
        }

        if ($player_count == 2) {
            // TODO: Add visible passed cards of all players, for spectators
            self::notifyAllPlayers('visibleHandsPublic', '', [
                'visible_hands' => $visible_hands,
            ]);
        }

        $this->gamestate->changeActivePlayer($this->getGameStateValue('firstPlayer'));
        $this->gamestate->nextState();
    }

    function stNewTrick() {
        self::setGameStateValue('ledSuit', 0);
        $this->gamestate->nextState();
    }

    function stNextPlayer() {
        // Move to next player
        $player_count = $target_card_count = $this->getPlayersNumber();
        if ($player_count == 2) {
            $target_card_count = 4;
        }
        $card_count = self::getUniqueValueFromDB('select count(*) from card where card_location like "cardsontable%"');
        if ($card_count != $target_card_count) {
            $player_id = self::activeNextPlayer();
            self::giveExtraTime($player_id);
            $this->gamestate->nextState('nextPlayer');
            return;
        }

        // Resolve the trick
        $bottle_info = $this->getBottlePriceAndOwner();
        $cards_on_table = $this->getCardsOnTable();
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
        self::DbQuery("UPDATE card SET card_location='scorepile', card_location_arg='$winning_player' WHERE card_location like 'cardsontable%'");

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

        $notify_args = [
            'player_id' => $winning_player,
            'player_name' => $players[$winning_player]['player_name'],
            'points' => $points,
            'bottle_id' => $bottle_id,
            'price' => $new_price,
        ];

        if ($winning_card['location_arg'][-1] == '2') {
            $notify_args['slot'] = 2;
        }

        self::notifyAllPlayers('trickWin', $win_message, $notify_args);

        $remaining_card_count = self::getUniqueValueFromDB('select count(*) from card where card_location like "hand%"');
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
        $center_cards = $this->deck->getCardsInLocation('center', null, 'card_type_arg');
        $devil_points = 0;
        $devil_cards_display = [];
        $devil_cards = [];
        foreach ($center_cards as $card) {
            $card['type_arg'] /= 10;
            $devil_points += $this->cards[$card['type_arg']]['points'];
            $devil_cards[] = $card['id'];
            $suit_display = $this->getSuitLogName($card);
            $devil_cards_display[] = "{$card['type_arg']}$suit_display";
        }
        sort($devil_cards);
        $devil_cards_str = join(", ", $devil_cards_display);


        self::notifyAllPlayers('revealDevilsTrick', clienttranslate('Devil\'s trick had ${devil_cards}, worth ${points} points'), [
            'devil_cards' => $devil_cards_str,
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
                self::notifyAllPlayers('log', $lose_message, [
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points_disp' => $points,
                    'points' => -$points,
                ]);
            } else {
                $points = $score_piles[$player_id]['points'];
                $individual_scores[$player_id] = $score_piles[$player_id]['points'];
                self::notifyAllPlayers('log', clienttranslate('${player_name} collected ${points} points'), [
                    'player_id' => $player_id,
                    'player_name' => $players[$player_id]['player_name'],
                    'points' => $points,
                ]);
            }
        }

        $score_object = &$individual_scores;

        // Calculate team scores
        $teams = $this->getTeams();
        $team_scores = [];
        if ($teams) {
            $player_team_scores = [];
            $score_object = &$player_team_scores;

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
            self::DbQuery("UPDATE player SET player_score=player_score+$points WHERE player_id='$player_id'");
        }

        // Check if this is the end of the game
        $end_of_game = false;
        if ($this->getGameStateValue('roundNumber') == count($players) * $this->getGameStateValue('roundsPerPlayer')) {
            $end_of_game = true;
        }

        $new_scores = self::getCollectionFromDb('SELECT player_id, player_score FROM player', true);
        $flat_scores = array_values($new_scores);
        self::notifyAllPlayers('newScores', '', [
            'individual_scores' => $individual_scores,
            'team_scores' => $team_scores,
            'player_points' => $score_object,
            'devil_cards' => $devil_cards,
            'devil_points' => $devil_points,
            'end_of_game' => $end_of_game,
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

        if ($state_name == 'passCards') {
            // Pass random cards
            $cards_in_hand = $this->deck->getPlayerHand($active_player);
            $player_count = $this->getPlayersNumber();
            if ($player_count == 2) {
                $card_count = 4;
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


