<?php
/**
 * Shashka Lite - O'yin (Telegram Mini App)
 * To'g'ridan-to'g'ri ochiladi: https://topkons.uz/shashka/index.php
 * mod_rewrite KERAK EMAS.
 */
$apiBase = './api.php';
$ver = '1.2.0';
$joinCode = isset($_GET['join']) ? preg_replace('/[^A-Za-z0-9]/', '', $_GET['join']) : '';
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
.loader{position:fixed;inset:0;background:radial-gradient(circle at 50% 35%,rgba(10,132,255,.12),var(--bg) 70%);display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:6px;z-index:200;}
.spin{width:36px;height:36px;border:3px solid var(--sep);border-top-color:var(--accent);border-radius:50%;animation:sp .8s linear infinite;}
@keyframes sp{to{transform:rotate(360deg);}}
.logo{animation:logoPop .7s cubic-bezier(.2,1.2,.3,1);}
.logo-svg{display:block;}
.logo-ring{transform-origin:60px 60px;animation:ringSpin 3.5s linear infinite;}
.logo-king{transform-origin:60px 60px;animation:kingFloat 2s ease-in-out infinite;}
.logo-dots circle{animation:dotPulse 2s ease-in-out infinite;}
.logo-title{font-size:30px;font-weight:800;letter-spacing:4px;margin-top:14px;
  background:linear-gradient(135deg,#0a84ff,#5e5ce6);-webkit-background-clip:text;background-clip:text;color:transparent;animation:fadeUp .6s .2s both;}
.logo-sub{font-size:13px;font-weight:700;letter-spacing:3px;color:var(--gold);animation:fadeUp .6s .35s both;}
.logo-bar{width:140px;height:5px;border-radius:3px;background:var(--sep);overflow:hidden;margin-top:18px;}
.logo-bar-fill{height:100%;width:30%;border-radius:3px;background:linear-gradient(90deg,#0a84ff,#5e5ce6);animation:loadBar 1.4s ease-in-out infinite;}
@keyframes logoPop{from{transform:scale(.5);opacity:0;}to{transform:scale(1);opacity:1;}}
@keyframes ringSpin{to{transform:rotate(360deg);}}
@keyframes kingFloat{0%,100%{transform:translateY(0) scale(1);}50%{transform:translateY(-4px) scale(1.06);}}
@keyframes dotPulse{0%,100%{opacity:.5;}50%{opacity:1;}}
@keyframes fadeUp{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}
@keyframes loadBar{0%{transform:translateX(-120%);}100%{transform:translateX(420%);}}
.matchwait{text-align:center;padding:40px 20px;}
.progress{height:10px;background:rgba(118,118,128,.18);border-radius:6px;overflow:hidden;}
.progress-fill{height:100%;background:linear-gradient(90deg,#0a84ff,#5e5ce6);border-radius:6px;transition:width .4s;}
.bp-row{display:flex;align-items:center;gap:10px;background:var(--card);border-radius:13px;padding:10px 12px;margin:7px 16px;}
.bp-lvl{width:34px;height:34px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0;}
.bp-lvl.lock{background:var(--muted);}
.bp-rew{flex:1;display:flex;gap:8px;flex-wrap:wrap;font-size:13px;}
.bp-chip{padding:3px 8px;border-radius:8px;background:rgba(118,118,128,.15);font-weight:600;}
.bp-chip.prem{background:linear-gradient(135deg,#ffd60a33,#ff950033);}
.podium{display:flex;align-items:flex-end;justify-content:center;gap:10px;padding:18px 16px 6px;}
.pod{display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;}
.pod .av{border-radius:50%;object-fit:cover;background:var(--bg2);border:3px solid;}
.pod1 .av{width:74px;height:74px;border-color:#ffd60a;}
.pod2 .av{width:60px;height:60px;border-color:#aeb3bd;}
.pod3 .av{width:60px;height:60px;border-color:#cd7f32;}
.pod .nm{font-size:12px;font-weight:700;max-width:84px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pod .rt{font-size:12px;font-weight:800;color:var(--accent);}
.pod .base{border-radius:10px 10px 0 0;background:linear-gradient(180deg,#0a84ff,#5e5ce6);color:#fff;font-weight:800;width:72px;display:flex;align-items:center;justify-content:center;}
.pod1 .base{height:64px;font-size:26px;}.pod2 .base{height:44px;font-size:20px;}.pod3 .base{height:30px;font-size:18px;}
.loader.hide{opacity:0;pointer-events:none;transition:opacity .45s;}
/* Chat */
.chat{margin:10px 16px;background:var(--card);border-radius:14px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.06);}
.chat-msgs{max-height:160px;overflow-y:auto;padding:10px;display:flex;flex-direction:column;gap:6px;}
.cmsg{max-width:78%;padding:7px 11px;border-radius:14px;font-size:14px;word-break:break-word;}
.cmsg.me{align-self:flex-end;background:var(--accent);color:#fff;border-bottom-right-radius:4px;}
.cmsg.them{align-self:flex-start;background:rgba(118,118,128,.16);border-bottom-left-radius:4px;}
.chat-input{display:flex;gap:6px;padding:8px;border-top:.5px solid var(--sep);}
.chat-input input{flex:1;padding:9px 12px;border-radius:18px;border:1px solid var(--sep);background:var(--bg);color:var(--text);font-size:14px;}
.chat-input button{width:40px;border-radius:50%;background:var(--accent);color:#fff;font-size:16px;}
.chat-quick{display:flex;gap:6px;flex-wrap:wrap;padding:0 8px 8px;}
.chat-quick span{padding:5px 10px;border-radius:14px;background:rgba(118,118,128,.16);font-size:16px;cursor:pointer;}
/* Tournament */
.tcard{background:var(--card);border-radius:16px;padding:15px;margin:9px 16px;box-shadow:0 1px 6px rgba(0,0,0,.06);}
.tcard .th{display:flex;justify-content:space-between;align-items:center;}
.tcard .tt{font-weight:800;font-size:17px;}
.tcard .tm{font-size:12px;color:var(--muted);margin-top:2px;}
.tprizes{display:flex;gap:8px;margin:10px 0;}
.tprize{flex:1;text-align:center;background:rgba(118,118,128,.1);border-radius:10px;padding:7px 4px;font-size:13px;font-weight:700;}
.tprize.g1{background:rgba(255,214,10,.16);}.tprize.g2{background:rgba(174,179,189,.16);}.tprize.g3{background:rgba(205,127,50,.16);}
.ttop{display:flex;align-items:center;gap:10px;padding:6px 0;border-top:.5px solid var(--sep);font-size:14px;}
.ttop .r{width:22px;font-weight:800;text-align:center;}
.ttop .nm{flex:1;}
.ttop .sc{font-weight:800;color:var(--accent);}
.countdown{font-variant-numeric:tabular-nums;font-weight:700;color:var(--danger);}
/* VIP */
.vipcard{background:linear-gradient(135deg,#1c1c2e,#3a2b6b);color:#fff;border-radius:16px;padding:16px;margin:9px 16px;position:relative;overflow:hidden;}
.vipcard.bronze{background:linear-gradient(135deg,#5a3a1a,#8a5a2a);}
.vipcard.gold{background:linear-gradient(135deg,#7a5c00,#ffd60a);color:#1c1c1e;}
.vipcard.platinum{background:linear-gradient(135deg,#3a3a4a,#aeb3bd);color:#1c1c1e;}
.vipcard .vt{font-weight:800;font-size:18px;}
.vipcard .vd{font-size:13px;opacity:.9;margin:4px 0 12px;}
.vip-badge-crown{display:inline-block;}
/* Premium shop shine */
.shop-item.prem{box-shadow:0 0 0 1.5px var(--gold),0 2px 10px rgba(255,214,10,.18);}
.shop-item.prem::after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;
  background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);animation:shine 3s infinite;}
@keyframes shine{0%,60%{left:-60%;}100%{left:130%;}}
.shop-prev svg{display:block;}
</style>
</head>
<body>

<div class="loader" id="loader">
  <div class="logo">
    <svg class="logo-svg" viewBox="0 0 120 120" width="110" height="110">
      <defs>
        <linearGradient id="lgBoard" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#0a84ff"/><stop offset="1" stop-color="#5e5ce6"/>
        </linearGradient>
        <radialGradient id="lgPiece" cx="0.35" cy="0.3" r="0.8">
          <stop offset="0" stop-color="#fff6d0"/><stop offset="0.5" stop-color="#ffd60a"/><stop offset="1" stop-color="#b8860b"/>
        </radialGradient>
        <filter id="lgGlow"><feGaussianBlur stdDeviation="2.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
      </defs>
      <rect class="logo-ring" x="6" y="6" width="108" height="108" rx="26" fill="none" stroke="url(#lgBoard)" stroke-width="4" stroke-dasharray="60 30"/>
      <rect x="22" y="22" width="76" height="76" rx="16" fill="url(#lgBoard)" opacity="0.16"/>
      <!-- checker dots -->
      <g class="logo-dots" fill="#0a84ff">
        <circle cx="38" cy="38" r="5"/><circle cx="60" cy="38" r="5" opacity="0.4"/><circle cx="82" cy="38" r="5"/>
        <circle cx="38" cy="60" r="5" opacity="0.4"/><circle cx="82" cy="60" r="5" opacity="0.4"/>
        <circle cx="38" cy="82" r="5"/><circle cx="60" cy="82" r="5" opacity="0.4"/><circle cx="82" cy="82" r="5"/>
      </g>
      <!-- center king -->
      <g class="logo-king" filter="url(#lgGlow)">
        <circle cx="60" cy="60" r="20" fill="url(#lgPiece)"/>
        <text x="60" y="67" font-size="22" text-anchor="middle" fill="#7a5c00" font-family="serif">&#9819;</text>
      </g>
    </svg>
  </div>
  <div class="logo-title">SHASHKA</div>
  <div class="logo-sub">Pro</div>
  <div class="logo-bar"><div class="logo-bar-fill"></div></div>
</div>

<div class="hdr">
  <div class="u"><img class="avatar" id="uAvatar" src=""><div><div id="uName" style="font-weight:700;">Mehmon</div><div class="muted">⭐ <span id="uRating">1000</span></div></div></div>
  <div class="row" style="gap:7px;">
    <div class="pill">🪙 <span id="uCoins">0</span></div>
    <div class="pill" onclick="nav('shop');shopTab('diamonds',document.querySelector('#shopSeg [data-t=diamonds]'))" style="cursor:pointer;">💎 <span id="uDiamonds">0</span></div>
  </div>
</div>

<!-- HOME -->
<div class="page active" id="p-home">
  <h1>Salom! 👋</h1>
  <div style="padding:0 16px;"><button class="btn grad" onclick="findOnline()">🌐 Online o'ynash</button></div>
  <div style="padding:8px 16px;"><button class="btn sec" onclick="openFriend()">👥 Do'st bilan 1v1</button></div>
  <div class="grid2" style="margin-top:4px;">
    <div class="mode" onclick="nav('tour')"><div class="e">🏆</div><div class="n">Turnirlar</div><div class="t">Kunlik/Haftalik</div></div>
    <div class="mode" onclick="nav('vip')"><div class="e">👑</div><div class="n">VIP</div><div class="t">Imtiyozlar</div></div>
  </div>
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
  <div class="row" style="padding:0 16px;gap:8px;">
    <button class="btn sec hidden" id="drawBtn" onclick="offerDraw()">🤝 Durang</button>
    <button class="btn sec hidden" id="chatBtn" onclick="toggleChat()">💬 Chat</button>
    <button class="btn danger" onclick="resignCurrent()">🏳️ Taslim</button>
  </div>
  <!-- Chat paneli (online) -->
  <div class="chat hidden" id="chatPanel">
    <div class="chat-msgs" id="chatMsgs"></div>
    <div class="chat-input">
      <input id="chatText" maxlength="200" placeholder="Xabar..." onkeydown="if(event.key==='Enter')sendChat()">
      <button onclick="sendChat()">➤</button>
    </div>
    <div class="chat-quick" id="chatQuick"></div>
  </div>
</div>

<!-- SHOP -->
<div class="page" id="p-shop">
  <h1>🏪 Do'kon</h1>
  <div class="seg" id="shopSeg">
    <button data-t="board" class="on" onclick="shopTab('board',this)">Doskalar</button>
    <button data-t="piece" onclick="shopTab('piece',this)">Toshlar</button>
    <button data-t="premium" onclick="shopTab('premium',this)">💎 Premium</button>
    <button data-t="diamonds" onclick="shopTab('diamonds',this)">Olmos</button>
  </div>
  <div class="shop-grid" id="shopGrid"><div class="muted" style="padding:16px;">Yuklanmoqda...</div></div>
</div>

<!-- BATTLE PASS -->
<div class="page" id="p-bp">
  <h1>🎟 Battle Pass</h1>
  <div class="card">
    <div class="row between"><div><b id="bpLevelTxt">Daraja 0</b><div class="muted" id="bpXpTxt">0 XP</div></div>
      <button class="btn sm gold" id="bpPremBtn" onclick="buyPremium()" style="width:auto;">Premium ochish (150⭐)</button></div>
    <div class="progress" style="margin-top:10px;"><div class="progress-fill" id="bpFill" style="width:0%;"></div></div>
  </div>
  <div id="bpLevels"></div>
</div>

<!-- TOURNAMENTS -->
<div class="page" id="p-tour">
  <h1>🏆 Turnirlar</h1>
  <p class="muted" style="padding:0 18px 6px;">G'alaba +3, durang +1 ochko. Top 3 olmos yutadi!</p>
  <div id="tourList"><div class="muted" style="padding:16px;">Yuklanmoqda...</div></div>
</div>

<!-- VIP -->
<div class="page" id="p-vip">
  <h1>👑 VIP</h1>
  <div class="card" id="vipStatus" style="text-align:center;">
    <div class="muted">Holat yuklanmoqda...</div>
  </div>
  <h2>Rejalar</h2>
  <div id="vipPlans"></div>
  <p class="muted" style="padding:10px 18px;">VIP imtiyozlari: kunlik olmos bonusi, profilda 👑 belgi, premium qo'llab-quvvatlash.</p>
</div>

<!-- LEADERBOARD -->
<div class="page" id="p-top">
  <h1>🏆 Reyting</h1>
  <div class="podium" id="podium"></div>
  <div id="lbList"><div class="muted" style="padding:16px;">Yuklanmoqda...</div></div>
</div>

<!-- PROFILE -->
<div class="page" id="p-profile">
  <h1>👤 Profil</h1>
  <div class="card" style="text-align:center;">
    <img class="avatar" id="pAvatar" style="width:84px;height:84px;margin:0 auto 10px;" src="">
    <h3 id="pName" style="font-size:19px;"><span id="pVip"></span>Mehmon</h3>
    <div class="muted">#<span id="pRank">-</span></div>
    <div class="row" style="gap:10px;margin-top:12px;">
      <div style="flex:1;background:rgba(118,118,128,.1);border-radius:12px;padding:10px;">
        <div style="font-weight:800;font-size:20px;">🌐 <span id="pRating">1000</span></div>
        <div class="muted">Online reyting</div>
      </div>
      <div style="flex:1;background:rgba(118,118,128,.1);border-radius:12px;padding:10px;">
        <div style="font-weight:800;font-size:20px;">🤖 <span id="pBotRating">1000</span></div>
        <div class="muted">Bot reyting</div>
      </div>
    </div>
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
  <div class="row" style="padding:8px 16px;gap:10px;">
    <button class="btn sec" onclick="toggleTheme()">🌙 Tema</button>
    <button class="btn sec" id="sndBtn" onclick="toggleSound()">🔊 Ovoz</button>
  </div>
</div>

<div class="nav">
  <a class="on" data-p="home" onclick="nav('home')"><span class="i">🏠</span>Bosh</a>
  <a data-p="shop" onclick="nav('shop')"><span class="i">🏪</span>Do'kon</a>
  <a data-p="bp" onclick="nav('bp')"><span class="i">🎟</span>Pass</a>
  <a data-p="top" onclick="nav('top')"><span class="i">🏆</span>Reyting</a>
  <a data-p="profile" onclick="nav('profile')"><span class="i">👤</span>Profil</a>
</div>

<div class="ov" id="ov"><div class="result" id="resultCard"></div></div>
<div class="toast" id="toast"></div>

<script>var API='<?php echo $apiBase; ?>'; var JOIN_CODE='<?php echo $joinCode; ?>';</script>
<script src="./game.js?v=<?php echo $ver; ?>"></script>
</body>
</html>
