/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * The Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * User interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

/* global define, ebg, _, $, g_gamethemeurl */
/* eslint no-unused-vars: ["error", {args: "none"}] */

'use strict';

define([
    'dojo',
    'dojo/_base/declare',
    'dojo/dom',
    'dojo/on',
    'ebg/core/gamegui',
    'ebg/counter',
    'ebg/stock'
],
function (dojo, declare) {
    return declare('bgagame.bottleimp', ebg.core.gamegui, {
        constructor: function(){
            this.cardWidth = 143;
            this.cardHeight = 200;

            this.suitSymbolToId = {
                '♥': 1,
                '♠': 2,
                '♣': 3,
            };
        },

        /*
            setup:

            This method must set up the game user interface according to current game situation specified
            in parameters.

            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)

            'gamedatas' argument contains all datas retrieved by your 'getAllDatas' PHP method.
        */


        setup: function(gamedatas) {
            console.log('gamedatas', gamedatas);

            this.suitNames = {
                1: _('red'),
                2: _('blue'),
                3: _('green'),
            };

            // Set dynamic UI strings
            // TODO: 2 players
            // if (this.isSpectator) {
            //     for (const player_info of Object.values(this.gamedatas.players)) {
            //         this.setStrawmanPlayerLabel(player_info);
            //     }
            // } else {
            //     this.setStrawmanPlayerLabel(gamedatas.players[gamedatas.opponent_id]);
            // }

            // Player hand
            this.playerHand = new ebg.stock();
            this.playerHand.setSelectionMode(1);
            this.playerHand.centerItems = false;
            this.playerHand.autowidth = true;
            this.playerHand.create(this, $('imp_myhand'), this.cardWidth, this.cardHeight);
            this.playerHand.image_items_per_row = 11;

            this.rankToSpritesheet = {
                '1': 1, '2': 2, '2.5': 3, '4': 4, '4.5': 5, '5': 6, '7': 7, '9': 8, '12': 9, '12.5': 10,
                '15': 11, '18': 12, '18.5': 13, '22': 14, '22.5': 15, '25': 16, '28': 17, '28.5': 18, '3': 19, '6': 20, '6.5': 21,
                '8': 22, '8.5': 23, '10': 24, '10.5': 25, '13': 26, '17': 27, '20': 28, '20.5': 29, '23': 30, '27': 31, '30': 32,
                '30.5': 33, '32': 34, '32.5': 35, '35': 36, '11': 37, '14': 38, '14.5': 39, '16': 40, '16.5': 41, '21': 42, '24': 43,
                '24.5': 44, '26': 45, '26.5': 46, '29': 47, '31': 48, '33': 49, '34': 50, '34.5': 51, '36': 52, '36.5': 53, '37': 54
            };

            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            // Info box
            document.getElementById('imp_round_number').textContent = this.gamedatas.roundNumber;
            document.getElementById('imp_total_rounds').textContent = this.gamedatas.totalRounds;
            this.initBottles();

            // Init pass boxes
            document.getElementById('imp_passCards').style.display = 'none';
            this.activePassType = null;
            this.passCards = {};
            dojo.query('.imp_pass').on('click', (e) => {
                if (!this.isCurrentPlayerActive())
                    return;
                this.markActivePassBox(e.currentTarget.dataset.passType);
            });

            // The player order array mixes strings and numbers
            let playerorder = gamedatas.playerorder.map(x => parseInt(x));
            let playerPos = playerorder.indexOf(this.player_id);

            this.playersPassedCards = [];
            this.passPlayers = {};
            this.passPlayers[playerorder[(playerPos + 1) % playerorder.length]] = 'left';
            this.passPlayers[playerorder[(playerPos == 0 ? playerorder.length : playerPos) - 1]] = 'right';
            document.querySelector('#imp_pass_center > .imp_playertablename').innerHTML = _("Devil's Trick");
            // TODO: Are tooltips necessary?
            // this.addTooltip('imp_pass_center', _("Card to place in the Devil's trick"), '');

            if (playerorder.length == 2) {
                // TODO: Tooltips
                this.passKeys = ['left', 'center', 'center2', 'right'];
            } else {
                this.passKeys = ['left', 'center', 'right'];
            }

            for (let [player_id, pos] of Object.entries(this.passPlayers)) {
                let player_info = gamedatas.players[player_id];
                document.querySelector(`#imp_pass_${pos} > .imp_playertablename`).innerHTML = dojo.string.substitute(
                    "${player_name}",
                    {player_name: `<span style="color:#${player_info.color}">${player_info.name}</span>`});

                if (gamedatas.gamestate.name == 'passCards' && gamedatas.players_yet_to_pass_cards.indexOf(player_id) == -1) {
                    this.playersPassedCards.push(player_id);
                }
            }

            // Create cards types
            for (let card of Object.values(gamedatas.cards)) {
                this.playerHand.addItemType(card.rank, card.rank, g_gamethemeurl+'img/cards.jpg', this.rankToSpritesheet[card.rank]);
            }

            // Cards in player's hand
            this.initPlayerHand(this.gamedatas.hand);

            // Mapping between strawmen card IDs and elements
            this.strawmenById = {};

            this.tricksWon = {};
            this.handSizes = {};

            for (const [player_id, player_info] of Object.entries(this.gamedatas.players)) {
                // Score piles
                let tricks_won_counter = new ebg.counter();
                this.tricksWon[player_id] = tricks_won_counter;
                tricks_won_counter.create(`imp_score_pile_${player_id}`);
                tricks_won_counter.setValue(player_info.tricks_won);

                // Hand size counter
                dojo.place(this.format_block('jstpl_player_hand_size', player_info),
                    document.getElementById(`player_board_${player_id}`));
                let hand_size_counter = new ebg.counter();
                this.handSizes[player_id] = hand_size_counter;
                hand_size_counter.create(`imp_player_hand_size_${player_id}`);
                hand_size_counter.setValue(player_info.hand_size);

                // Strawmen
                // TODO: 2p
                // this.initStrawmen(player_id, player_info.visible_strawmen, player_info.more_strawmen);
            }
            this.addTooltipToClass('imp_hand_size', _('Number of cards in hand'), '');

            // Cards played on table
            for (let i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var player_id = card.location_arg;
                this.putCardOnTable(player_id, card.id);
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            this.ensureSpecificImageLoading(['../common/point.png']);
        },

        ///////////////////////////////////////////////////
        //// Game & client states

        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function(stateName, args)
        {
            console.log('Entering state:', stateName);

            switch (stateName) {
            case 'passCards':
                document.querySelectorAll('.imp_playertable').forEach(e => e.style.display = 'none');
                if (this.isSpectator)
                    break;
                document.getElementById('imp_passCards').style.display = 'flex';
                this.showCenterPassBox(true);
                if (this.isCurrentPlayerActive()) {
                    this.markActivePassBox('left');
                    // Mark clickable cards and boxes
                    document.querySelectorAll('#imp_myhand .stockitem, .imp_pass').forEach(
                        e => e.classList.add('imp_clickable'));
                } else {
                    this.showCenterPassBox(false);
                    for (let player_id of this.playersPassedCards) {
                        this.showPassedCardBack(player_id);
                    }
                }
                break;

            // Mark playable cards
            case 'playerTurn':
                this.markActivePlayerTable(true);

                if (!this.isCurrentPlayerActive())
                    break;

                // Highlight playable cards
                for (let card_id of args.args._private.playable_cards) {
                    let elem = document.getElementById(`imp_myhand_item_${card_id}`);
                    // Look for strawman
                    if (!elem) {
                        elem = document.querySelector(`#imp_mystrawmen div[data-card_id="${card_id}"]`)
                    }
                    if (elem) {
                        elem.classList.add('imp_playable');
                    }
                }
                break;

            case 'endHand':
                this.markActivePlayerTable(false);
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName)
        {
        },

        // onUpdateActionButtons: in this method you can manage 'action buttons' that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args)
        {
            if (this.isCurrentPlayerActive()) {
                switch(stateName) {
                case 'passCards':
                    // TODO: this.addActionButton('resetPassCards_button', _('Reset choices'), 'onResetPassCards', null, false, 'gray');
                    this.addActionButton('passCards_button', _('Pass selected cards'), 'onPassCards');
                    dojo.addClass('passCards_button', 'disabled');
                    break;
                }
            }
        },

        ///////////////////////////////////////////////////
        //// Utility methods

        /*

            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.

        */

        ajaxAction: function (action, args, func, err, lock) {
            if (!args) {
                args = [];
            }
            delete args.action;
            if (!Object.hasOwn(args, 'lock') || args.lock) {
                args.lock = true;
            } else {
                delete args.lock;
            }
            if (typeof func == 'undefined' || func == null) {
                func = () => {};
            }

            this.ajaxcall(`/bottleimp/bottleimp/${action}.html`, args, this, func, err);
        },

        /** Override this function to inject html for log items  */

        /* @Override */
        format_string_recursive: function (log, args) {
            try {
                if (log && args && !args.processed) {
                    args.processed = true;

                    for (let key in args) {
                        if (args[key] && typeof args[key] == 'string' && key == 'suit') {
                            args[key] = this.getSuitDiv(args[key]);
                        }
                    }
                }
            } catch (e) {
                console.error(log, args, "Exception thrown", e.stack);
            }
            return this.inherited(this.format_string_recursive, arguments);
        },

        getSuitDiv: function (suit_symbol) {
            let suit_id = this.suitSymbolToId[suit_symbol];
            let suit_name = this.suitNames[suit_id];
            return `<div role="img" title="${suit_name}" aria-label="${suit_name}" class="imp_log_suit imp_suit_icon_${suit_id}"></div>`;
        },

        initPlayerHand: function(card_list) {
            for (let i in card_list) {
                let card = card_list[i];
                let rank = card.type_arg / 10;
                this.playerHand.addToStockWithId(rank, card.id);
            }
            this.updateStockOverlap();
        },

        putCardOnTable: function(player_id, card_id) {
            let spritePos = this.getSpriteXY(card_id);
            let placedCard = dojo.place(
                this.format_block('jstpl_cardontable', {
                    x: spritePos.x,
                    y: spritePos.y,
                    id: `imp_cardontable_${player_id}`,
                } ), `imp_playertablecard_${player_id}`);
            placedCard.dataset.card_id = card_id;
        },

        playCardOnTable: function(player_id, card_id) {
            this.putCardOnTable(player_id, card_id);

            let strawElem = this.strawmenById[card_id];
            if (strawElem) {
                this.placeOnObject('imp_cardontable_' + player_id, strawElem.id);
                strawElem.remove();
                delete this.strawmenById[card_id];
            } else {
                if (player_id != this.player_id) {
                    // Some opponent played a card
                    // Move card from player panel
                    this.placeOnObject('imp_cardontable_' + player_id, 'overall_player_board_' + player_id);
                } else {
                    // You played a card. If it exists in your hand, move card from there and remove
                    // corresponding item
                    if ($('imp_myhand_item_' + card_id)) {
                        this.placeOnObject('imp_cardontable_' + player_id, 'imp_myhand_item_' + card_id);
                        this.playerHand.removeFromStockById(card_id);
                    }
                }
                this.handSizes[player_id].incValue(-1);
            }

            // In any case: move it to its final destination
            this.slideToObject('imp_cardontable_' + player_id, 'imp_playertablecard_' + player_id).play();
        },

        putPassCardOnTable: function(card_id, pass_type, elem_id) {
            let spritePos = this.getSpriteXY(card_id);
            let placedCard = dojo.place(
                this.format_block('jstpl_cardontable', {
                    x: spritePos.x,
                    y: spritePos.y,
                    id: elem_id ?? `imp_passcardontable_${card_id}`,
                } ), `imp_passcard_${pass_type}`);
            placedCard.style.zIndex = 100;
            placedCard.dataset.card_id = card_id;
            return placedCard;
        },

        playPassCard: function(card_id) {
            let pass_type = this.activePassType;
            // If there's already a card in the pass box, put it back in the hand
            let old_card_id = this.passCards[pass_type];
            if (old_card_id) {
                this.playerHand.addToStockWithId(this.gamedatas.cards_by_id[old_card_id], old_card_id, `imp_passcard_${pass_type}`);
                dojo.destroy(`imp_passcardontable_${old_card_id}`);
            }

            this.passCards[pass_type] = card_id;

            // Remove card from hand and move to table
            let elem_id = `imp_passcardontable_${card_id}`;
            this.putPassCardOnTable(card_id, pass_type);
            this.placeOnObject(elem_id, 'imp_myhand_item_' + card_id);
            let anim = this.slideToObject(elem_id, 'imp_passcard_' + pass_type);
            dojo.connect(anim, 'onEnd', (node) => {
                node.style.zIndex = 1;
            });
            anim.play()
            this.playerHand.removeFromStockById(card_id);
            $(elem_id).onclick = (e) => {
                // Will also onclick the box, which marks it as active. Call e.stopPropagation() to prevent.
                dojo.destroy(e.target);
                delete this.passCards[pass_type];
                this.playerHand.addToStockWithId(this.gamedatas.cards_by_id[card_id], card_id, `imp_passcard_${pass_type}`);
                dojo.addClass('passCards_button', 'disabled');
            };

            if (Object.keys(this.passCards).length == this.passKeys.length) {
                dojo.removeClass('passCards_button', 'disabled');
                return;
            }

            // Change active pass box
            for (let pass of this.passKeys) {
                if (!this.passCards[pass]) {
                    this.markActivePassBox(pass);
                    break;
                }
            }
        },

        showCenterPassBox: function(show) {
            document.querySelectorAll('#imp_pass_center').forEach(e => e.style.visibility = show ? 'visible' : 'hidden');
            this.markActivePassBox();
        },

        showPassedCardBack: function(player_id) {
            let pass_type = this.passPlayers[player_id];
            if (!pass_type)
                return;
            let elem = dojo.place(
                this.format_block('jstpl_cardontable', {
                    x: 0,
                    y: 0,
                    id: `imp_passcardontable_${pass_type}`,
                } ), `imp_passcard_${pass_type}`);
            if (!this.instantaneousMode) {
                elem.style.opacity = 0;
                dojo.fadeIn({node: elem, duration: 500}).play();
            }
        },

        markActivePlayerTable: function(turn_on, player_id) {
            if (!player_id) {
                player_id = this.getActivePlayerId();
            }
            if (turn_on && player_id && document.getElementById(`imp_playertable_${player_id}`).classList.contains('imp_table_selected'))
                // Do nothing
                return;

            // Remove from all players before adding for desired player
            document.querySelectorAll('#imp_centerarea .imp_table_selected').forEach(
                e => e.classList.remove('imp_table_selected'));
            if (!turn_on) {
                return;
            }
            if (!player_id) {
                return;
            }
            document.getElementById(`imp_playertable_${player_id}`).classList.add('imp_table_selected')
        },

        unmarkPlayableCards: function() {
            document.querySelectorAll('#imp_myhand .imp_playable, #imp_myhand .imp_clickable, .imp_pass').forEach(
                e => e.classList.remove('imp_playable', 'imp_clickable'));
        },

        markActivePassBox: function(pass_type) {
            document.querySelectorAll('#imp_passCards .imp_table_selected').forEach(
                e => e.classList.remove('imp_table_selected'));
            if (pass_type) {
                this.activePassType = pass_type;
                document.getElementById(`imp_pass_${pass_type}`).classList.add('imp_table_selected')
                // TODO: Change instruction label
            }
        },


        // Copied from Tichu
        // TODO: Call on window resize
        updateStockOverlap: function() {
            const availableWidthForOverlapPerItem =
              (this.playerHand.container_div.clientWidth - (this.playerHand.item_width + this.playerHand.item_margin)) /
              (this.playerHand.items.length - 1);
            let overlap = Math.floor(
              ((availableWidthForOverlapPerItem - this.playerHand.item_margin - 1) / this.playerHand.item_width) * 100
            );
            if (overlap > 60) overlap = 60;
            if (overlap < 12) overlap = 12;
            this.playerHand.setOverlap(overlap, 0);
        },

        getSpriteXY: function(card_id) {
            let pos = this.rankToSpritesheet[this.gamedatas.cards_by_id[card_id]];
            return {
                x: this.cardWidth * (pos % 11),
                y: this.cardHeight * Math.floor(pos / 11),
            }
        },

        initBottles: function() {
            let maxPrice = 0;
            for (let bottle of Object.values(this.gamedatas.bottles)) {
                if (bottle.price > maxPrice) {
                    maxPrice = bottle.price;
                }
                dojo.destroy(`imp_bottle_${bottle.id}`);
                dojo.place(
                    this.format_block('jstpl_bottle', {
                        id: bottle.id,
                        price: bottle.price,
                    } ), bottle.owner ? `imp_bottle_slot_${bottle.owner}` : 'imp_bottles');
            }
            document.getElementById('imp_max_bottle_price').textContent = maxPrice;
        },

        // /////////////////////////////////////////////////
        // // Player's action

        /*
         *
         * Here, you are defining methods to handle player's action (ex: results of mouse click on game objects).
         *
         * Most of the time, these methods: _ check the action is possible at this game state. _ make a call to the game server
         *
         */

        onPlayerHandSelectionChanged: function() {
            let items = this.playerHand.getSelectedItems();
            if (items.length == 0)
                return
            this.playerHand.unselectAll();

            if (this.checkAction('playCard', true)) {
                if (!document.getElementById(this.playerHand.getItemDivId(items[0].id)).classList.contains('imp_playable')) {
                    return;
                }
                let card_id = items[0].id;
                this.ajaxAction('playCard', {
                    id: card_id,
                });
            } else if (this.checkAction('passCards', true)) {
                this.playPassCard(items[0].id);
            } else {
                this.playerHand.unselectAll();
            }
        },

        onPassCards: function(event) {
            if (!this.checkAction('passCards', true))
                return;

            this.ajaxAction('passCards', this.passCards);
        },

        onResetPassCards: function(event) {
            if (!this.checkAction('passCards', true))
                return;

            // TODO
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:

            In this method, you associate each of your game notifications with your local method to handle it.

            Note: game notification names correspond to 'notifyAllPlayers' and 'notifyPlayer' calls in
                  your template.game.php file.

        */
        setupNotifications: function() {
            console.log('notifications subscriptions setup');

            dojo.subscribe('newHand', this, 'notif_newHand');
            dojo.subscribe('newHandPublic', this, 'notif_newHandPublic');
            dojo.subscribe('passCardsPrivate', this, 'notif_passCardsPrivate');
            this.notifqueue.setSynchronous('passCardsPrivate');
            dojo.subscribe('passCards', this, 'notif_passCards');
            this.notifqueue.setSynchronous('passCards');
            dojo.subscribe('takePassedCards', this, 'notif_takePassedCards');
            this.notifqueue.setSynchronous('takePassedCards');
            dojo.subscribe('playCard', this, 'notif_playCard');
            this.notifqueue.setSynchronous('playCard', 1000);
            dojo.subscribe('trickWin', this, 'notif_trickWin');
            this.notifqueue.setSynchronous('trickWin', 0);
            this.notifqueue.setSynchronous('giveAllCardsToPlayer', 1000);
            // dojo.subscribe('endHand', this, 'notif_endHand');
            dojo.subscribe('newScores', this, 'notif_newScores');
        },

        notif_newHandPublic: function(notif) {
            // Reset scores and hand size
            for (let scorePile of Object.values(this.tricksWon)) {
                scorePile.setValue(0);
            }

            for (let handSize of Object.values(this.handSizes)) {
                handSize.setValue(notif.args.hand_size);
            }
            document.getElementById('imp_round_number').textContent = notif.args.round_number;
            for (let bottle of Object.values(this.gamedatas.bottles)) {
                bottle.price = 19;
                bottle.owner = null;
            }
            this.initBottles();
        },

        notif_newHand: function(notif) {
            this.playerHand.removeAll();
            this.initPlayerHand(notif.args.hand_cards);
        },

        notif_passCardsPrivate: function(notif) {
            this.unmarkPlayableCards();
            this.showCenterPassBox(false);
            for (let player_id of this.playersPassedCards) {
                this.showPassedCardBack(player_id);
            }

            // Fade out passed cards
            for (let pos of this.passKeys) {
                this.fadeOutAndDestroy(document.querySelector(`#imp_passcard_${pos} > div`));
            }
            this.notifqueue.setSynchronousDuration(0);
        },

        notif_passCards: function(notif) {
            if (notif.args.player_id != this.player_id)
                this.handSizes[notif.args.player_id].incValue(this.gamedatas.playerorder.length == 2 ? -4 : -3);
            if (this.passPlayers[notif.args.player_id]) {
                if (this.isCurrentPlayerActive()) {
                    // Remember this player passed and animate later
                    this.playersPassedCards.push(notif.args.player_id);
                } else {
                    this.showPassedCardBack(notif.args.player_id);
                }
            }
            this.notifqueue.setSynchronousDuration(0);
        },

        notif_takePassedCards: async function(notif) {
            for (let pos of ['left', 'right']) {
                let card_id = notif.args[`card_id_${pos}`];
                this.fadeOutAndDestroy(`imp_passcardontable_${pos}`);
                let reveal_id = `imp_passcardreveal_${pos}`;
                let elem = this.putPassCardOnTable(card_id, pos, reveal_id);
                if (!this.instantaneousMode) {
                    elem.style.opacity = 0;
                    dojo.fadeIn({node: elem, duration: 500}).play();
                }
            }
            if (!this.instantaneousMode)
                await new Promise(r => setTimeout(r, 2000));

            for (let pos of ['left', 'right']) {
                let card_id = notif.args[`card_id_${pos}`];
                let reveal_id = `imp_passcardreveal_${pos}`;
                dojo.destroy(reveal_id);
                this.playerHand.addToStockWithId(this.gamedatas.cards_by_id[card_id], card_id, `imp_passcard_${pos}`);
            }

            // Give cards time to slide to the player's hand
            if (!this.instantaneousMode)
                await new Promise(r => setTimeout(r, 500));

            // Show game area
            this.passCards = {};
            this.playersPassedCards = [];
            document.getElementById('imp_passCards').style.display = 'none';
            document.querySelectorAll('.imp_playertable').forEach(e => e.style.display = 'block');

            this.notifqueue.setSynchronousDuration(0);
        },

        notif_playCard: function(notif) {
            // Mark the active player, in case this was an automated move (skipping playerTurn state)
            this.markActivePlayerTable(true, notif.args.player_id);
            this.unmarkPlayableCards();
            this.playCardOnTable(notif.args.player_id, notif.args.card_id);
        },

        notif_trickWin: async function(notif) {
            // Move all cards on table to given table, then destroy them
            let winner_id = notif.args.player_id;
            for (let player_id in this.gamedatas.players) {
                // Make sure the moved card is above the winner card
                let animated_id = 'imp_cardontable_' + player_id;
                if (player_id != winner_id) {
                    document.getElementById(animated_id).style.zIndex = 3;
                }

                let anim = this.slideToObject(animated_id, 'imp_cardontable_' + winner_id);
                dojo.connect(anim, 'onEnd', (node) => {
                    dojo.destroy(node);
                });
                anim.play();
            }
            this.tricksWon[winner_id].incValue(1);

            // Update bottle info
            let bottle_id = notif.args.bottle_id;
            let bottle_info = this.gamedatas.bottles[bottle_id];
            if (bottle_id) {
                let bottle_elem = document.getElementById(`imp_bottle_${bottle_id}`);
                if (notif.args.price != bottle_info.price) {
                    bottle_info.price = notif.args.price;
                    bottle_elem.textContent = notif.args.price;
                    document.getElementById('imp_max_bottle_price').textContent =
                        Math.max(...this.gamedatas.bottles.map(b => b.price), 0);
                }

                if (winner_id != bottle_info.owner) {
                    bottle_info.owner = winner_id;
                    this.slideToObject(bottle_elem, `imp_bottle_slot_${winner_id}`);
                }
            }

            if (!this.instantaneousMode)
                await new Promise(r => setTimeout(r, 1000));
            this.notifqueue.setSynchronousDuration(0);
        },

        notif_endHand: function(notif) {
            // TODO: Adjust scores here or in newScores?
        },

        notif_newScores: function(notif) {
            // Update players' scores
            for (let player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },
   });
});
