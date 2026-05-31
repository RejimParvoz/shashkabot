/* Shashka Lite - O'yin mantig'i (Telegram avto-login + bot AI) */
'use strict';

var tg = window.Telegram ? window.Telegram.WebApp : null;
var USER = null;
var INIT_DATA = tg ? tg.initData : '';
var botLevel = 'medium';
var gameMode = 'classic';

/* ---------- API ---------- */
function api(action, body) {
  body = body || {};
  body.init_data = INIT_DATA;
  return fetch(API + '?action=' + action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  }).then(function (r) { return r.json(); });
}

/* ---------- UI yordamchilari ---------- */
function nav(page) {
  document.querySelectorAll('.page').forEach(function (p) { p.classList.remove('active'); });
  document.getElementById('p-' + page).classList.add('active');
  document.querySelectorAll('.nav a').forEach(function (a) {
    a.classList.toggle('active', a.getAttribute('data-p') === page);
  });
  if (page === 'top') loadLeaderboard();
  if (page === 'profile') loadProfile();
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
  var names = { easy: 'Oson', medium: "O'rta", hard: 'Qiyin', expert: 'Ekspert' };
  document.getElementById('curLevel').textContent = names[lvl];
  toast('Daraja: ' + names[lvl]);
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
  gameMode = mode;
  BOARD = newBoard();
  SELECTED = null; LEGAL = []; MY_TURN = true; MOVES = 0; GAME_OVER = false;
  document.getElementById('gLevel').textContent =
    ({ easy: 'oson', medium: "o'rta", hard: 'qiyin', expert: 'ekspert' })[botLevel];
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

function render() {
  var size = CELL * 8;
  ctx.clearRect(0, 0, size, size);
  for (var r = 0; r < 8; r++) {
    for (var c = 0; c < 8; c++) {
      var dark = (r + c) % 2 === 1;
      ctx.fillStyle = dark ? getColor('--board-d') : getColor('--board-l');
      ctx.fillRect(c * CELL, r * CELL, CELL, CELL);
    }
  }
  // tanlangan + yurishlar
  if (SELECTED) {
    ctx.fillStyle = 'rgba(52,199,89,.4)';
    ctx.fillRect(SELECTED.c * CELL, SELECTED.r * CELL, CELL, CELL);
    LEGAL.forEach(function (m) {
      ctx.fillStyle = 'rgba(52,199,89,.7)';
      ctx.beginPath();
      ctx.arc(m.to[1] * CELL + CELL / 2, m.to[0] * CELL + CELL / 2, CELL * 0.16, 0, 7);
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
  var x = c * CELL + CELL / 2, y = r * CELL + CELL / 2, rad = CELL * 0.36;
  var white = (p === 2 || p === 4), king = (p === 3 || p === 4);
  ctx.beginPath(); ctx.arc(x, y + 2, rad, 0, 7); ctx.fillStyle = 'rgba(0,0,0,.25)'; ctx.fill();
  var g = ctx.createRadialGradient(x - rad / 3, y - rad / 3, rad / 4, x, y, rad);
  if (white) { g.addColorStop(0, '#fff'); g.addColorStop(1, '#cfcfcf'); }
  else { g.addColorStop(0, '#666'); g.addColorStop(1, '#161616'); }
  ctx.beginPath(); ctx.arc(x, y, rad, 0, 7); ctx.fillStyle = g; ctx.fill();
  ctx.lineWidth = 2; ctx.strokeStyle = white ? '#aaa' : '#000'; ctx.stroke();
  if (king) {
    ctx.fillStyle = '#ffd700';
    ctx.font = (rad * 1.1) + 'px serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('\u265B', x, y);
  }
}

function handleClick(clientX, clientY) {
  if (!MY_TURN || GAME_OVER) return;
  var rect = canvas.getBoundingClientRect();
  var c = Math.floor((clientX - rect.left) / CELL);
  var r = Math.floor((clientY - rect.top) / CELL);
  if (r < 0 || r > 7 || c < 0 || c > 7) return;

  // agar yurish nishoniga bosilsa
  if (SELECTED) {
    var mv = LEGAL.find(function (m) { return m.to[0] === r && m.to[1] === c; });
    if (mv) { doMove(mv, true); return; }
  }
  // tosh tanlash (faqat o'z toshlari va majburiy yutish qoidasi)
  if (BOARD[r][c] === 2 || BOARD[r][c] === 4) {
    var all = getMoves(BOARD, 2);
    var forPiece = all.filter(function (m) { return m.from[0] === r && m.from[1] === c; });
    if (forPiece.length) { SELECTED = { r: r, c: c }; LEGAL = forPiece; render(); }
    else toast('Bu tosh yura olmaydi (majburiy yutish bor)');
  }
}


/* ---------- Yurishlarni hisoblash (majburiy yutish bilan) ---------- */
function isOwn(p, white) { return white ? (p === 2 || p === 4) : (p === 1 || p === 3); }
function isOpp(p, white) { return white ? (p === 1 || p === 3) : (p === 2 || p === 4); }
function inB(r, c) { return r >= 0 && r < 8 && c >= 0 && c < 8; }

function pieceDirs(p) {
  var king = (p === 3 || p === 4);
  if (king) return [[-1, -1], [-1, 1], [1, -1], [1, 1]];
  if (p === 2) return [[-1, -1], [-1, 1]];   // oq tepaga
  return [[1, -1], [1, 1]];                  // qora pastga
}

/* Bitta tosh uchun yutib olish ketma-ketliklari (ko'p sakrash) */
function captureSeqs(board, r, c, p) {
  var white = (p === 2 || p === 4);
  var res = [];
  var dirs = (p === 3 || p === 4) ? [[-1, -1], [-1, 1], [1, -1], [1, 1]] : pieceDirs(p);
  for (var i = 0; i < dirs.length; i++) {
    var mr = r + dirs[i][0], mc = c + dirs[i][1];
    var tr = r + dirs[i][0] * 2, tc = c + dirs[i][1] * 2;
    if (inB(tr, tc) && board[tr][tc] === 0 && inB(mr, mc) && isOpp(board[mr][mc], white)) {
      var nb = cloneBoard(board);
      nb[tr][tc] = nb[r][c]; nb[r][c] = 0; nb[mr][mc] = 0;
      var promoted = maybePromote(nb, tr, tc);
      var further = promoted ? [] : captureSeqs(nb, tr, tc, nb[tr][tc]);
      if (further.length === 0) {
        res.push({ from: [r, c], to: [tr, tc], captures: [[mr, mc]] });
      } else {
        further.forEach(function (f) {
          res.push({ from: [r, c], to: f.to, captures: [[mr, mc]].concat(f.captures) });
        });
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
  var my = 0, bot = 0;
  for (var r = 0; r < 8; r++) for (var c = 0; c < 8; c++) {
    if (BOARD[r][c] === 2 || BOARD[r][c] === 4) my++;
    if (BOARD[r][c] === 1 || BOARD[r][c] === 3) bot++;
  }
  document.getElementById('gMyCount').textContent = my;
  document.getElementById('gBotCount').textContent = bot;
}

function setTurnText() {
  document.getElementById('turn').textContent = MY_TURN ? 'Sizning navbatingiz' : "Bot o'ylayapti...";
}

function checkOver() {
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
function resignGame() {
  if (GAME_OVER) return;
  if (confirm("Taslim bo'lasizmi?")) finish('loss');
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
    '<h2>' + title + '</h2>' +
    '<div class="rc ' + rcCls + '">Reyting: ' + rcTxt + '</div>' +
    '<div class="muted" style="margin:6px 0 16px;">+' + coins + ' \uD83E\uDE99 tanga</div>' +
    '<button class="btn grad" onclick="closeResult()">Davom etish</button>';
  document.getElementById('resultCard').innerHTML = html;
  document.getElementById('overlay').classList.add('show');
}

function closeResult() {
  document.getElementById('overlay').classList.remove('show');
  nav('home');
}
