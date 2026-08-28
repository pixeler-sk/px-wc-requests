=== Pixeler Woo Requests ===
Contributors: pixeler
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.8.0
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
* Preložené do slovenčiny a češtiny

Plugin nie je hostovaný na wordpress.org. Aktualizácie chodia priamo z
verejného repozitára https://github.com/pixeler-sk/px-wc-requests a zobrazujú
sa v administrácii ako pri každom inom plugine.

== Installation ==

1. Nahraj plugin do `wp-content/plugins/px-wc-requests/` a aktivuj ho.
2. WooCommerce → Nastavenia → **Žiadosti** — pre každý typ žiadosti
   priraď stránku s formulárom (stránka sa dá vytvoriť jedným klikom).
3. Do stránky vlož shortcode, ak si ju vytváral ručne:
   `[pxer_request_form type="withdrawal"]` alebo `type="claim"`.
4. V tých istých nastaveniach vyplň právne poučenie pre každý typ a vyber
   e-maily, do ktorých sa má vkladať odkaz na formulár.

Žiadosti nájdeš potom pod menu WooCommerce → Žiadosti.

== Frequently Asked Questions ==

= Ako zmením vzhľad formulára alebo e-mailov? =

Skopíruj šablónu z `templates/` do `wp-content/themes/<tvoja-téma>/px-wc-requests/`
a uprav ju tam. Prepis prežije aktualizáciu pluginu — ale pozor, práve preto sa
v prepísanom mieste neprejavia zmeny z nových verzií.

= Ako pridám vlastný typ žiadosti alebo pole? =

Cez filtre `pxer_request_types`, `pxer_request_statuses` a
`pxer_request_period_end`. Jadro pluginu sa upravovať nemusí.

= Kde sú uložené prílohy z reklamácií? =

V privátnom priečinku, ktorý nie je dostupný cez priamu URL. Súbory sa
doručujú len cez kontrolovaný výdaj oprávnenému zákazníkovi alebo adminovi.

= Sú žiadosti súčasťou GDPR exportu? =

Áno. Žiadosti vrátane IBAN a kontaktných údajov sú zapojené do štandardného
WordPress exportu aj výmazu osobných údajov.

= Dá sa prejsť z WPify Woo (Withdrawal & Claims)? =

Áno, plugin obsahuje dávkový migrátor s dry-run režimom. Je idempotentný,
takže sa dá spustiť opakovane.

== Other Notes ==

**Aktualizácie**

Plugin sa raz za 12 hodín pýta GitHub API, či existuje novšie vydanie. Overiť
sa to dá odkazom „Check for updates" v riadku pluginu na stránke Pluginy —
zelená hláška znamená, že spojenie funguje.

**Rozšíriteľnosť**

REST API v namespace `px-wc-requests/v1` (kontrola lehoty, odoslanie žiadosti,
zoznam a detail) pre headless frontendy; hostia sa autorizujú kľúčom objednávky.

== Changelog ==

= 1.8.0 =
História odoslaných e-mailov je pri žiadosti, nie v objednávke.

* Každý odoslaný (alebo neúspešný) e-mail žiadosti — potvrdenie, zmena stavu,
  poznámka pre zákazníka, upozornenie adminovi — sa zapíše ako interná poznámka
  do histórie žiadosti spolu s adresátom.
* WooCommerce 10.9+ už tieto e-maily nezapisuje do histórie objednávky; tam
  ostáva len poznámka o vytvorení žiadosti a o refundácii. Log WooCommerce
  (Stav → Logy) sa nemení.

= 1.7.0 =
Jeden kus tovaru môže byť naraz len v jednej žiadosti.

* Formulár aj server sledujú **dostupné kusy** každej položky: objednané mínus
  kusy v otvorených žiadostiach (odstúpenie aj reklamácia) mínus kusy už
  refundované vo WooCommerce. Položka bez voľných kusov sa vo formulári
  nezobrazí; pri viacerých kusoch je množstvo obmedzené na voľný zvyšok
  a formulár to pri položke vypíše.
* Vybavená alebo zamietnutá **reklamácia** kusy uvoľní — opravený tovar je
  možné reklamovať znova. Vybavené **odstúpenie** kusy spotrebuje natrvalo
  (tovar bol vrátený), nezávisle od toho, či je zapnutá automatická refundácia.
* Ak sú všetky položky objednávky už v inej žiadosti, formulár to povie
  namiesto všeobecného „žiadne oprávnené položky".
* REST `GET /eligibility` vracia pri položke aj `available`.
* Vylúčenie produktu z typu žiadosti (nastavenie produktu) platí aj pri
  vypnutej lehote.
* Nové filtre `pxer_item_available_qty` a `pxer_closed_statuses`; nový kľúč
  typu `consumes_items`.

= 1.6.0 =
Rýchlejšia práca so stavmi v zozname žiadostí.

* Stĺpec **Stav** v zozname žiadostí zobrazuje farebný badge v štýle stavov
  WooCommerce objednávok (prijatá oranžová, rieši sa zelená, vybavená modrá,
  zamietnutá červená) — rovnaká paleta ako stĺpec „Žiadosti" v objednávkach.
* **Rýchle úpravy** a hromadné úpravy ponúkajú v poli Stav priamo stavy
  žiadostí — zmena stavu už nevyžaduje otvárať detail. E-mail zákazníkovi
  a zápis do histórie prebehnú rovnako ako pri zmene v detaile.
* Textový stav pri názve žiadosti sa už nezobrazuje — nahradil ho badge.

= 1.5.0 =
Automatické refundácie pri odstúpení od zmluvy.

* Nová sekcia WooCommerce → Nastavenia → Žiadosti → **Automatické refundácie**:
  keď žiadosť o odstúpenie prejde do zvoleného stavu (predvolene vypnuté), na
  objednávke sa zaeviduje refundácia presne pre položky a množstvá zo žiadosti —
  sumy po zľave, DPH, pri žiadosti na celú objednávku aj doprava a stav
  Refundovaná.
* Peniaze sa cez platobnú bránu neposielajú nikdy — reálny prevod ostáva
  manuálny (napr. na IBAN zo žiadosti). Plugin pridá k objednávke aj žiadosti
  poznámku, že refundácia je zaevidovaná a peniaze treba poslať ručne.
* Každá žiadosť vytvorí najviac jednu refundáciu — prepínanie stavov tam a späť
  druhú nevytvorí. Prípadné zlyhanie sa zapíše do poznámok žiadosti.
* V zozname pluginov pribudol odkaz **Nastavenia** priamo na kartu nastavení.
* Karta nastavení, admin menu a GDPR skupina sa premenovali zo „Žiadosti
  zákazníkov" na kratšie „Žiadosti".

= 1.4.1 =
Farby stavov žiadostí zrkadlia stavy objednávok.

* Badge žiadostí v zozname objednávok používajú rovnakú farebnú reč ako stavy
  objednávok WooCommerce: prijatá oranžová (ako Čaká na vybavenie), rieši sa
  zelená (ako Spracováva sa), vybavená modrá (ako Vybavená), zamietnutá červená
  (ako Neúspešná).

= 1.4.0 =
Stĺpec „Žiadosti" v admin zozname objednávok.

* Zoznam objednávok (klasický aj HPOS) má nový úzky stĺpec hneď za stavom
  objednávky: pre každú naviazanú žiadosť badge `#ID` s preklikom priamo na
  žiadosť. Stav vyjadruje farba (prijatá modrá, rieši sa oranžová, vybavená
  zelená, zamietnutá červená), typ a stav slovne ukáže tooltip.
* Bez dopadu na rýchlosť zoznamu — žiadosti sa načítajú jedným dotazom pre celú
  stránku, nie po riadkoch.

= 1.3.1 =
Oprava tlačidla „Uložiť zmeny" pri WYSIWYG poliach.

* Po napísaní textu do WYSIWYG poľa v nastaveniach (Texty v e-mailoch, Právne
  poučenie na formulároch) zostávalo tlačidlo **Uložiť zmeny** vyblednuté a
  zmenu sa nedalo uložiť. WooCommerce sleduje len bežné polia formulára a
  editor píše do iframu, takže o zmene nevedelo.

= 1.3.0 =
Vlastný text v zákazníckych e-mailoch.

* Nová sekcia **WooCommerce → Nastavenia → Customer requests → Texty
  v e-mailoch**: vlastný text (WYSIWYG) sa dá pridať do e-mailu s potvrdením
  žiadosti — zvlášť pre každý typ žiadosti — a do e-mailu o zmene stavu —
  zvlášť pre každý typ **a stav**. Typické použitie: adresa, kam má zákazník
  poslať vrátený alebo reklamovaný tovar.
* Text podporuje zástupné znaky `{request_type}`, `{request_number}`,
  `{order_number}` a `{request_status}`, funguje v HTML aj textovej verzii
  e-mailu a zobrazuje sa aj v natívnom náhľade e-mailov vo WooCommerce.
* Prázdne pole nepridá nič — existujúce e-maily sa bez nastavenia nemenia.

= 1.2.3 =
Autor, preklad popisu a čeština.

* Autor pluginu je teraz Roman Kraiger | Pixeler s. r. o., odkazy smerujú na
  https://pixeler.sk.
* Popis pluginu na stránke Pluginy sa zobrazuje preložený.
* Pridaný **český preklad** (cs_CZ) — 241 reťazcov vrátane množných čísel.
* Oprava: generátor prekladov už neprehľadáva `vendor/`, takže sa do prekladov
  nedostávajú reťazce cudzích knižníc.

= 1.2.2 =
Vzhľad karty pluginu. **Bez funkčných zmien — kód pluginu je zhodný s 1.2.1.**

* Pridaný banner a ikona pluginu. Banner sa zobrazuje v hlavičke okna
  „Zobraziť podrobnosti", ikona v riadkoch aktualizácií.
* `readme.txt` doplnený o záložky Inštalácia, Časté otázky a Ďalšie poznámky.

= 1.2.1 =
Prvá verzia distribuovaná cez GitHub releases.

* Pridaný mechanizmus automatických aktualizácií (Plugin Update Checker 5.7),
  zdrojom je zip priložený k releasu.
* Doplnený `readme.txt` — changelog sa zobrazuje v okne „Zobraziť podrobnosti".

Poznámka: verzie staršie ako 1.2.1 vznikli pred zavedením verzovania v git-e,
takže ich zmeny nie sú spätne zdokumentované.
