{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- The Bottle Imp implementation : © Ori Avtalion <ori@avtalion.name>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    bottleimp_bottleimp.tpl

    This is the HTML template of your game.

-->

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

<div id="imp_passCards">
<!-- BEGIN pass -->
<div id="imp_pass_{PASS_TYPE}" class="imp_pass whiteblock">
    <div class="imp_playertablename"></div>
    <div class="imp_playertablecard" id="imp_passcard_{PASS_TYPE}"></div>
</div>
<!-- END pass -->
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
elem.href = 'https://fonts.googleapis.com/css?family=Stint+Ultra+Condensed&text=0123456789.';
document.head.appendChild(elem);

// Javascript HTML templates

var jstpl_player_hand_size = '<div class="imp_hand_size">\
    <span id="imp_player_hand_size_${id}">0</span>\
    <span class="fa fa-hand-paper-o"/>\
</div>';

</script>

{OVERALL_GAME_FOOTER}
