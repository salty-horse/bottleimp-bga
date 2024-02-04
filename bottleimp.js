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
            this.cardWidth = 93;
            this.cardHeight = 93;

            this.suitSymbolToId = {
                '♠': 1,
                '♥': 2,
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
                1: _('spades'),
                2: _('hearts'),
                3: _('clubs'),
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
            this.playerHand.centerItems = true;
            this.playerHand.create(this, $('imp_myhand'), this.cardWidth, this.cardHeight);
            this.playerHand.image_items_per_row = 9;
            this.playerHand.onItemCreate = dojo.hitch(this, this.setupNewCard);
            this.playerHand.jstpl_stock_item = '<div id="${id}" class="imp_card" style="top:${top}px;left:${left}px"></div>';

            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            // Init pass boxes
            document.getElementById('imp_passCards').style.display = 'none';
            this.activePassType = null;
            this.passCards = {};
            dojo.query('.imp_pass').on('click', (e) => {
                this.markActivePassBox(e.currentTarget.dataset.passType);
            });

            // The player order array mixes strings and numbers
            let playerorder = gamedatas.playerorder.map(x => parseInt(x));
            let playerPos = playerorder.indexOf(this.player_id);
            let passTitles = {
                'left': playerorder[(playerPos + 1) % playerorder.length],
                'right': playerorder[(playerPos == 0 ? playerorder.length : playerPos) - 1],
            };
            document.querySelector('#imp_pass_center > .imp_playertablename').innerHTML = _("Devil's Trick");
            // TODO: Are tooltips necessary?
            // this.addTooltip('imp_pass_center', _("Card to place in the Devil's trick"), '');

            if (playerorder.length == 2) {
                // TODO: Tooltips
                this.passKeys = ['left', 'center', 'center2', 'right'];
            } else {
                this.passKeys = ['left', 'center', 'right'];
            }

            for (let k in passTitles) {
                let player_info = gamedatas.players[passTitles[k]];
                document.querySelector(`#imp_pass_${k} > .imp_playertablename`).innerHTML = dojo.string.substitute(
                    "${player_name}",
                    {player_name: `<span style="color:#${player_info.color}">${player_info.name}</span>`});
            }

            // Create cards types
            for (let card of Object.values(gamedatas.cards)) {
                this.playerHand.addItemType(card.rank, card.rank);
            }

            // Cards in player's hand
            this.initPlayerHand(this.gamedatas.hand);

            // Mapping between strawmen card IDs and elements
            this.strawmenById = {};

            this.scorePiles = {};
            this.handSizes = {};

            for (const [player_id, player_info] of Object.entries(this.gamedatas.players)) {
                // Score piles
                let score_pile_counter = new ebg.counter();
                this.scorePiles[player_id] = score_pile_counter;
                score_pile_counter.create(`imp_score_pile_${player_id}`);
                score_pile_counter.setValue(player_info.score_pile);

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
            for (i in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[i];
                var player_id = card.location_arg;
                this.putCardOnTable(player_id, card.type_arg, card.id);
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
                if (this.isCurrentPlayerActive()) {
                    this.markActivePassBox('left');
                } else {
                    // TODO: Hide center boxes to prepare for receiving cards
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
            switch (stateName) {
            }
        },

        // onUpdateActionButtons: in this method you can manage 'action buttons' that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //
        onUpdateActionButtons: function(stateName, args)
        {
            if (this.isCurrentPlayerActive()) {
                switch(stateName) {
                case 'passCards':
                    this.addActionButton('resetPassCards_button', _('Reset choices'), 'onResetPassCards', null, false, 'gray');
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
            if (!args.hasOwnProperty('lock') || args.lock) {
                args.lock = true;
            } else {
                delete args.lock;
            }
            if (typeof func == 'undefined' || func == null) {
                func = result => {};
            }

            let name = this.game_name;
            this.ajaxcall(`/bottleimp/bottleimp/${action}.html`, args, this, func, err);
        },

        populateCardElement: function(card_div, rank) {
            let card_info = this.gamedatas.cards[rank];
            dojo.place(`<div class="imp_card_points">${card_info.points}</div>`, card_div);
            dojo.place(`<div class="imp_card_main"><div class="imp_card_rank imp_suit_color_${card_info.suit}">${card_info.rank}</div><div class="imp_card_suit imp_card_suit_${card_info.suit}">&nbsp;</div></div>`, card_div);
        },

        setupNewCard: function(card_div, rank, card_id) {
            this.populateCardElement(card_div, rank);
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
            return `<div role=\"img\" title=\"${suit_name}\" aria-label=\"${suit_name}\" class=\"imp_log_suit imp_suit_icon_${suit_id}\"></div>`;
        },

        initPlayerHand: function(card_list) {
            for (let i in card_list) {
                let card = card_list[i];
                let suit = card.type;
                let rank = card.type_arg;
                this.playerHand.addToStockWithId(rank, card.id);
            }
        },

        putCardOnTable: function(player_id, rank, card_id) {
            let placedCard = dojo.create('div', {
                id: 'imp_cardontable_' + player_id,
                class: 'imp_card imp_cardontable',
            }, 'imp_playertablecard_' + player_id);
            this.populateCardElement(placedCard, rank);
            placedCard.dataset.card_id = card_id;
        },

        playCardOnTable: function(player_id, rank, card_id) {
            this.putCardOnTable(player_id, rank, card_id);

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

        putPassCardOnTable: function(card_id, pass_type) {
            let placedCard = dojo.create('div', {
                id: 'imp_passcardontable_' + card_id,
                class: 'imp_card imp_cardontable',
            }, 'imp_passcard_' + pass_type);
            this.populateCardElement(placedCard, this.gamedatas.cards_by_id[card_id]);
            placedCard.dataset.card_id = card_id;
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
            this.slideToObject(elem_id, 'imp_passcard_' + pass_type).play();
            this.playerHand.removeFromStockById(card_id);
            $(elem_id).onclick = (e) => {
                // TODO: Broken
                dojo.destroy(e);
                delete this.passCards[pass_type];
                this.playerHand.addToStockWithId(this.gamedatas.cards_by_id[card_id], card_id, `imp_passcard_${pass_type}`);

                // TODO switch focus and undo confirm
                // this.markActivePassBox(this.activePassType);
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
            document.querySelectorAll('#imp_mystrawmen .imp_playable, #imp_myhand .imp_playable').forEach(
                e => e.classList.remove('imp_playable'));
        },

        markActivePassBox: function(pass_type) {
            this.activePassType = pass_type;

            document.querySelectorAll('#imp_passCards .imp_table_selected').forEach(
                e => e.classList.remove('imp_table_selected'));
            document.getElementById(`imp_pass_${pass_type}`).classList.add('imp_table_selected')
            // TODO: Change instruction label
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
            dojo.subscribe('giftCardPrivate', this, 'notif_giftCardPrivate');
            dojo.subscribe('giftCard', this, 'notif_giftCard');
            dojo.subscribe('playCard', this, 'notif_playCard');
            this.notifqueue.setSynchronous('playCard', 1000);
            dojo.subscribe('trickWin', this, 'notif_trickWin');
            dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer');
            this.notifqueue.setSynchronous('giveAllCardsToPlayer', 1000);
            dojo.subscribe('endHand', this, 'notif_endHand');
            dojo.subscribe('newScores', this, 'notif_newScores');
        },

        notif_newHandPublic: function(notif) {
            // Reset scores and hand size
            for (let scorePile of Object.values(this.scorePiles)) {
                scorePile.setValue(0);
            }

            for (let handSize of Object.values(this.handSizes)) {
                handSize.setValue(notif.args.hand_size);
            }
        },

        notif_newHand: function(notif) {
            // We received a new full hand of 13 cards.
            this.playerHand.removeAll();

            this.initPlayerHand(notif.args.hand_cards);
        },

        notif_giftCardPrivate: function(notif) {
            this.unmarkPlayableCards();

            this.playerHand.removeFromStockById(notif.args.card);

            // Hand size is decreased in notif_giftCard
        },

        notif_giftCard: function(notif) {
            this.handSizes[notif.args.player_id].incValue(-1);
        },

        notif_playCard: function(notif) {
            // Mark the active player, in case this was an automated move (skipping playerTurn state)
            this.markActivePlayerTable(true, notif.args.player_id);
            this.unmarkPlayableCards();
            this.playCardOnTable(notif.args.player_id, notif.args.value, notif.args.card_id);
        },

        notif_trickWin: function(notif) {
            // We do nothing here (just wait in order players can view the cards played before they're gone
        },

        notif_giveAllCardsToPlayer: function(notif) {
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
            this.scorePiles[winner_id].incValue(notif.args.points);
        },

        notif_endHand: function(notif) {
            this.scorePiles[notif.args.player_id].incValue(notif.args.gift_value);
        },

        notif_newScores: function(notif) {
            // Update players' scores
            for (let player_id in notif.args.newScores) {
                this.scoreCtrl[player_id].toValue(notif.args.newScores[player_id]);
            }
        },
   });
});
