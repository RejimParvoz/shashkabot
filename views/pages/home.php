<?php
/**
 * Home Page - quick play, daily bonus, tournaments, recent games
 */
?>
<div class="container">
    <h1 class="mb-16">Salom! 👋</h1>

    <!-- Daily bonus card -->
    <div class="card" id="dailyBonusCard">
        <div class="row between">
            <div>
                <h3>🎁 Kunlik bonus</h3>
                <div class="caption">Har kuni kirib mukofot oling</div>
            </div>
            <button class="btn btn-sm btn-grad" onclick="claimDailyBonus()">Olish</button>
        </div>
    </div>

    <!-- Quick play -->
    <h2 class="mt-16 mb-16">Tezkor o'yin</h2>
    <div class="mode-grid" id="modeGrid">
        <div class="mode-card" onclick="quickPlay('classic')">
            <div class="emoji">⚡</div>
            <div class="name">Klassik</div>
            <div class="time">5 daqiqa</div>
        </div>
        <div class="mode-card" onclick="quickPlay('blitz')">
            <div class="emoji">🔥</div>
            <div class="name">Blitz</div>
            <div class="time">3 daqiqa</div>
        </div>
        <div class="mode-card" onclick="quickPlay('bullet')">
            <div class="emoji">💨</div>
            <div class="name">Bullet</div>
            <div class="time">1 daqiqa</div>
        </div>
        <div class="mode-card" onclick="quickPlay('rapid')">
            <div class="emoji">🎯</div>
            <div class="name">Rapid</div>
            <div class="time">10 daqiqa</div>
        </div>
    </div>

    <!-- Play vs bot -->
    <button class="btn btn-grad mt-16" onclick="showBotLevels()">🤖 Bot bilan o'ynash</button>

    <!-- Active tournament banner -->
    <div id="tournamentBanner" class="card mt-16 hidden">
        <h3>🏆 <span id="tournamentName"></span></h3>
        <div class="caption" id="tournamentInfo"></div>
        <button class="btn btn-sm mt-16" onclick="window.App.navigate('tournament')">Ko'rish</button>
    </div>

    <!-- Recent games -->
    <h2 class="mt-16 mb-16">So'nggi o'yinlar</h2>
    <div id="recentGames">
        <div class="caption text-center">O'yinlar yuklanmoqda...</div>
    </div>
</div>

<script>
async function claimDailyBonus() {
  try {
    const res = await window.App.api.post('/daily/claim', {});
    window.App.toast('Bonus olindi: ' + res.diamonds + ' 💎 + ' + res.coins + ' 🪙', 'success');
    window.App.user.diamonds = res.balance.diamonds;
    window.App.user.coins = res.balance.coins;
    window.App.renderUserHeader();
  } catch (e) {
    window.App.toast(e.message, 'error');
  }
}

async function quickPlay(mode) {
  const modeMap = { classic: 1, blitz: 2, bullet: 3, rapid: 4, marathon: 5 };
  try {
    const game = await window.App.api.post('/game/create', { mode: modeMap[mode], opponent: 'bot', bot_level: 'medium' });
    window.Game.init(game.id);
    window.App.navigate('game');
  } catch (e) {
    window.App.toast(e.message, 'error');
  }
}

function showBotLevels() {
  window.App.showModal(
    '<h2 class="mb-16">Bot darajasi</h2>' +
    '<button class="btn btn-secondary mb-16" onclick="playBot(\'easy\')">😊 Oson</button>' +
    '<button class="btn btn-secondary mb-16" onclick="playBot(\'medium\')">😐 O\'rta</button>' +
    '<button class="btn btn-secondary mb-16" onclick="playBot(\'hard\')">😤 Qiyin</button>' +
    '<button class="btn btn-secondary" onclick="playBot(\'expert\')">🤯 Ekspert</button>'
  );
}

async function playBot(level) {
  window.App.hideModal();
  try {
    const game = await window.App.api.post('/game/create', { mode: 1, opponent: 'bot', bot_level: level });
    window.Game.init(game.id);
    window.App.navigate('game');
  } catch (e) {
    window.App.toast(e.message, 'error');
  }
}

// Load home data when navigated to
window.App.onPageLoad = function(page) {
  if (page === 'home') loadHomeData();
};

async function loadHomeData() {
  try {
    const games = await window.App.api.get('/user/games?limit=5');
    const el = document.getElementById('recentGames');
    if (!games || !games.length) {
      el.innerHTML = '<div class="caption text-center">Hali o\'yin yo\'q</div>';
      return;
    }
    el.innerHTML = games.map(g =>
      '<div class="card row between"><div>' + (g.mode_name || 'O\'yin') +
      '</div><div class="caption">' + window.App.timeAgo(g.finished_at) + '</div></div>'
    ).join('');
  } catch (e) { /* silent */ }
}
</script>
