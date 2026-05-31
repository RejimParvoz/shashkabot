/* Shashka Lite - O'yin mantig'i (Telegram avto-login + bot AI) */
'use strict';

var tg = window.Telegram ? window.Telegram.WebApp : null;
var USER = null;
var INIT_DATA = tg ? tg.initData : '';
var START_PARAM = (tg && tg.initDataUnsafe) ? (tg.initDataUnsafe.start_param || '') : '';
var botLevel = 'medium';
var gameMode = 'classic';

// Rejim: 'bot' yoki 'online'
var MODE = 'bot';
var MATCH_ID = null;
var MY_COLOR = 2;      // online: 2=oq(p1), 1=qora(p2)
var FLIP = false;      // qora o'yinchi uchun doskani aylantirish
var POLL = null;       // online holatni so'rab turuvchi timer

// Skin ranglari (install.php bilan bir xil kodlar)
var BOARD_THEMES = {
  board_classic: ['#f0d9b5', '#b58863'],
  board_green: ['#eeeed2', '#769656'],
  board_blue: ['#dee3e6', '#8ca2ad'],
  board_dark: ['#b0b0b0', '#4a4a4a'],
  board_purple: ['#e6d8f0', '#7a5ba6']
};
var PIECE_THEMES = {
  piece_classic: ['#ffffff', '#cfcfcf', '#666666', '#161616'],
  piece_gold: ['#fff6d0', '#e0b830', '#7a5c00', '#3a2c00'],
  piece_red: ['#ffd6d6', '#d83030', '#5a0000', '#2a0000'],
  piece_ocean: ['#d6f0ff', '#2090d8', '#003a5a', '#001a2a'],
  piece_neon: ['#e0ffe0', '#30d830', '#2a005a', '#10002a']
};

function boardTheme() {
  var code = (USER && USER.equipped_board) ? USER.equipped_board : 'board_classic';
  return BOARD_THEMES[code] || BOARD_THEMES.board_classic;
}
function pieceTheme() {
  var code = (USER && USER.equipped_piece) ? USER.equipped_piece : 'piece_classic';
  return PIECE_THEMES[code] || PIECE_THEMES.piece_classic;
}

/* ---------- API ---------- */
function api(action, body) {
  body = body || {};
  body.init_data = INIT_DATA;
  if (START_PARAM) body.start_param = START_PARAM;
  return fetch(API + '?action=' + action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  }).then(function (r) { return r.json(); });
}

/* ---------- UI yordamchilari ---------- */
function nav(page) {
  document.querySelectorAll('.page').forEach(function (p) { p.classList.remove('active'); });
  var el = document.getElementById('p-' + page);
  if (el) el.classList.add('active');
  document.querySelectorAll('.nav a').forEach(function (a) {
    a.classList.toggle('on', a.getAttribute('data-p') === page);
  });
  if (page === 'top') loadLeaderboard();
  if (page === 'profile') loadProfile();
  if (page === 'shop') loadShop();
  if (page === 'bp') loadBP();
  if (page === 'tour') loadTour();
  if (page === 'vip') loadVip();
  if (typeof SND !== 'undefined') SND.play('tap');
}

function toast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function () { t.classList.remove('show'); }, 2500);
}

function toggleTheme() {
  var cur = document.documentElement.getAttribute('data-theme');
  var next = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
}

function setLevel(lvl, el) {
  botLevel = lvl;
  document.querySelectorAll('#levelSeg button').forEach(function (b) { b.classList.remove('on'); });
  if (el) el.classList.add('on');
}

function renderUser() {
  if (!USER) return;
  document.getElementById('uName').textContent = USER.first_name || 'Mehmon';
  document.getElementById('uRating').textContent = USER.rating;
  document.getElementById('uCoins').textContent = USER.coins;
  var dEl = document.getElementById('uDiamonds');
  if (dEl) dEl.textContent = USER.diamonds || 0;
  if (USER.photo_url) {
    document.getElementById('uAvatar').src = USER.photo_url;
    document.getElementById('pAvatar').src = USER.photo_url;
  }
}

/* ---------- Avto-login ---------- */
function init() {
  if (tg) { tg.ready(); tg.expand(); }
  var savedTheme = localStorage.getItem('theme');
  if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme);
  else if (tg && tg.colorScheme) document.documentElement.setAttribute('data-theme', tg.colorScheme);

  api('auth', {}).then(function (res) {
    if (res.success) {
      USER = res.user;
      if (res.bot_username) { INVITE_BOT = res.bot_username; }
      renderUser();
      var code = (typeof JOIN_CODE !== 'undefined' && JOIN_CODE) ? JOIN_CODE : '';
      if (!code && START_PARAM && START_PARAM.indexOf('match_') === 0) {
        code = START_PARAM.substring(6);
      }
      if (code) { setTimeout(function () { joinByCode(code); }, 300); }
    } else {
      toast('Avtorizatsiya: ' + (res.error || 'xato'));
    }
    hideLoader();
  }).catch(function () {
    hideLoader();
    toast('Serverga ulanib bolmadi');
  });
}

function hideLoader() {
  var el = document.getElementById('loader');
  if (!el) return;
  el.classList.add('hide');
  setTimeout(function () { el.style.display = 'none'; }, 450);
}

function loadProfile() {
  api('profile', {}).then(function (res) {
    if (!res.success) return;
    var p = res.profile;
    var crown = p.is_vip ? '👑 ' : '';
    document.getElementById('pName').textContent = crown + (p.first_name || 'Mehmon');
    document.getElementById('pRating').textContent = p.rating;
    document.getElementById('pBotRating').textContent = p.bot_rating;
    document.getElementById('pRank').textContent = p.rank;
    document.getElementById('pGames').textContent = p.total_games;
    document.getElementById('pWins').textContent = p.wins;
    document.getElementById('pLosses').textContent = p.losses;
    document.getElementById('pDraws').textContent = p.draws;
    document.getElementById('pWinRate').textContent = p.win_rate + '%';
    document.getElementById('pCoins').textContent = p.coins;
    document.getElementById('pRefs').textContent = p.referral_count || 0;
    if (p.photo_url) document.getElementById('pAvatar').src = p.photo_url;
    USER.equipped_board = p.equipped_board;
    USER.equipped_piece = p.equipped_piece;
    USER.coins = p.coins;
    USER.diamonds = p.diamonds;
    renderUser();
  });
}

function loadLeaderboard() {
  fetch(API + '?action=leaderboard').then(function (r) { return r.json(); }).then(function (res) {
    if (!res.success) return;
    var lb = res.leaderboard;
    // Podium (top 3): 2 chapda, 1 markazda(baland), 3 o'ngda
    var podium = document.getElementById('podium');
    podium.innerHTML = '';
    var order = [{ i: 1, c: 'pod2' }, { i: 0, c: 'pod1' }, { i: 2, c: 'pod3' }];
    order.forEach(function (o) {
      var u = lb[o.i];
      if (!u) return;
      var av = u.photo_url || '';
      podium.innerHTML +=
        '<div class="pod ' + o.c + '" onclick="showUserStats(' + u.telegram_id + ')">' +
        '<img class="av" src="' + av + '" onerror="this.style.visibility=\'hidden\'">' +
        '<div class="nm">' + escapeHtml(u.first_name || u.username || 'Player') + '</div>' +
        '<div class="rt">⭐' + u.rating + '</div>' +
        '<div class="base">' + (o.i + 1) + '</div></div>';
    });

    // Qolganlari (4+)
    var html = '';
    lb.forEach(function (u, idx) {
      if (idx < 3) return;
      html += '<div class="lb" onclick="showUserStats(' + u.telegram_id + ')">' +
        '<div class="r">' + u.rank + '</div>' +
        '<div class="nm">' + escapeHtml(u.first_name || u.username || 'Player') + '</div>' +
        '<div class="rt">' + u.rating + '</div></div>';
    });
    document.getElementById('lbList').innerHTML = html;
  });
}

/* O'yinchi statistikasi modali */
function showUserStats(tgid) {
  api('user_stats', { telegram_id: tgid }).then(function (res) {
    if (!res.success) { toast('Topilmadi'); return; }
    var p = res.profile;
    var av = p.photo_url || '';
    var html =
      '<img class="avatar" style="width:80px;height:80px;margin:0 auto 10px;" src="' + av + '" onerror="this.style.visibility=\'hidden\'">' +
      '<h2 style="padding:0;">' + escapeHtml(p.first_name || 'Player') + '</h2>' +
      '<div class="muted" style="margin-bottom:14px;">⭐ ' + p.rating + ' • #' + p.rank + '</div>' +
      '<div style="text-align:left;">' +
      statRow('🎮 O\'yinlar', p.total_games) +
      statRow('✅ G\'alaba', p.wins) +
      statRow('❌ Mag\'lubiyat', p.losses) +
      statRow('🤝 Durang', p.draws) +
      statRow('📊 G\'alaba %', p.win_rate + '%') +
      statRow('🎟 BP daraja', p.bp_level) +
      '</div>' +
      '<button class="btn grad" style="margin-top:16px;" onclick="closeResult()">Yopish</button>';
    document.getElementById('resultCard').innerHTML = html;
    document.getElementById('ov').classList.add('show');
  });
}

function statRow(label, val) {
  return '<div class="stat"><span>' + label + '</span><b>' + val + '</b></div>';
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

document.addEventListener('DOMContentLoaded', init);


/* ================= SHASHKA DVIGATELI ================= */
/* 0=bosh, 1=qora(bot), 2=oq(siz), 3=qora dama, 4=oq dama */

var BOARD = [];
var SELECTED = null;      // {r,c}
var LEGAL = [];           // joriy tanlangan tosh uchun yurishlar
var MY_TURN = true;
var MOVES = 0;
var GAME_OVER = false;
var canvas, ctx, CELL;

function newBoard() {
  var b = [];
  for (var r = 0; r < 8; r++) {
    b[r] = [];
    for (var c = 0; c < 8; c++) {
      var dark = (r + c) % 2 === 1;
      if (dark && r < 3) b[r][c] = 1;        // bot (qora) tepada
      else if (dark && r > 4) b[r][c] = 2;   // siz (oq) pastda
      else b[r][c] = 0;
    }
  }
  return b;
}

function startGame(mode) {
  if (!USER) { toast('Avval avtorizatsiya kuting'); return; }
  stopPoll();
  MODE = 'bot';
  FLIP = false;
  MY_COLOR = 2;
  gameMode = mode;
  BOARD = newBoard();
  SELECTED = null; LEGAL = []; MY_TURN = true; MOVES = 0; GAME_OVER = false;
  var names = { easy: 'Oson', medium: "O'rta", hard: 'Qiyin', expert: 'Ekspert' };
  document.getElementById('oppName').textContent = '🤖 Bot (' + names[botLevel] + ')';
  document.getElementById('meName').textContent = '😎 Siz';
  document.getElementById('oppDot').classList.remove('on');
  document.getElementById('drawBtn').classList.add('hidden');
  document.getElementById('chatBtn').classList.add('hidden');
  document.getElementById('chatPanel').classList.add('hidden');
  nav('game');
  setupCanvas();
  render();
  updateCounts();
  setTurnText();
}

function setupCanvas() {
  canvas = document.getElementById('board');
  ctx = canvas.getContext('2d');
  var rect = canvas.getBoundingClientRect();
  var size = Math.min(rect.width || 420, 420);
  var dpr = window.devicePixelRatio || 1;
  canvas.width = size * dpr;
  canvas.height = size * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  CELL = size / 8;
  canvas.onclick = function (e) { handleClick(e.clientX, e.clientY); };
  canvas.ontouchstart = function (e) {
    e.preventDefault();
    var t = e.touches[0];
    handleClick(t.clientX, t.clientY);
  };
}

function dispRC(r, c) { return FLIP ? { r: 7 - r, c: 7 - c } : { r: r, c: c }; }

function render() {
  var size = CELL * 8;
  ctx.clearRect(0, 0, size, size);
  var bt = boardTheme();   // [light, dark]
  for (var r = 0; r < 8; r++) {
    for (var c = 0; c < 8; c++) {
      var dark = (r + c) % 2 === 1;
      ctx.fillStyle = dark ? bt[1] : bt[0];
      ctx.fillRect(c * CELL, r * CELL, CELL, CELL);
    }
  }
  // tanlangan + yurishlar (display koordinatada)
  if (SELECTED) {
    var ds = dispRC(SELECTED.r, SELECTED.c);
    ctx.fillStyle = 'rgba(10,132,255,.38)';
    ctx.fillRect(ds.c * CELL, ds.r * CELL, CELL, CELL);
    LEGAL.forEach(function (m) {
      var dt = dispRC(m.to[0], m.to[1]);
      ctx.fillStyle = m.captures.length ? 'rgba(255,59,48,.75)' : 'rgba(52,199,89,.75)';
      ctx.beginPath();
      ctx.arc(dt.c * CELL + CELL / 2, dt.r * CELL + CELL / 2, CELL * 0.17, 0, 7);
      ctx.fill();
    });
  }
  // toshlar
  for (var rr = 0; rr < 8; rr++) {
    for (var cc = 0; cc < 8; cc++) {
      if (BOARD[rr][cc]) drawPiece(rr, cc, BOARD[rr][cc]);
    }
  }
}

function getColor(v) {
  return getComputedStyle(document.documentElement).getPropertyValue(v).trim() || '#ccc';
}

function drawPiece(r, c, p) {
  var d = dispRC(r, c);
  var x = d.c * CELL + CELL / 2, y = d.r * CELL + CELL / 2, rad = CELL * 0.36;
  var white = (p === 2 || p === 4), king = (p === 3 || p === 4);
  var th = pieceTheme(); // [oqG1, oqG2, qoraG1, qoraG2]
  var premiumPieces = ['piece_diamond', 'piece_ruby', 'piece_royal'];
  var isPrem = USER && premiumPieces.indexOf(USER.equipped_piece) >= 0;

  // soya
  ctx.beginPath(); ctx.arc(x, y + 2, rad, 0, 7); ctx.fillStyle = 'rgba(0,0,0,.25)'; ctx.fill();

  // premium porlash (glow)
  if (isPrem) {
    ctx.save();
    ctx.shadowColor = white ? th[1] : th[2];
    ctx.shadowBlur = CELL * 0.22;
    ctx.beginPath(); ctx.arc(x, y, rad, 0, 7); ctx.fillStyle = white ? th[1] : th[2]; ctx.fill();
    ctx.restore();
  }

  var g = ctx.createRadialGradient(x - rad / 3, y - rad / 3, rad / 4, x, y, rad);
  if (white) { g.addColorStop(0, th[0]); g.addColorStop(1, th[1]); }
  else { g.addColorStop(0, th[2]); g.addColorStop(1, th[3]); }
  ctx.beginPath(); ctx.arc(x, y, rad, 0, 7); ctx.fillStyle = g; ctx.fill();
  ctx.lineWidth = 2; ctx.strokeStyle = white ? 'rgba(0,0,0,.25)' : 'rgba(255,255,255,.25)'; ctx.stroke();

  // yaltiroq nuqta (premium his)
  ctx.beginPath();
  ctx.arc(x - rad * 0.32, y - rad * 0.32, rad * 0.28, 0, 7);
  ctx.fillStyle = 'rgba(255,255,255,0.35)';
  ctx.fill();

  if (king) {
    ctx.fillStyle = '#ffd60a';
    ctx.font = (rad * 1.15) + 'px serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('\u265B', x, y);
  }
}

function handleClick(clientX, clientY) {
  if (!MY_TURN || GAME_OVER) return;
  var rect = canvas.getBoundingClientRect();
  var dc = Math.floor((clientX - rect.left) / CELL);
  var dr = Math.floor((clientY - rect.top) / CELL);
  if (dr < 0 || dr > 7 || dc < 0 || dc > 7) return;
  // display -> board koordinata (flip bo'lsa teskari)
  var r = FLIP ? 7 - dr : dr;
  var c = FLIP ? 7 - dc : dc;

  // yurish nishoniga bosilsa
  if (SELECTED) {
    var mv = LEGAL.find(function (m) { return m.to[0] === r && m.to[1] === c; });
    if (mv) { doMove(mv, true); return; }
  }
  // o'z toshini tanlash (online: MY_COLOR, bot: oq=2)
  var myColor = (MODE === 'online') ? MY_COLOR : 2;
  var mine = (myColor === 2) ? (BOARD[r][c] === 2 || BOARD[r][c] === 4) : (BOARD[r][c] === 1 || BOARD[r][c] === 3);
  if (mine) {
    var all = getMoves(BOARD, myColor);
    var forPiece = all.filter(function (m) { return m.from[0] === r && m.from[1] === c; });
    if (forPiece.length) { SELECTED = { r: r, c: c }; LEGAL = forPiece; render(); }
    else toast('Bu tosh yura olmaydi (majburiy yutish bor)');
  }
}


/* ---------- Yurishlarni hisoblash (majburiy yutish bilan) ---------- */
function isOwn(p, white) { return white ? (p === 2 || p === 4) : (p === 1 || p === 3); }
function isOpp(p, white) { return white ? (p === 1 || p === 3) : (p === 2 || p === 4); }
function inB(r, c) { return r >= 0 && r < 8 && c >= 0 && c < 8; }

var ALL_DIRS = [[-1, -1], [-1, 1], [1, -1], [1, 1]];

/* Oddiy tosh YURISH yo'nalishlari (faqat oldinga). Dama hamma tomonga. */
function pieceDirs(p) {
  var king = (p === 3 || p === 4);
  if (king) return ALL_DIRS;
  if (p === 2) return [[-1, -1], [-1, 1]];   // oq tepaga (oldinga)
  return [[1, -1], [1, 1]];                  // qora pastga (oldinga)
}

/*
 * Bitta tosh uchun yutib olish ketma-ketliklari (ko'p sakrash).
 * O'zbek/Rus shashka qoidalari:
 *   - Oddiy tosh OLDINGA va ORQAGA yutadi (4 tomon).
 *   - Dama (uchuvchi) diagonal bo'ylab istalgan masofadan yutadi.
 */
function captureSeqs(board, r, c, p) {
  var white = (p === 2 || p === 4);
  var king = (p === 3 || p === 4);
  var res = [];

  if (!king) {
    // ---- ODDIY TOSH: 4 tomonga 1 katak sakrab yutish ----
    for (var i = 0; i < 4; i++) {
      var dr = ALL_DIRS[i][0], dc = ALL_DIRS[i][1];
      var mr = r + dr, mc = c + dc;          // yutiladigan katak
      var tr = r + dr * 2, tc = c + dc * 2;  // qo'niladigan katak
      if (inB(tr, tc) && board[tr][tc] === 0 && inB(mr, mc) && isOpp(board[mr][mc], white)) {
        var nb = cloneBoard(board);
        nb[tr][tc] = nb[r][c]; nb[r][c] = 0; nb[mr][mc] = 0;
        // damaga aylansa, davomini DAMA sifatida yutadi
        maybePromote(nb, tr, tc);
        var further = captureSeqs(nb, tr, tc, nb[tr][tc]);
        if (further.length === 0) {
          res.push({ from: [r, c], to: [tr, tc], captures: [[mr, mc]] });
        } else {
          further.forEach(function (f) {
            res.push({ from: [r, c], to: f.to, captures: [[mr, mc]].concat(f.captures) });
          });
        }
      }
    }
  } else {
    // ---- DAMA (uchuvchi): diagonal bo'ylab uzoqdan yutish ----
    for (var d = 0; d < 4; d++) {
      var ddr = ALL_DIRS[d][0], ddc = ALL_DIRS[d][1];
      var rr = r + ddr, cc = c + ddc;
      // bo'sh kataklardan o'tib, birinchi toshgacha boramiz
      while (inB(rr, cc) && board[rr][cc] === 0) { rr += ddr; cc += ddc; }
      if (!inB(rr, cc)) continue;
      if (isOwn(board[rr][cc], white)) continue;   // o'z toshi to'sib turibdi
      // (rr,cc) da raqib toshi bor — orqasidagi bo'sh kataklarga qo'nish mumkin
      var capR = rr, capC = cc;
      var lr = capR + ddr, lc = capC + ddc;
      while (inB(lr, lc) && board[lr][lc] === 0) {
        var nb2 = cloneBoard(board);
        nb2[lr][lc] = nb2[r][c]; nb2[r][c] = 0; nb2[capR][capC] = 0;
        var further2 = captureSeqs(nb2, lr, lc, nb2[lr][lc]);
        if (further2.length === 0) {
          res.push({ from: [r, c], to: [lr, lc], captures: [[capR, capC]] });
        } else {
          further2.forEach(function (f) {
            res.push({ from: [r, c], to: f.to, captures: [[capR, capC]].concat(f.captures) });
          });
        }
        lr += ddr; lc += ddc;
      }
    }
  }
  return res;
}

function getMoves(board, player) {
  // player: 2=oq(siz), 1=qora(bot)
  var white = (player === 2);
  var captures = [], simple = [];
  for (var r = 0; r < 8; r++) {
    for (var c = 0; c < 8; c++) {
      var p = board[r][c];
      if (!isOwn(p, white)) continue;

      var seqs = captureSeqs(board, r, c, p);
      for (var s = 0; s < seqs.length; s++) captures.push(seqs[s]);

      if (seqs.length === 0) {
        var king = (p === 3 || p === 4);
        if (king) {
          // DAMA: diagonal bo'ylab istalgan masofa (uchuvchi)
          for (var k = 0; k < 4; k++) {
            var kr = r + ALL_DIRS[k][0], kc = c + ALL_DIRS[k][1];
            while (inB(kr, kc) && board[kr][kc] === 0) {
              simple.push({ from: [r, c], to: [kr, kc], captures: [] });
              kr += ALL_DIRS[k][0]; kc += ALL_DIRS[k][1];
            }
          }
        } else {
          // ODDIY TOSH: oldinga 1 katak
          var dirs = pieceDirs(p);
          for (var i = 0; i < dirs.length; i++) {
            var nr = r + dirs[i][0], nc = c + dirs[i][1];
            if (inB(nr, nc) && board[nr][nc] === 0) {
              simple.push({ from: [r, c], to: [nr, nc], captures: [] });
            }
          }
        }
      }
    }
  }
  return captures.length ? captures : simple;  // majburiy yutish
}

function maybePromote(board, r, c) {
  if (board[r][c] === 2 && r === 0) { board[r][c] = 4; return true; }
  if (board[r][c] === 1 && r === 7) { board[r][c] = 3; return true; }
  return false;
}

function cloneBoard(b) {
  var n = [];
  for (var r = 0; r < 8; r++) n[r] = b[r].slice();
  return n;
}

function applyMove(board, mv) {
  var nb = cloneBoard(board);
  var p = nb[mv.from[0]][mv.from[1]];
  nb[mv.from[0]][mv.from[1]] = 0;
  mv.captures.forEach(function (cap) { nb[cap[0]][cap[1]] = 0; });
  nb[mv.to[0]][mv.to[1]] = p;
  maybePromote(nb, mv.to[0], mv.to[1]);
  return nb;
}

/* ---------- Yurishni bajarish ---------- */
function doMove(mv, isHuman) {
  // ONLINE rejim: serverga yuboramiz
  if (MODE === 'online' && isHuman) {
    sendOnlineMove(mv);
    return;
  }

  BOARD = applyMove(BOARD, mv);
  MOVES++;
  SELECTED = null; LEGAL = [];
  render();
  updateCounts();
  if (typeof SND !== 'undefined') SND.play(mv.captures && mv.captures.length ? 'capture' : 'move');

  var over = checkOver();
  if (over) { finish(over); return; }

  if (isHuman) {
    MY_TURN = false;
    setTurnText();
    setTimeout(botMove, 450);
  } else {
    MY_TURN = true;
    setTurnText();
  }
}

function updateCounts() {
  var mine = 0, opp = 0;
  var myWhite = (MODE === 'online') ? (MY_COLOR === 2) : true;
  for (var r = 0; r < 8; r++) for (var c = 0; c < 8; c++) {
    var p = BOARD[r][c];
    var isW = (p === 2 || p === 4), isB = (p === 1 || p === 3);
    if (myWhite) { if (isW) mine++; if (isB) opp++; }
    else { if (isB) mine++; if (isW) opp++; }
  }
  document.getElementById('myCount').textContent = mine;
  document.getElementById('oppCount').textContent = opp;
}

function setTurnText() {
  var el = document.getElementById('turn');
  if (MY_TURN) { el.textContent = 'Sizning navbatingiz'; }
  else { el.textContent = (MODE === 'online') ? "Raqib o'ylayapti..." : "Bot o'ylayapti..."; }
  document.getElementById('meBar').classList.toggle('act', MY_TURN);
  document.getElementById('oppBar').classList.toggle('act', !MY_TURN);
}

function checkOver() {
  // bot rejimi uchun (men = oq)
  var myMoves = getMoves(BOARD, 2).length;
  var botMoves = getMoves(BOARD, 1).length;
  if (myMoves === 0) return 'loss';
  if (botMoves === 0) return 'win';
  if (MOVES > 200) return 'draw';
  return null;
}


/* ---------- BOT AI (Minimax + Alpha-Beta) ---------- */
function botMove() {
  if (GAME_OVER) return;
  var moves = getMoves(BOARD, 1);
  if (!moves.length) { finish('win'); return; }

  var depth = { easy: 1, medium: 3, hard: 5, expert: 7 }[botLevel] || 3;
  var best = null, bestVal = -Infinity;

  // Oson darajada ba'zan tasodifiy yuradi
  if (botLevel === 'easy' && Math.random() < 0.35) {
    best = moves[Math.floor(Math.random() * moves.length)];
  } else {
    for (var i = 0; i < moves.length; i++) {
      var nb = applyMove(BOARD, moves[i]);
      var val = minimax(nb, depth - 1, -Infinity, Infinity, false);
      if (val > bestVal) { bestVal = val; best = moves[i]; }
    }
  }
  if (!best) best = moves[0];
  doMove(best, false);
}

function minimax(board, depth, alpha, beta, botTurn) {
  if (depth === 0) return evaluate(board);
  var player = botTurn ? 1 : 2;
  var moves = getMoves(board, player);
  if (!moves.length) return botTurn ? -10000 : 10000;

  if (botTurn) {
    var best = -Infinity;
    for (var i = 0; i < moves.length; i++) {
      var v = minimax(applyMove(board, moves[i]), depth - 1, alpha, beta, false);
      if (v > best) best = v;
      if (best > alpha) alpha = best;
      if (beta <= alpha) break;
    }
    return best;
  } else {
    var bestM = Infinity;
    for (var j = 0; j < moves.length; j++) {
      var v2 = minimax(applyMove(board, moves[j]), depth - 1, alpha, beta, true);
      if (v2 < bestM) bestM = v2;
      if (bestM < beta) beta = bestM;
      if (beta <= alpha) break;
    }
    return bestM;
  }
}

/* Baholash: bot (qora) musbat, siz (oq) manfiy */
function evaluate(board) {
  var score = 0;
  for (var r = 0; r < 8; r++) {
    for (var c = 0; c < 8; c++) {
      var p = board[r][c];
      if (p === 1) score += 10 + r;          // qora oddiy (pastga intilsa yaxshi)
      else if (p === 3) score += 25;         // qora dama
      else if (p === 2) score -= 10 + (7 - r);
      else if (p === 4) score -= 25;
    }
  }
  return score;
}

/* ---------- Yakun ---------- */
function resignCurrent() {
  if (GAME_OVER) return;
  if (!confirm("Taslim bo'lasizmi?")) return;
  if (MODE === 'online') { onlineResign(); }
  else { finish('loss'); }
}

function finish(result) {
  if (GAME_OVER) return;
  GAME_OVER = true;
  MY_TURN = false;

  api('save_result', {
    result: result, mode: gameMode, bot_level: botLevel, moves: MOVES
  }).then(function (res) {
    if (res.success) {
      USER = res.user;
      renderUser();
      showResult(result, res.rating_change, res.coins_earned);
    } else {
      showResult(result, 0, 0);
    }
  }).catch(function () { showResult(result, 0, 0); });
}

function showResult(result, ratingChange, coins) {
  if (typeof SND !== 'undefined') SND.play(result === 'win' ? 'win' : (result === 'draw' ? 'draw' : 'lose'));
  var emoji = result === 'win' ? '\uD83C\uDFC6' : (result === 'draw' ? '\uD83E\uDD1D' : '\uD83D\uDE14');
  var title = result === 'win' ? "G'alaba!" : (result === 'draw' ? 'Durang!' : "Mag'lubiyat");
  var rcCls = ratingChange >= 0 ? 'up' : 'down';
  var rcTxt = (ratingChange >= 0 ? '+' : '') + ratingChange;
  var html = '<div class="e">' + emoji + '</div>' +
    '<h2 style="padding:0;">' + title + '</h2>' +
    '<div class="rc ' + rcCls + '">Reyting: ' + rcTxt + '</div>' +
    '<div class="muted" style="margin:6px 0 18px;">+' + coins + ' \uD83E\uDE99 tanga</div>' +
    '<button class="btn grad" onclick="closeResult()">Davom etish</button>';
  document.getElementById('resultCard').innerHTML = html;
  document.getElementById('ov').classList.add('show');
}

function closeResult() {
  document.getElementById('ov').classList.remove('show');
  nav('home');
}


/* ==================== ONLINE O'YIN ==================== */
var ONLINE_MC = -1;            // oxirgi qo'llangan move_count
var ONLINE_RATING_BEFORE = 0;
var WAIT_POLL = null;

function findOnline() {
  if (!USER) { toast('Avtorizatsiya kuting'); return; }
  stopPoll();
  api('match_find', {}).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    MATCH_ID = res.match_id;
    if (res.status === 'active') {
      enterOnline();
    } else {
      showWaiting();
      WAIT_POLL = setInterval(checkWaiting, 2000);
    }
  }).catch(function () { toast('Serverga ulanib bo\'lmadi'); });
}

function showWaiting() {
  var html = '<div class="e">⏳</div><h2 style="padding:0;">Raqib qidirilmoqda...</h2>' +
    '<div class="spin" style="margin:18px auto;"></div>' +
    '<button class="btn sec" onclick="cancelWaiting()">Bekor qilish</button>';
  document.getElementById('resultCard').innerHTML = html;
  document.getElementById('ov').classList.add('show');
}

function checkWaiting() {
  api('match_state', { match_id: MATCH_ID }).then(function (s) {
    if (s.success && s.status === 'active') {
      clearInterval(WAIT_POLL); WAIT_POLL = null;
      document.getElementById('ov').classList.remove('show');
      enterOnline();
    }
  });
}

function cancelWaiting() {
  if (WAIT_POLL) { clearInterval(WAIT_POLL); WAIT_POLL = null; }
  api('match_cancel', { match_id: MATCH_ID });
  document.getElementById('ov').classList.remove('show');
  MATCH_ID = null;
}

function enterOnline() {
  MODE = 'online';
  GAME_OVER = false;
  SELECTED = null; LEGAL = [];
  ONLINE_MC = -1;
  ONLINE_RATING_BEFORE = USER ? USER.rating : 1000;
  api('match_state', { match_id: MATCH_ID }).then(function (s) {
    if (!s.success) { toast('O\'yin holati xato'); return; }
    MY_COLOR = s.my_color;        // 2=oq(p1, pastda), 1=qora(p2, tepada)
    FLIP = (MY_COLOR === 1);       // qora o'yinchi doskani aylantiradi
    var oppName = s.opponent ? (s.opponent.first_name || 'Raqib') : 'Raqib';
    document.getElementById('oppName').textContent = '👤 ' + oppName;
    document.getElementById('meName').textContent = '😎 Siz';
    document.getElementById('oppDot').classList.add('on');
    // chat va durang tugmalarini ko'rsatish
    document.getElementById('drawBtn').classList.remove('hidden');
    document.getElementById('chatBtn').classList.remove('hidden');
    document.getElementById('chatPanel').classList.add('hidden');
    document.getElementById('chatMsgs').innerHTML = '';
    CHAT_LAST = 0; DRAW_SHOWN = false;
    nav('game');
    setupCanvas();
    applyOnline(s);
    startPoll();
  });
}

function startPoll() { stopPoll(); POLL = setInterval(pollOnline, 1500); }
function stopPoll() { if (POLL) { clearInterval(POLL); POLL = null; } }

function pollOnline() {
  if (MODE !== 'online' || GAME_OVER) { stopPoll(); return; }
  api('match_state', { match_id: MATCH_ID }).then(function (s) {
    if (!s.success) return;
    pollChat();
    if (s.status === 'finished') {
      applyOnline(s);
      onlineFinished(s);
    } else {
      if (typeof s.draw_offer !== 'undefined') checkDrawOffer(s.draw_offer);
      if (s.move_count !== ONLINE_MC) {
        applyOnline(s);
        if (typeof SND !== 'undefined') SND.play('move');
      }
    }
  });
}

function applyOnline(s) {
  ONLINE_MC = s.move_count;
  BOARD = s.board;
  MY_TURN = (s.status === 'active' && s.turn === MY_COLOR);
  SELECTED = null; LEGAL = [];
  render();
  updateCounts();
  setTurnText();
}

function sendOnlineMove(mv) {
  MY_TURN = false;           // ikki marta yurishni oldini olish
  SELECTED = null; LEGAL = [];
  api('match_move', { match_id: MATCH_ID, from: mv.from, to: mv.to }).then(function (res) {
    if (!res.success) {
      toast(res.error || 'Yurish xato');
      // serverdan to'g'ri holatni qayta olamiz
      pollOnline();
      return;
    }
    BOARD = res.board;
    ONLINE_MC++;
    render();
    updateCounts();
    if (typeof SND !== 'undefined') SND.play(mv.captures && mv.captures.length ? 'capture' : 'move');
    if (res.finished) {
      api('match_state', { match_id: MATCH_ID }).then(onlineFinished);
    } else {
      MY_TURN = false;
      setTurnText();
    }
  }).catch(function () { toast('Server xato'); pollOnline(); });
}

function onlineResign() {
  api('match_resign', { match_id: MATCH_ID }).then(function () {
    GAME_OVER = true; stopPoll();
    showResultOnline('loss');
  });
}

function onlineFinished(s) {
  if (GAME_OVER) return;
  GAME_OVER = true;
  stopPoll();
  var result;
  if (!s.winner_id) result = 'draw';
  else result = (s.winner_id === USER.id) ? 'win' : 'loss';
  showResultOnline(result);
}

function showResultOnline(result) {
  // reyting o'zgarishini olish uchun profilni yangilaymiz
  api('auth', {}).then(function (res) {
    var change = 0;
    if (res.success) {
      change = res.user.rating - ONLINE_RATING_BEFORE;
      USER = res.user;
      renderUser();
    }
    var coins = result === 'win' ? 25 : (result === 'draw' ? 10 : 5);
    showResult(result, change, coins);
  }).catch(function () {
    showResult(result, 0, 0);
  });
}

/* ==================== DO'KON (faqat tanga) ==================== */
var SHOP_ITEMS = [];
var SHOP_TAB = 'board';

function loadShop() {
  api('shop_list', {}).then(function (res) {
    if (!res.success) { return; }
    SHOP_ITEMS = res.items;
    if (USER) { USER.coins = res.coins; USER.diamonds = res.diamonds; renderUser(); }
    renderShop();
  });
}

function shopTab(t, el) {
  SHOP_TAB = t;
  document.querySelectorAll('#shopSeg button').forEach(function (b) { b.classList.remove('on'); });
  if (el) el.classList.add('on');
  renderShop();
}

function renderShop() {
  var grid = document.getElementById('shopGrid');
  if (SHOP_TAB === 'diamonds') { renderDiamondPacks(grid); return; }

  var items = SHOP_ITEMS.filter(function (it) {
    if (SHOP_TAB === 'premium') return it.premium === 1;
    return it.type === SHOP_TAB && it.premium !== 1;
  });
  if (!items.length) { grid.innerHTML = '<div class="muted" style="padding:16px;">Bo\'sh</div>'; return; }
  grid.innerHTML = items.map(function (it) {
    return '<div class="shop-item' + (it.premium ? ' prem' : '') + '">' +
      (it.equipped ? '<span class="tag eq">Tanlangan</span>' : (it.owned ? '<span class="tag">Bor</span>' : '')) +
      shopPreview(it) +
      '<div class="nm">' + escapeHtml(it.name) + '</div>' +
      shopBtn(it) +
      '</div>';
  }).join('');
}

function shopPreview(it) {
  var d = it.data.split(',');
  var prem = it.premium === 1;
  if (it.type === 'board') {
    // 4x4 mini doska (SVG)
    var l = '#' + d[0], dk = '#' + d[1], cells = '';
    for (var r = 0; r < 4; r++) for (var c = 0; c < 4; c++) {
      var dark = (r + c) % 2 === 1;
      cells += '<rect x="' + (c * 16) + '" y="' + (r * 16) + '" width="16" height="16" fill="' + (dark ? dk : l) + '"/>';
    }
    var star = prem ? '<text x="56" y="14" font-size="12">✦</text>' : '';
    return '<div class="shop-prev"><svg viewBox="0 0 64 64" width="70" height="70">' +
      '<defs><clipPath id="cb"><rect x="0" y="0" width="64" height="64" rx="10"/></clipPath></defs>' +
      '<g clip-path="url(#cb)">' + cells + '</g>' +
      '<rect x="0.5" y="0.5" width="63" height="63" rx="10" fill="none" stroke="rgba(0,0,0,.15)"/>' + star + '</svg></div>';
  }
  // toshlar (SVG): oq va qora doira + premium toj/yarq
  var uid = 'p' + Math.random().toString(36).substr(2, 5);
  var crown = prem ? '<text x="46" y="26" font-size="13" fill="#ffd60a">♛</text>' : '';
  return '<div class="shop-prev"><svg viewBox="0 0 64 40" width="84" height="54">' +
    '<defs>' +
    '<radialGradient id="w' + uid + '" cx="0.35" cy="0.3" r="0.8"><stop offset="0" stop-color="#' + d[0] + '"/><stop offset="1" stop-color="#' + d[1] + '"/></radialGradient>' +
    '<radialGradient id="b' + uid + '" cx="0.35" cy="0.3" r="0.8"><stop offset="0" stop-color="#' + d[2] + '"/><stop offset="1" stop-color="#' + d[3] + '"/></radialGradient>' +
    '</defs>' +
    '<circle cx="20" cy="20" r="15" fill="url(#w' + uid + ')" stroke="rgba(0,0,0,.2)"/>' +
    '<circle cx="44" cy="20" r="15" fill="url(#b' + uid + ')" stroke="rgba(255,255,255,.15)"/>' +
    crown + '</svg></div>';
}

function shopBtn(it) {
  if (it.equipped) return '<button class="btn sm sec" disabled style="width:100%;">✓ Tanlangan</button>';
  if (it.owned) return '<button class="btn sm" style="width:100%;" onclick="equipItem(\'' + it.code + '\')">Tanlash</button>';
  if (it.price_diamonds > 0) {
    return '<button class="btn sm grad" style="width:100%;" onclick="buyItem(\'' + it.code + '\')">' + it.price_diamonds + ' 💎</button>';
  }
  return '<button class="btn sm gold" style="width:100%;" onclick="buyItem(\'' + it.code + '\')">' + it.price_coins + ' 🪙</button>';
}

function buyItem(code) {
  api('shop_buy', { code: code }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    USER.coins = res.coins; USER.diamonds = res.diamonds;
    renderUser();
    toast('Sotib olindi! Endi tanlang.');
    loadShop();
  });
}

function equipItem(code) {
  api('shop_equip', { code: code }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    var it = SHOP_ITEMS.find(function (x) { return x.code === code; });
    if (it) {
      if (it.type === 'board') USER.equipped_board = code;
      else USER.equipped_piece = code;
    }
    toast('Tanlandi ✓');
    loadShop();
  });
}

/* ---------- Olmos sotib olish (Telegram Stars) ---------- */
function renderDiamondPacks(grid) {
  grid.innerHTML = '<div class="muted" style="padding:16px;">Yuklanmoqda...</div>';
  fetch(API + '?action=diamond_packs', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ init_data: INIT_DATA })
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (!res.success) { grid.innerHTML = '<div class="muted" style="padding:16px;">Xato</div>'; return; }
    grid.innerHTML = res.packs.map(function (p) {
      return '<div class="shop-item"><div class="shop-prev" style="font-size:34px;">💎</div>' +
        '<div class="nm">' + p.diamonds + ' olmos</div>' +
        '<button class="btn sm grad" style="width:100%;" onclick="buyDiamonds(\'' + p.id + '\')">' + p.stars + ' ⭐</button></div>';
    }).join('');
  });
}

function buyDiamonds(pack) {
  api('buy_diamonds', { pack: pack }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    if (tg && tg.openInvoice) {
      tg.openInvoice(res.invoice, function (status) {
        if (status === 'paid') { toast('To\'lov qabul qilindi! 💎'); setTimeout(function () { api('auth', {}).then(function (a) { if (a.success) { USER = a.user; renderUser(); loadShop(); } }); }, 1500); }
      });
    } else { toast('Telegram orqali oching'); }
  });
}

/* ==================== BATTLE PASS ==================== */
function loadBP() {
  api('bp_state', {}).then(function (res) {
    if (!res.success) { return; }
    if (USER) { USER.coins = res.coins; USER.diamonds = res.diamonds; USER.bp_premium = res.premium; renderUser(); }
    document.getElementById('bpLevelTxt').textContent = 'Daraja ' + res.level;
    document.getElementById('bpXpTxt').textContent = res.xp + ' XP';
    document.getElementById('bpPremBtn').style.display = res.premium ? 'none' : 'block';
    // progress: keyingi darajagacha
    var levels = res.levels;
    var nextXp = levels.length ? levels[levels.length - 1].xp_required : 100;
    for (var i = 0; i < levels.length; i++) { if (levels[i].level === res.level + 1) { nextXp = levels[i].xp_required; break; } }
    var pct = Math.min(100, Math.round(res.xp / nextXp * 100));
    document.getElementById('bpFill').style.width = pct + '%';

    document.getElementById('bpLevels').innerHTML = levels.map(function (l) {
      var lockCls = l.unlocked ? '' : ' lock';
      var free = bpReward(l.free_coins, l.free_diamonds, null, l.claimed_free, l.unlocked, l.level, 'free');
      var prem = bpReward(l.prem_coins, l.prem_diamonds, l.prem_item, l.claimed_prem, l.unlocked && res.premium, l.level, 'prem');
      return '<div class="bp-row"><div class="bp-lvl' + lockCls + '">' + l.level + '</div>' +
        '<div class="bp-rew"><div>' + free + '</div><div>' + prem + '</div></div></div>';
    }).join('');
  });
}

function bpReward(coins, diamonds, item, claimed, canClaim, level, track) {
  var parts = [];
  if (coins) parts.push(coins + '🪙');
  if (diamonds) parts.push(diamonds + '💎');
  if (item) parts.push('🎁');
  if (!parts.length) parts.push('—');
  var label = (track === 'prem' ? '⭐ ' : '') + parts.join(' ');
  var chip = '<span class="bp-chip' + (track === 'prem' ? ' prem' : '') + '">' + label + '</span>';
  if (claimed) return chip + ' ✅';
  if (canClaim && (coins || diamonds || item)) {
    return chip + ' <span class="bp-chip" style="cursor:pointer;color:var(--accent);" onclick="claimBP(' + level + ',\'' + track + '\')">Olish</span>';
  }
  return chip;
}

function claimBP(level, track) {
  api('bp_claim', { level: level, track: track }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    USER.coins = res.coins; USER.diamonds = res.diamonds;
    renderUser();
    var r = res.reward;
    toast('Olindi: ' + (r.coins ? r.coins + '🪙 ' : '') + (r.diamonds ? r.diamonds + '💎' : ''));
    loadBP();
  });
}

function buyPremium() {
  api('bp_buy_premium', {}).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    if (tg && tg.openInvoice) {
      tg.openInvoice(res.invoice, function (status) {
        if (status === 'paid') { toast('Premium ochildi! 🎟'); setTimeout(loadBP, 1500); }
      });
    } else { toast('Telegram orqali oching'); }
  });
}

/* ==================== DO'ST BILAN 1v1 ==================== */
function openFriend() {
  var html = '<h2 style="padding:0;">👥 Do\'st bilan 1v1</h2>' +
    '<p class="muted" style="margin:8px 0 16px;">Havola yarating va do\'stingizga yuboring. U havolaga kirsa, o\'yin boshlanadi.</p>' +
    '<button class="btn grad" onclick="createChallenge()">🔗 Havola yaratish</button>' +
    '<div style="margin:14px 0;text-align:center;" class="muted">yoki</div>' +
    '<input id="joinInput" placeholder="Kod kiriting" style="width:100%;padding:12px;border-radius:11px;border:1px solid var(--sep);background:var(--bg);color:var(--text);text-align:center;font-size:16px;text-transform:uppercase;">' +
    '<button class="btn sec" style="margin-top:10px;" onclick="joinByCode(document.getElementById(\'joinInput\').value)">Kirish</button>' +
    '<button class="btn sec" style="margin-top:10px;" onclick="closeResult()">Yopish</button>';
  document.getElementById('resultCard').innerHTML = html;
  document.getElementById('ov').classList.add('show');
}

function createChallenge() {
  api('challenge_create', {}).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    MATCH_ID = res.match_id;
    var bot = INVITE_BOT || '';
    var link = bot ? ('https://t.me/' + bot + '?start=match_' + res.code) : (location.origin + location.pathname + '?join=' + res.code);
    var text = "Shashka o'yiniga chaqiraman! 🎯 Kod: " + res.code;
    var share = 'https://t.me/share/url?url=' + encodeURIComponent(link) + '&text=' + encodeURIComponent(text);
    var html = '<h2 style="padding:0;">🔗 Havola tayyor</h2>' +
      '<div style="font-size:30px;font-weight:800;letter-spacing:3px;margin:14px 0;">' + res.code + '</div>' +
      '<button class="btn grad" onclick="shareLink(\'' + share.replace(/'/g, "%27") + '\')">📤 Do\'stga yuborish</button>' +
      '<p class="muted" style="margin-top:12px;">Do\'stingiz kirgach o\'yin avtomatik boshlanadi. Kuting...</p>' +
      '<div class="spin" style="margin:14px auto;"></div>';
    document.getElementById('resultCard').innerHTML = html;
    document.getElementById('ov').classList.add('show');
    // raqib kirishini kutamiz
    if (WAIT_POLL) clearInterval(WAIT_POLL);
    WAIT_POLL = setInterval(checkWaiting, 2000);
  });
}

function shareLink(url) {
  if (tg && tg.openTelegramLink) tg.openTelegramLink(url);
  else window.open(url, '_blank');
}

function joinByCode(code) {
  code = (code || '').trim().toUpperCase();
  if (!code) { toast('Kod kiriting'); return; }
  api('challenge_join', { code: code }).then(function (res) {
    if (!res.success) { toast(res.error || 'Topilmadi'); return; }
    MATCH_ID = res.match_id;
    document.getElementById('ov').classList.remove('show');
    enterOnline();
  });
}

/* ==================== DO'ST TAKLIF (referral) ==================== */
var INVITE_BOT = '';
function inviteFriend() {
  api('invite_info', {}).then(function (res) {
    if (!res.success) { toast('Xato'); return; }
    INVITE_BOT = res.bot_username || '';
    var refCode = 'ref_' + res.telegram_id;
    var shareUrl = INVITE_BOT
      ? ('https://t.me/' + INVITE_BOT + '?start=' + refCode)
      : (res.app_url + '?start=' + refCode);
    var text = "Men bilan Shashka o'ynaymizmi? 🎯 Sovg'a: 100🪙 olasiz!";
    var tgShare = 'https://t.me/share/url?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(text);
    if (tg && tg.openTelegramLink) tg.openTelegramLink(tgShare);
    else window.open(tgShare, '_blank');
  });
}



/* ==================== OVOZ EFFEKTLARI (Web Audio) ==================== */
var SND = {
  ctx: null,
  on: (localStorage.getItem('snd') !== '0'),
  ac: function () {
    if (!this.ctx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (AC) this.ctx = new AC();
    }
    return this.ctx;
  },
  beep: function (freq, dur, type, vol) {
    if (!this.on) return;
    var c = this.ac(); if (!c) return;
    var o = c.createOscillator(), g = c.createGain();
    o.type = type || 'sine'; o.frequency.value = freq;
    g.gain.setValueAtTime(vol || 0.12, c.currentTime);
    g.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + (dur || 0.15));
    o.connect(g); g.connect(c.destination);
    o.start(); o.stop(c.currentTime + (dur || 0.15));
  },
  play: function (kind) {
    if (!this.on) return;
    switch (kind) {
      case 'move': this.beep(330, 0.09, 'sine', 0.1); break;
      case 'capture': this.beep(180, 0.14, 'square', 0.12); setTimeout(function(){SND.beep(120,0.12,'square',0.1);},60); break;
      case 'tap': this.beep(520, 0.05, 'sine', 0.07); break;
      case 'chat': this.beep(660, 0.08, 'triangle', 0.09); break;
      case 'win': this.beep(523,0.12,'sine',0.13); setTimeout(function(){SND.beep(659,0.12,'sine',0.13);},120); setTimeout(function(){SND.beep(784,0.2,'sine',0.13);},240); break;
      case 'lose': this.beep(300,0.18,'sine',0.12); setTimeout(function(){SND.beep(200,0.3,'sine',0.12);},160); break;
      case 'draw': this.beep(440,0.12,'sine',0.1); setTimeout(function(){SND.beep(440,0.12,'sine',0.1);},150); break;
      case 'king': this.beep(700,0.1,'triangle',0.12); setTimeout(function(){SND.beep(950,0.14,'triangle',0.12);},90); break;
    }
  },
  toggle: function () { this.on = !this.on; localStorage.setItem('snd', this.on ? '1' : '0'); return this.on; }
};

/* ==================== TURNIRLAR ==================== */
var TOUR_TIMER = null;
function loadTour() {
  api('tournament_list', {}).then(function (res) {
    if (!res.success) return;
    var skew = Date.now() / 1000 - res.server_ts;
    var html = res.tournaments.map(function (t) {
      return '<div class="tcard" data-ends="' + t.ends_ts + '">' +
        '<div class="th"><div><div class="tt">' + tourIcon(t.type) + ' ' + t.title + '</div>' +
        '<div class="tm">' + t.players + ' ishtirokchi • tugashiga <span class="countdown" data-ends="' + t.ends_ts + '">--</span></div></div>' +
        (t.joined ? '<div class="tprize g1">Siz: ' + t.my_score + '</div>'
          : '<button class="btn sm grad" style="width:auto;" onclick="joinTour(' + t.id + ')">Qatnashish</button>') +
        '</div>' +
        '<div class="tprizes"><div class="tprize g1">🥇 ' + t.prize1 + '💎</div>' +
        '<div class="tprize g2">🥈 ' + t.prize2 + '💎</div><div class="tprize g3">🥉 ' + t.prize3 + '💎</div></div>' +
        '<div id="ttop' + t.id + '"></div>' +
        '<div class="tm" style="cursor:pointer;color:var(--accent);" onclick="loadTourTop(' + t.id + ')">Reytingni ko\'rish ▾</div>' +
        '</div>';
    }).join('');
    document.getElementById('tourList').innerHTML = html || '<div class="muted" style="padding:16px;">Turnir yo\'q</div>';
    startCountdowns(skew);
  });
}
function tourIcon(t) { return t === 'daily' ? '📅' : (t === 'weekly' ? '🗓️' : '🏆'); }
function joinTour(id) {
  SND.play('tap');
  api('tournament_join', { tournament_id: id }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    toast('Turnirga qo\'shildingiz! G\'alaba qozoning 🏆');
    loadTour();
  });
}
function loadTourTop(id) {
  api('tournament_top', { tournament_id: id }).then(function (res) {
    if (!res.success) return;
    var el = document.getElementById('ttop' + id);
    if (!res.top.length) { el.innerHTML = '<div class="tm">Hali ishtirokchi yo\'q</div>'; return; }
    el.innerHTML = res.top.map(function (u) {
      return '<div class="ttop"><div class="r">' + u.rank + '</div>' +
        '<div class="nm">' + escapeHtml(u.first_name || 'Player') + '</div>' +
        '<div class="sc">' + u.score + '</div></div>';
    }).join('');
  });
}
function startCountdowns(skew) {
  if (TOUR_TIMER) clearInterval(TOUR_TIMER);
  function tick() {
    var now = Date.now() / 1000 - (skew || 0);
    document.querySelectorAll('.countdown').forEach(function (el) {
      var left = Math.max(0, Math.floor(el.getAttribute('data-ends') - now));
      var d = Math.floor(left / 86400), h = Math.floor((left % 86400) / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
      el.textContent = (d > 0 ? d + 'k ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
    });
  }
  tick(); TOUR_TIMER = setInterval(tick, 1000);
}
function pad(n) { return n < 10 ? '0' + n : '' + n; }

/* ==================== VIP ==================== */
function loadVip() {
  api('vip_info', {}).then(function (res) {
    if (!res.success) return;
    var st = document.getElementById('vipStatus');
    if (res.is_vip) {
      st.innerHTML = '<div style="font-size:34px;">👑</div><h3 style="font-size:18px;">VIP ' + (res.level ? res.level.toUpperCase() : '') + '</h3>' +
        '<div class="muted">Tugaydi: ' + (res.until || '').substring(0, 10) + '</div>' +
        (res.can_claim ? '<button class="btn gold" style="margin-top:12px;" onclick="claimVip()">🎁 Kunlik olmosni olish</button>'
          : '<div class="muted" style="margin-top:10px;">Bugungi bonus olingan ✅</div>');
    } else {
      st.innerHTML = '<div style="font-size:34px;">👑</div><div class="muted">Sizda VIP yo\'q. Quyidan tanlang.</div>';
    }
    var plans = res.plans;
    var order = ['bronze', 'gold', 'platinum'];
    document.getElementById('vipPlans').innerHTML = order.map(function (k) {
      var p = plans[k]; if (!p) return '';
      return '<div class="vipcard ' + k + '"><div class="vt">' + crownFor(k) + ' VIP ' + p.name + '</div>' +
        '<div class="vd">Kunlik ' + p.daily + ' 💎 • 30 kun</div>' +
        '<button class="btn" style="background:rgba(0,0,0,.25);color:#fff;" onclick="buyVip(\'' + k + '\')">' + p.stars + ' ⭐ sotib olish</button></div>';
    }).join('');
  });
}
function crownFor(k) { return k === 'platinum' ? '💎' : (k === 'gold' ? '👑' : '🥉'); }
function buyVip(level) {
  SND.play('tap');
  api('vip_buy', { level: level }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    if (tg && tg.openInvoice) {
      tg.openInvoice(res.invoice, function (s) { if (s === 'paid') { toast('VIP faollashtirildi! 👑'); setTimeout(loadVip, 1500); } });
    } else { toast('Telegram orqali oching'); }
  });
}
function claimVip() {
  api('vip_claim', {}).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    USER.diamonds = res.diamonds; renderUser();
    toast('+' + res.claimed + ' 💎 olindi!'); SND.play('win');
    loadVip();
  });
}

/* ==================== O'YIN CHATI ==================== */
var CHAT_LAST = 0;
var QUICK_EMOJIS = ['👍', '😅', '🔥', '😮', '🤝', 'GG', 'Salom'];
function toggleChat() {
  var p = document.getElementById('chatPanel');
  p.classList.toggle('hidden');
  if (!p.classList.contains('hidden')) {
    var q = document.getElementById('chatQuick');
    q.innerHTML = QUICK_EMOJIS.map(function (e) { return '<span onclick="quickChat(\'' + e + '\')">' + e + '</span>'; }).join('');
  }
}
function quickChat(t) { document.getElementById('chatText').value = t; sendChat(); }
function sendChat() {
  var inp = document.getElementById('chatText');
  var t = inp.value.trim();
  if (!t || MODE !== 'online') return;
  inp.value = '';
  api('match_chat_send', { match_id: MATCH_ID, text: t });
  SND.play('tap');
}
function pollChat() {
  if (MODE !== 'online' || !MATCH_ID) return;
  api('match_chat_get', { match_id: MATCH_ID, after: CHAT_LAST }).then(function (res) {
    if (!res.success || !res.messages.length) return;
    var box = document.getElementById('chatMsgs');
    res.messages.forEach(function (m) {
      CHAT_LAST = m.id;
      var d = document.createElement('div');
      d.className = 'cmsg ' + (m.mine ? 'me' : 'them');
      d.textContent = m.text;
      box.appendChild(d);
      if (!m.mine) SND.play('chat');
    });
    box.scrollTop = box.scrollHeight;
  });
}

/* ==================== DURANG ==================== */
function offerDraw() {
  if (MODE !== 'online') return;
  api('match_draw_offer', { match_id: MATCH_ID }).then(function () { toast('Durang taklif qilindi'); });
}
var DRAW_SHOWN = false;
function checkDrawOffer(offerWho) {
  // offerWho: 1=p1, 2=p2; mening rangim MY_COLOR (2=p1, 1=p2)
  var iAmP1 = (MY_COLOR === 2);
  var offeredByOpponent = (iAmP1 && offerWho === 2) || (!iAmP1 && offerWho === 1);
  if (offeredByOpponent && !DRAW_SHOWN && !GAME_OVER) {
    DRAW_SHOWN = true;
    if (confirm('Raqib durang taklif qildi. Qabul qilasizmi?')) {
      api('match_draw_respond', { match_id: MATCH_ID, accept: true }).then(function () { /* poll yakunlaydi */ });
    } else {
      api('match_draw_respond', { match_id: MATCH_ID, accept: false });
    }
    setTimeout(function () { DRAW_SHOWN = false; }, 5000);
  }
}


/* Ovozni yoqish/o'chirish */
function toggleSound() {
  var on = SND.toggle();
  var btn = document.getElementById('sndBtn');
  if (btn) btn.textContent = on ? '🔊 Ovoz' : '🔇 Ovoz';
  if (on) SND.play('tap');
  toast(on ? 'Ovoz yoqildi' : 'Ovoz o\'chirildi');
}
