# Automatická refundácia pri odstúpení od zmluvy — návrh

**Stav:** implementované (2026-08-05) v `inc/Refunds.php`, s týmito odchýlkami
od návrhu nižšie:

- **`mode` (confirm/auto) sa nerealizoval** — žiadny metabox s tlačidlom.
  Refundácia sa vytvára automaticky pri prechode žiadosti do stavu zvoleného
  v nastaveniach (**Customer requests → Automatic refunds**,
  `pxer_{type}_refund_status`, default vypnuté). Potvrdením je samotná zmena
  stavu v admine; refund pri vytvorení žiadosti nikdy nevznikne (guard na
  reálne prechody medzi registrovanými stavmi).
- **`refund_payment` je vždy `false`** — peniaze sa cez bránu neposielajú nikdy,
  ani pri karte (rozhodnutie 2026-08-05, bezpečnosť). Refund sa iba zaeviduje;
  reálny presun peňazí je vždy manuálny.
- **`trigger_status` z konfigu typu nahradilo nastavenie v admine** — blok
  `refund` v type má už len `enabled` / `restock` / `include_shipping`.
- **Zlyhanie** sa loguje ako interná poznámka žiadosti (`RequestNotes`), nielen
  ako chyba adminovi; `_pxer_refund_id` sa neuloží, takže opakovanie funguje.
  Ak admin refund v objednávke zmaže, opätovný prechod do stavu vytvorí nový.

Zvyšok dokumentu je pôvodný návrh (2026-07-31) — výpočty, poistky a chybové
stavy platia tak, ako sú implementované.

## Cieľ

Keď je žiadosť o odstúpenie od zmluvy vybavená, vytvoriť vo WooCommerce refundáciu
presne na tie položky a množstvá, ktoré zákazník v žiadosti uviedol — bez ručného
prepisovania súm do refund formulára objednávky.

Hodnota nie je v tom, že peniaze odídu samé. Je v tom, že **refundácia je správne
zaevidovaná** (sumy, DPH, reporty, stav objednávky) na jedno kliknutie namiesto
prepisovania čísel medzi dvoma obrazovkami.

## Odsúhlasené

| Rozhodnutie | Dôvod |
|---|---|
| Iba typ `withdrawal` | Reklamácia môže znamenať opravu alebo výmenu, nie vrátenie peňazí. Rieši sa samostatne. |
| Stav objednávky = core `wc-refunded` | WooCommerce ho pri plnom refunde nastaví sám. Vlastný stav „Zrušená - vrátená“ v `px-wc-order-shipped-status` sa preto **robiť nebude**. |
| Skutočné vrátenie peňazí len pri karte | Dobierka a prevod sa cez bránu refundovať nedajú — refund sa iba zaeviduje a peniaze pošle účtovník ručne na IBAN zo žiadosti. |
| Naskladnenie vypnuté | Na spimsi.sk sa sklad matracov nerieši. V konfigurácii ostáva, lebo plugin je univerzálny. |

## Nezahrnuté

- reklamácie (`claim`)
- automatické opätovné stiahnutie peňazí
- správa skladu nad rámec prepínača `restock`
- vlastná zmena stavu objednávky — necháva sa výhradne na WooCommerce

## Zapojenie do architektúry

Nová trieda `Pixeler\Requests\Refunds` v `inc/Refunds.php` so `setup()`, zaregistrovaná
v `Plugin::__construct()` medzi ostatné. Konfigurácia ide do `RequestTypes::normalize()`
ako blok `refund`, presne tak ako existujúci blok `emails`. Konkrétny e-shop si to
zapína cez `pxer_request_types`.

## Konfigurácia

```php
'refund' => array(
	'enabled'          => false,          // opt-in per typ žiadosti
	'trigger_status'   => 'pxer_resolved',
	'mode'             => 'confirm',      // confirm | auto
	'refund_payment'   => 'if_supported', // if_supported | never
	'restock'          => false,
	'include_shipping' => 'if_full',      // never | if_full | always
),
```

Spimsi (`withdrawal`): `enabled => true`, zvyšok defaulty.

## Priebeh

### `mode = confirm` (default, odporúčané)

1. Admin prepne žiadosť na `trigger_status`.
2. Na detaile žiadosti sa v metaboxe **Refundácia** zobrazí predpočítaný súhrn: položky,
   množstvá, suma bez DPH / DPH / spolu, či sa vracia doprava, či brána podporuje
   automatický refund.
3. Admin klikne **Vytvoriť refundáciu**. Až vtedy sa volá `wc_create_refund()`.

Prečo nie tichá automatika: refundácia je nevratná operácia s peniazmi spúšťaná zmenou
selectu v administrácii. Mis-click znamená odoslané peniaze, ktoré sa nedajú vziať späť —
zákazníka by bolo treba znova zaťažiť. Potvrdenie stojí jedno kliknutie a odstráni celú
triedu chýb. Pre e-shopy, ktoré to chcú bez potvrdenia, ostáva `mode = auto`.

### `mode = auto`

Refund sa vytvorí priamo v `transition_post_status` na CPT žiadosti — tam, kde už
`Emails.php` rieši notifikáciu o zmene stavu. Rovnaké výpočty aj poistky.

## Výpočet refundovanej sumy

Pre každú položku žiadosti (`$data['items'][ $item_id ]['quantity']`):

```php
$item     = $order->get_item( $item_id );
$already  = abs( $order->get_qty_refunded_for_item( $item_id ) );
$qty      = min( $requested_qty, $item->get_quantity() - $already );
$unit_net = $item->get_total() / $item->get_quantity();   // po zľave!
```

Na čo si dať pozor:

- **`get_total()`, nie cena produktu.** `get_total()` je suma po zľave. Pri kupónoch by
  cena produktu vrátila viac, než zákazník reálne zaplatil.
- **DPH** z `$item->get_taxes()['total']` — je to pole podľa `rate_id`, treba ho rozrátať
  na kus a poslať do `line_items` ako `refund_tax`.
- **Odčítať už refundované množstvá.** `wc_create_refund()` validuje
  `$args['amount'] > $order->get_remaining_refund_amount()` a vráti `WP_Error`.
- Ak vyjde `$qty <= 0`, položku preskočiť. Ak neostane nič, refund nevytvárať.

Overené API na `WC_Order` (WooCommerce 10.8):
`get_qty_refunded_for_item()`, `get_total_refunded_for_item()`,
`get_remaining_refund_amount()`, `get_item_count_refunded()`.

### Doprava

`include_shipping => 'if_full'`: doprava sa pridá len vtedy, keď žiadosť pokrýva všetky
zostávajúce položky objednávky. Shipping lines sú tiež order items, idú do `line_items`
rovnakým spôsobom.

**Právny kontext:** podľa §9 ods. 3 zákona 102/2014 Z. z. sa pri odstúpení vracia aj
*najlacnejšia bežná* doprava, ktorú e-shop ponúka — nie nutne tá, ktorú si zákazník
zvolil. Ak si priplatil za expresné doručenie, rozdiel sa nevracia. Viď otvorenú
otázku č. 1.

## Platba

```php
$gateway  = wc_get_payment_gateway_by_order( $order );
$can_auto = $gateway && $gateway->supports( 'refunds' ) && $order->get_transaction_id();
```

- `$can_auto` → `refund_payment => true`
- inak → `false`; refund sa iba zaeviduje a v metaboxe sa uvedie, že peniaze treba
  poslať ručne

Pri zlyhaní brány `wc_create_refund()` refund objekt **sám zmaže** a vráti `WP_Error`
(overené v `wc-order-functions.php`). Preto je bezpečné operáciu opakovať — stačí
neuložiť príznak o refundácii a chybu zobraziť adminovi.

### IBAN a osobné údaje

IBAN je v dátach žiadosti (`FieldSchema::bank_fields()`). **Nekopírovať ho do poznámky
objednávky** — poznámky sa zobrazujú a exportujú inde a `Privacy.php` by ho tam pri
erasure nenašiel. V metaboxe a poznámke stačí odkaz na žiadosť.

## Poistky

- **Idempotencia:** meta `_pxer_refund_id` na žiadosti. Skontrolovať pred vytvorením,
  uložiť po ňom. Prepnutie stavu tam a späť už druhý refund nevytvorí.
- **Oprávnenia:** `current_user_can( 'edit_shop_orders' )`.
- **Nonce** na tlačidle v metaboxe.
- **Objednávka musí existovať** a mať `get_remaining_refund_amount() > 0`.
- **Audit:** interná poznámka k objednávke („Refundácia #123 vytvorená zo žiadosti #456“)
  aj k žiadosti cez `RequestNotes`.

## Chybové stavy

| Situácia | Správanie |
|---|---|
| Brána odmietne refund | `WP_Error` adminovi, `_pxer_refund_id` sa neuloží, dá sa opakovať |
| Objednávka už plne refundovaná | Tlačidlo skryté, info v metaboxe |
| Položka už čiastočne refundovaná | Množstvo sa zníži o už refundované |
| Objednávka zmazaná / nenájdená | Metabox zobrazí upozornenie, nič sa nevytvára |
| Vypočítaná suma je 0 | Refund sa nevytvára |
| Žiadosť nemá vyplnené položky | Refund sa nevytvára |

## Dotknuté súbory

| Súbor | Zmena |
|---|---|
| `inc/Refunds.php` | nový — výpočet, vytvorenie, hooky |
| `inc/RefundMetaBox.php` | nový — metabox s potvrdením (alebo do `RequestPostType.php`) |
| `inc/RequestTypes.php` | blok `refund` v `normalize()` defaults |
| `inc/Plugin.php` | `( new Refunds() )->setup();` |
| `CLAUDE.md` | sekcia o refundáciách |

## Otvorené otázky

1. **Doprava pri plnom odstúpení** — vracať skutočne zaplatenú, alebo najlacnejšiu bežnú
   (zákonné minimum)? Potrebné rozhodnutie od klienta.
2. **Čiastočné odstúpenie** — vytvárať refund aj keď žiadosť pokrýva len časť položiek,
   alebo len pri odstúpení od celej objednávky?
3. **Časový limit brány** — karta refundovateľná typicky do 180 dní. Po limite fallback
   na ručné vrátenie; treba overiť správanie konkrétnej brány.
4. **Pripomienka** — má sa adminovi pripomenúť nevytvorená refundácia po X dňoch?
   `refund_due_days` v konfigurácii typu už existuje a dal by sa na to naviazať.

## Testovací scenár

1. Objednávka s 2 položkami, dobierka → žiadosť na 1 položku → potvrdenie → refund
   zaevidovaný, `refund_payment` false, objednávka ostáva v pôvodnom stave s čiastočnou
   refundáciou.
2. Tá istá objednávka, žiadosť na všetky položky + doprava → objednávka prejde na
   `wc-refunded` (nastaví WooCommerce sám).
3. Objednávka platená kartou cez bránu s podporou refundov → `refund_payment` true,
   peniaze reálne odídu.
4. Dvojité prepnutie stavu žiadosti → druhý refund sa nevytvorí.
5. Brána vráti chybu → žiadosť neoznačená ako refundovaná, operáciu možno opakovať.
6. Objednávka s kupónom → refundovaná suma zodpovedá zaplatenej, nie cenníkovej.
