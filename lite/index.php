<?php
/**
 * Shashka Lite - O'yin (Telegram Mini App)
 * To'g'ridan-to'g'ri ochiladi: https://topkons.uz/shashka/index.php
 * mod_rewrite KERAK EMAS.
 */
$apiBase = './api.php';
$ver = '1.1.0';
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0a84ff">
<title>Shashka</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
:root{
  --bg:#f2f2f7;--bg2:#ffffff;--card:#ffffff;--text:#1c1c1e;--muted:#8e8e93;
  --accent:#0a84ff;--accent2:#5e5ce6;--success:#34c759;--danger:#ff3b30;--gold:#ffd60a;
  --board-l:#f0d9b5;--board-d:#b58863;--sep:rgba(60,60,67,.12);--radius:16px;
}
[data-theme="dark"]{
  --bg:#000000;--bg2:#1c1c1e;--card:#1c1c1e;--text:#ffffff;--muted:#98989f;
  --sep:rgba(120,120,128,.3);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-user-select:none;user-select:none;}
body{font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','SF Pro Text',Arial,sans-serif;
  background:var(--bg);color:var(--text);max-width:480px;margin:0 auto;min-height:100vh;
  padding-bottom:calc(80px + env(safe-area-inset-bottom));-webkit-font-smoothing:antialiased;}
.hdr{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;
  position:sticky;top:0;z-index:10;background:rgba(242,242,247,.8);backdrop-filter:saturate(180%) blur(20px);
  -webkit-backdrop-filter:saturate(180%) blur(20px);}
[data-theme="dark"] .hdr{background:rgba(0,0,0,.7);}
.hdr .u{display:flex;align-items:center;gap:11px;}
.avatar{width:44px;height:44px;border-radius:50%;background:var(--bg2);object-fit:cover;border:2px solid var(--sep);}
.pill{display:flex;align-items:center;gap:5px;background:var(--card);padding:7px 13px;border-radius:20px;
  font-weight:700;font-size:14px;box-shadow:0 1px 4px rgba(0,0,0,.06);}
h1{font-size:30px;font-weight:800;letter-spacing:-.5px;padding:8px 18px 4px;}
h2{font-size:17px;font-weight:700;padding:14px 18px 8px;letter-spacing:-.2px;}
.muted{color:var(--muted);font-size:13px;}
.card{background:var(--card);border-radius:var(--radius);padding:16px;margin:8px 16px;
  box-shadow:0 1px 6px rgba(0,0,0,.05);}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:15px;border:none;
  border-radius:14px;background:var(--accent);color:#fff;font-size:16px;font-weight:600;cursor:pointer;
  transition:transform .12s,opacity .12s;font-family:inherit;}
.btn:active{transform:scale(.97);opacity:.9;}
.btn.grad{background:linear-gradient(135deg,#0a84ff,#5e5ce6);}
.btn.gold{background:linear-gradient(135deg,#ffd60a,#ff9500);color:#1c1c1e;}
.btn.sec{background:var(--card);color:var(--text);box-shadow:inset 0 0 0 1px var(--sep);}
.btn.danger{background:var(--danger);}
.btn.sm{width:auto;padding:9px 16px;font-size:14px;border-radius:11px;}
.row{display:flex;gap:10px;align-items:center;}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 16px;}
.mode{background:var(--card);border-radius:var(--radius);padding:18px 14px;text-align:center;cursor:pointer;
  transition:transform .12s;box-shadow:0 1px 6px rgba(0,0,0,.05);}
.mode:active{transform:scale(.96);}
.mode .e{font-size:32px;}
.mode .n{font-weight:700;margin-top:7px;font-size:15px;}
.mode .t{font-size:12px;color:var(--muted);margin-top:2px;}
/* Segment control (iOS) */
.seg{display:flex;background:rgba(118,118,128,.12);border-radius:11px;padding:2px;margin:8px 16px;}
.seg button{flex:1;border:none;background:transparent;padding:9px 0;border-radius:9px;font-size:13px;
  font-weight:600;color:var(--text);cursor:pointer;font-family:inherit;transition:.15s;}
.seg button.on{background:var(--card);box-shadow:0 1px 4px rgba(0,0,0,.12);}
/* Pages */
.page{display:none;}.page.active{display:block;animation:fade .25s;}
@keyframes fade{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
/* Nav */
.nav{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;display:flex;
  background:rgba(255,255,255,.86);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
  border-top:.5px solid var(--sep);padding:8px 0 calc(8px + env(safe-area-inset-bottom));}
[data-theme="dark"] .nav{background:rgba(20,20,22,.86);}
.nav a{flex:1;text-align:center;color:var(--muted);font-size:10px;text-decoration:none;cursor:pointer;font-weight:600;}
.nav a .i{font-size:23px;display:block;margin-bottom:1px;}
.nav a.on{color:var(--accent);}
/* Board */
.board-wrap{display:flex;justify-content:center;padding:8px 16px;}
#board{width:100%;max-width:430px;aspect-ratio:1/1;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.22);touch-action:none;}
.pbar{display:flex;justify-content:space-between;align-items:center;background:var(--card);border-radius:13px;
  padding:10px 14px;margin:7px 16px;box-shadow:0 1px 4px rgba(0,0,0,.05);}
.pbar.act{box-shadow:0 0 0 2px var(--accent);}
.pbar .nm{font-weight:700;display:flex;align-items:center;gap:8px;}
.dot{width:9px;height:9px;border-radius:50%;background:var(--muted);}
.dot.on{background:var(--success);}
.turn{text-align:center;color:var(--muted);font-size:14px;margin:6px;font-weight:600;}
/* Leaderboard */
.lb{display:flex;align-items:center;gap:12px;background:var(--card);border-radius:13px;padding:11px 14px;margin:7px 16px;}
.lb .r{font-weight:800;width:26px;text-align:center;font-size:16px;}
.lb .r.g1{color:#ffd60a;}.lb .r.g2{color:#aeb3bd;}.lb .r.g3{color:#cd7f32;}
.lb .nm{flex:1;font-weight:600;}
.lb .rt{font-weight:800;color:var(--accent);}
/* Shop */
.shop-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 16px;}
.shop-item{background:var(--card);border-radius:var(--radius);padding:14px 12px;text-align:center;
  box-shadow:0 1px 6px rgba(0,0,0,.05);position:relative;}
.shop-prev{width:100%;height:70px;border-radius:10px;margin-bottom:8px;display:flex;align-items:center;justify-content:center;}
.shop-item .nm{font-weight:700;font-size:13px;margin-bottom:8px;}
.tag{position:absolute;top:8px;right:8px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:7px;background:var(--success);color:#fff;}
.tag.eq{background:var(--accent);}
.stat{display:flex;justify-content:space-between;padding:11px 0;border-bottom:.5px solid var(--sep);font-size:15px;}
.stat:last-child{border-bottom:none;}
/* Overlay */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:50;padding:20px;}
.ov.show{display:flex;}
.result{background:var(--card);border-radius:22px;padding:30px 24px;text-align:center;max-width:320px;width:100%;}
.result .e{font-size:64px;}
.result .rc{font-size:18px;font-weight:800;margin-top:6px;}
.rc.up{color:var(--success);}.rc.down{color:var(--danger);}
.toast{position:fixed;top:16px;left:50%;transform:translateX(-50%);background:var(--card);padding:13px 18px;
  border-radius:13px;box-shadow:0 8px 24px rgba(0,0,0,.2);z-index:100;display:none;font-size:14px;font-weight:600;max-width:90%;}
.toast.show{display:block;animation:fade .2s;}
.loader{position:fixed;inset:0;background:var(--bg);display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:16px;z-index:200;}
.spin{width:36px;height:36px;border:3px solid var(--sep);border-top-color:var(--accent);border-radius:50%;animation:sp .8s linear infinite;}
@keyframes sp{to{transform:rotate(360deg);}}
.matchwait{text-align:center;padding:40px 20px;}
</style>
</head>
<body>

<div class="loader" id="loader"><div style="font-size:34px;">🎯</div><div class="spin"></div><div class="muted">Yuklanmoqda...</div></div>

<div class="hdr">
  <div class="u"><img class="avatar" id="uAvatar" src=""><div><div id="uName" style="font-weight:700;">Mehmon</div><div class="muted">⭐ <span id="uRating">1000</span></div></div></div>
  <div class="pill">🪙 <span id="uCoins">0</span></div>
</div>

<!-- HOME -->
<div class="page active" id="p-home">
  <h1>Salom! 👋</h1>
  <div style="padding:0 16px;"><button class="btn grad" onclick="findOnline()">🌐 Online o'ynash</button></div>
  <h2>Bot bilan o'ynash</h2>
  <div class="seg" id="levelSeg">
    <button data-l="easy" onclick="setLevel('easy',this)">Oson</button>
    <button data-l="medium" class="on" onclick="setLevel('medium',this)">O'rta</button>
    <button data-l="hard" onclick="setLevel('hard',this)">Qiyin</button>
    <button data-l="expert" onclick="setLevel('expert',this)">Ekspert</button>
  </div>
  <div class="grid2">
    <div class="mode" onclick="startGame('classic')"><div class="e">⚡</div><div class="n">Klassik</div><div class="t">Bot bilan</div></div>
    <div class="mode" onclick="startGame('blitz')"><div class="e">🔥</div><div class="n">Blitz</div><div class="t">Tezkor</div></div>
  </div>
</div>

<!-- GAME -->
<div class="page" id="p-game">
  <div class="pbar" id="oppBar"><div class="nm"><span class="dot" id="oppDot"></span><span id="oppName">🤖 Bot</span></div><div id="oppCount">12</div></div>
  <div class="board-wrap"><canvas id="board" width="430" height="430"></canvas></div>
  <div class="turn" id="turn">Sizning navbatingiz</div>
  <div class="pbar act" id="meBar"><div class="nm"><span class="dot on"></span><span id="meName">😎 Siz</span></div><div id="myCount">12</div></div>
  <div style="padding:0 16px;"><button class="btn danger" onclick="resignCurrent()">🏳️ Taslim bo'lish</button></div>
</div>

<!-- SHOP -->
<div class="page" id="p-shop">
  <h1>🏪 Do'kon</h1>
  <div class="seg" id="shopSeg">
    <button data-t="board" class="on" onclick="shopTab('board',this)">Doskalar</button>
    <button data-t="piece" onclick="shopTab('piece',this)">Toshlar</button>
  </div>
  <div class="shop-grid" id="shopGrid"><div class="muted" style="padding:16px;">Yuklanmoqda...</div></div>
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
    <img class="avatar" id="pAvatar" style="width:84px;height:84px;margin:0 auto 10px;" src="">
    <h3 id="pName" style="font-size:19px;">Mehmon</h3>
    <div class="muted">⭐ <b id="pRating">1000</b> • #<span id="pRank">-</span></div>
  </div>
  <div class="card">
    <div class="stat"><span>🎮 O'yinlar</span><b id="pGames">0</b></div>
    <div class="stat"><span>✅ G'alaba</span><b id="pWins">0</b></div>
    <div class="stat"><span>❌ Mag'lubiyat</span><b id="pLosses">0</b></div>
    <div class="stat"><span>🤝 Durang</span><b id="pDraws">0</b></div>
    <div class="stat"><span>📊 G'alaba %</span><b id="pWinRate">0%</b></div>
    <div class="stat"><span>🪙 Tanga</span><b id="pCoins">0</b></div>
    <div class="stat"><span>👥 Takliflar</span><b id="pRefs">0</b></div>
  </div>
  <div style="padding:0 16px;"><button class="btn gold" onclick="inviteFriend()">🎁 Do'st taklif qilish (+200🪙)</button></div>
  <div style="padding:8px 16px;"><button class="btn sec" onclick="toggleTheme()">🌙 Tema</button></div>
</div>

<div class="nav">
  <a class="on" data-p="home" onclick="nav('home')"><span class="i">🏠</span>Bosh</a>
  <a data-p="shop" onclick="nav('shop')"><span class="i">🏪</span>Do'kon</a>
  <a data-p="top" onclick="nav('top')"><span class="i">🏆</span>Reyting</a>
  <a data-p="profile" onclick="nav('profile')"><span class="i">👤</span>Profil</a>
</div>

<div class="ov" id="ov"><div class="result" id="resultCard"></div></div>
<div class="toast" id="toast"></div>

<script>var API='<?php echo $apiBase; ?>';</script>
<script src="./game.js?v=<?php echo $ver; ?>"></script>
</body>
</html>
