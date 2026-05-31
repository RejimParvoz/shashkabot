<?php
/**
 * Shashka Lite - Server tomon dvigatel (PHP)
 * JS dvigatelining aynan nusxasi: online o'yinda yurishni tekshirish uchun.
 * Doska: 8x8 massiv. 0=bo'sh, 1=qora, 2=oq, 3=qora dama, 4=oq dama.
 */

function eNewBoard() {
    $b = [];
    for ($r = 0; $r < 8; $r++) {
        $b[$r] = [];
        for ($c = 0; $c < 8; $c++) {
            $dark = (($r + $c) % 2) === 1;
            if ($dark && $r < 3) $b[$r][$c] = 1;
            elseif ($dark && $r > 4) $b[$r][$c] = 2;
            else $b[$r][$c] = 0;
        }
    }
    return $b;
}

function eInB($r, $c) { return $r >= 0 && $r < 8 && $c >= 0 && $c < 8; }
function eIsOwn($p, $white) { return $white ? ($p === 2 || $p === 4) : ($p === 1 || $p === 3); }
function eIsOpp($p, $white) { return $white ? ($p === 1 || $p === 3) : ($p === 2 || $p === 4); }

function eMaybePromote(&$board, $r, $c) {
    if ($board[$r][$c] === 2 && $r === 0) { $board[$r][$c] = 4; return true; }
    if ($board[$r][$c] === 1 && $r === 7) { $board[$r][$c] = 3; return true; }
    return false;
}

function eClone($b) {
    $n = [];
    for ($r = 0; $r < 8; $r++) $n[$r] = $b[$r];
    return $n;
}

$E_DIRS = [[-1, -1], [-1, 1], [1, -1], [1, 1]];

function eCaptureSeqs($board, $r, $c, $p) {
    global $E_DIRS;
    $white = ($p === 2 || $p === 4);
    $king = ($p === 3 || $p === 4);
    $res = [];

    if (!$king) {
        foreach ($E_DIRS as $d) {
            $mr = $r + $d[0]; $mc = $c + $d[1];
            $tr = $r + $d[0] * 2; $tc = $c + $d[1] * 2;
            if (eInB($tr, $tc) && $board[$tr][$tc] === 0 && eInB($mr, $mc) && eIsOpp($board[$mr][$mc], $white)) {
                $nb = eClone($board);
                $nb[$tr][$tc] = $nb[$r][$c]; $nb[$r][$c] = 0; $nb[$mr][$mc] = 0;
                eMaybePromote($nb, $tr, $tc);
                $further = eCaptureSeqs($nb, $tr, $tc, $nb[$tr][$tc]);
                if (count($further) === 0) {
                    $res[] = ['from' => [$r, $c], 'to' => [$tr, $tc], 'captures' => [[$mr, $mc]]];
                } else {
                    foreach ($further as $f) {
                        $res[] = ['from' => [$r, $c], 'to' => $f['to'], 'captures' => array_merge([[$mr, $mc]], $f['captures'])];
                    }
                }
            }
        }
    } else {
        foreach ($E_DIRS as $d) {
            $rr = $r + $d[0]; $cc = $c + $d[1];
            while (eInB($rr, $cc) && $board[$rr][$cc] === 0) { $rr += $d[0]; $cc += $d[1]; }
            if (!eInB($rr, $cc)) continue;
            if (eIsOwn($board[$rr][$cc], $white)) continue;
            $capR = $rr; $capC = $cc;
            $lr = $capR + $d[0]; $lc = $capC + $d[1];
            while (eInB($lr, $lc) && $board[$lr][$lc] === 0) {
                $nb = eClone($board);
                $nb[$lr][$lc] = $nb[$r][$c]; $nb[$r][$c] = 0; $nb[$capR][$capC] = 0;
                $further = eCaptureSeqs($nb, $lr, $lc, $nb[$lr][$lc]);
                if (count($further) === 0) {
                    $res[] = ['from' => [$r, $c], 'to' => [$lr, $lc], 'captures' => [[$capR, $capC]]];
                } else {
                    foreach ($further as $f) {
                        $res[] = ['from' => [$r, $c], 'to' => $f['to'], 'captures' => array_merge([[$capR, $capC]], $f['captures'])];
                    }
                }
                $lr += $d[0]; $lc += $d[1];
            }
        }
    }
    return $res;
}

function eGetMoves($board, $player) {
    global $E_DIRS;
    $white = ($player === 2);
    $captures = []; $simple = [];
    for ($r = 0; $r < 8; $r++) {
        for ($c = 0; $c < 8; $c++) {
            $p = $board[$r][$c];
            if (!eIsOwn($p, $white)) continue;
            $seqs = eCaptureSeqs($board, $r, $c, $p);
            foreach ($seqs as $s) $captures[] = $s;
            if (count($seqs) === 0) {
                $king = ($p === 3 || $p === 4);
                if ($king) {
                    foreach ($E_DIRS as $d) {
                        $kr = $r + $d[0]; $kc = $c + $d[1];
                        while (eInB($kr, $kc) && $board[$kr][$kc] === 0) {
                            $simple[] = ['from' => [$r, $c], 'to' => [$kr, $kc], 'captures' => []];
                            $kr += $d[0]; $kc += $d[1];
                        }
                    }
                } else {
                    $fdirs = ($p === 2) ? [[-1, -1], [-1, 1]] : [[1, -1], [1, 1]];
                    foreach ($fdirs as $d) {
                        $nr = $r + $d[0]; $nc = $c + $d[1];
                        if (eInB($nr, $nc) && $board[$nr][$nc] === 0) {
                            $simple[] = ['from' => [$r, $c], 'to' => [$nr, $nc], 'captures' => []];
                        }
                    }
                }
            }
        }
    }
    return count($captures) ? $captures : $simple;
}

/** Yurishni qo'llash (faqat qonuniy bo'lsa). Yangi doska yoki null qaytaradi. */
function eApplyIfLegal($board, $player, $from, $to) {
    $moves = eGetMoves($board, $player);
    foreach ($moves as $m) {
        if ($m['from'][0] === $from[0] && $m['from'][1] === $from[1]
            && $m['to'][0] === $to[0] && $m['to'][1] === $to[1]) {
            $nb = eClone($board);
            $p = $nb[$from[0]][$from[1]];
            $nb[$from[0]][$from[1]] = 0;
            foreach ($m['captures'] as $cap) { $nb[$cap[0]][$cap[1]] = 0; }
            $nb[$to[0]][$to[1]] = $p;
            eMaybePromote($nb, $to[0], $to[1]);
            return $nb;
        }
    }
    return null;
}

/** G'olibni aniqlash: 'p1_win'(qora yutmaydi...) -> bu yerda player raqami qaytadi yoki null */
function eCheckWinner($board) {
    $w = count(eGetMoves($board, 2));
    $b = count(eGetMoves($board, 1));
    if ($w === 0) return 1; // oq yura olmaydi -> qora yutdi
    if ($b === 0) return 2; // qora yura olmaydi -> oq yutdi
    return 0; // davom etadi
}
