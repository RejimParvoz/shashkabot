/* Shashka Lite - dvigatel testi (Node, DOM stub bilan) */
const fs = require('fs');
const path = require('path');

// DOM/Telegram stublari
global.window = { Telegram: null };
global.document = {
  addEventListener: function () {},
  getElementById: function () { return { textContent: '', style: {}, classList: { add: function(){}, remove: function(){}, toggle: function(){} } }; },
  querySelectorAll: function () { return []; },
  documentElement: { setAttribute: function () {}, getAttribute: function () { return null; } }
};
global.localStorage = { getItem: function () { return null; }, setItem: function () {} };
global.fetch = function () { return Promise.resolve({ json: function () { return Promise.resolve({}); } }); };
global.API = '';
global.getComputedStyle = function () { return { getPropertyValue: function () { return '#ccc'; } }; };

// game.js ni yuklash (global scope'da, indirect eval orqali)
const code = fs.readFileSync(path.join(__dirname, 'game.js'), 'utf8');
const expose = '\n;Object.assign(globalThis,{newBoard:newBoard,getMoves:getMoves,applyMove:applyMove,minimax:minimax,evaluate:evaluate,captureSeqs:captureSeqs});';
(0, eval)(code + expose);

let pass = 0, fail = 0;
function test(name, cond) {
  if (cond) { console.log('  PASS: ' + name); pass++; }
  else { console.log('  FAIL: ' + name); fail++; }
}

console.log('========================================');
console.log('  SHASHKA LITE - DVIGATEL TESTI');
console.log('========================================\n');

console.log('1. BOSHLANG\'ICH DOSKA');
var b = newBoard();
var white = 0, black = 0;
for (var r = 0; r < 8; r++) for (var c = 0; c < 8; c++) {
  if (b[r][c] === 2) white++;
  if (b[r][c] === 1) black++;
}
test('12 oq tosh', white === 12);
test('12 qora tosh', black === 12);
test('Markaz bo\'sh', b[3][0] === 0 && b[4][7] === 0);

console.log('\n2. BOSHLANG\'ICH YURISHLAR');
var wm = getMoves(b, 2);
var bm = getMoves(b, 1);
test('Oq uchun 7 ta yurish', wm.length === 7);
test('Qora uchun 7 ta yurish', bm.length === 7);
test('Boshda yutish yo\'q', wm[0].captures.length === 0);

console.log('\n3. MAJBURIY YUTISH');
// Oddiy yutish holati: oq (2) r5c2, qora (1) r4c3 -> r3c4 ga sakraydi
var cb = [];
for (var i = 0; i < 8; i++) cb.push([0,0,0,0,0,0,0,0]);
cb[5][2] = 2;   // oq tosh
cb[4][3] = 1;   // qora tosh (yutiladi)
var cm = getMoves(cb, 2);
test('Faqat yutish yurishi qaytadi', cm.length === 1 && cm[0].captures.length === 1);
test('Yutish nishoni to\'g\'ri (3,4)', cm[0].to[0] === 3 && cm[0].to[1] === 4);
var after = applyMove(cb, cm[0]);
test('Yutilgan tosh yo\'qoldi', after[4][3] === 0);
test('Oq tosh yangi joyda', after[3][4] === 2);

console.log('\n4. DAMAGA AYLANISH');
var pb = [];
for (var j = 0; j < 8; j++) pb.push([0,0,0,0,0,0,0,0]);
pb[1][2] = 2;   // oq tosh 1-qatorda
var pm = getMoves(pb, 2).filter(function(m){return m.from[0]===1&&m.from[1]===2;});
var pa = applyMove(pb, pm[0]);
test('Oq 0-qatorga yetib dama bo\'ldi', pa[0][1] === 4 || pa[0][3] === 4);

console.log('\n5. MINIMAX BOT');
var ev = evaluate(newBoard());
test('Boshlang\'ich baho ~0 (simmetrik)', Math.abs(ev) <= 14);
// Bot uchun yurish topadi
var botMoves = getMoves(newBoard(), 1);
var bestVal = minimax(applyMove(newBoard(), botMoves[0]), 2, -Infinity, Infinity, false);
test('Minimax son qaytaradi', typeof bestVal === 'number' && isFinite(bestVal));

console.log('\n6. KO\'P SAKRASH (zanjir yutish)');
var mb = [];
for (var k = 0; k < 8; k++) mb.push([0,0,0,0,0,0,0,0]);
mb[5][2] = 2;   // oq
mb[4][3] = 1;   // 1-qurbon
mb[2][3] = 1;   // 2-qurbon (zanjir uchun)
var mm = getMoves(mb, 2);
var hasDouble = mm.some(function(m){ return m.captures.length === 2; });
test('Ikki tosh ketma-ket yutiladi', hasDouble);

console.log('\n========================================');
console.log('  NATIJA: ' + pass + ' passed, ' + fail + ' failed');
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
