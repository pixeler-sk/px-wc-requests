# Vydávanie verzií

Plugin nie je na wordpress.org. Distribuuje sa z verejného repozitára
[pixeler-sk/px-wc-requests](https://github.com/pixeler-sk/px-wc-requests) a na
klientskych weboch sa aktualizuje cez bežnú WordPress aktualizáciu.

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
