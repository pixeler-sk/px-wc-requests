# CLAUDE.md

Guidance for Claude Code when working in this plugin.

## Prehľad

**Pixeler Woo Requests** je univerzálny, samostatný WooCommerce plugin pre
zákaznícke žiadosti — **odstúpenie od zmluvy** a **reklamácie**. Žiadosti sú
uložené ako jeden zdieľaný CPT s vlastnými stavmi. Celý systém je riadený
**konfiguráciou typov + schémou polí**, takže je prenositeľný medzi e-shopmi bez
úpravy jadra.

Vznikol zovšeobecnením a dedupláciou funkcionality z `pixeler-eshop-addons`
(tam boli dva takmer identické CPT `px_refund`/`px_return`).

- **Hlavný súbor:** `px-wc-requests.php`
- **Namespace:** `Pixeler\Requests` (manuálny `require`, bez Composeru)
- **Prefix funkcií/meta/option:** `pxer_` / `_pxer_`
- **Vyžaduje:** WordPress + WooCommerce, PHP 8.0+

## Architektúra

```
px-wc-requests/
├── px-wc-requests.php   # konštanty, bootstrap, aktivácia (flush rewrite)
├── inc/
│   ├── Plugin.php               # singleton bootstrap, wiring, assets
│   ├── helpers.php              # globálne funkcie: pxer_get_request, IBAN mod-97, upload, summary
│   ├── CustomPostStatus.php     # generický registrar stavov (dropdown + bulk), ported
│   ├── RequestTypes.php         # REGISTRY typov (withdrawal/claim) – filter `pxer_request_types`
│   ├── FieldSchema.php          # typy polí: render/sanitize/validate + builders skupín polí
│   ├── RequestPostType.php      # 1 CPT `pxer_request`, dynamické stĺpce/metaboxy/stavy, N+1 cache
│   ├── Request.php              # model + pxer_get_request()
│   ├── RequestController.php    # AJAX submit, sanitize/validate, create, after-success
│   ├── Emails.php               # registrácia WC emailov + transition + attachments
│   ├── emails/                  # WC_Email triedy (admin-new, customer-status)
│   ├── Settings.php             # WC settings tab (page mapping per typ)
│   ├── Shortcodes.php           # [pxer_request_form type="..."] + order-search 2FA
│   └── TemplateLoader.php       # pxer_get_template(_html) – override v téme
├── templates/                   # request-form, order-search-form, emails/(plain/)
└── assets/                      # ajax-form.js (AJAX + toggle dôvodov), ajax-form.css
```

### Tok žiadosti
1. Shortcode `[pxer_request_form type="withdrawal"]` → ak nie je `?key`, zobrazí
   order-search (e-mail + číslo objednávky).
2. Po odoslaní vyhľadávania sa overí zhoda e-mailu s objednávkou → redirect s
   `?key={order_key}` (2FA na úrovni vlastníctva objednávky). **Redirect beží na
   `template_redirect`** (`Shortcodes::maybe_redirect_search`), NIE v shortcode —
   inak by `wp_safe_redirect()` zlyhal na „headers already sent" (page buildery
   ako Elementor renderujú obsah až po odoslaní hlavičiek → prázdna stránka).
3. S platným `?key` sa vykreslí formulár (polia zo schémy typu, predvyplnené z
   objednávky).
4. AJAX (`action=pxer_submit_request`) → nonce → sanitize/validate (vrátane IBAN
   mod-97 a kontroly fotiek) → vytvorí `pxer_request` v default stave typu.
5. `after_success` → admin e-mail + poznámka k objednávke. Zmena stavu v admine →
   e-mail zákazníkovi (`transition_post_status`).

## Meta kľúče (CPT `pxer_request`)
- `_pxer_type` – id typu (`withdrawal` | `claim` | vlastný)
- `_pxer_order_id` – ID objednávky
- `_pxer_data` – pole všetkých hodnôt polí (vrátane `items`, `iban`, `account_name`)
- `_pxer_images` – pole attachment ID (typy s `file` poľom)

## Stavy
Lean zdieľaná sada pre oba typy (`RequestTypes::default_statuses()`):
`pxer_received` (Prijatá) → `pxer_in_progress` (Rieši sa) →
`pxer_resolved` (Vybavená) / `pxer_rejected` (Zamietnutá). Default: `pxer_received`.

Registrované globálne ako post statusy. **Zmena stavu v detaile**: dedikovaný
metabox „Status" (`render_status_metabox`) so selectom; uloží sa cez filter
`wp_insert_post_data` (`apply_status_on_save`, nonce `pxer_save_meta`), takže
transition (e-mail zákazníkovi + log poznámka) prebehne raz. WP publish-box
dropdown je pre tento CPT **vypnutý** (`CustomPostStatus` arg `_submitbox=false`),
aby bol jediný ovládač. Bulk zmena stavu v zozname ostáva (`CustomPostStatus`).
**Dôležité príznaky stavov** (`status_args`):
- `protected => true` + `show_in_admin_all_list => true` — aby boli v admin „Všetky".
- `exclude_from_search => false` — **nutné**, inak `post_status => 'any'` (používa
  každý dotaz na žiadosti: Moje žiadosti, história, GDPR, backfill, duplicity)
  tieto stavy vylúči a vráti prázdno. CPT je neverejný, takže do front-end
  vyhľadávania sa žiadosti aj tak nedostanú.

**Úprava stavov bez redefinovania typu** – filter `pxer_request_statuses`:
```php
add_filter( 'pxer_request_statuses', function ( $statuses, $type_id ) {
    if ( 'claim' === $type_id ) {
        $statuses['pxer_shipped_back'] = 'Tovar vrátený'; // pridať
        unset( $statuses['pxer_in_progress'] );           // odobrať
    }
    return $statuses;
}, 10, 2 );
```
(Alternatívne celé pole `statuses` prepíšeš cez `pxer_request_types`.)

## Rozšíriteľnosť (pre iný e-shop)

**Pridať/upraviť typ** (napr. vrátenie tovaru):
```php
add_filter( 'pxer_request_types', function ( $types ) {
    $types['return'] = array(
        'label'        => 'Vrátenie tovaru',
        'label_plural' => 'Vrátenia tovaru',
        'item_mode'    => 'multiple',
        'statuses'     => array( 'pxer_received' => 'Prijatá', 'pxer_completed' => 'Vybavená' ),
        'default_status' => 'pxer_received',
        'fields'       => array_merge(
            array( \Pixeler\Requests\FieldSchema::order_items_field( 'multiple', false ) ),
            \Pixeler\Requests\FieldSchema::customer_fields(),
            \Pixeler\Requests\FieldSchema::bank_fields(),
            array(
                // doménovo špecifické pole len pre tento e-shop:
                \Pixeler\Requests\FieldSchema::normalize( array(
                    'key' => 'is_rolled', 'type' => 'checkbox',
                    'label' => 'Matrac je zrolovaný', 'show_in' => array( 'form', 'admin', 'email' ),
                ) ),
                \Pixeler\Requests\FieldSchema::consent_field(),
            )
        ),
    );
    return $types;
} );
```

**Pridať pole do existujúceho typu** – ten istý filter, doplň do `fields`.

**Typy polí:** `text`, `email`, `tel`, `textarea`, `checkbox`, `iban`,
`order_items` (`item_mode` single|multiple), `file` (obrázky). Nový typ poľa =
rozšíriť `FieldSchema::render_field/sanitize_value`.

### Hooky
- `pxer_request_types` (filter) – registry typov
- `pxer_settings` (filter) – polia WC settings tabu
- `pxer_request_data` (filter, `$data,$type`) – dáta tesne pred uložením
- `pxer_request_created` (action, `$request_id,$order_id,$type`) – po vytvorení

## Shortcode
`[pxer_request_form type="withdrawal"]` / `[pxer_request_form type="claim"]`
(stránku priraď v **WooCommerce → Nastavenia → Žiadosti zákazníkov**).

## Emaily
Telo každého emailu obsahuje súhrn žiadosti cez `pxer_render_request_summary()`
(typ, číslo, stav, dátum, objednávka, polia ako IBAN/meno + položky). Summary je
nezávislé od existencie objednávky (ak chýba, vykreslí uložené dáta).
- `Pxer_Admin_Request_Email` (id `pxer_admin_request`) – adminovi pri novej žiadosti, prikladá fotky
- `Pxer_Customer_Request_Email` (id `pxer_customer_request`) – **potvrdenie zákazníkovi** pri odoslaní
- `Pxer_Customer_Status_Email` (id `pxer_customer_status`) – zákazníkovi pri **zmene** stavu (nie pri vytvorení)
- `Pxer_Customer_Note_Email` (id `pxer_customer_note`) – zákazníkovi pri poznámke preňho

**Pozor:** všetky zákaznícke e-maily sa spúšťajú **explicitne** cez
`WC()->mailer()->get_emails()` (`after_success`, `on_transition`,
`on_customer_note`) — NIE cez `add_action` v konštruktore e-mailovej triedy.
Dôvod: v admin-ajax (pridanie poznámky) WC mailer ešte nie je načítaný, takže
poslucháč z konštruktora by nebol aktívny → e-mail by sa neodoslal. Recipient =
`_pxer_data['email']` žiadosti.

Spúšťanie: admin + potvrdenie v `RequestController::after_success()`; status-change cez
`transition_post_status` (len medzi registrovanými stavmi, nie pri vytvorení). Zapnutie/texty:
WooCommerce → Nastavenia → E-maily. Per-typ vypnutie cez `emails` kľúč v definícii typu.

**Odkaz na formulár v zákazníckych e-mailoch** (`Emails::inject_links`, hook
`woocommerce_email_order_meta`): per typ sa dá vybrať, do ktorých WC zákazníckych
e-mailov sa na koniec pridá odkaz na formulár (predvyplnený objednávkou cez
`?key={order_key}`). Nastavenie **Customer requests → Form links in e-mails**
(`pxer_{type}_inject_emails`, multiselect). Odkaz sa zobrazí len ak má typ stránku
formulára a objednávka je ešte v lehote (`Eligibility::gate`). Vlastné `pxer_`
e-maily sú z výberu vylúčené.

## Template override
Skopíruj súbor z `templates/` do `yourtheme/px-wc-requests/<súbor>`.

## Migrátor z WPify (`inc/Migrator.php`)
Admin nástroj (`manage_woocommerce`) — číta WPify tabuľku
`{prefix}wpify_woo_requests` (len na čítanie) a vytvára `pxer_request`.
- **Prístup cez nastavenia, len keď je WPify aktívny**: stránka importéra je
  zaregistrovaná, ale **skrytá z menu WooCommerce** (`remove_submenu_page`).
  Odkaz naň (tlačidlo „Open importer") sa pridáva do karty **WooCommerce →
  Nastavenia → Customer requests** cez filter `pxer_settings`. Stránka aj odkaz
  sa zobrazia **iba ak je WPify Woo aktívny** — `Migrator::is_wpify_active()`
  (test `wpify_woo()` / triedy `WpifyWoo\Plugin`).
- **Nedeštruktívne + idempotentné**: každá importovaná žiadosť má
  `_pxer_migrated_from = {wpify_id}`; re-run preskočí už importované.
- **Dávkové cez AJAX** (`pxer_migrate_batch`, BATCH=50) + **dry-run** + súhrn
  (importované/preskočené/chyby). JS: `assets/admin-migrate.js`.
- **Mapovanie**: `request_type→_pxer_type` (rovnaké slugy), `customer_name`→
  firstname/lastname, `items_json`/`scope=whole_order`→`_pxer_data['items']`,
  `reason`→reason položiek + poznámka, status→volený cieľový stav (default
  `pxer_received`). IBAN/fotky WPify nemá → prázdne. Vlastník cez
  `pxer_resolve_owner_id`.
- **Neposiela e-maily** (používa `wp_insert_post`, nie `RequestController::create`).

## REST API (`inc/RestApi.php`)
Namespace `px-wc-requests/v1` pre headless/JS frontend:
- `GET /eligibility` (`order_key` alebo prihlásený `order_id`, `type`) → oprávnené
  položky, termín, právne poučenie.
- `POST /requests` → odoslanie (JSON aj multipart). Order sa **resolvuje
  serverovo** z `order_key` (2FA pre hostí) alebo z objednávky prihláseného
  vlastníka — klientskemu `order_id` sa neverí. Honeypot/time-trap sa pre REST
  vynechá (UI-specifické); rate-limit a max/objednávku platia.
- `GET /requests`, `GET /requests/{id}` (prihlásený + `MyAccount::user_owns_request`)
  → zoznam a detail s históriou viditeľnou pre zákazníka.

Zdieľané jadro odoslania: `RequestController::submit( $params, $type, $check_bot )`
volá AJAX aj REST (žiadna duplicita).

## EU mechanika (smernica 2023/2673) — bez právnej záruky
- **Začiatok lehoty = doručenie** cez `period.start_statuses` (default `completed`).
- **Lehota na vrátenie peňazí**: typ má `refund_due_days` (withdrawal = 14). Pri
  vytvorení sa uloží `_pxer_refund_due` a pridá interná poznámka so zákonným
  termínom refundu.
- **Právne poučenie na formulári**: typ má `legal_notice` (default text pre
  withdrawal/claim), konfigurovateľné v nastaveniach (`pxer_{type}_legal_notice`,
  `Settings::get_legal_notice`). Vykreslené na formulári + vrátené v REST
  `/eligibility`.
- **Výnimky z práva na odstúpenie**: per-produkt `_pxer_{type}_excluded` (už existuje).
- ⚠️ Texty a logiku **musí odobriť právnik** — plugin dodáva nástroje, nie právnu istotu.

## Vymáhanie lehôt (Eligibility)

`inc/Eligibility.php` vyhodnocuje, či je typ žiadosti ešte otvorený, **podľa
dátumu dokončenia objednávky** (fallback: zaplatené → vytvorené).
- withdrawal = N **dní**, claim = N **mesiacov** (predvolene 14 / 24).
- Štart sa počíta od stavu objednávky v `start_statuses` (predvolene `completed`).
- Konfigurácia: **WooCommerce → Nastavenia → Customer requests → Periods & deadlines**
  (`pxer_{type}_period_amount`, `pxer_{type}_period_start_statuses`), s fallbackom
  na config typu.
- **Per-produkt** (Product data → General): vylúčenie z typu
  (`_pxer_{type}_excluded`) a override lehoty (`_pxer_{type}_period_override`).
- Enforcement: `RequestController::show_form` (gate + zobrazí len oprávnené
  položky) aj `validate()` (server-side, aj keď je formulár zastaraný).
- Filtre: `pxer_request_period_end`, `pxer_is_item_eligible`, `pxer_eligible_items`.

## Poznámky k žiadosti (história)
`inc/RequestNotes.php` – poznámky ako vo WooCommerce objednávkach. Uložené ako WP
komentáre typu `pxer_request_note` na príspevku žiadosti; poznámka pre zákazníka
má comment meta `is_customer_note` a spustí e‑mail zákazníkovi.
- Admin metabox „Request notes" (side) s históriou + formulár (Interná poznámka /
  Poznámka pre zákazníka) cez AJAX (pridať/zmazať).
- Poznámky sú **skryté z bežných comment query** (`comments_clauses`,
  `comment_feed_where`) – neukážu sa na frontende ani v admin Komentároch.
- E‑mail: `Pxer_Customer_Note_Email` (id `pxer_customer_note`), trigger cez akciu
  `pxer_request_customer_note_notification`.
- API: `RequestNotes::add_note( $request_id, $note, $is_customer_note )`,
  `get_notes()`, `delete_note()`. Akcie: `pxer_request_note_added`,
  `pxer_request_customer_note_notification`.

## Môj účet (My Account)
`inc/MyAccount.php`:
- **Tlačidlá pri objednávkach** (Môj účet → Objednávky) per typ. Text je
  konfigurovateľný v nastaveniach (sekcia „Button texts": `pxer_{type}_button_label`
  akčné tlačidlo, `pxer_{type}_submit_label` odosielacie; gettery
  `Settings::get_button_label/get_submit_label`). Default „Odstúpiť od zmluvy"
  (withdrawal) / „Reklamovať" (claim).
  Akčný text sa použije aj v odkazoch v e-mailoch. Zobrazia sa **iba ak** má typ nastavenú
  stránku formulára **a** `Eligibility::gate()` prejde. Odkaz vedie na formulár s
  `?key={order_key}`. Filter: `pxer_my_account_action`.
- **Záložka „Moje žiadosti"** – WC account endpoint. Slug je konfigurovateľný
  **natívne v** WooCommerce → Nastavenia → Pokročilé → *Koncové body účtu*
  (pole pridané cez filter `woocommerce_get_settings_advanced`,
  `MyAccount::add_account_endpoint_setting`, option `pxer_account_endpoint`,
  default `requests`). Čítanie cez `MyAccount::endpoint()` so `sanitize_title`;
  `maybe_flush` flushne pri zmene slugu aj verzie. So zoznamom
  žiadostí prihláseného zákazníka. Tabuľka používa BEM triedy WC objednávok
  (`woocommerce-orders-table__row/__cell/__header`), aby ju page-buildery (napr.
  Elementor) oštýlovali rovnako ako objednávky. Výpis je **jeden lacný meta dotaz** podľa
  `_pxer_user_id` (= `order->get_customer_id()`, fallback na prihláseného
  odosielateľa / e‑mail; fallback na objednávky účtu len keď je výsledok prázdny).
  Staré žiadosti bez meta sa **raz** doplnia cez `Plugin::maybe_upgrade()`
  (guard `pxer_data_version`). Šablóna `templates/myaccount/requests.php`.
- **Titulok stránky** – filter `woocommerce_endpoint_{slug}_title`
  (`MyAccount::endpoint_title`). Bez neho `WC_Query::get_endpoint_title()` vráti
  pre cudzí endpoint `''` a `wc_page_endpoint_title()` spadne na názov stránky
  „Môj účet" — každá obrazovka žiadostí mala rovnaký nadpis ako sesterské taby
  svoj vlastný. **Pozor:** argument `$action` toho filtra je WooCommerce
  `$_GET['action']`, **nie** hodnota nášho endpointu — ID pre detail sa musí
  čítať z `get_query_var( self::endpoint() )`, rovnako ako v `render_endpoint()`.
- **Detail žiadosti** – `…/{endpoint}/{id}/` (`render_detail`, kontrola
  `user_owns_request`). Zobrazí súhrn (`pxer_render_request_summary`) + **časovú os**
  zmien stavov a **zákazníckych** poznámok. Interné poznámky sa nezobrazujú –
  `RequestNotes::get_notes( $id, true )` vracia len `is_customer_note` alebo
  `_pxer_status_log` (flag pridaný v `log_status_change`). Šablóna
  `templates/myaccount/request-detail.php`. Rewrite rules sa flushnú raz za verziu
  (`maybe_flush`).
- **Kompatibilita s Elementor „My Account" widgetom**: Elementor scope-uje
  štýlovanie `.woocommerce-MyAccount-content-wrapper` len na svoje známe endpointy
  (`.e-my-account-tab__orders/__downloads/…`). Náš endpoint dostane
  `.e-my-account-tab__{slug}`, ktorý kompilované CSS nepozná → dizajn sa neaplikuje.
  `render_endpoint()` preto cez inline skript (`borrow_account_tab_class`) prepožičia
  tabu triedu `e-my-account-tab__orders` (zmena/vypnutie filtrom `pxer_account_tab_class`).
  Wrapper `<div>` pridáva sám Elementor (`woocommerce_account_content`), preto ho
  nevykresľujeme. Poistka `enqueue_account_assets()` doenqueuene štýl widgetu
  (`widget-woocommerce-my-account`, filter `pxer_account_style_handles`), ak chýba.
  Všetko sú no-opy bez Elementoru.

## Anti-spam (`inc/Security.php`)
Ochrana verejného formulára: honeypot (`pxer_homepage`), time-trap
(`pxer_tt`, HMAC + min. čas vyplnenia), per-IP rate limit (transient),
max žiadostí na objednávku a detekcia duplicít (fingerprint typ+objednávka+položky).
Bot → tiché „úspešné" zlyhanie. Skryté polia: `Security::render_hidden_fields()`
vo formulári; kontroly v `RequestController::ajax_submit()`. Nastavenia v
**WooCommerce → Nastavenia → Customer requests → Security & anti-spam**
(`pxer_max_requests_per_order`, `pxer_min_fill_seconds`, `pxer_rate_limit_ip_hour`,
`pxer_duplicate_window_hours`; 0 = vypnuté).

## GDPR / súkromie
- **Export & výmaz** (`inc/Privacy.php`) – registruje WP personal-data exporter +
  eraser (skupina „Customer requests"), vyhľadáva podľa `_pxer_email`. Výmaz
  anonymizuje osobné polia v `_pxer_data`.
- **Maskovanie IBAN** v admin zozname (`pxer_mask_iban()`); plný IBAN len v detaile.
- **Privátne prílohy** (`inc/PrivateFiles.php`) – fotky sa nahrávajú do
  `uploads/pxer-private/` (.htaccess deny) a zobrazujú len cez chránený endpoint
  `admin-post.php?action=pxer_view_file` (kontrola `edit_post`). Email prílohy
  fungujú ďalej (cez serverovú cestu). Attachment meta `_pxer_private`.
  Pozn.: .htaccess chráni Apache; na Nginx treba pridať pravidlo na deny pre tento
  adresár.

### Ďalšie meta kľúče
`_pxer_email` (queryable e-mail pre GDPR), `_pxer_user_id` (vlastník pre Môj účet),
`_pxer_private` (príznak privátnej prílohy).

## Lokalizácia
- **Zdrojový jazyk = EN.** Text domain `px-wc-requests`.
- `languages/px-wc-requests.pot` (šablóna), `…-sk_SK.po` + `.mo` (SK).
- Regenerácia po zmene reťazcov: `php languages/build-translations.php`
  (extrahuje msgid zo zdroja, vypíše chýbajúce SK preklady, skompiluje `.mo`).
  SK slovník je priamo v tom skripte.

## Vedome NEimplementované (zatiaľ)
- **Migrácia** zo starého `pixeler-eshop-addons` – zámerne žiadna.
- **QR platba z IBAN** (Pay by Square) – navrhnuté, zatiaľ neimplementované.
- **Gutenberg bloky** pre formulár – zatiaľ len shortcode + REST.
- **PHPUnit testy** – zatiaľ len ad-hoc overenia.

## Build/Test
Bez build pipeline. PHP lint: `php -l <súbor>`.

## Vydávanie a aktualizácie
Plugin sa distribuuje z verejného repa `pixeler-sk/px-wc-requests`; klientske
weby ho aktualizujú cez bežnú WordPress aktualizáciu (`inc/Updater.php`,
Plugin Update Checker 5.7 vo `vendor/`). Celý postup je v `RELEASING.md`.

**Verzia je na troch miestach a musia sedieť** — hlavička `px-wc-requests.php`,
konštanta `PXER_VERSION` a `Stable tag` v `readme.txt`. CI to pri tagu kontroluje
a release odmietne, ak nesedia alebo ak `readme.txt` nemá sekciu `= X.Y.Z =`.

Changelog sa píše **do `readme.txt`**, nie do samostatného CHANGELOG.md —
WordPress z neho skladá okno „Zobraziť podrobnosti" a CI z neho berie popis
GitHub releasu.

Aktualizácia sa ponúkne len z **zipu priloženého k releasu**
(`REQUIRE_RELEASE_ASSETS`), takže release vytvorený ručne bez assetu je pre
klientov neviditeľný. Vydávaj vždy tagom.
```
