/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
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

            this.teamLetters = {
                1: 'A',
                2: 'B',
                3: 'C',
                4: 'D',
            }
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
                1: _('Red'),
                2: _('Blue'),
                3: _('Green'),
            };

            this.rankToSpritesheet = {
                '1': 1, '2': 2, '2.5': 3, '4': 4, '4.5': 5, '5': 6, '7': 7, '9': 8, '12': 9, '12.5': 10,
                '15': 11, '18': 12, '18.5': 13, '22': 14, '22.5': 15, '25': 16, '28': 17, '28.5': 18, '3': 19, '6': 20, '6.5': 21,
                '8': 22, '8.5': 23, '10': 24, '10.5': 25, '13': 26, '17': 27, '20': 28, '20.5': 29, '23': 30, '27': 31, '30': 32,
                '30.5': 33, '32': 34, '32.5': 35, '35': 36, '11': 37, '14': 38, '14.5': 39, '16': 40, '16.5': 41, '21': 42, '24': 43,
                '24.5': 44, '26': 45, '26.5': 46, '29': 47, '31': 48, '33': 49, '34': 50, '34.5': 51, '36': 52, '36.5': 53, '37': 54
            };

            this.playerCount = gamedatas.playerorder.length;

            // The player order array mixes strings and numbers
            let playerorder = gamedatas.playerorder.map(x => parseInt(x));
            let playerPos = playerorder.indexOf(this.player_id);

            // Card stocks
            this.stocksById = {};
            this.playerHand = this.initHandStock($('imp_myhand'));
            this.handStocks = [this.playerHand];
            if (this.playerCount == 2) {
                this.visibleHands = {};
                for (let player_id of playerorder) {
                    let stock = this.initHandStock(
                        document.getElementById(`imp_player_${player_id}_visible_hand`),
                        player_id == this.player_id);
                    this.visibleHands[player_id] = stock;
                    this.handStocks.push(stock);
                }
            }

            this.playerCount = gamedatas.playerorder.length;

            // Info box
            document.getElementById('imp_round_number').textContent = this.gamedatas.roundNumber;
            document.getElementById('imp_total_rounds').textContent = this.gamedatas.totalRounds;
            if (gamedatas.bottles[2]) {
                document.getElementById('imp_price_label').textContent = _('Top bottle price');
            }
            this.initBottles();
            this.initTeams();

            // Init pass boxes
            document.getElementById('imp_passCards').style.display = 'none';
            this.activePassType = null;
            this.passCards = {};
            dojo.query('.imp_pass').on('click', (e) => {
                if (!this.isCurrentPlayerActive())
                    return;
                this.markActivePassBox(e.currentTarget.dataset.passType);
            });

            // Set dynamic UI strings
            if (this.playerCount == 2) {
                this.opponent_id = playerorder[1 - playerPos];
                this.opponent_name_color = this.format_player_name(this.gamedatas.players[this.opponent_id]);
                if (this.isSpectator) {
                    for (const player_info of Object.values(this.gamedatas.players)) {
                        this.setVisibleHandPlayerLabel(player_info);
                    }
                } else {
                    this.setVisibleHandPlayerLabel(gamedatas.players[this.opponent_id]);
                }
            } else {
                document.querySelectorAll('.imp_second_card_slot').forEach(o => {o.remove()});
            }

            if (!this.isSpectator) {
                this.passPlayers = {};
                this.playersPassedCards = [];
                let devilsTrickLabel = _("Devil's Trick")
                document.querySelector('#imp_pass_center > .imp_playertablename').innerHTML = devilsTrickLabel;
                // TODO: Are tooltips necessary?
                // this.addTooltip('imp_pass_center', _("Card to place in the Devil's trick"), '');

                if (this.playerCount == 2) {
                    this.passPlayers[this.opponent_id] = ['next', 'prev'];
                    this.passKeys = ['next', 'center', 'center2', 'prev'];
                    document.querySelector('#imp_pass_center2 > .imp_playertablename').innerHTML = devilsTrickLabel;
                    if (gamedatas.gamestate.name == 'passCards' && gamedatas.players_yet_to_pass_cards.indexOf(this.opponent_id.toString()) == -1) {
                        this.playersPassedCards.push(this.opponent_id);
                    }
                } else {
                    // For 5 players, this is set inside onEnteringState
                    if (this.playerCount != 5) {
                        this.passKeys = ['next', 'center', 'prev'];
                    }
                    this.passPlayers[playerorder[(playerPos + 1) % this.playerCount]] = ['next'];
                    this.passPlayers[playerorder[(playerPos == 0 ? this.playerCount : playerPos) - 1]] = ['prev'];

                    for (let [player_id, pos] of Object.entries(this.passPlayers)) {
                        let player_info = gamedatas.players[player_id];
                        document.querySelector(`#imp_pass_${pos} > .imp_playertablename`).innerHTML = dojo.string.substitute(
                            "${player_name}",
                            {player_name: this.format_player_name(player_info)});

                        if (gamedatas.gamestate.name == 'passCards' && gamedatas.players_yet_to_pass_cards.indexOf(player_id.toString()) == -1) {
                            this.playersPassedCards.push(player_id);
                        }
                    }
                }
            }

            // Cards in player's hand
            this.initHand(this.playerHand, this.gamedatas.hand);

            this.tricksWon = {};
            this.handSizes = {};

            for (const [player_id, player_info] of Object.entries(this.gamedatas.players)) {
                // Score piles
                let tricks_won_counter = new ebg.counter();
                this.tricksWon[player_id] = tricks_won_counter;
                tricks_won_counter.create(`imp_score_pile_${player_id}`);
                tricks_won_counter.setValue(player_info.tricks_won);

                // Hand size counter
                if (this.playerCount == 2) {
                    dojo.place(this.format_block('jstpl_player_hand_size', player_info),
                        document.getElementById(`player_board_${player_id}`));
                    let hand_size_counter = new ebg.counter();
                    this.handSizes[player_id] = hand_size_counter;
                    hand_size_counter.create(`imp_player_hand_size_${player_id}`);
                    hand_size_counter.setValue(player_info.hand_size);
                }

                if (player_info.visible_hand) {
                    document.getElementById(`imp_player_${player_id}_visible_hand_wrap`).style.display = 'block';
                    this.initHand(this.visibleHands[player_id], player_info.visible_hand);
                }
            }
            this.addTooltipToClass('imp_hand_size', _('Number of cards in hand'), '');

            if (this.gamedatas.visible_hand) {
                document.getElementById(`imp_player_${this.player_id}_visible_hand_wrap`).style.display = 'block';
                this.initHand(this.visibleHands[this.player_id], this.gamedatas.visible_hand);
            }
            
            if (this.playerCount == 2 && !this.isSpectator) {
                document.getElementById(`imp_player_${this.player_id}_visible_hand_wrap`).style.display = 'block';
            }

            // Cards played on table
            for (let card of this.gamedatas.cardsontable) {
                let player_id = card.location_arg;
                this.putCardOnTable(player_id, card.id, card.location.slice(-1) == '2' ? 2 : 1);
            }

            this.largePrint = (this.getGameUserPreference(100) == 2);

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
                document.querySelectorAll('.imp_visible_hand').forEach(e => e.style.display = 'none');
                if (this.isSpectator) {
                    break;
                }
                if (this.playerCount == 2) {
                    document.getElementById(`imp_player_${this.player_id}_visible_hand_wrap`).style.display = 'block';
                    if (this.isCurrentPlayerActive()) {
                        document.querySelector('#imp_pass_next > .imp_playertablename').innerHTML =
                            _("${player_name}'s visible hand").replace('${player_name}', this.opponent_name_color);
                        document.querySelector('#imp_pass_prev > .imp_playertablename').innerHTML =
                            _("${player_name}'s hidden hand").replace('${player_name}', this.opponent_name_color);
                    } else {
                        document.querySelector('#imp_pass_next > .imp_playertablename').innerHTML =
                        document.querySelector('#imp_pass_prev > .imp_playertablename').innerHTML =
                            this.opponent_name_color;
                    }
                }
                document.getElementById('imp_passCards').style.display = 'flex';

                if (!this.isCurrentPlayerActive()) {
                    this.showReceivingCardsUI();
                    break;
                }

                if (this.playerCount == 5) {
                    if (this.player_id == this.gamedatas.dealer) {
                        this.passKeys = ['next', 'prev'];
                        this.showCenterPassBox(false);
                    } else {
                        this.passKeys = ['next', 'center', 'prev'];
                        this.showCenterPassBox(true);
                    }
                } else {
                    this.showCenterPassBox(true);
                }
                this.markActivePassBox('next');
                // Mark clickable cards and boxes
                document.querySelectorAll('#imp_myhand .stockitem, .imp_pass').forEach(
                    e => e.classList.add('imp_clickable'));
                break;

            // Mark playable cards
            case 'playerTurn':
                this.markActivePlayerTable(true);

                if (!this.isCurrentPlayerActive())
                    break;

                // Highlight playable cards
                for (let card_id of args.args._private.playable_cards) {
                    let elem = document.getElementById(`imp_myhand_item_${card_id}`);
                    if (!elem) {
                        elem = document.getElementById(`imp_player_${this.player_id}_visible_hand_item_${card_id}`)
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

                    // In case this was called after a player passes while the current player has just selected the cards to pass
                    if (Object.keys(this.passCards).length != this.passKeys.length) {
                        dojo.addClass('passCards_button', 'disabled');
                    }
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

        format_player_name: function(player_info) {
            return this.format_block(player_info.color_back ? 'jstpl_player_name_color_back' : 'jstpl_player_name', player_info)
        },

        getSuitDiv: function (suit_symbol) {
            let suit_id = this.suitSymbolToId[suit_symbol];
            let suit_name = this.suitNames[suit_id];
            return `<div role="img" title="${suit_name}" aria-label="${suit_name}" class="imp_log_suit imp_suit_icon_${suit_id}"></div>`;
        },

        animateFrom: function(elem, oldPos, duration = 500) {
            if (oldPos instanceof HTMLElement) {
                oldPos = oldPos.getBoundingClientRect();
            } else if (typeof(oldPos) === 'string' || oldPos instanceof String) {
                oldPos = document.getElementById(oldPos).getBoundingClientRect();
            }

            if (this.instantaneousMode || !elem.animate) {
                return;
            }
            const newPos = elem.getBoundingClientRect();
            const translateX = oldPos.x - newPos.x;
            const translateY = oldPos.y - newPos.y;
            if (translateX == 0 && translateY == 0)
                return;
            elem.animate([
                {transform: `translate(${translateX}px, ${translateY}px)`},
                {transform: 'none'}

            ], {easing: 'ease-out', duration: duration}).play();
        },

        prepareDivSlide: function(elem) {
            if (this.instantaneousMode || !elem.animate) {
                return;
            }
            let wm = new WeakMap();
            for (const item of elem.children) {
                wm.set(item, item.getBoundingClientRect());
            }
            return {elem: elem, items: wm};
        },

        animateDivSlide: function(o) {
            for (const item of o.elem.children) {
                let rect = o.items.get(item);
                if (!rect) continue;
                this.animateFrom(item, rect);
            }
        },

        initHand: function(stock, card_list) {
            stock.removeAll();
            for (let i in card_list) {
                let card = card_list[i];
                let rank = card.type_arg / 10;
                stock.addToStockWithId(rank, card.id);
            }
            this.updateStockOverlap(stock);
        },

        putCardOnTable: function(player_id, card_id, slot) {
            let container_id = `imp_playertablecard_${player_id}`;
            let suffix = '';
            if (slot) {
                if (slot == 2) {
                    suffix = '_2';
                }
            } else if (document.getElementById(container_id).children.length > 0) {
                suffix = '_2';
            }
            let spritePos = this.getSpriteXY(card_id);
            let placedCard = dojo.place(
                this.format_block('jstpl_cardontable', {
                    x: spritePos.x,
                    y: spritePos.y,
                    largeprint: this.largePrint ? 'imp_largeprint' : '',
                    id: `imp_cardontable_${player_id}${suffix}`,
                }), `${container_id}${suffix}`);
            placedCard.dataset.card_id = card_id;

            return placedCard;
        },

        playCardOnTable: function(player_id, card_id) {
            let newCard = this.putCardOnTable(player_id, card_id);

            // Check if the card is in a visible hand
            let fromStock = null;
            let handElem;

            for (let stock of this.handStocks) {
                handElem = document.getElementById(stock.getItemDivId(card_id));
                if (handElem) {
                    fromStock = stock;
                    break;
                }
            }

            let fromHand = false;
            if (fromStock) {
                // Move card from stock
                this.animateFrom(newCard, handElem);
                fromStock.removeFromStockById(card_id);
                fromHand = (fromStock == this.playerHand[this.player_id]);
            } else {
                // Move card from player panel
                this.animateFrom(newCard, `overall_player_board_${player_id}`);
                fromHand = true;
            }

            if (this.playerCount == 2 && fromHand) {
                this.handSizes[player_id].incValue(-1);
            }
        },

        putPassCardOnTable: function(card_id, pass_type, elem_id) {
            let spritePos = this.getSpriteXY(card_id);
            let placedCard = dojo.place(
                this.format_block('jstpl_cardontable', {
                    x: spritePos.x,
                    y: spritePos.y,
                    largeprint: this.largePrint ? 'imp_largeprint' : '',
                    id: elem_id ?? `imp_passcardontable_${card_id}`,
                }), `imp_passcard_${pass_type}`);
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
            document.querySelectorAll('#imp_pass_center, #imp_pass_center2').forEach(e => {
                if (show) {
                    e.classList.remove('imp_hidden');
                } else {
                    e.classList.add('imp_hidden');
                }
            });
            this.markActivePassBox();
        },

        showPassedCardBack: function(player_id) {
            let pass_types = this.passPlayers[player_id];
            if (!pass_types)
                return;
            for (let pass_type of pass_types) {
                // TODO: Animate cards as coming from player panel
                let elem = dojo.place(
                    this.format_block('jstpl_cardontable', {
                        x: 0,
                        y: 0,
                        largeprint: '',
                        id: `imp_passcardontable_${pass_type}`,
                    }), `imp_passcard_${pass_type}`);
                if (!this.instantaneousMode) {
                    elem.style.opacity = 0;
                    dojo.fadeIn({node: elem, duration: 500}).play();
                }
            }
        },

        showReceivingCardsUI: function() {
            this.showCenterPassBox(false);
            if (this.playerCount == 2) {
                document.querySelector('#imp_pass_next > .imp_playertablename').innerHTML =
                document.querySelector('#imp_pass_prev > .imp_playertablename').innerHTML =
                    this.opponent_name_color;
            }
            for (let player_id of this.playersPassedCards) {
                this.showPassedCardBack(player_id);
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
            document.querySelectorAll('.imp_playable, .imp_clickable').forEach(
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
        updateStockOverlap: function(stock) {
            const availableWidthForOverlapPerItem =
              (stock.container_div.clientWidth - (stock.item_width + stock.item_margin)) /
              (stock.items.length - 1);
            let overlap = Math.floor(
              ((availableWidthForOverlapPerItem - stock.item_margin - 1) / stock.item_width) * 100
            );
            if (overlap > 60) overlap = 60;
            if (overlap < 12) overlap = 12;
            stock.setOverlap(overlap, 0);
        },

        getSpriteXY: function(card_id) {
            let pos = this.rankToSpritesheet[this.gamedatas.cards_by_id[card_id]];
            return {
                x: this.cardWidth * (pos % 11),
                y: this.cardHeight * Math.floor(pos / 11),
            }
        },

        initHandStock: function(container, clickable = true) {
            let stock = new ebg.stock();
            stock.setSelectionMode(1);
            stock.centerItems = false;
            stock.autowidth = true;
            stock.create(this, container, this.cardWidth, this.cardHeight);
            stock.image_items_per_row = 11;

            let cards_url;
            let card_style = this.getGameUserPreference(100);
            if (card_style == 1) {
                cards_url = 'img/cards.jpg';
            } else if (card_style == 2) {
                cards_url = 'img/cards_large_print.jpg';
            }

            for (let card of Object.values(this.gamedatas.cards)) {
                stock.addItemType(card.rank, card.rank, g_gamethemeurl+'img/cards.jpg', this.rankToSpritesheet[card.rank]);
                stock.addItemType(card.rank, card.rank, g_gamethemeurl + cards_url, this.rankToSpritesheet[card.rank]);
            }

            if (clickable) {
                this.stocksById[container.id] = stock;
                dojo.connect(stock, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');
            }
            return stock;
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
                    }), bottle.owner ? `imp_bottle_slot_${bottle.owner}` : 'imp_bottles');
            }
            document.getElementById('imp_max_bottle_price').textContent = maxPrice;
        },

        initTeams: function() {
            if (!this.gamedatas.teams)
                return;
            for (let [player_id, team] of Object.entries(this.gamedatas.teams)) {
                let e = document.getElementById(`imp_playertable_team_${player_id}`);
                if (team == e.dataset.team)
                    continue;
                e.style.display = 'block';
                e.textContent = _('Team ${letter}').replace('${letter}', this.teamLetters[team]);
                if (e.dataset.team) {
                    e.classList.remove(`imp_team_${e.dataset.team}`);
                }
                e.classList.add(`imp_team_${team}`);
                e.dataset.team = team;
            }
        },

        markDealer: function() {
            // TODO: mark new dealer
        },

        setVisibleHandPlayerLabel: function(player_info) {
            document.querySelector(`#imp_player_${player_info.id}_visible_hand_wrap > h3`).innerHTML =
                _("${player_name}'s visible hand").replace('${player_name}', this.format_player_name(player_info));
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

        onPlayerHandSelectionChanged: function(container_id) {
            let stock = this.stocksById[container_id];
            let items = stock.getSelectedItems();
            if (items.length == 0)
                return;
            stock.unselectAll();

            if (this.checkAction('playCard', true)) {
                if (!document.getElementById(stock.getItemDivId(items[0].id)).classList.contains('imp_playable')) {
                    return;
                }
                let card_id = items[0].id;
                this.ajaxAction('playCard', {
                    id: card_id,
                });
            } else if (stock == this.playerHand && this.checkAction('passCards', true)) {
                this.playPassCard(items[0].id);
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
            [
                'newHand',
                'newHandPublic',
                'passCardsPrivate',
                'passCards',
                'takePassedCards',
                'visibleHandsPublic',
                'playCard',
                'trickWin',
                'newScores',
            ].forEach((n) => {
                dojo.subscribe(n, this, `notif_${n}`);
            });
            this.notifqueue.setSynchronous('passCardsPrivate');
            this.notifqueue.setSynchronous('passCards');
            this.notifqueue.setSynchronous('takePassedCards');
            this.notifqueue.setSynchronous('playCard', 1000);
            this.notifqueue.setSynchronous('trickWin');
        },

        notif_newHandPublic: function(notif) {
            // Reset counters
            for (let tricksWon of Object.values(this.tricksWon)) {
                tricksWon.setValue(0);
            }
            for (let handSize of Object.values(this.handSizes)) {
                handSize.setValue(notif.args.hand_size);
            }
            if (this.playerCount == 5) {
                this.handSizes[notif.args.dealer].setValue(notif.args.hand_size - 1);
            }
            this.gamedatas.dealer = notif.args.dealer;
            this.markDealer();
            document.getElementById('imp_round_number').textContent = notif.args.round_number;
            for (let bottle of Object.values(this.gamedatas.bottles)) {
                bottle.price = 19;
                bottle.owner = null;
            }
            this.initBottles();
            this.gamedatas.teams = notif.args.teams;
            this.initTeams();
        },

        notif_newHand: function(notif) {
            this.initHand(this.playerHand, notif.args.hand_cards);
            if (notif.args.visible_hand) {
                this.initHand(this.visibleHands[this.player_id], notif.args.visible_hand);
            }
        },

        notif_passCardsPrivate: async function(notif) {
            this.unmarkPlayableCards();

            // Fade out passed cards
            for (let pos of this.passKeys) {
                this.fadeOutAndDestroy(document.querySelector(`#imp_passcard_${pos} > div`));
            }

            if (!this.instantaneousMode)
                await new Promise(r => setTimeout(r, 500));

            this.showReceivingCardsUI();

            this.notifqueue.setSynchronousDuration(0);
        },

        notif_passCards: function(notif) {
            if (this.isSpectator)
                return;
            if (this.playerCount == 2 && notif.args.player_id != this.player_id)
                this.handSizes[notif.args.player_id].incValue(-4);
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
            for (let pos of ['next', 'prev']) {
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

            for (let pos of ['next', 'prev']) {
                let card_id = notif.args[`card_id_${pos}`];
                let reveal_id = `imp_passcardreveal_${pos}`;
                dojo.destroy(reveal_id);
                let stock = this.playerHand;
                if (this.playerCount == 2 && pos == 'next') {
                    stock = this.visibleHands[this.player_id];
                }
                stock.addToStockWithId(this.gamedatas.cards_by_id[card_id], card_id, `imp_passcard_${pos}`);
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

        notif_visibleHandsPublic: function(notif) {
            // TODO: Tell spectators who passed what publicly with:
            // this.showMessage(_("My message"), "only_to_log")

            for (let [player_id, cards] of Object.entries(notif.args.visible_hands)) {
                if (player_id == this.player_id) {
                    continue;
                }
                document.getElementById(`imp_player_${player_id}_visible_hand_wrap`).style.display = 'block';
                this.initHand(this.visibleHands[player_id], cards);
            }
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
            let winner_div_id = `imp_cardontable_${winner_id}`;
            if (notif.args.slot == 2) {
                winner_div_id += '_2';
            }
            document.querySelectorAll('.imp_cardontable').forEach(elem => {
                // Make sure the moved card is above the winner card
                if (elem.id != winner_div_id) {
                    elem.style.zIndex = 3;
                }

                let anim = this.slideToObject(elem, winner_div_id);
                dojo.connect(anim, 'onEnd', (node) => {
                    dojo.destroy(node);
                });
                anim.play();
            });
            this.tricksWon[winner_id].incValue(1);

            // Update bottle info
            let bottle_id = notif.args.bottle_id;
            let bottle_info = this.gamedatas.bottles[bottle_id];
            if (bottle_id) {
                let bottleElem = document.getElementById(`imp_bottle_${bottle_id}`);
                if (notif.args.price != bottle_info.price) {
                    bottle_info.price = notif.args.price;
                    bottleElem.firstChild.textContent = notif.args.price;
                    document.getElementById('imp_max_bottle_price').textContent =
                        Math.max(...Object.values(this.gamedatas.bottles).map(b => b.price), 0);
                }

                if (winner_id != bottle_info.owner) {
                    bottle_info.owner = winner_id;
                    let sourceSlot = bottleElem.parentElement;
                    let targetSlot = document.getElementById(`imp_bottle_slot_${winner_id}`);
                    this.gamedatas.bottles[bottle_id].owner = winner_id;
                    let oldPos = bottleElem.getBoundingClientRect();
                    let sourceSlotAnim = this.prepareDivSlide(sourceSlot);
                    let targetSlotAnim = this.prepareDivSlide(targetSlot);

                    bottleElem.remove()
                    targetSlot.appendChild(bottleElem);

                    this.animateFrom(bottleElem, oldPos);
                    this.animateDivSlide(sourceSlotAnim);
                    this.animateDivSlide(targetSlotAnim);
                }
            }

            if (!this.instantaneousMode)
                await new Promise(r => setTimeout(r, 1000));
            this.notifqueue.setSynchronousDuration(0);
        },

        notif_newScores: function(notif) {
            // Update players' scores
            for (let player_id in notif.args.player_points) {
                this.scoreCtrl[player_id].incValue(notif.args.individual_scores[player_id]);
            }

            // Show scores
            let tableDlg = new ebg.popindialog();
            tableDlg.create('tableWindow');
            tableDlg.setTitle(notif.args.end_of_game ? _('End of Game') : _('End of Round'));

            let parts = ['<div class="tableWindow">'];
            // Show Devil's trick
            parts.push('<div><div>', _("Devil's Trick"), ': ', _('${p} points').replace('${p}', notif.args.devil_points), '</div>');
            parts.push('<div class="imp_devil_list">');
            for (let card_id of notif.args.devil_cards) {
                let spritePos = this.getSpriteXY(card_id);
                let html = this.format_block('jstpl_card', {
                    x: spritePos.x,
                    y: spritePos.y,
                    largeprint: this.largePrint ? 'imp_largeprint' : '',
                });
                parts.push(html);
            }
            parts.push('</div></div>');

            parts.push('<table style="margin: 0 auto;">',
                '<tr>',
                '<th></th>'); // Player name
            if (this.gamedatas.teams) {
                parts.push(
                    '<th>', '</th>', // Team name
                    '<th>', _('Individual points'), '</th>',
                    '<th>', _('Team points'), '</th>');
            } else {
                parts.push(
                    '<th>', _('Round points'), '</th>');
            }
            parts.push(
                '<th>', _('Total score'), '</th>',
                '</tr>');

            // Sort by teams, current player on top
            let player_list = Object.keys(this.gamedatas.players);
            if (this.gamedatas.teams) {
                player_list.sort((a, b) => {
                    if (a == this.player_id)
                        return -1;
                    else if (b == this.player_id)
                        return 1;
                    else if (this.gamedatas.teams[a] == this.gamedatas.teams[this.player_id])
                        return -1;
                    else if (this.gamedatas.teams[b] == this.gamedatas.teams[this.player_id])
                        return 1;
                    else {
                        return this.gamedatas.teams[a] - this.gamedatas.teams[b];
                    }
                });
            }

            let skip_row = false;
            for (const player_id of player_list) {
                let team;
                let player_info = this.gamedatas.players[player_id];
                let player_points = notif.args.individual_scores[player_id];
                parts.push('<tr>');
                parts.push('<td>', this.format_player_name(player_info));
                if (this.gamedatas.teams && !skip_row) {
                    team = this.gamedatas.teams[player_id];
                    parts.push(`<td rowspan=2><span class="imp_team_scores imp_team_${team}">`, _('Team'), ' ', this.teamLetters[team], '</span></td>')
                }
                parts.push('</td>');
                parts.push('<td>', player_points, '</td>');
                if (this.gamedatas.teams) {
                    if (skip_row)
                        skip_row = false;
                    else {
                        parts.push('<td rowspan=2>', notif.args.team_scores[team], '</td>');
                        skip_row = true;
                    }
                }
                parts.push('<td>', this.scoreCtrl[player_id].getValue(), '</td>');
                parts.push('</tr>');
            }

            parts.push('</table>',
                '<br/><br/><div style="text-align: center">',
                '<a class="bgabutton bgabutton_blue" id="close_btn" href="#"><span>',
                _('Close'),
                '</span></a></div></div>');

            tableDlg.setContent(parts.join(''));
            if ($('close_btn')) {
                dojo.connect($('close_btn'), 'onclick', this, function(ev) {
                    ev.preventDefault();
                    tableDlg.destroy();
                });
            }

            tableDlg.show();
        },
   });
});
