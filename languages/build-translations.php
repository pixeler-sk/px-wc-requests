<?php
/**
 * Translation build tool (run from CLI):  php languages/build-translations.php
 *
 * 1. Extracts msgids from the plugin source.
 * 2. Writes px-wc-requests.pot (English template).
 * 3. Writes + compiles px-wc-requests-sk_SK.po / .mo from the SK map below.
 *
 * Re-run after adding/changing translatable strings. Report lists any source
 * string missing a Slovak translation (it will fall back to English).
 *
 * @package Pixeler\Requests
 */

if ( 'cli' !== php_sapi_name() ) {
	exit( "CLI only.\n" );
}

$root      = dirname( __DIR__ );
$domain    = 'px-wc-requests';
$lang_dir  = __DIR__;

// ---------------------------------------------------------------------------
// 1. Extract msgids
// ---------------------------------------------------------------------------
$singulars = array();
$plurals   = array(); // [singular, plural]

$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $rii as $file ) {
	if ( $file->getExtension() !== 'php' || strpos( $file->getPathname(), DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR ) !== false ) {
		continue;
	}
	$src = file_get_contents( $file->getPathname() );

	if ( preg_match_all( '/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_x)\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $src, $m ) ) {
		foreach ( $m[1] as $s ) {
			$singulars[ stripcslashes( $s ) ] = true;
		}
	}
	if ( preg_match_all( '/\b_n\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $src, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $pair ) {
			$plurals[ stripcslashes( $pair[1] ) ] = stripcslashes( $pair[2] );
		}
	}
}
ksort( $singulars );

// ---------------------------------------------------------------------------
// 2. Slovak translations
// ---------------------------------------------------------------------------
$sk = array(
	// Types & statuses
	'Withdrawal from contract' => 'Odstúpenie od zmluvy',
	'Withdrawals from contract' => 'Odstúpenia od zmluvy',
	'Withdrawals' => 'Odstúpenia',
	'Received' => 'Prijatá',
	'In progress' => 'Rieši sa',
	'Resolved' => 'Vybavená',
	'Rejected' => 'Zamietnutá',
	'Warranty claim' => 'Reklamácia',
	'Warranty claims' => 'Reklamácie',
	'Claims' => 'Reklamácie',
	'Claim form' => 'Reklamačný formulár',
	// Settings: quick-create form page
	'Create page for %s' => 'Vytvoriť stránku pre %s',
	'Creating…' => 'Vytváram…',
	'Edit page' => 'Upraviť stránku',
	'View' => 'Zobraziť',
	'Form page created and selected.' => 'Stránka formulára bola vytvorená a predvybraná.',
	'Could not create the form page.' => 'Stránku formulára sa nepodarilo vytvoriť.',
	'Unknown request type.' => 'Neznámy typ žiadosti.',
	'Request form: %s' => 'Formulár žiadosti: %s',
	// My Account action buttons
	'Return' => 'Vrátiť',
	'Withdraw from contract' => 'Odstúpiť od zmluvy',
	'File a claim' => 'Reklamovať',
	// Fields
	'Reason' => 'Dôvod',
	'First name' => 'Meno',
	'Last name' => 'Priezvisko',
	'E-mail' => 'E-mail',
	'Phone' => 'Telefón',
	'Street and number' => 'Ulica a číslo',
	'Postcode' => 'PSČ',
	'City' => 'Mesto',
	'Account name / recipient' => 'Názov účtu / meno príjemcu',
	'IBAN' => 'IBAN',
	'for a possible refund' => 'pre prípadné vrátenie peňazí',
	'I agree with the processing of personal data' => 'Súhlasím so spracovaním osobných údajov',
	'Select items' => 'Vyberte tovar',
	'Describe the defect' => 'Popis závady',
	'Photos' => 'Fotografie',
	'Maximum size per photo is 7 MB (JPG, PNG)' => 'Maximálna veľkosť jednej fotografie je 7 MB (JPG, PNG)',
	'I agree with the %s.' => 'Súhlasím so %s.',
	'processing of personal data' => 'spracovaním osobných údajov',
	'Quantity:' => 'Množstvo:',
	// Summary / helpers
	'Product' => 'Produkt',
	'Quantity' => 'Množstvo',
	'Reason:' => 'Dôvod:',
	'Date created:' => 'Dátum vytvorenia:',
	'Order number:' => 'Číslo objednávky:',
	'Status:' => 'Stav:',
	'Item #%d' => 'Položka #%d',
	// CustomPostStatus
	'Change status to: %s' => 'Zmeniť stav na: %s',
	// Post type
	'Requests' => 'Žiadosti',
	'Request' => 'Žiadosť',
	'Customer requests' => 'Žiadosti zákazníkov',
	'Add request' => 'Pridať žiadosť',
	'New request' => 'Nová žiadosť',
	'Edit request' => 'Upraviť žiadosť',
	'View request' => 'Zobraziť žiadosť',
	'View requests' => 'Zobraziť žiadosti',
	'Search requests' => 'Hľadať žiadosti',
	'No requests found' => 'Žiadne žiadosti',
	'No requests in trash' => 'Žiadne žiadosti v koši',
	'Request updated.' => 'Žiadosť aktualizovaná.',
	'Request created.' => 'Žiadosť vytvorená.',
	'Request saved.' => 'Žiadosť uložená.',
	'Request submitted.' => 'Žiadosť odoslaná.',
	'Request draft updated.' => 'Koncept žiadosti aktualizovaný.',
	'Custom field updated.' => 'Vlastné pole aktualizované.',
	'Custom field deleted.' => 'Vlastné pole zmazané.',
	'Requests list' => 'Zoznam žiadostí',
	'Requests list navigation' => 'Navigácia v zozname žiadostí',
	'Filter requests list' => 'Filtrovať zoznam žiadostí',
	'Type' => 'Typ',
	'Order' => 'Objednávka',
	'Status' => 'Stav',
	'Contact' => 'Kontakt',
	'Items' => 'Položky',
	'Date' => 'Dátum',
	'All types' => 'Všetky typy',
	'Request details' => 'Údaje žiadosti',
	'Order number' => 'Číslo objednávky',
	'Order not found or has no items.' => 'Objednávka nebola nájdená alebo neobsahuje položky.',
	'Reason / description' => 'Dôvod / popis',
	'Max:' => 'Max:',
	'No photos' => 'Žiadne fotografie',
	// Eligibility
	'The period has not started yet — the order is not completed.' => 'Lehota zatiaľ nezačala — objednávka nie je dokončená.',
	'The deadline for this request has already passed (it ended on %s).' => 'Lehota na túto žiadosť už uplynula (skončila %s).',
	'There are no eligible items for this request.' => 'Pre túto žiadosť nie sú žiadne oprávnené položky.',
	// Product settings
	'months' => 'mesiace',
	'days' => 'dni',
	'Exclude from: %s' => 'Vylúčiť z: %s',
	'This product cannot be selected in this request type.' => 'Tento produkt nie je možné vybrať v tomto type žiadosti.',
	'Period override (%1$s, in %2$s)' => 'Override lehoty (%1$s, v %2$s)',
	'Leave empty to use the global period.' => 'Ponechajte prázdne pre použitie globálnej lehoty.',
	// Settings
	'Form pages' => 'Stránky formulárov',
	'Assign the page that contains the shortcode of each request type.' => 'Priraďte ku každému typu žiadosti stránku s jeho shortcode.',
	'Page: %s' => 'Stránka: %s',
	'Page containing the shortcode %s' => 'Stránka so shortcode %s',
	'Periods & deadlines' => 'Lehoty a termíny',
	'How long a request stays open, counted from the order completion date.' => 'Ako dlho zostáva žiadosť otvorená, počítané od dátumu dokončenia objednávky.',
	'Period: %1$s (in %2$s)' => 'Lehota: %1$s (v %2$s)',
	'Start counting from order status: %s' => 'Začať počítať od stavu objednávky: %s',
	'Order statuses that start the clock.' => 'Stavy objednávky, ktoré spúšťajú počítanie lehoty.',
	'— Select a page —' => '— Vyberte stránku —',
	'Available shortcodes' => 'Dostupné shortcodes',
	// Controller
	'Order not found.' => 'Objednávka nebola nájdená.',
	'Invalid request type.' => 'Neplatný typ žiadosti.',
	'Security check failed. Please reload the page and try again.' => 'Bezpečnostná kontrola zlyhala. Obnovte stránku a skúste znova.',
	'Your request has been submitted successfully.' => 'Vaša žiadosť bola úspešne odoslaná.',
	'Select at least one item.' => 'Vyberte aspoň jednu položku.',
	'One of the selected items is not eligible for this request.' => 'Jedna z vybraných položiek nie je oprávnená pre túto žiadosť.',
	'Please fill in the description for each selected item.' => 'Vyplňte popis ku každej vybranej položke.',
	'Please fill in the field: %s' => 'Vyplňte pole: %s',
	'Please enter a valid IBAN.' => 'Zadajte platný IBAN.',
	'Invalid file extension, allowed only: %s' => 'Neplatná prípona súboru, povolené iba: %s',
	'Invalid file type. Upload JPG or PNG only.' => 'Neplatný typ súboru. Nahrajte iba JPG alebo PNG.',
	'The uploaded file is not a valid image.' => 'Nahraný súbor nie je platný obrázok.',
	'The file must not be larger than %d MB.' => 'Súbor nesmie byť väčší ako %d MB.',
	'An error occurred while saving the request.' => 'Pri ukladaní žiadosti nastala chyba.',
	'%1$s no. %2$d' => '%1$s č. %2$d',
	'Customer request created: %1$s no. %2$d' => 'Vytvorená žiadosť zákazníka: %1$s č. %2$d',
	// Shortcodes
	'Unknown request type: %s' => 'Neznámy typ žiadosti: %s',
	'No order found with this e-mail and number.' => 'Nenájdená objednávka s týmto e-mailom a číslom.',
	// request-form template
	'Order no.:' => 'Objednávka č.:',
	'Created:' => 'Vytvorená:',
	'Deadline:' => 'Termín:',
	'Submit request' => 'Odoslať žiadosť',
	'Previous requests for this order' => 'Predchádzajúce žiadosti k tejto objednávke',
	// order-search-form template
	'To continue, enter your e-mail and order number.' => 'Pre pokračovanie zadajte e-mail a číslo objednávky.',
	'Continue' => 'Pokračovať',
	// Emails
	'New request (admin)' => 'Nová žiadosť (admin)',
	'Admin notification about a new customer request (withdrawal / claim).' => 'Notifikácia administrátorovi o novej žiadosti zákazníka (odstúpenie / reklamácia).',
	'New request: {request_type} no. {request_number}' => 'Nová žiadosť: {request_type} č. {request_number}',
	'Enable/Disable' => 'Povoliť/Zakázať',
	'Enable this email notification' => 'Povoliť túto emailovú notifikáciu',
	'Recipient(s)' => 'Príjemca(ovia)',
	'Comma-separated emails. Defaults to %s.' => 'Emaily oddelené čiarkou. Predvolene %s.',
	'Subject' => 'Predmet',
	'Email heading' => 'Nadpis e-mailu',
	'Email type' => 'Typ e-mailu',
	'Request status change' => 'Zmena stavu žiadosti',
	'Customer notification when their request status changes.' => 'Notifikácia zákazníkovi pri zmene stavu jeho žiadosti.',
	'Status of request {request_type} no. {request_number} has changed' => 'Stav žiadosti {request_type} č. {request_number} bol zmenený',
	'The status of your request %1$s no. %2$d has changed to:' => 'Stav vašej žiadosti %1$s č. %2$d bol zmenený na:',
	'The status of your request %1$s no. %2$d has changed to: %3$s' => 'Stav vašej žiadosti %1$s č. %2$d bol zmenený na: %3$s',
	'Type:' => 'Typ:',
	'Request number:' => 'Číslo žiadosti:',
	'Order:' => 'Objednávka:',
	'IBAN:' => 'IBAN:',
	'E-mail:' => 'E-mail:',
	// Main file
	'Pixeler Woo Requests requires the WooCommerce plugin to be active.' => 'Pixeler Woo Requests vyžaduje aktívny plugin WooCommerce.',
	// Request notes
	'System' => 'Systém',
	'Request notes' => 'Poznámky k žiadosti',
	'There are no notes yet.' => 'Zatiaľ žiadne poznámky.',
	'Add note' => 'Pridať poznámku',
	'Private note' => 'Interná poznámka',
	'Note to customer' => 'Poznámka pre zákazníka',
	'Add' => 'Pridať',
	'added on %1$s at %2$s by %3$s' => 'pridané %1$s o %2$s, %3$s',
	'note to customer' => 'poznámka pre zákazníka',
	'Delete' => 'Zmazať',
	'Delete this note?' => 'Zmazať túto poznámku?',
	'Request note' => 'Poznámka k žiadosti',
	'Sent to the customer when you add a note addressed to them.' => 'Odošle sa zákazníkovi pri pridaní poznámky určenej preňho.',
	'A note has been added to your request {request_type} no. {request_number}' => 'K vašej žiadosti {request_type} č. {request_number} bola pridaná poznámka',
	'A note regarding your request' => 'Poznámka k vašej žiadosti',
	'A note has been added to your request %1$s no. %2$d:' => 'K vašej žiadosti %1$s č. %2$d bola pridaná poznámka:',
	'Status changed from %1$s to %2$s.' => 'Stav zmenený z %1$s na %2$s.',
	'Request created with status: %s' => 'Žiadosť vytvorená so stavom: %s',
	// Customer confirmation email
	'Request confirmation' => 'Potvrdenie žiadosti',
	'Sent to the customer right after they submit a request.' => 'Odošle sa zákazníkovi hneď po odoslaní žiadosti.',
	'We have received your request {request_type} no. {request_number}' => 'Prijali sme vašu žiadosť {request_type} č. {request_number}',
	'We have received your request' => 'Vašu žiadosť sme prijali',
	'We have received your request %1$s no. %2$d and will get back to you soon.' => 'Prijali sme vašu žiadosť %1$s č. %2$d. Čoskoro sa vám ozveme.',
	// Email preview placeholders
	'This is a sample preview of the email.' => 'Toto je ukážkový náhľad emailu.',
	'Sample product' => 'Vzorový produkt',
	'Sample note text shown in the preview.' => 'Ukážkový text poznámky v náhľade.',
	// EU mechanics + REST
	'Statutory refund deadline: %s.' => 'Zákonná lehota na vrátenie peňazí: %s.',
	'You have the right to withdraw from this contract within 14 days without giving any reason. The withdrawal period starts on the day you take possession of the goods. You bear the direct cost of returning the goods. We will refund all payments within 14 days of being informed of your decision to withdraw.' => 'Máte právo odstúpiť od tejto zmluvy do 14 dní bez uvedenia dôvodu. Lehota na odstúpenie začína plynúť dňom prevzatia tovaru. Priame náklady na vrátenie tovaru znášate vy. Všetky platby vám vrátime do 14 dní odo dňa doručenia vášho rozhodnutia o odstúpení.',
	'The statutory warranty applies for 24 months from receipt of the goods. Please describe the defect as precisely as possible and attach photos if available.' => 'Zákonná záruka platí 24 mesiacov od prevzatia tovaru. Závadu prosím opíšte čo najpresnejšie a priložte fotografie, ak ich máte.',
	'Legal notice on forms' => 'Právne poučenie na formulároch',
	'Informational text shown on each request form (e.g. statutory withdrawal information). Have it reviewed by a lawyer.' => 'Informačný text zobrazený na každom formulári žiadosti (napr. poučenie o odstúpení). Nechajte si ho skontrolovať právnikom.',
	'Notice: %s' => 'Poučenie: %s',
	'Order not found or not accessible.' => 'Objednávka nebola nájdená alebo nie je prístupná.',
	// Security / anti-spam
	'Too many attempts. Please try again later.' => 'Príliš veľa pokusov. Skúste neskôr.',
	'The maximum number of requests for this order has been reached.' => 'Bol dosiahnutý maximálny počet žiadostí pre túto objednávku.',
	'You have already submitted an identical request recently.' => 'Identickú žiadosť ste nedávno už odoslali.',
	'Security & anti-spam' => 'Bezpečnosť a anti-spam',
	'Protect the public form from bots and abuse. Set 0 to disable a limit.' => 'Ochrana verejného formulára pred botmi a zneužitím. 0 = limit vypnutý.',
	'Max requests per order' => 'Max počet žiadostí na objednávku',
	'Minimum form fill time (seconds)' => 'Minimálny čas vyplnenia formulára (sekundy)',
	'Submissions faster than this are treated as bots.' => 'Rýchlejšie odoslania sa považujú za boty.',
	'Rate limit per IP / hour' => 'Limit na IP / hodinu',
	'Block duplicate requests within (hours)' => 'Blokovať duplicitné žiadosti počas (hodín)',
	// Form links in emails
	'Need to return or claim items from this order?' => 'Potrebujete vrátiť tovar alebo reklamovať z tejto objednávky?',
	'Form links in e-mails' => 'Odkazy na formuláre v e-mailoch',
	'Append a link to the request form at the end of the selected customer e-mails.' => 'Na koniec vybraných zákazníckych e-mailov pridá odkaz na formulár žiadosti.',
	'Add “%s” link to e-mails' => 'Pridať odkaz „%s“ do e-mailov',
	// Private files
	'Invalid request.' => 'Neplatná požiadavka.',
	'You are not allowed to view this file.' => 'Nemáte oprávnenie zobraziť tento súbor.',
	'File not found.' => 'Súbor nenájdený.',
	// My Account / GDPR
	'My requests' => 'Moje žiadosti',
	'Request #%s' => 'Žiadosť č. %s',
	'Endpoint for the customer requests page.' => 'Koncový bod pre stránku zákazníckych žiadostí.',
	'You have no requests yet.' => 'Zatiaľ nemáte žiadne žiadosti.',
	'Request number' => 'Číslo žiadosti',
	'Request not found.' => 'Žiadosť nebola nájdená.',
	'Back to my requests' => 'Späť na moje žiadosti',
	'History & updates' => 'História a aktualizácie',
	'No updates yet.' => 'Zatiaľ žiadne aktualizácie.',
	'Note' => 'Poznámka',
	// Admin status metabox
	'Save the request to apply the status. The customer is notified by e-mail.' => 'Stav sa uplatní uložením žiadosti. Zákazník bude informovaný e-mailom.',
	// Button texts
	'Button texts' => 'Texty tlačidiel',
	'Customise the request button labels.' => 'Prispôsobte texty tlačidiel žiadostí.',
	'Action button: %s' => 'Akčné tlačidlo: %s',
	'Shown in My Account orders and e-mail links.' => 'Zobrazené v Mojom účte pri objednávkach a v odkazoch v e-mailoch.',
	'Submit button: %s' => 'Odosielacie tlačidlo: %s',
	// Migrator
	'Import requests from WPify' => 'Import žiadostí z WPify',
	'Import from WPify' => 'Import z WPify',
	'WPify Woo is active. Import its existing requests: %s' => 'WPify Woo je aktívny. Importujte jeho existujúce žiadosti: %s',
	'Open importer' => 'Otvoriť importér',
	'No WPify data found (table %s does not exist).' => 'Nenašli sa žiadne WPify dáta (tabuľka %s neexistuje).',
	'This is non-destructive and can be run repeatedly — already imported requests are skipped. Customers are NOT e-mailed.' => 'Operácia je nedeštruktívna a dá sa spustiť opakovane — už importované žiadosti sa preskočia. Zákazníkom sa NEodosielajú e-maily.',
	'Total requests in WPify' => 'Spolu žiadostí vo WPify',
	'Already imported' => 'Už importované',
	'Import as status' => 'Importovať so stavom',
	'Dry run' => 'Suchý beh',
	'Import' => 'Importovať',
	'Dry-run finished' => 'Suchý beh dokončený',
	'Import finished' => 'Import dokončený',
	'imported' => 'importované',
	'skipped' => 'preskočené',
	'errors' => 'chyby',
	'Request failed.' => 'Požiadavka zlyhala.',
	'You are not allowed to do this.' => 'Na túto akciu nemáte oprávnenie.',
	'Row #%1$s: %2$s' => 'Riadok #%1$s: %2$s',
	'Missing source id.' => 'Chýba zdrojové id.',
	'Imported from WPify (submitted %s).' => 'Importované z WPify (podané %s).',
);

// Slovak plural forms (3): n==1 / 2..4 / other
$sk_plurals = array(
	'%s request processed.' => array(
		'Spracovaná %s žiadosť.',
		'Spracované %s žiadosti.',
		'Spracovaných %s žiadostí.',
	),
	'%s request updated.' => array(
		'Aktualizovaná %s žiadosť.',
		'Aktualizované %s žiadosti.',
		'Aktualizovaných %s žiadostí.',
	),
	'%s request not updated, somebody is editing it.' => array(
		'%s žiadosť nebola aktualizovaná, niekto ju práve upravuje.',
		'%s žiadosti neboli aktualizované, niekto ich práve upravuje.',
		'%s žiadostí nebolo aktualizovaných, niekto ich práve upravuje.',
	),
	'%s request permanently deleted.' => array(
		'%s žiadosť natrvalo zmazaná.',
		'%s žiadosti natrvalo zmazané.',
		'%s žiadostí natrvalo zmazaných.',
	),
	'%s request moved to the Trash.' => array(
		'%s žiadosť presunutá do koša.',
		'%s žiadosti presunuté do koša.',
		'%s žiadostí presunutých do koša.',
	),
	'%s request restored from the Trash.' => array(
		'%s žiadosť obnovená z koša.',
		'%s žiadosti obnovené z koša.',
		'%s žiadostí obnovených z koša.',
	),
);

// ---------------------------------------------------------------------------
// 3. Report missing
// ---------------------------------------------------------------------------
$missing = array();
foreach ( array_keys( $singulars ) as $msgid ) {
	if ( ! isset( $sk[ $msgid ] ) ) {
		$missing[] = $msgid;
	}
}
foreach ( array_keys( $plurals ) as $msgid ) {
	if ( ! isset( $sk_plurals[ $msgid ] ) ) {
		$missing[] = $msgid . ' (plural)';
	}
}

// ---------------------------------------------------------------------------
// 4. Writers
// ---------------------------------------------------------------------------
$po_escape = static fn( $s ) => str_replace( array( "\\", "\"", "\n" ), array( "\\\\", "\\\"", "\\n" ), $s );

// POT
$pot  = "# Translation template for Pixeler Woo Requests.\nmsgid \"\"\nmsgstr \"\"\n";
$pot .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Project-Id-Version: px-wc-requests\\n\"\n\n";
foreach ( array_keys( $singulars ) as $msgid ) {
	$pot .= 'msgid "' . $po_escape( $msgid ) . "\"\nmsgstr \"\"\n\n";
}
foreach ( $plurals as $s1 => $s2 ) {
	$pot .= 'msgid "' . $po_escape( $s1 ) . "\"\n";
	$pot .= 'msgid_plural "' . $po_escape( $s2 ) . "\"\n";
	$pot .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
}
file_put_contents( $lang_dir . '/' . $domain . '.pot', $pot );

// PO (sk_SK)
$po  = "# Slovak translation for Pixeler Woo Requests.\nmsgid \"\"\nmsgstr \"\"\n";
$po .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$po .= "\"Language: sk_SK\\n\"\n";
$po .= "\"Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\\n\"\n\n";

$mo_entries = array(); // original => translation (with \0 for plurals)

foreach ( array_keys( $singulars ) as $msgid ) {
	$tr  = $sk[ $msgid ] ?? '';
	$po .= 'msgid "' . $po_escape( $msgid ) . "\"\nmsgstr \"" . $po_escape( $tr ) . "\"\n\n";
	if ( '' !== $tr ) {
		$mo_entries[ $msgid ] = $tr;
	}
}
foreach ( $plurals as $s1 => $s2 ) {
	$forms = $sk_plurals[ $s1 ] ?? array( '', '', '' );
	$po   .= 'msgid "' . $po_escape( $s1 ) . "\"\n";
	$po   .= 'msgid_plural "' . $po_escape( $s2 ) . "\"\n";
	foreach ( $forms as $i => $f ) {
		$po .= 'msgstr[' . $i . '] "' . $po_escape( $f ) . "\"\n";
	}
	$po .= "\n";
	if ( '' !== ( $forms[0] ?? '' ) ) {
		$mo_entries[ $s1 . "\0" . $s2 ] = implode( "\0", $forms );
	}
}
file_put_contents( $lang_dir . '/' . $domain . '-sk_SK.po', $po );

// MO compiler
function pxer_build_mo( array $entries, string $headers ): string {
	$entries  = array( '' => $headers ) + $entries;
	ksort( $entries );
	$ids = array_keys( $entries );

	$n          = count( $entries );
	$o_offsets  = '';
	$t_offsets  = '';
	$ids_blob   = '';
	$str_blob   = '';
	$start_ids  = 28 + $n * 16;
	$start_strs = 0; // filled after ids blob known

	foreach ( $ids as $id ) {
		$o_offsets .= pack( 'VV', strlen( $id ), $start_ids + strlen( $ids_blob ) );
		$ids_blob  .= $id . "\0";
	}
	$start_strs = $start_ids + strlen( $ids_blob );
	foreach ( $ids as $id ) {
		$tr        = $entries[ $id ];
		$t_offsets .= pack( 'VV', strlen( $tr ), $start_strs + strlen( $str_blob ) );
		$str_blob  .= $tr . "\0";
	}

	$out  = pack( 'V', 0x950412de );  // magic
	$out .= pack( 'V', 0 );           // revision
	$out .= pack( 'V', $n );          // count
	$out .= pack( 'V', 28 );          // offset of originals table
	$out .= pack( 'V', 28 + $n * 8 ); // offset of translations table
	$out .= pack( 'V', 0 );           // hash size
	$out .= pack( 'V', 28 + $n * 16 );// hash offset
	$out .= $o_offsets;
	$out .= $t_offsets;
	$out .= $ids_blob;
	$out .= $str_blob;

	return $out;
}

$mo_headers = "Content-Type: text/plain; charset=UTF-8\n"
	. "Language: sk_SK\n"
	. "Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;\n";

file_put_contents( $lang_dir . '/' . $domain . '-sk_SK.mo', pxer_build_mo( $mo_entries, $mo_headers ) );

// ---------------------------------------------------------------------------
// 5. Report
// ---------------------------------------------------------------------------
printf( "Extracted singulars: %d, plurals: %d\n", count( $singulars ), count( $plurals ) );
printf( "Translated (mo entries): %d\n", count( $mo_entries ) );
if ( $missing ) {
	echo "MISSING Slovak translations (" . count( $missing ) . "):\n - " . implode( "\n - ", $missing ) . "\n";
} else {
	echo "All strings translated.\n";
}
echo "Wrote: {$domain}.pot, {$domain}-sk_SK.po, {$domain}-sk_SK.mo\n";
