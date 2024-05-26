{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    bottleimp_bottleimp.tpl

    This is the HTML template of your game.

-->

<div id="imp_info_box" class="whiteblock">
    <div>Round: <span id="imp_round_number">1</span>/<span id="imp_total_rounds">6</span></div>
    <div><span id="imp_price_label">Bottle price</span>:</div>
    <div id="imp_max_bottle_price">19</div>
    <div id="imp_bottles" style="">
    </div>
</div>

<div id="imp_player_{TOP_PLAYER_ID}_visible_hand_wrap" class="imp_visible_hand whiteblock">
    <h3>Opponent's visible hand</h3>
    <div id="imp_player_{TOP_PLAYER_ID}_visible_hand"></div>
</div>

<div id="imp_centerarea">

<!-- BEGIN player -->
<div id="imp_playertable_{PLAYER_ID}" class="imp_playertable whiteblock">
    <div class="imp_playertablename" style="color:#{PLAYER_COLOR}">{PLAYER_NAME}</div>
    <div id="imp_playertable_team_{PLAYER_ID}" class="imp_team_label">Team A</div>
    <div class="imp_playertablecard" id="imp_playertablecard_{PLAYER_ID}"></div>
    <div class="imp_playertablecard imp_second_card_slot" id="imp_playertablecard_{PLAYER_ID}_2"></div>
    <span class="imp_playertable_info">
        <span>{TRICKS_WON}: </span>
        <span id="imp_score_pile_{PLAYER_ID}"></span>
    </span>
    <div id="imp_bottle_slot_{PLAYER_ID}" class="imp_bottle_slot"></div>
</div>
<!-- END player -->

<div id="imp_passCards">
<!-- BEGIN pass -->
<div id="imp_pass_{PASS_TYPE}" data-pass-type="{PASS_TYPE}" class="imp_pass whiteblock">
    <div class="imp_playertablename"></div>
    <div class="imp_playertablecard" id="imp_passcard_{PASS_TYPE}"></div>
</div>
<!-- END pass -->
</div>

</div>

<div id="imp_my_hands">
<div id="imp_myhand_wrap" class="whiteblock">
    <h3>{YOUR_HAND}</h3>
    <div id="imp_myhand">
    </div>
</div>

<div id="imp_player_{BOTTOM_PLAYER_ID}_visible_hand_wrap" class="imp_visible_hand whiteblock">
    <h3>{YOUR_VISIBLE_HAND}</h3>
    <div id="imp_player_{BOTTOM_PLAYER_ID}_visible_hand">
    </div>
</div>
</div>

<script type="text/javascript">
// Javascript HTML templates

var jstpl_player_hand_size = '<div class="imp_hand_size">\
    <span id="imp_player_hand_size_${id}">0</span>\
    <span class="fa fa-hand-paper-o"/>\
</div>';

var jstpl_cardontable = '<div class="imp_cardontable" id="${id}" style="background-position:-${x}px -${y}px"></div>';

var jstpl_bottle = '<div id="imp_bottle_${id}" class="imp_bottle"><span>${price}</span></div>';

var jstpl_player_name = '<span style="color:#${color}">${name}</span>';

</script>

{OVERALL_GAME_FOOTER}
