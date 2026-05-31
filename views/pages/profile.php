<?php
/**
 * Profile Page - user stats, league, settings
 */
?>
<div class="container">
    <!-- Profile header -->
    <div class="card text-center">
        <img class="avatar avatar-lg" src="/images/default-avatar.png" alt="avatar" id="profileAvatar" style="margin:0 auto 12px;">
        <h2 id="profileName">Mehmon</h2>
        <div class="caption" id="profileLeague">Bronza Liga</div>
        <div class="row center gap-12 mt-16">
            <div>
                <div style="font-weight:700; font-size:22px;" id="profileRating">1000</div>
                <div class="caption">Reyting</div>
            </div>
            <div>
                <div style="font-weight:700; font-size:22px;" id="profileGames">0</div>
                <div class="caption">O'yinlar</div>
            </div>
            <div>
                <div style="font-weight:700; font-size:22px;" id="profileWinRate">0%</div>
                <div class="caption">G'alaba</div>
            </div>
        </div>
    </div>

    <!-- Detailed stats -->
    <div class="card">
        <h3 class="mb-16">📊 Statistika</h3>
        <div class="row between"><span>✅ G'alabalar</span><span id="statWins">0</span></div>
        <div class="row between mt-16"><span>❌ Mag'lubiyatlar</span><span id="statLosses">0</span></div>
        <div class="row between mt-16"><span>🤝 Duranglar</span><span id="statDraws">0</span></div>
        <div class="row between mt-16"><span>🔥 Eng uzun seriya</span><span id="statStreak">0</span></div>
        <div class="row between mt-16"><span>🏅 Reyting o'rni</span><span id="statRank">-</span></div>
    </div>

    <!-- Settings -->
    <div class="card">
        <h3 class="mb-16">⚙️ Sozlamalar</h3>
        <div class="row between">
            <span>🌙 Tungi rejim</span>
            <button class="btn btn-sm btn-secondary" onclick="window.App.toggleTheme()">O'zgartirish</button>
        </div>
        <div class="row between mt-16">
            <span>🔊 Ovoz effektlari</span>
            <button class="btn btn-sm btn-secondary" onclick="toggleSound(this)">Yoqilgan</button>
        </div>
    </div>

    <!-- VIP & Friends shortcuts -->
    <button class="btn btn-grad mt-16" onclick="loadVipPlans()">👑 VIP bo'lish</button>
    <button class="btn btn-secondary mt-16" onclick="window.App.navigate('home')">🏠 Bosh sahifa</button>
</div>

<script>
window.App.onPageLoad = (function(prev) {
  return function(page) {
    if (typeof prev === 'function') prev(page);
    if (page === 'profile') loadProfile();
  };
})(window.App.onPageLoad);

async function loadProfile() {
  try {
    const data = await window.App.api.get('/user/stats');
    document.getElementById('profileRating').textContent = data.rating;
    document.getElementById('profileGames').textContent = data.total_games;
    document.getElementById('profileWinRate').textContent = data.win_rate + '%';
    document.getElementById('statWins').textContent = data.wins;
    document.getElementById('statLosses').textContent = data.losses;
    document.getElementById('statDraws').textContent = data.draws;
    document.getElementById('statStreak').textContent = data.max_win_streak;
    document.getElementById('statRank').textContent = '#' + (data.leaderboard_position || '-');
    if (data.league && data.league.name) {
      document.getElementById('profileLeague').textContent = data.league.name;
    }
  } catch (e) { /* silent */ }

  if (window.App.user) {
    document.getElementById('profileName').textContent = window.App.user.display_name || 'Mehmon';
  }
}

function toggleSound(btn) {
  window.App.sound.toggle();
  btn.textContent = window.App.sound.enabled ? 'Yoqilgan' : 'O\'chirilgan';
}

async function loadVipPlans() {
  try {
    const plans = await window.App.api.get('/vip/plans');
    let html = '<h2 class="mb-16">👑 VIP rejalar</h2>';
    plans.forEach(p => {
      html += '<div class="card row between"><div><b>' + p.name + '</b><div class="caption">Kunlik ' +
        p.daily_diamonds + ' 💎</div></div><button class="btn btn-sm btn-grad" onclick="subscribeVip(\'' +
        p.level + '\')">' + p.stars_price + ' ⭐</button></div>';
    });
    window.App.showModal(html);
  } catch (e) {
    window.App.toast(e.message, 'error');
  }
}

async function subscribeVip(level) {
  try {
    const res = await window.App.api.post('/vip/subscribe', { level: level });
    if (res.invoice && window.Telegram && window.Telegram.WebApp) {
      window.App.toast('To\'lov oynasi ochilmoqda...', 'info');
      // Telegram.WebApp.openInvoice would be used in production
    }
    window.App.hideModal();
  } catch (e) {
    window.App.toast(e.message, 'error');
  }
}
</script>
