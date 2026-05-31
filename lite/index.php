<?php
/**
 * Shashka Lite - O'yin (Telegram Mini App)
 * To'g'ridan-to'g'ri ochiladi: https://topkons.uz/shashka/index.php
 * mod_rewrite KERAK EMAS.
 */
$apiBase = './api.php';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Shashka</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
:root{
  --bg:#f2f2f7;--card:#fff;--text:#1c1c1e;--muted:#8e8e93;--accent:#007aff;
  --board-l:#f0d9b5;--board-d:#b58863;--success:#34c759;--danger:#ff3b30;
}
[data-theme="dark"]{--bg:#000;--card:#1c1c1e;--text:#fff;--muted:#98989f;--board-l:#e8c99b;--board-d:#9b6a43;}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
body{font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display',Arial,sans-serif;background:var(--bg);color:var(--text);max-width:480px;margin:0 auto;min-height:100vh;padding-bottom:76px;}
.header{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;position:sticky;top:0;background:var(--bg);z-index:10;}
.header .u{display:flex;align-items:center;gap:10px;}
.avatar{width:42px;height:42px;border-radius:50%;background:var(--card);object-fit:cover;}
.bal{display:flex;gap:10px;font-weight:700;font-size:14px;}
.card{background:var(--card);border-radius:16px;padding:16px;margin:10px 16px;box-shadow:0 1px 6px rgba(0,0,0,.05);}
h1{font-size:26px;padding:6px 16px;}
h2{font-size:20px;padding:6px 16px;}
h3{font-size:16px;}
.muted{color:var(--muted);font-size:13px;}
.btn{display:block;width:100%;padding:14px;border:none;border-radius:12px;background:var(--accent);color:#fff;font-size:16px;font-weight:600;cursor:pointer;}
.btn:active{opacity:.85;}
.btn.grad{background:linear-gradient(135deg,#667eea,#764ba2);}
.btn.sec{background:var(--card);color:var(--text);border:1px solid rgba(0,0,0,.1);}
.btn.danger{background:var(--danger);}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 16px;}
.mode{background:var(--card);border-radius:16px;padding:16px;text-align:center;cursor:pointer;}
.mode .e{font-size:30px;}
.mode .n{font-weight:600;margin-top:6px;}
.mode .t{font-size:12px;color:var(--muted);}
.page{display:none;}
.page.active{display:block;}
.nav{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;display:flex;background:var(--card);border-top:1px solid rgba(0,0,0,.08);padding:8px 0 calc(8px + env(safe-area-inset-bottom));}
.nav a{flex:1;text-align:center;color:var(--muted);font-size:10px;text-decoration:none;cursor:pointer;}
.nav a .i{font-size:22px;display:block;}
.nav a.active{color:var(--accent);}
.board-wrap{display:flex;justify-content:center;padding:10px;}
#board{width:100%;max-width:420px;aspect-ratio:1/1;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.18);touch-action:none;}
.pbar{display:flex;justify-content:space-between;align-items:center;background:var(--card);border-radius:12px;padding:10px 14px;margin:8px 16px;}
.pbar .nm{font-weight:600;}
.turn{text-align:center;color:var(--muted);font-size:14px;margin:6px;}
.lb{display:flex;align-items:center;gap:12px;background:var(--card);border-radius:12px;padding:10px 14px;margin:8px 16px;}
.lb .r{font-weight:700;width:28px;text-align:center;}
.lb .r.g1{color:#ffd700;}.lb .r.g2{color:#c0c0c0;}.lb .r.g3{color:#cd7f32;}
.lb .nm{flex:1;}
.lb .rt{font-weight:700;color:var(--accent);}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;z-index:50;}
.overlay.show{display:flex;}
.result{background:var(--card);border-radius:20px;padding:30px 24px;text-align:center;max-width:300px;width:88%;}
.result .e{font-size:60px;}
.result .rc{font-size:18px;font-weight:700;margin-top:6px;}
.rc.up{color:var(--success);}.rc.down{color:var(--danger);}
.toast{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:var(--card);padding:12px 18px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.2);z-index:100;display:none;font-size:14px;}
.toast.show{display:block;}
.loader{position:fixed;inset:0;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;z-index:200;}
.spin{width:34px;height:34px;border:3px solid rgba(0,0,0,.1);border-top-color:var(--accent);border-radius:50%;animation:sp 0.8s linear infinite;}
@keyframes sp{to{transform:rotate(360deg);}}
.stat{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(0,0,0,.05);}
</style>
</head>
<body>

<div class="loader" id="loader"><div style="font-size:30px;">🎯</div><div class="spin"></div><div class="muted">Yuklanmoqda...</div></div>

<div class="header">
  <div class="u"><img class="avatar" id="uAvatar" src=""><div><div id="uName" style="font-weight:600;">Mehmon</div><div class="muted">Reyting: <span id="uRating">1000</span></div></div></div>
  <div class="bal"><span>🪙 <span id="uCoins">0</span></span></div>
</div>

<!-- HOME -->
<div class="page active" id="p-home">
  <h1>Salom! 👋</h1>
  <h2>O'yin rejimi</h2>
  <div class="grid">
    <div class="mode" onclick="startGame('classic')"><div class="e">⚡</div><div class="n">Klassik</div><div class="t">Bot bilan</div></div>
    <div class="mode" onclick="startGame('blitz')"><div class="e">🔥</div><div class="n">Blitz</div><div class="t">Tezkor</div></div>
  </div>
  <h2 style="margin-top:10px;">Bot darajasi</h2>
  <div class="grid">
    <div class="mode" onclick="setLevel('easy',this)"><div class="e">😊</div><div class="n">Oson</div></div>
    <div class="mode" onclick="setLevel('medium',this)"><div class="e">😐</div><div class="n">O'rta</div></div>
    <div class="mode" onclick="setLevel('hard',this)"><div class="e">😤</div><div class="n">Qiyin</div></div>
    <div class="mode" onclick="setLevel('expert',this)"><div class="e">🤯</div><div class="n">Ekspert</div></div>
  </div>
  <div class="card"><div class="muted">Tanlangan daraja: <b id="curLevel">O'rta</b></div></div>
</div>

<!-- GAME -->
<div class="page" id="p-game">
  <div class="pbar"><div class="nm">🤖 Bot (<span id="gLevel">o'rta</span>)</div><div id="gBotCount">12</div></div>
  <div class="board-wrap"><canvas id="board" width="420" height="420"></canvas></div>
  <div class="turn" id="turn">Sizning navbatingiz</div>
  <div class="pbar"><div class="nm">😎 Siz</div><div id="gMyCount">12</div></div>
  <div style="padding:0 16px;"><button class="btn danger" onclick="resignGame()">🏳️ Taslim bo'lish</button></div>
</div>

<!-- LEADERBOARD -->
<div class="page" id="p-top">
  <h1>🏆 Reyting</h1>
  <div id="lbList"><div class="muted" style="padding:16px;">Yuklanmoqda...</div></div>
</div>

<!-- PROFILE -->
<div class="page" id="p-profile">
  <h1>👤 Profil</h1>
  <div class="card" style="text-align:center;">
    <img class="avatar" id="pAvatar" style="width:80px;height:80px;margin:0 auto 10px;" src="">
    <h3 id="pName">Mehmon</h3>
    <div class="muted">Reyting: <b id="pRating">1000</b> • #<span id="pRank">-</span></div>
  </div>
  <div class="card">
    <div class="stat"><span>🎮 Jami o'yinlar</span><b id="pGames">0</b></div>
    <div class="stat"><span>✅ G'alabalar</span><b id="pWins">0</b></div>
    <div class="stat"><span>❌ Mag'lubiyatlar</span><b id="pLosses">0</b></div>
    <div class="stat"><span>🤝 Duranglar</span><b id="pDraws">0</b></div>
    <div class="stat"><span>📊 G'alaba foizi</span><b id="pWinRate">0%</b></div>
    <div class="stat"><span>🪙 Tangalar</span><b id="pCoins">0</b></div>
  </div>
  <div style="padding:0 16px;"><button class="btn sec" onclick="toggleTheme()">🌙 Tema o'zgartirish</button></div>
</div>

<div class="nav">
  <a class="active" data-p="home" onclick="nav('home')"><span class="i">🏠</span>Bosh</a>
  <a data-p="top" onclick="nav('top')"><span class="i">🏆</span>Reyting</a>
  <a data-p="profile" onclick="nav('profile')"><span class="i">👤</span>Profil</a>
</div>

<div class="overlay" id="overlay"><div class="result" id="resultCard"></div></div>
<div class="toast" id="toast"></div>

<script>const API='<?php echo $apiBase; ?>';</script>
<script src="./game.js"></script>
</body>
</html>
