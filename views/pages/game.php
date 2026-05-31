<?php
/**
 * Game Page - canvas board, player bars, timers, controls
 */
?>
<div class="container">
    <!-- Opponent bar -->
    <div class="player-bar">
        <div class="info">
            <img class="avatar" src="/images/default-avatar.png" alt="opp" id="oppAvatar">
            <div>
                <div id="oppName" style="font-weight:600;">Raqib</div>
                <div class="caption" id="oppRating">1000</div>
            </div>
        </div>
        <div class="timer" id="oppTimer">5:00</div>
    </div>

    <!-- Board -->
    <div class="board-wrap">
        <canvas id="gameBoard" width="440" height="440"></canvas>
    </div>

    <!-- Turn indicator -->
    <div class="text-center caption" id="turnIndicator">O'yin boshlanmoqda...</div>

    <!-- My bar -->
    <div class="player-bar">
        <div class="info">
            <img class="avatar" src="/images/default-avatar.png" alt="me" id="meAvatar">
            <div>
                <div id="meName" style="font-weight:600;">Siz</div>
                <div class="caption" id="meRating">1000</div>
            </div>
        </div>
        <div class="timer active" id="myTimer">5:00</div>
    </div>

    <!-- Controls -->
    <div class="row gap-12 mt-16">
        <button class="btn btn-secondary" onclick="window.Game.offerDraw()">🤝 Durang</button>
        <button class="btn btn-danger" onclick="window.Game.resign()">🏳️ Taslim</button>
    </div>

    <!-- Move history -->
    <h3 class="mt-16 mb-16">Yurishlar tarixi</h3>
    <div class="card" id="moveHistory" style="max-height:200px; overflow-y:auto;">
        <div class="caption">Hali yurish yo'q</div>
    </div>
</div>

<style>
.move-row { display: flex; gap: 8px; padding: 4px 0; font-size: 14px; }
.move-row span:first-child { color: var(--text-secondary); min-width: 28px; }
</style>
