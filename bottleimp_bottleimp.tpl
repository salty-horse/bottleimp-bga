{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- The Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    bottleimp_vidrasso.tpl

    This is the HTML template of your game.

-->

<div id="imp_player_{TOP_PLAYER_ID}_strawmen_wrap" class="whiteblock imp_strawmen_wrap">
    <h3>Opponent's strawmen</h3>
    <div class="imp_strawmen">
        <div class="imp_straw" id="imp_playerstraw_{TOP_PLAYER_ID}_1"></div>
        <div class="imp_straw" id="imp_playerstraw_{TOP_PLAYER_ID}_2"></div>
        <div class="imp_straw" id="imp_playerstraw_{TOP_PLAYER_ID}_3"></div>
        <div class="imp_straw" id="imp_playerstraw_{TOP_PLAYER_ID}_4"></div>
        <div class="imp_straw" id="imp_playerstraw_{TOP_PLAYER_ID}_5"></div>
    </div>
</div>

<div id="imp_centerarea">

<!-- BEGIN player -->
<div id="imp_playertable_{PLAYER_ID}" class="imp_playertable whiteblock">
    <div class="imp_playertablename" style="color:#{PLAYER_COLOR}">
        {PLAYER_NAME}
    </div>
    <div class="imp_playertablecard" id="imp_playertablecard_{PLAYER_ID}"></div>
    <span class="imp_playertable_info">
        <span>{SCORE_PILE}: </span>
        <span id="imp_score_pile_{PLAYER_ID}"></span>
    </span>
</div>
<!-- END player -->

<div id="imp_trumpSelector" class="whiteblock">
    <div>
    <div>{TRUMP_RANK}:</div>
    <div id="imp_trump_rank" class="imp_trump_indicator"></div>
    <ul id="imp_rankSelector">
        <li data-type="rank" data-id="1">1</li>
        <li data-type="rank" data-id="2">2</li>
        <li data-type="rank" data-id="3">3</li>
        <li data-type="rank" data-id="4">4</li>
        <li data-type="rank" data-id="5">5</li>
        <li data-type="rank" data-id="6">6</li>
        <li data-type="rank" data-id="7">7</li>
        <li data-type="rank" data-id="8">8</li>
        <li data-type="rank" data-id="9">9</li>
    </ul>
    </div>
    <br>
    <div>
    <div>{TRUMP_SUIT}:</div>
    <div id="imp_trump_suit" class="imp_trump_indicator"></div>
    <ul id="imp_suitSelector">
        <li data-type="suit" class="imp_suit_icon_1" data-id="1"></li>
        <li data-type="suit" class="imp_suit_icon_2" data-id="2"></li>
        <li data-type="suit" class="imp_suit_icon_3" data-id="3"></li>
        <li data-type="suit" class="imp_suit_icon_4" data-id="4"></li>
    </ul>
    </div>
</div>

</div>

<div id="imp_player_{BOTTOM_PLAYER_ID}_strawmen_wrap" class="whiteblock imp_strawmen_wrap">
    <h3>{MY_STRAWMEN}</h3>
    <div id="imp_mystrawmen" class="imp_strawmen">
        <div class="imp_straw" id="imp_playerstraw_{BOTTOM_PLAYER_ID}_1"></div>
        <div class="imp_straw" id="imp_playerstraw_{BOTTOM_PLAYER_ID}_2"></div>
        <div class="imp_straw" id="imp_playerstraw_{BOTTOM_PLAYER_ID}_3"></div>
        <div class="imp_straw" id="imp_playerstraw_{BOTTOM_PLAYER_ID}_4"></div>
        <div class="imp_straw" id="imp_playerstraw_{BOTTOM_PLAYER_ID}_5"></div>
    </div>
</div>
<div id="imp_myhand_wrap" class="whiteblock">
    <h3>{MY_HAND}</h3>
    <div id="imp_myhand">
    </div>
</div>


<script type="text/javascript">
const elem = document.createElement('link');
elem.rel = 'stylesheet';
elem.href = 'https://fonts.googleapis.com/css?family=Stint+Ultra+Condensed&text=123456789';
document.head.appendChild(elem);

// Javascript HTML templates

var jstpl_player_hand_size = '<div class="imp_hand_size">\
    <span id="imp_player_hand_size_${id}">0</span>\
    <span class="fa fa-hand-paper-o"/>\
</div>';

</script>

{OVERALL_GAME_FOOTER}
