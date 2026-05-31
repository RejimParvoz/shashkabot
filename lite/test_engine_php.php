<?php
/**
 * Shashka Lite - PHP dvigatel testi (online o'yin uchun)
 */
require_once __DIR__ . '/engine.php';

$pass = 0; $fail = 0;
function t($n, $c) { global $pass, $fail; if ($c) { echo "  PASS: $n\n"; $pass++; } else { echo "  FAIL: $n\n"; $fail++; } }

echo "========================================\n";
echo "  PHP DVIGATEL TESTI (online)\n";
echo "========================================\n\n";

echo "1. BOSHLANG'ICH DOSKA\n";
$b = eNewBoard();
$w = 0; $bl = 0;
for ($r = 0; $r < 8; $r++) for ($c = 0; $c < 8; $c++) {
    if ($b[$r][$c] === 2) $w++;
    if ($b[$r][$c] === 1) $bl++;
}
t('12 oq', $w === 12);
t('12 qora', $bl === 12);
t('Oq uchun 7 yurish', count(eGetMoves($b, 2)) === 7);
t('Qora uchun 7 yurish', count(eGetMoves($b, 1)) === 7);

echo "\n2. QONUNIY YURISHNI QO'LLASH\n";
$nb = eApplyIfLegal($b, 2, [5, 0], [4, 1]);
t('Oq (5,0)->(4,1) qonuniy', $nb !== null);
t('Tosh ko\'chdi', $nb !== null && $nb[5][0] === 0 && $nb[4][1] === 2);
$bad = eApplyIfLegal($b, 2, [5, 0], [3, 2]);
t('Noqonuniy yurish rad etiladi', $bad === null);
$badTurn = eApplyIfLegal($b, 2, [2, 1], [3, 2]); // qora toshni oq yura olmaydi
t('Begona toshni yura olmaydi', $badTurn === null);

echo "\n3. ORQAGA OLISH (PHP)\n";
$bb = []; for ($i=0;$i<8;$i++) $bb[$i]=[0,0,0,0,0,0,0,0];
$bb[3][3] = 2; $bb[4][4] = 1;  // orqada raqib
$cap = eApplyIfLegal($bb, 2, [3,3], [5,5]);
t('Oq orqaga yutadi (3,3)->(5,5)', $cap !== null);
t('Yutilgan tosh o\'chdi', $cap !== null && $cap[4][4] === 0);

echo "\n4. UCHUVCHI DAMA (PHP)\n";
$kb = []; for ($i=0;$i<8;$i++) $kb[$i]=[0,0,0,0,0,0,0,0];
$kb[7][0] = 4;
$far = eApplyIfLegal($kb, 2, [7,0], [0,7]);
t('Dama uzoqqa yuradi (7,0)->(0,7)', $far !== null);
$kc = []; for ($i=0;$i<8;$i++) $kc[$i]=[0,0,0,0,0,0,0,0];
$kc[7][0] = 4; $kc[4][3] = 1;
$kcap = eApplyIfLegal($kc, 2, [7,0], [3,4]); // (4,3) ni yutib, orqasidagi (3,4) ga
t('Dama uzoqdan yutadi', $kcap !== null && $kcap[4][3] === 0);

echo "\n5. G'OLIBNI ANIQLASH\n";
$wb = []; for ($i=0;$i<8;$i++) $wb[$i]=[0,0,0,0,0,0,0,0];
$wb[5][0] = 2;  // faqat oq (yura oladi), qora yo'q
t('Qora toshsiz -> oq yutdi (2)', eCheckWinner($wb) === 2);
$wb2 = []; for ($i=0;$i<8;$i++) $wb2[$i]=[0,0,0,0,0,0,0,0];
$wb2[7][0] = 1;  // faqat qora
t('Oq toshsiz -> qora yutdi (1)', eCheckWinner($wb2) === 1);
t('Boshlang\'ich -> davom (0)', eCheckWinner(eNewBoard()) === 0);

echo "\n========================================\n";
echo "  NATIJA: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
