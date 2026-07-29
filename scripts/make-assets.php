<?php
/**
 * Vygeneruje ikonu a banner pluginu do `assets/`.
 *
 * WordPress ich zobrazuje v okne „Zobraziť podrobnosti" (banner) a v riadkoch
 * aktualizácií (ikona). Plugin Update Checker ich hľadá v priečinku `assets/`
 * pod presnými názvami podľa konvencie wordpress.org — pozri RELEASING.md.
 *
 * Ide o jednoduchú zástupnú grafiku vygenerovanú kódom, aby bolo možné dizajn
 * kedykoľvek doladiť zmenou farieb nižšie. Ak vznikne poriadne logo, súbory
 * v `assets/` sa dajú jednoducho nahradiť — tento skript vtedy zmaž.
 *
 * Spustenie:  php scripts/make-assets.php
 *
 * Skript sa nebalí do release zipu (vylúčený v .github/workflows/release.yml).
 *
 * @package Pixeler\Requests
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only.' );
}

// --- Paleta -----------------------------------------------------------------

$INK    = array( 15, 23, 42 );    // pozadie, tmavá navy
$INK_2  = array( 30, 41, 59 );    // jemnejší odtieň na prechod
$ACCENT = array( 56, 189, 248 );  // akcent
$PAPER  = array( 248, 250, 252 ); // dokument
$MUTED  = array( 148, 163, 184 ); // podtitul

$FONT_BOLD    = 'C:/Windows/Fonts/segoeuib.ttf';
$FONT_REGULAR = 'C:/Windows/Fonts/segoeui.ttf';

$assetsDir = dirname( __DIR__ ) . '/assets';

// --- Pomocné funkcie --------------------------------------------------------

function px_color( $img, array $rgb, int $alpha = 0 ) {
	return imagecolorallocatealpha( $img, $rgb[0], $rgb[1], $rgb[2], $alpha );
}

/**
 * Obdĺžnik so zaoblenými rohmi (GD ho nevie natívne).
 */
function px_rounded_rect( $img, float $x, float $y, float $w, float $h, float $r, $color ): void {
	imagefilledrectangle( $img, (int) ( $x + $r ), (int) $y, (int) ( $x + $w - $r ), (int) ( $y + $h ), $color );
	imagefilledrectangle( $img, (int) $x, (int) ( $y + $r ), (int) ( $x + $w ), (int) ( $y + $h - $r ), $color );

	$d = (int) ( $r * 2 );
	imagefilledellipse( $img, (int) ( $x + $r ), (int) ( $y + $r ), $d, $d, $color );
	imagefilledellipse( $img, (int) ( $x + $w - $r ), (int) ( $y + $r ), $d, $d, $color );
	imagefilledellipse( $img, (int) ( $x + $r ), (int) ( $y + $h - $r ), $d, $d, $color );
	imagefilledellipse( $img, (int) ( $x + $w - $r ), (int) ( $y + $h - $r ), $d, $d, $color );
}

/**
 * Značka pluginu: list papiera s riadkami a akcentovaný šíp „naspäť".
 */
function px_draw_mark( $img, float $cx, float $cy, float $size, array $paper, array $accent, array $ink ): void {
	$paperC  = px_color( $img, $paper );
	$accentC = px_color( $img, $accent );
	$inkC    = px_color( $img, $ink );

	$w = $size * 0.62;
	$h = $size * 0.78;
	$x = $cx - $w / 2;
	$y = $cy - $h / 2;

	px_rounded_rect( $img, $x, $y, $w, $h, $size * 0.07, $paperC );

	// Riadky textu na papieri.
	$lineH = max( 2, (int) round( $size * 0.045 ) );
	$lx    = $x + $w * 0.16;
	$lw    = $w * 0.68;
	for ( $i = 0; $i < 3; $i++ ) {
		$ly = $y + $h * ( 0.24 + $i * 0.16 );
		$ww = ( 2 === $i ) ? $lw * 0.55 : $lw;
		px_rounded_rect( $img, $lx, $ly, $ww, $lineH, $lineH / 2, $inkC );
	}

	// Akcentový disk so šípom doľava (vrátenie / odstúpenie).
	$r  = $size * 0.26;
	$bx = $cx + $w * 0.42;
	$by = $cy + $h * 0.34;
	imagefilledellipse( $img, (int) $bx, (int) $by, (int) ( $r * 2 ), (int) ( $r * 2 ), $accentC );

	$a = $r * 0.52;
	imagefilledpolygon(
		$img,
		array(
			(int) ( $bx - $a * 0.75 ), (int) $by,
			(int) ( $bx + $a * 0.15 ), (int) ( $by - $a * 0.85 ),
			(int) ( $bx + $a * 0.15 ), (int) ( $by + $a * 0.85 ),
		),
		$paperC
	);
	px_rounded_rect( $img, $bx + $a * 0.1, $by - $a * 0.28, $a * 0.8, $a * 0.56, $a * 0.28, $paperC );
}

// --- Ikony ------------------------------------------------------------------

foreach ( array( 256, 128 ) as $size ) {
	$img = imagecreatetruecolor( $size, $size );
	imagealphablending( $img, true );
	imageantialias( $img, true );

	$transparent = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
	imagefill( $img, 0, 0, $transparent );

	px_rounded_rect( $img, 0, 0, $size - 1, $size - 1, $size * 0.22, px_color( $img, $INK ) );
	px_draw_mark( $img, $size / 2, $size * 0.47, $size * 0.66, $PAPER, $ACCENT, $INK );

	imagesavealpha( $img, true );
	imagepng( $img, sprintf( '%s/icon-%dx%d.png', $assetsDir, $size, $size ) );
	imagedestroy( $img );

	echo "icon-{$size}x{$size}.png\n";
}

// --- Banner -----------------------------------------------------------------
// Len 1544x500. Plugin Update Checker mapuje 772x250 na 'high' a 1544x500 na
// 'low', čo je oproti konvencii wordpress.org naopak. Keď dodáme jediný súbor,
// WordPress ho použije pre obe rozlíšenia a k zámene nedôjde.

$bw  = 1544;
$bh  = 500;
$img = imagecreatetruecolor( $bw, $bh );
imagealphablending( $img, true );
imageantialias( $img, true );

// Zvislý prechod pozadia.
for ( $y = 0; $y < $bh; $y++ ) {
	$t = $y / $bh;
	$c = imagecolorallocate(
		$img,
		(int) round( $INK[0] + ( $INK_2[0] - $INK[0] ) * $t ),
		(int) round( $INK[1] + ( $INK_2[1] - $INK[1] ) * $t ),
		(int) round( $INK[2] + ( $INK_2[2] - $INK[2] ) * $t )
	);
	imageline( $img, 0, $y, $bw, $y, $c );
}

// Jemné diagonálne pruhy vpravo.
$stripe = px_color( $img, $ACCENT, 112 );
for ( $i = 0; $i < 14; $i++ ) {
	$x = $bw - 620 + $i * 46;
	imagefilledpolygon(
		$img,
		array( $x, $bh, $x + 150, 0, $x + 168, 0, $x + 18, $bh ),
		$stripe
	);
}

px_draw_mark( $img, $bw - 250, $bh / 2, 300, $PAPER, $ACCENT, $INK );

// Akcentová linka nad titulkom.
px_rounded_rect( $img, 96, 176, 84, 7, 3.5, px_color( $img, $ACCENT ) );

imagettftext( $img, 62, 0, 96, 268, px_color( $img, $PAPER ), $FONT_BOLD, 'Pixeler Woo Requests' );
imagettftext( $img, 27, 0, 99, 330, px_color( $img, $MUTED ), $FONT_REGULAR, 'Odstúpenie od zmluvy a reklamácie pre WooCommerce' );

imagepng( $img, $assetsDir . '/banner-1544x500.png' );
imagedestroy( $img );

echo "banner-1544x500.png\n";
