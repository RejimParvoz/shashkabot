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
      renderUser();
    } else {
      toast('Avtorizatsiya: ' + (res.error || 'xato'));
    }
    document.getElementById('loader').style.display = 'none';
  }).catch(function () {
    document.getElementById('loader').style.display = 'none';
    toast('Serverga ulanib bolmadi');
  });
}

function loadProfile() {
  api('profile', {}).then(function (res) {
    if (!res.success) return;
    var p = res.profile;
    document.getElementById('pName').textContent = p.first_name || 'Mehmon';
    document.getElementById('pRating').textContent = p.rating;
    document.getElementById('pRank').textContent = p.rank;
    document.getElementById('pGames').textContent = p.total_games;
    document.getElementById('pWins').textContent = p.wins;
    document.getElementById('pLosses').textContent = p.losses;
    document.getElementById('pDraws').textContent = p.draws;
    document.getElementById('pWinRate').textContent = p.win_rate + '%';
    document.getElementById('pCoins').textContent = p.coins;
    document.getElementById('pRefs').textContent = p.referral_count || 0;
    if (p.photo_url) document.getElementById('pAvatar').src = p.photo_url;
    // skinlarni yangilash
    USER.equipped_board = p.equipped_board;
    USER.equipped_piece = p.equipped_piece;
    USER.coins = p.coins;
  });
}

function loadLeaderboard() {
  fetch(API + '?action=leaderboard').then(function (r) { return r.json(); }).then(function (res) {
    if (!res.success) return;
    var html = '';
    res.leaderboard.forEach(function (u) {
      var cls = u.rank === 1 ? 'g1' : (u.rank === 2 ? 'g2' : (u.rank === 3 ? 'g3' : ''));
      html += '<div class="lb"><div class="r ' + cls + '">' + u.rank + '</div>' +
        '<div class="nm">' + escapeHtml(u.first_name || u.username || 'Player') + '</div>' +
        '<div class="rt">' + u.rating + '</div></div>';
    });
    document.getElementById('lbList').innerHTML = html || '<div class="muted" style="padding:16px;">Hozircha bosh</div>';
  });
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
  ctx.beginPath(); ctx.arc(x, y + 2, rad, 0, 7); ctx.fillStyle = 'rgba(0,0,0,.25)'; ctx.fill();
  var g = ctx.createRadialGradient(x - rad / 3, y - rad / 3, rad / 4, x, y, rad);
  if (white) { g.addColorStop(0, th[0]); g.addColorStop(1, th[1]); }
  else { g.addColorStop(0, th[2]); g.addColorStop(1, th[3]); }
  ctx.beginPath(); ctx.arc(x, y, rad, 0, 7); ctx.fillStyle = g; ctx.fill();
  ctx.lineWidth = 2; ctx.strokeStyle = white ? 'rgba(0,0,0,.25)' : 'rgba(255,255,255,.25)'; ctx.stroke();
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
    if (s.status === 'finished') {
      applyOnline(s);
      onlineFinished(s);
    } else if (s.move_count !== ONLINE_MC) {
      applyOnline(s);
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
    window.App && window.App.sound && window.App.sound.play('move');
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
    if (USER) { USER.coins = res.coins; renderUser(); }
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
  var items = SHOP_ITEMS.filter(function (it) { return it.type === SHOP_TAB; });
  if (!items.length) { grid.innerHTML = '<div class="muted" style="padding:16px;">Bo\'sh</div>'; return; }
  grid.innerHTML = items.map(function (it) {
    return '<div class="shop-item">' +
      (it.equipped ? '<span class="tag eq">Tanlangan</span>' : (it.owned ? '<span class="tag">Bor</span>' : '')) +
      shopPreview(it) +
      '<div class="nm">' + escapeHtml(it.name) + '</div>' +
      shopBtn(it) +
      '</div>';
  }).join('');
}

function shopPreview(it) {
  var d = it.data.split(',');
  if (it.type === 'board') {
    return '<div class="shop-prev" style="background:repeating-conic-gradient(#' + d[0] +
      ' 0% 25%, #' + d[1] + ' 0% 50%);background-size:34px 34px;"></div>';
  }
  // piece: oq va qora doira
  return '<div class="shop-prev" style="background:var(--bg);gap:8px;">' +
    '<span style="width:30px;height:30px;border-radius:50%;background:radial-gradient(circle at 35% 35%,#' + d[0] + ',#' + d[1] + ');display:inline-block;"></span>' +
    '<span style="width:30px;height:30px;border-radius:50%;background:radial-gradient(circle at 35% 35%,#' + d[2] + ',#' + d[3] + ');display:inline-block;"></span>' +
    '</div>';
}

function shopBtn(it) {
  if (it.equipped) return '<button class="btn sm sec" disabled style="width:100%;">✓ Tanlangan</button>';
  if (it.owned) return '<button class="btn sm" style="width:100%;" onclick="equipItem(\'' + it.code + '\')">Tanlash</button>';
  return '<button class="btn sm gold" style="width:100%;" onclick="buyItem(\'' + it.code + '\',' + it.price_coins + ')">' + it.price_coins + ' 🪙</button>';
}

function buyItem(code, price) {
  if (USER && USER.coins < price) { toast('Tanga yetarli emas'); return; }
  api('shop_buy', { code: code }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    USER.coins = res.coins;
    renderUser();
    toast('Sotib olindi! Endi tanlang.');
    loadShop();
  });
}

function equipItem(code) {
  api('shop_equip', { code: code }).then(function (res) {
    if (!res.success) { toast(res.error || 'Xato'); return; }
    // mahalliy holatni yangilash
    var it = SHOP_ITEMS.find(function (x) { return x.code === code; });
    if (it) {
      if (it.type === 'board') USER.equipped_board = code;
      else USER.equipped_piece = code;
    }
    toast('Tanlandi ✓');
    loadShop();
  });
}

/* ==================== DO'ST TAKLIF QILISH ==================== */
function inviteFriend() {
  api('invite_info', {}).then(function (res) {
    if (!res.success) { toast('Xato'); return; }
    // Mini App havolasi: bot orqali start parametri bilan
    var botUser = (tg && tg.initDataUnsafe && tg.initDataUnsafe.bot) ? tg.initDataUnsafe.bot.username : null;
    var refCode = 'ref_' + res.telegram_id;
    var shareUrl;
    if (botUser) {
      shareUrl = 'https://t.me/' + botUser + '?start=' + refCode;
    } else {
      // bot username bo'lmasa, app_url orqali
      shareUrl = res.app_url + '?start=' + refCode;
    }
    var text = "Men bilan Shashka o'ynaymizmi? 🎯 Sovg'a sifatida tangalar oling!";
    var tgShare = 'https://t.me/share/url?url=' + encodeURIComponent(shareUrl) + '&text=' + encodeURIComponent(text);

    if (tg && tg.openTelegramLink) {
      tg.openTelegramLink(tgShare);
    } else {
      window.open(tgShare, '_blank');
    }
  });
}
