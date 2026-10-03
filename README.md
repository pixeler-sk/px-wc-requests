# Pixeler Woo Requests (px-wc-requests)

Univerzálny systém zákazníckych žiadostí pre WooCommerce — odstúpenie od zmluvy
(vrátenie tovaru) a reklamácie. Žiadosti sa ukladajú ako vlastný post type
`pxer_request` s vlastnými stavmi; typy žiadostí, ich polia aj stavy sú
konfigurovateľné a rozšíriteľné cez filtre.

## Základné funkcie

### Formuláre a podávanie žiadostí
- Shortcode `[pxer_request_form type="withdrawal|claim"]` — vyhľadanie objednávky
  (číslo + email) a následne formulár žiadosti; prihláseným zákazníkom stačí
  deep-link s kľúčom objednávky (bez vypisovania údajov).
- Polia formulára definované schémou (text, email, telefón, IBAN, textarea,
  checkbox, select, radio, súbory, položky objednávky) s podmieneným
  zobrazovaním (`show_if`), predvyplnením z fakturačných údajov, povinnosťou
  a šírkou poľa.
- Výber položiek objednávky vrátane množstva a dôvodu, podľa typu žiadosti
  jedna alebo viac položiek.
- Nahrávanie príloh (fotky k reklamácii) do privátneho úložiska — súbory nie sú
  verejne prístupné cez priamu URL.
- Právne poučenie na formulári aj na kroku vyhľadania objednávky — editovateľné
  vo WYSIWYG editore v nastaveniach, samostatne pre každý typ žiadosti.
  Predvolené poučenie pri odstúpení obsahuje vetu z prílohy č. 3 bod 3a
  zákona č. 108/2024 Z. z.; zástupný text `{url}` sa nahradí adresou stránky
  formulára (bez priradenej stránky sa odsek s ním vynechá).
- Predvolené texty podľa § 20a zákona č. 108/2024 Z. z.: tlačidlo odoslania
  **Potvrdiť odstúpenie od zmluvy**, odkaz **Odstúpiť od zmluvy tu**
  (reklamácia: **Odoslať reklamáciu**, **Reklamovať tovar**).
- Anti-spam ochrana: honeypot, časová pasca, limit na IP, limit počtu žiadostí
  na objednávku a detekcia duplicitného obsahu.

### Lehoty (eligibility)
- Kontrola zákonnej lehoty podľa dátumu dokončenia objednávky — 14 dní pre
  odstúpenie, 24 mesiacov pre reklamáciu (konfigurovateľné per typ).
- Per-produkt výnimky: produkt možno z typu žiadosti vylúčiť alebo mu nastaviť
  vlastnú lehotu (v karte produktu, záložka Všeobecné).
- Zobrazenie termínu (deadline) priamo na formulári; po lehote sa formulár
  nezobrazí.
- Lehota vybavenia reklamácie (§ 622 ods. 3 OZ, predvolene a najviac 30 dní) —
  uloží sa k žiadosti pri vytvorení a potvrdenie zákazníkovi ju uvádza aj
  s dátumom („Vadu odstránime najneskôr do 30 dní od vytknutia vady, t. j. do …").

### Môj účet
- Tlačidlá **Odstúpiť od zmluvy / Reklamovať** pri objednávkach v Mojom účte
  (len kým je objednávka v lehote).
- Vlastná záložka **Moje žiadosti** — zoznam žiadostí zákazníka so stavmi
  (slug endpointu konfigurovateľný vo WooCommerce → Nastavenia → Rozšírené).
- Detail žiadosti v Mojom účte so zhrnutím údajov a históriou — zákazník vidí
  zmeny stavu a poznámky, ktoré sú mu určené.

### Administrácia žiadostí
- Zoznam žiadostí pod menu WooCommerce, vlastné stavy per typ (napr. Prijatá →
  V riešení → Vybavená; rozšíriteľné filtrami) s dropdownom a bulk akciami.
- Detail žiadosti so schémou definovaným prehľadom polí, položiek a príloh.
- **História žiadosti ako pri objednávke** — poznámky v štýle WooCommerce order
  notes (interné aj „poznámka zákazníkovi"; zákaznícka poznámka automaticky
  odošle email).

### Emaily
- Vlastné WooCommerce emaily: nová žiadosť (admin), potvrdenie žiadosti
  (zákazník), zmena stavu (zákazník), poznámka zákazníkovi.
- Prílohy reklamácie sa automaticky priložia k admin emailu.
- **Automatické pridanie odkazu na formulár do vybraných zákazníckych emailov**
  (napr. do „Objednávka dokončená") — per typ nastaviteľné, odkaz sa pridá len
  kým je objednávka v lehote a nesie deep-link s kľúčom objednávky.
- Integrácia s pluginom **px-wc-empty-emails**: placeholdery
  `{claim_form_url}`, `{withdrawal_form_url}` (odkazy na formuláre) a
  `{request_data}` (tabuľka so zhrnutím žiadosti); emailom žiadostí možno
  zapnúť „empty" šablónu s vlastným obsahom.

### Nastavenia (WooCommerce → Nastavenia → Zákaznícke žiadosti)
- Priradenie stránok formulárov per typ (+ tlačidlo na vytvorenie stránky
  jedným klikom, stránky označené v zozname stránok).
- Právne poučenia (WYSIWYG editor, základné formátovanie bez obrázkov).
- Výber emailov, do ktorých sa vkladajú odkazy na formuláre.
- Texty tlačidiel per typ: akčné tlačidlo (Môj účet + text odkazu v e-mailoch,
  `pxer_{typ}_button_label`) a odosielacie tlačidlo formulára
  (`pxer_{typ}_submit_label`). Prázdne pole = predvolený text typu (kľúče
  `button_label` / `submit_label` v konfigurácii typu).
- Lehota vybavenia reklamácie v dňoch (`pxer_claim_resolution_days`,
  predvolene 30, rozsah 1–30; kľúč typu `resolution_days`, 0 = vypnuté).

### Integrácie a rozšíriteľnosť
- REST API (`px-wc-requests/v1`): kontrola lehoty, odoslanie žiadosti, zoznam
  a detail žiadostí — pre headless/JS frontendy; hostia sa autorizujú kľúčom
  objednávky.
- GDPR: žiadosti (vrátane IBAN a kontaktov) sú súčasťou WordPress exportu
  a výmazu osobných údajov.
- Migrátor dát z WPify Woo (Withdrawal & Claims) — dávkový, idempotentný,
  s dry-run režimom.
- Šablóny prepísateľné v téme (`yourtheme/px-wc-requests/...`).
- Filtre pre vlastné typy, stavy, polia a lehoty (`pxer_request_types`,
  `pxer_request_statuses`, `pxer_request_period_end`, …) — site-specific
  úpravy bez zásahu do jadra (vzor: glue v plugine spimsi-eshop).
- Ochrana formulára: okrem vlastného honeypotu, časovej pasce a limitov
  filter `pxer_submit_check` (`true|WP_Error`, `$params`, `$type`) — beží len
  pri odoslaní z formulára, nie cez REST. Po chybe AJAXu sa na `document`
  spustí jQuery udalosť `pxer:failed` (`[form]`). Používa ich modul
  `antispam` z px-shop-core (Turnstile / reCAPTCHA, widget cez
  `pxer_request_form_after_fields`).
