=== Pixeler Woo Requests ===
Contributors: pixeler
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zákaznícke žiadosti pre WooCommerce — odstúpenie od zmluvy a reklamácie.

== Description ==

Univerzálny systém zákazníckych žiadostí pre WooCommerce. Žiadosti sa ukladajú
ako vlastný post type `pxer_request` s vlastnými stavmi; typy žiadostí, ich polia
aj stavy sú konfigurovateľné cez filtre, takže plugin je prenositeľný medzi
e-shopmi bez úpravy jadra.

* Formuláre cez shortcode `[pxer_request_form type="withdrawal|claim"]`
* Overenie vlastníctva objednávky (číslo + e-mail) pred zobrazením formulára
* Schéma polí vrátane IBAN s kontrolou mod-97, príloh a výberu položiek objednávky
* Kontrola zákonných lehôt (14 dní / 24 mesiacov, konfigurovateľné per typ)
* Prílohy v privátnom úložisku — nie sú dostupné cez priamu URL
* WooCommerce e-maily pre zákazníka aj administrátora, vrátane zmeny stavu
* Šablóny prepísateľné v téme cez `yourtheme/px-wc-requests/`

Plugin nie je hostovaný na wordpress.org. Aktualizácie chodia priamo z
verejného repozitára https://github.com/pixeler-sk/px-wc-requests a zobrazujú
sa v administrácii ako pri každom inom plugine.

== Changelog ==

= 1.2.1 =
Prvá verzia distribuovaná cez GitHub releases.

* Pridaný mechanizmus automatických aktualizácií (Plugin Update Checker 5.7),
  zdrojom je zip priložený k releasu.
* Doplnený `readme.txt` — changelog sa zobrazuje v okne „Zobraziť podrobnosti".

Poznámka: verzie staršie ako 1.2.1 vznikli pred zavedením verzovania v git-e,
takže ich zmeny nie sú spätne zdokumentované.
