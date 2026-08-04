# Vydávanie a nasadzovanie

Plugin nie je na wordpress.org. Distribuuje sa z verejného repozitára
[pixeler-sk/px-wc-requests](https://github.com/pixeler-sk/px-wc-requests) a na
klientskych weboch sa aktualizuje cez bežnú WordPress aktualizáciu.

- **Vydanie novej verzie** → [Postup](#postup)
- **Prvé nasadenie na web, ktorý plugin už má** → [Prvé nasadenie](#prvé-nasadenie-na-existujúci-web)

## Ako to funguje

```
  git tag v1.2.2 → push
        │
        ▼
  GitHub Action (.github/workflows/release.yml)
        ├─ overí, že verzia sedí na všetkých 3 miestach
        ├─ zostaví px-wc-requests-1.2.2.zip (bez vývojárskych súborov)
        └─ vytvorí GitHub release + priloží zip
        │
        ▼
  klientsky web: Plugin Update Checker sa raz za 12 h spýta GitHub API
        └─ nájde novšiu verziu → Nástenka → Aktualizácie
```

Na klientskych weboch nie sú žiadne tokeny ani prístupové údaje — repozitár je
verejný.

## Postup

1. **Zmeň verziu na troch miestach** (musia sedieť, CI to kontroluje):

   | Súbor | Riadok |
   |---|---|
   | `px-wc-requests.php` | `* Version: 1.2.2` (hlavička) |
   | `px-wc-requests.php` | `define( 'PXER_VERSION', '1.2.2' );` |
   | `readme.txt` | `Stable tag: 1.2.2` |

2. **Doplň changelog** do `readme.txt` — sekcia musí byť presne `= 1.2.2 =`,
   inak CI zlyhá. Text tejto sekcie sa stane popisom releasu na GitHube aj
   obsahom okna „Zobraziť podrobnosti" vo WordPresse.

3. **Commitni, otaguj, pushni:**

   ```bash
   git commit -am "Release 1.2.2"
   git tag v1.2.2
   git push origin main --tags
   ```

4. **Skontroluj**, že Action prebehla a release má priložený zip:

   ```bash
   gh release view v1.2.2
   ```

## Prvé nasadenie na existujúci web

Weby, kde plugin beží z čias pred zavedením aktualizácií, majú kód **bez**
`inc/Updater.php` — a teda sa nemajú ako dozvedieť, že existuje novšia verzia.
Prvú verziu s updaterom (1.2.1 a vyššie) tam preto treba dostať raz ručne.
**Od nej ďalej už aktualizácie chodia samé.**

Poradie krokov je rovnaké pre všetky weby: skontroluj → nasaď → over.

### 1. Pred nasadením skontroluj prepísané šablóny

```bash
ls wp-content/themes/<téma>/px-wc-requests/
```

Ak si téma niektorú šablónu prepisuje, **prepis vyhráva aj po aktualizácii** —
takže nová verzia sa v tom mieste neprejaví. Horší prípad: keď sa šablóna
v plugine medzitým premenovala, prepis sa prestane načítavať a nikde to nevypíše
chybu, len sa začne používať pôvodná šablóna z pluginu.

Konkrétne pri skoku z **1.0.0 na 1.2.1** sa premenovala šablóna e-mailu
s poznámkou:

| 1.0.0 | 1.2.1 |
|---|---|
| `emails/customer-note.php` | `emails/request-note.php` |
| `emails/plain/customer-note.php` | `emails/plain/request-note.php` |

Ak má téma override starého názvu, premenuj ho spolu s aktualizáciou.

### 2. Nasaď verziu

Plugin **nedeaktivuj** — dáta sú v databáze (CPT `pxer_request` + options),
takže prepis súborov o nič nepríde. Deaktivácia by len zbytočne zhodila
rewrite pravidlá.

**a) Cez WP admin — keď na web nemáš SSH (väčšina klientov)**

1. Stiahni zip z posledného releasu:
   https://github.com/pixeler-sk/px-wc-requests/releases/latest
2. Pluginy → Pridať nový → **Nahrať plugin** → vyber zip → Inštalovať
3. WordPress zistí, že plugin už existuje, a ukáže porovnanie starej a novej
   verzie → **Nahradiť súčasnú verziu nahranou**

**b) Cez WP-CLI — keď máš SSH**

```bash
wp plugin install \
  https://github.com/pixeler-sk/px-wc-requests/releases/latest/download/px-wc-requests-1.2.1.zip \
  --force
```

`--force` je nutné, inak WP-CLI iba oznámi, že plugin je už nainštalovaný.
Názov súboru v URL obsahuje verziu, takže ho pri ďalšom vydaní treba prepísať.

**c) Cez SFTP** — prepíš obsah `wp-content/plugins/px-wc-requests/`. Pozor na
zvyšky: ak nová verzia súbor odstránila, SFTP mirror ho tam nechá. Radšej
priečinok najprv vymaž a nahraj celý nanovo.

### 3. Čo sa spraví samo

Po prvom načítaní administrácie:

- **Rewrite pravidlá** sa preplachnú, keď sa zmení verzia alebo slug endpointu
  (`MyAccount::maybe_flush()`, podpis v option `pxer_rewrite_version`). Netreba
  chodiť do Nastavenia → Trvalé odkazy.
- **Dobehnú sa chýbajúce meta** na starých žiadostiach — vlastník a e-mail, aby
  fungovala záložka „Moje žiadosti" a GDPR export (`Plugin::maybe_upgrade()`,
  option `pxer_data_version`). Beží raz na schému a načíta všetky žiadosti
  naraz; na webe s tisíckami žiadostí bude prvé načítanie admina pomalšie.

### 4. Over, že to sedí

1. Pluginy → verzia pri `Pixeler Woo Requests` je tá nová.
2. Otvor jednu existujúcu žiadosť a záložku „Moje žiadosti" v účte zákazníka.

**A hlavne over, že sa web naozaj dovolá na GitHub.** Toto je jediná vec, ktorá
sa dá pokaziť ticho: keď hosting blokuje odchádzajúce HTTPS požiadavky na
`api.github.com`, plugin funguje normálne, len sa už nikdy nedozvie o novej
verzii.

Plugin Update Checker pridá do riadku pluginu na stránke Pluginy odkaz
**„Check for updates"**. Klikni naň a riaď sa farbou hlášky:

| Hláška | Znamená |
|---|---|
| 🟢 *The Pixeler Woo Requests plugin is up to date.* | ✅ web sa dovolal na GitHub, našiel release a porovnal verzie |
| 🟢 *A new version of the … plugin is available.* | ✅ funguje, je čo aktualizovať |
| 🔴 *Could not determine if updates are available for …* | ✗ nedovolal sa — zablokovaný výstup na internet alebo rate limit |

Samotná existencia toho odkazu dokazuje, že `inc/Updater.php` je nasadený;
farba hlášky dokazuje, že spojenie funguje.

Cez SSH sa to isté dá overiť aj z option, do ktorej si PUC zapisuje výsledok:

```bash
wp option get external_updates-px-wc-requests
```

Nedávny `lastCheck` = kontrola dobehla. Chýbajúca option = nikdy neprebehla.

**Jedna vec, ktorú zelená hláška nedokazuje.** Kvôli `REQUIRE_RELEASE_ASSETS`
sa „up to date" zobrazí aj vtedy, keby novší release existoval, ale nemal
priložený zip so sedícim názvom — tieto dva prípady sú z admina nerozlíšiteľné.
Že sa budúce vydania naozaj ponúknu, garantuje zhoda medzi názvom, ktorý skladá
CI (`px-wc-requests-<verzia>.zip`), a regexom v `inc/Updater.php`. Keď meníš
jedno, skontroluj druhé.

Ak treba potvrdiť, že sa aktualizácia naozaj **ponúkne**, zníž na jednom webe
dočasne verziu v hlavičke `px-wc-requests.php` na `1.2.0`, otvor Pluginy a klikni
na *Check for updates* — aktualizácia sa musí objaviť. Potom verziu vráť.

### 5. Ďalšie aktualizácie už ručne netreba

Plugin Update Checker sa pýta GitHub API **raz za 12 hodín** (a navyše pri
každom otvorení stránky Pluginy, najviac však raz za hodinu). Výsledok si kešuje,
takže nová verzia sa neobjaví hneď po vydaní. Vynútiť sa dá cez
Nástenka → Aktualizácie → *Skontrolovať znova*, alebo:

```bash
wp plugin update px-wc-requests
```

Aktualizácia sa **neinštaluje sama** — WordPress ju len ponúkne, klikať treba.
Ak to má chodiť bez zásahu, zapni pri plugine automatické aktualizácie
(Pluginy → *Povoliť automatické aktualizácie*).

Repozitár je verejný, takže požiadavky na GitHub API idú neprihlásené a platí pre
ne limit **60 požiadaviek za hodinu na IP**. Pri bežnej prevádzke sa to nedá
vyčerpať, ale na zdieľanom hostingu s desiatkami WordPressov za jednou IP sa
kontrola môže občas nepodariť. Nie je to porucha — nabudúce prejde.

### Prehľad webov

Doplň pri každom nasadení, nech je vidno, kde čo beží:

| Web | Verzia | Nasadené |
|---|---|---|
| libike.sk | ? | ✅ |
| elbe.sk | ? | ✅ |
| spimsi.sk | ? | ✅ |
| pneuvosovic.pixeler.sk (staging) | 1.2.1 | — |
| benab | 1.0.0 | čaká |

Verzia so `?` = beží tam, ale ktorá verzia nie je overené. Zistíš to na stránke
Pluginy daného webu, alebo cez `wp plugin list --name=px-wc-requests`.

## Grafika v okne „Zobraziť podrobnosti"

Banner a ikonu si Plugin Update Checker berie **z priečinka `assets/`
nainštalovanej kópie pluginu**, nie z GitHubu. Názvy súborov musia sedieť presne
(konvencia wordpress.org):

| Súbor | Kde sa zobrazí |
|---|---|
| `assets/banner-1544x500.png` | hlavička okna „Zobraziť podrobnosti" |
| `assets/icon-256x256.png` | riadky aktualizácií, Nástenka → Aktualizácie |
| `assets/icon-128x128.png` | to isté, bez retina displeja |

Dôsledok toho, že sa čítajú lokálne: **grafika sa na klientskom webe objaví až
po aktualizácii na verziu, ktorá ju obsahuje.** Nie je to niečo, čo sa dá
doplniť spätne bez vydania.

**Banner dodávaj len v jednej veľkosti.** Plugin Update Checker mapuje
`banner-772x250` na kľúč `high` a `banner-1544x500` na `low`, čo je oproti
konvencii wordpress.org naopak (tam je 772 „low" a 1544 „high"). Keby si dodal
obe, WordPress by na retina displeji ukázal menší obrázok a na bežnom väčší. Pri
jedinom súbore `banner-1544x500.png` si WordPress chýbajúci kľúč doplní z toho
druhého, takže sa v oboch prípadoch použije ostrá veľká verzia.

Grafiku generuje `scripts/make-assets.php` (`php scripts/make-assets.php`) —
zámerne jednoduchá, farby sú na začiatku súboru. Ak vznikne poriadne logo,
súbory v `assets/` sa dajú nahradiť a skript zmazať. Do release zipu sa
`scripts/` nebalí.

Záložky v tom istom okne (Popis, Inštalácia, FAQ, Changelog) vznikajú zo sekcií
`== Nadpis ==` v `readme.txt` — pridanie sekcie stačí na pridanie záložky.
Sekcia `== Screenshots ==` zmysel nemá: WordPress obrázky k nej dohľadáva na
serveroch wordpress.org, kde tento plugin nie je.

## Verzovanie

Semver v duchu, nie doslova:

- **patch** (1.2.**2**) — oprava chyby, nič sa nemení v správaní
- **minor** (1.**3**.0) — nová funkcia, spätne kompatibilná
- **major** (**2**.0.0) — zmena, ktorá vyžaduje zásah pri nasadení (migrácia dát,
  premenované filtre, iná štruktúra šablón)

## Prečo `REQUIRE_RELEASE_ASSETS`

`inc/Updater.php` vyžaduje, aby release mal priložený zip, a odmietne použiť
automaticky generovaný archív zdrojákov. Dôvod: ten archív obsahuje `.github/`,
`CLAUDE.md` a `RELEASING.md`, a jeho koreňový priečinok sa volá
`pixeler-sk-px-wc-requests-<sha>`.

Dôsledok, ktorý treba poznať: **release vytvorený ručne cez web bez priloženia
zipu sa klientom nikdy neponúkne.** Vždy vydávaj cez tag, nech to zostaví CI.

## Ako to isté nasadiť na ďalší plugin

Mechanizmus je zámerne bez závislostí na tomto plugine. Prenos na `px-shop-core`
alebo iný plugin:

1. Skopíruj `vendor/plugin-update-checker/`, `inc/Updater.php`,
   `.github/workflows/release.yml`, `readme.txt` a tento súbor.
2. V `inc/Updater.php` zmeň tri veci: `PXER_GITHUB_REPO`, slug v
   `buildUpdateChecker()` (musí sedieť s názvom priečinka pluginu) a regex na
   názov assetu.
3. V `release.yml` prepíš názov pluginu v `rsync`, `zip` a v kontrole verzií
   (`PXER_VERSION` → konštanta daného pluginu).
4. Napoj v hlavnom súbore pluginu: `require` + `add_action( 'init', ... )`.
   Na `init`, nie skôr — Plugin Update Checker používa preklady a WordPress 6.7+
   varuje pri načítaní textovej domény pred `init`.

Zoznam pluginov, ktoré tento mechanizmus majú:

| Plugin | Repozitár | Stav |
|---|---|---|
| `px-wc-requests` | `pixeler-sk/px-wc-requests` | ✅ |
| `px-shop-core` | `pixeler-sk/px-shop-core` | čaká |
| `px-batch-import-framework` | — | čaká |
| `px-multi-warehouse` | — | čaká |
