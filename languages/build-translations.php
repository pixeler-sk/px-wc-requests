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

// `vendor/` musí byť vynechaný — cudzie knižnice (Plugin Update Checker) majú
// vlastnú text domain a ich reťazce do našich prekladov nepatria. Bez tohto sa
// v .pot objavia hlášky ako „Check for updates", ktoré nikdy nič nepreloží.
$skip_dirs = array( 'languages', 'vendor', 'scripts' );

$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $rii as $file ) {
	if ( $file->getExtension() !== 'php' ) {
		continue;
	}

	$skip = false;
	foreach ( $skip_dirs as $skip_dir ) {
		if ( strpos( $file->getPathname(), DIRECTORY_SEPARATOR . $skip_dir . DIRECTORY_SEPARATOR ) !== false ) {
			$skip = true;
			break;
		}
	}
	if ( $skip ) {
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

// Polia z hlavičky pluginu. WordPress ich pri vykresľovaní stránky Pluginy
// prežene cez translate() s text domain pluginu, takže sa dajú preložiť — ale
// sedia v komentári na začiatku súboru, kde ich extraktor vyššie nevidí.
// Reťazce tu musia byť znak po znaku zhodné s hlavičkou v px-wc-requests.php.
$header_strings = array(
	'Universal customer-request system for WooCommerce — withdrawal from contract and warranty claims. Requests stored as a CPT with custom statuses, configurable fields (including IBAN), emails and admin UI. Type/config driven and portable between eshops.',
);
foreach ( $header_strings as $header_string ) {
	$singulars[ $header_string ] = true;
}

ksort( $singulars );

// ---------------------------------------------------------------------------
// 2. Translations
//
// Jedna mapa na jazyk. Nový jazyk = nová mapa + záznam v $locales nižšie;
// zvyšok skriptu je jazykovo neutrálny.
// ---------------------------------------------------------------------------
$sk = array(
	// Popis pluginu z hlavičky (stránka Pluginy v administrácii)
	'Universal customer-request system for WooCommerce — withdrawal from contract and warranty claims. Requests stored as a CPT with custom statuses, configurable fields (including IBAN), emails and admin UI. Type/config driven and portable between eshops.' => 'Univerzálny systém zákazníckych žiadostí pre WooCommerce — odstúpenie od zmluvy a reklamácie. Typy žiadostí, ich polia aj stavy sú konfigurovateľné, takže plugin je prenositeľný medzi e-shopmi bez úpravy jadra. Obsahuje formuláre, lehoty, prílohy, e-maily a správu žiadostí v administrácii.',
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
	'Customer requests' => 'Žiadosti',
	'Settings' => 'Nastavenia',
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
	'All items of this order are already covered by an existing request.' => 'Všetky položky tejto objednávky už sú súčasťou inej žiadosti.',
	'Only %2$d unit(s) of "%1$s" can still be requested — the rest is already part of another request.' => 'Z položky „%1$s“ je možné požadovať už len %2$d ks — zvyšok je súčasťou inej žiadosti.',
	'(%d still available — the rest is already part of another request)' => '(k dispozícii ešte %d ks — zvyšok je súčasťou inej žiadosti)',
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
	// Automatic refunds
	'Automatic refunds' => 'Automatické refundácie',
	'When a request reaches the selected status, a refund for the requested items is recorded on the order (amounts after discounts, tax, reports, order status). Money is never sent to the payment gateway — transfer it manually, e.g. to the IBAN from the request.' => 'Keď žiadosť dosiahne zvolený stav, na objednávke sa zaeviduje refundácia požadovaných položiek (sumy po zľavách, DPH, reporty, stav objednávky). Peniaze sa nikdy neposielajú cez platobnú bránu — pošlite ich ručne, napr. na IBAN zo žiadosti.',
	'Create refund: %s' => 'Vytvoriť refundáciu: %s',
	'Request status that records the refund. Each request creates at most one refund.' => 'Stav žiadosti, pri ktorom sa refundácia zaeviduje. Každá žiadosť vytvorí najviac jednu refundáciu.',
	'— Disabled —' => '— Vypnuté —',
	'Automatic refund was not created: %s' => 'Automatická refundácia nebola vytvorená: %s',
	'The linked order no longer exists.' => 'Prepojená objednávka už neexistuje.',
	'The order is already fully refunded.' => 'Objednávka je už plne refundovaná.',
	'The request has no items to refund.' => 'Žiadosť neobsahuje položky na refundáciu.',
	'Nothing is left to refund for the requested items.' => 'Z požadovaných položiek už nie je čo refundovať.',
	'The calculated refund amount is zero.' => 'Vypočítaná suma refundácie je nulová.',
	'Refund of %1$s recorded from customer request #%2$d. The money was NOT sent — transfer it manually.' => 'Zo zákazníckej žiadosti #%2$d bola zaevidovaná refundácia %1$s. Peniaze NEboli odoslané — pošlite ich ručne.',
	'Refund of %1$s recorded on order #%2$s. The money was NOT sent — transfer it manually.' => 'Na objednávke č. %2$s bola zaevidovaná refundácia %1$s. Peniaze NEboli odoslané — pošlite ich ručne.',
	// Form links in emails
	'Need to return or claim items from this order?' => 'Potrebujete vrátiť tovar alebo reklamovať z tejto objednávky?',
	'Form links in e-mails' => 'Odkazy na formuláre v e-mailoch',
	'Append a link to the request form at the end of the selected customer e-mails.' => 'Na koniec vybraných zákazníckych e-mailov pridá odkaz na formulár žiadosti.',
	'Add “%s” link to e-mails' => 'Pridať odkaz „%s“ do e-mailov',
	// Custom e-mail texts
	'E-mail texts' => 'Texty v e-mailoch',
	'Extra text added to the customer e-mails — typically the address the goods should be returned to, or instructions for the next step. Leave empty to add nothing. Placeholders: %s' => 'Doplnkový text pridaný do zákazníckych e-mailov — typicky adresa, kam má zákazník vrátiť tovar, alebo pokyny k ďalšiemu kroku. Prázdne pole nepridá nič. Zástupné znaky: %s',
	'Confirmation: %s' => 'Potvrdenie: %s',
	'Added to the e-mail sent right after the request is submitted.' => 'Pridá sa do e-mailu odoslaného hneď po odoslaní žiadosti.',
	'%1$s → status “%2$s”' => '%1$s → stav „%2$s“',
	'Added to the e-mail sent when the request changes to this status.' => 'Pridá sa do e-mailu odoslaného pri zmene žiadosti na tento stav.',
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

$cs = array(
	// Popis pluginu z hlavičky (stránka Pluginy v administrácii)
	'Universal customer-request system for WooCommerce — withdrawal from contract and warranty claims. Requests stored as a CPT with custom statuses, configurable fields (including IBAN), emails and admin UI. Type/config driven and portable between eshops.' => 'Univerzální systém zákaznických žádostí pro WooCommerce — odstoupení od smlouvy a reklamace. Typy žádostí, jejich pole i stavy jsou konfigurovatelné, takže je plugin přenositelný mezi e-shopy bez úpravy jádra. Obsahuje formuláře, lhůty, přílohy, e-maily a správu žádostí v administraci.',
	// Types & statuses
	'Withdrawal from contract' => 'Odstoupení od smlouvy',
	'Withdrawals from contract' => 'Odstoupení od smlouvy',
	'Withdrawals' => 'Odstoupení',
	'Received' => 'Přijatá',
	'In progress' => 'Řeší se',
	'Resolved' => 'Vyřízená',
	'Rejected' => 'Zamítnutá',
	'Warranty claim' => 'Reklamace',
	'Warranty claims' => 'Reklamace',
	'Claims' => 'Reklamace',
	'Claim form' => 'Reklamační formulář',
	// Settings: quick-create form page
	'Create page for %s' => 'Vytvořit stránku pro %s',
	'Creating…' => 'Vytvářím…',
	'Edit page' => 'Upravit stránku',
	'View' => 'Zobrazit',
	'Form page created and selected.' => 'Stránka formuláře byla vytvořena a předvybrána.',
	'Could not create the form page.' => 'Stránku formuláře se nepodařilo vytvořit.',
	'Unknown request type.' => 'Neznámý typ žádosti.',
	'Request form: %s' => 'Formulář žádosti: %s',
	// My Account action buttons
	'Return' => 'Vrátit',
	'Withdraw from contract' => 'Odstoupit od smlouvy',
	'File a claim' => 'Reklamovat',
	// Fields
	'Reason' => 'Důvod',
	'First name' => 'Jméno',
	'Last name' => 'Příjmení',
	'E-mail' => 'E-mail',
	'Phone' => 'Telefon',
	'Street and number' => 'Ulice a číslo',
	'Postcode' => 'PSČ',
	'City' => 'Město',
	'Account name / recipient' => 'Název účtu / jméno příjemce',
	'IBAN' => 'IBAN',
	'for a possible refund' => 'pro případné vrácení peněz',
	'I agree with the processing of personal data' => 'Souhlasím se zpracováním osobních údajů',
	'Select items' => 'Vyberte zboží',
	'Describe the defect' => 'Popis závady',
	'Photos' => 'Fotografie',
	'Maximum size per photo is 7 MB (JPG, PNG)' => 'Maximální velikost jedné fotografie je 7 MB (JPG, PNG)',
	'I agree with the %s.' => 'Souhlasím se %s.',
	'processing of personal data' => 'zpracováním osobních údajů',
	'Quantity:' => 'Množství:',
	// Summary / helpers
	'Product' => 'Produkt',
	'Quantity' => 'Množství',
	'Reason:' => 'Důvod:',
	'Date created:' => 'Datum vytvoření:',
	'Order number:' => 'Číslo objednávky:',
	'Status:' => 'Stav:',
	'Item #%d' => 'Položka č. %d',
	// CustomPostStatus
	'Change status to: %s' => 'Změnit stav na: %s',
	// Post type
	'Requests' => 'Žádosti',
	'Request' => 'Žádost',
	'Customer requests' => 'Žádosti',
	'Settings' => 'Nastavení',
	'Add request' => 'Přidat žádost',
	'New request' => 'Nová žádost',
	'Edit request' => 'Upravit žádost',
	'View request' => 'Zobrazit žádost',
	'View requests' => 'Zobrazit žádosti',
	'Search requests' => 'Hledat žádosti',
	'No requests found' => 'Žádné žádosti',
	'No requests in trash' => 'Žádné žádosti v koši',
	'Request updated.' => 'Žádost aktualizována.',
	'Request created.' => 'Žádost vytvořena.',
	'Request saved.' => 'Žádost uložena.',
	'Request submitted.' => 'Žádost odeslána.',
	'Request draft updated.' => 'Koncept žádosti aktualizován.',
	'Custom field updated.' => 'Vlastní pole aktualizováno.',
	'Custom field deleted.' => 'Vlastní pole smazáno.',
	'Requests list' => 'Seznam žádostí',
	'Requests list navigation' => 'Navigace v seznamu žádostí',
	'Filter requests list' => 'Filtrovat seznam žádostí',
	'Type' => 'Typ',
	'Order' => 'Objednávka',
	'Status' => 'Stav',
	'Contact' => 'Kontakt',
	'Items' => 'Položky',
	'Date' => 'Datum',
	'All types' => 'Všechny typy',
	'Request details' => 'Údaje žádosti',
	'Order number' => 'Číslo objednávky',
	'Order not found or has no items.' => 'Objednávka nebyla nalezena nebo neobsahuje položky.',
	'Reason / description' => 'Důvod / popis',
	'Max:' => 'Max:',
	'No photos' => 'Žádné fotografie',
	// Eligibility
	'The period has not started yet — the order is not completed.' => 'Lhůta zatím nezačala — objednávka není dokončena.',
	'The deadline for this request has already passed (it ended on %s).' => 'Lhůta pro tuto žádost už uplynula (skončila %s).',
	'There are no eligible items for this request.' => 'Pro tuto žádost nejsou žádné oprávněné položky.',
	'All items of this order are already covered by an existing request.' => 'Všechny položky této objednávky už jsou součástí jiné žádosti.',
	'Only %2$d unit(s) of "%1$s" can still be requested — the rest is already part of another request.' => 'Z položky „%1$s“ lze požadovat už jen %2$d ks — zbytek je součástí jiné žádosti.',
	'(%d still available — the rest is already part of another request)' => '(k dispozici ještě %d ks — zbytek je součástí jiné žádosti)',
	// Product settings
	'months' => 'měsíce',
	'days' => 'dny',
	'Exclude from: %s' => 'Vyloučit z: %s',
	'This product cannot be selected in this request type.' => 'Tento produkt nelze v tomto typu žádosti vybrat.',
	'Period override (%1$s, in %2$s)' => 'Vlastní lhůta (%1$s, v %2$s)',
	'Leave empty to use the global period.' => 'Ponechte prázdné pro použití globální lhůty.',
	// Settings
	'Form pages' => 'Stránky formulářů',
	'Assign the page that contains the shortcode of each request type.' => 'Přiřaďte ke každému typu žádosti stránku s jeho shortcode.',
	'Page: %s' => 'Stránka: %s',
	'Page containing the shortcode %s' => 'Stránka se shortcode %s',
	'Periods & deadlines' => 'Lhůty a termíny',
	'How long a request stays open, counted from the order completion date.' => 'Jak dlouho zůstává žádost otevřená, počítáno od data dokončení objednávky.',
	'Period: %1$s (in %2$s)' => 'Lhůta: %1$s (v %2$s)',
	'Start counting from order status: %s' => 'Začít počítat od stavu objednávky: %s',
	'Order statuses that start the clock.' => 'Stavy objednávky, které spouštějí počítání lhůty.',
	'— Select a page —' => '— Vyberte stránku —',
	'Available shortcodes' => 'Dostupné shortcodes',
	// Controller
	'Order not found.' => 'Objednávka nebyla nalezena.',
	'Invalid request type.' => 'Neplatný typ žádosti.',
	'Security check failed. Please reload the page and try again.' => 'Bezpečnostní kontrola selhala. Obnovte stránku a zkuste to znovu.',
	'Your request has been submitted successfully.' => 'Vaše žádost byla úspěšně odeslána.',
	'Select at least one item.' => 'Vyberte alespoň jednu položku.',
	'One of the selected items is not eligible for this request.' => 'Jedna z vybraných položek není pro tuto žádost oprávněná.',
	'Please fill in the description for each selected item.' => 'Vyplňte popis ke každé vybrané položce.',
	'Please fill in the field: %s' => 'Vyplňte pole: %s',
	'Please enter a valid IBAN.' => 'Zadejte platný IBAN.',
	'Invalid file extension, allowed only: %s' => 'Neplatná přípona souboru, povoleno pouze: %s',
	'Invalid file type. Upload JPG or PNG only.' => 'Neplatný typ souboru. Nahrajte pouze JPG nebo PNG.',
	'The uploaded file is not a valid image.' => 'Nahraný soubor není platný obrázek.',
	'The file must not be larger than %d MB.' => 'Soubor nesmí být větší než %d MB.',
	'An error occurred while saving the request.' => 'Při ukládání žádosti nastala chyba.',
	'%1$s no. %2$d' => '%1$s č. %2$d',
	'Customer request created: %1$s no. %2$d' => 'Vytvořena žádost zákazníka: %1$s č. %2$d',
	// Shortcodes
	'Unknown request type: %s' => 'Neznámý typ žádosti: %s',
	'No order found with this e-mail and number.' => 'Nebyla nalezena objednávka s tímto e-mailem a číslem.',
	// request-form template
	'Order no.:' => 'Objednávka č.:',
	'Created:' => 'Vytvořena:',
	'Deadline:' => 'Termín:',
	'Submit request' => 'Odeslat žádost',
	'Previous requests for this order' => 'Předchozí žádosti k této objednávce',
	// order-search-form template
	'To continue, enter your e-mail and order number.' => 'Pro pokračování zadejte e-mail a číslo objednávky.',
	'Continue' => 'Pokračovat',
	// Emails
	'New request (admin)' => 'Nová žádost (admin)',
	'Admin notification about a new customer request (withdrawal / claim).' => 'Upozornění administrátora na novou žádost zákazníka (odstoupení / reklamace).',
	'New request: {request_type} no. {request_number}' => 'Nová žádost: {request_type} č. {request_number}',
	'Enable/Disable' => 'Povolit/Zakázat',
	'Enable this email notification' => 'Povolit toto e-mailové upozornění',
	'Recipient(s)' => 'Příjemce',
	'Comma-separated emails. Defaults to %s.' => 'E-maily oddělené čárkou. Výchozí %s.',
	'Subject' => 'Předmět',
	'Email heading' => 'Nadpis e-mailu',
	'Email type' => 'Typ e-mailu',
	'Request status change' => 'Změna stavu žádosti',
	'Customer notification when their request status changes.' => 'Upozornění zákazníka při změně stavu jeho žádosti.',
	'Status of request {request_type} no. {request_number} has changed' => 'Stav žádosti {request_type} č. {request_number} byl změněn',
	'The status of your request %1$s no. %2$d has changed to:' => 'Stav vaší žádosti %1$s č. %2$d byl změněn na:',
	'The status of your request %1$s no. %2$d has changed to: %3$s' => 'Stav vaší žádosti %1$s č. %2$d byl změněn na: %3$s',
	'Type:' => 'Typ:',
	'Request number:' => 'Číslo žádosti:',
	'Order:' => 'Objednávka:',
	'IBAN:' => 'IBAN:',
	'E-mail:' => 'E-mail:',
	// Main file
	'Pixeler Woo Requests requires the WooCommerce plugin to be active.' => 'Pixeler Woo Requests vyžaduje aktivní plugin WooCommerce.',
	// Request notes
	'System' => 'Systém',
	'Request notes' => 'Poznámky k žádosti',
	'There are no notes yet.' => 'Zatím žádné poznámky.',
	'Add note' => 'Přidat poznámku',
	'Private note' => 'Interní poznámka',
	'Note to customer' => 'Poznámka pro zákazníka',
	'Add' => 'Přidat',
	'added on %1$s at %2$s by %3$s' => 'přidáno %1$s v %2$s, %3$s',
	'note to customer' => 'poznámka pro zákazníka',
	'Delete' => 'Smazat',
	'Delete this note?' => 'Smazat tuto poznámku?',
	'Request note' => 'Poznámka k žádosti',
	'Sent to the customer when you add a note addressed to them.' => 'Odešle se zákazníkovi při přidání poznámky určené pro něj.',
	'A note has been added to your request {request_type} no. {request_number}' => 'K vaší žádosti {request_type} č. {request_number} byla přidána poznámka',
	'A note regarding your request' => 'Poznámka k vaší žádosti',
	'A note has been added to your request %1$s no. %2$d:' => 'K vaší žádosti %1$s č. %2$d byla přidána poznámka:',
	'Status changed from %1$s to %2$s.' => 'Stav změněn z %1$s na %2$s.',
	'Request created with status: %s' => 'Žádost vytvořena se stavem: %s',
	// Customer confirmation email
	'Request confirmation' => 'Potvrzení žádosti',
	'Sent to the customer right after they submit a request.' => 'Odešle se zákazníkovi hned po odeslání žádosti.',
	'We have received your request {request_type} no. {request_number}' => 'Přijali jsme vaši žádost {request_type} č. {request_number}',
	'We have received your request' => 'Vaši žádost jsme přijali',
	'We have received your request %1$s no. %2$d and will get back to you soon.' => 'Přijali jsme vaši žádost %1$s č. %2$d. Brzy se vám ozveme.',
	// Email preview placeholders
	'This is a sample preview of the email.' => 'Toto je ukázkový náhled e-mailu.',
	'Sample product' => 'Vzorový produkt',
	'Sample note text shown in the preview.' => 'Ukázkový text poznámky v náhledu.',
	// EU mechanics + REST
	'Statutory refund deadline: %s.' => 'Zákonná lhůta pro vrácení peněz: %s.',
	'You have the right to withdraw from this contract within 14 days without giving any reason. The withdrawal period starts on the day you take possession of the goods. You bear the direct cost of returning the goods. We will refund all payments within 14 days of being informed of your decision to withdraw.' => 'Máte právo odstoupit od této smlouvy do 14 dnů bez udání důvodu. Lhůta pro odstoupení začíná běžet dnem převzetí zboží. Přímé náklady na vrácení zboží nesete vy. Všechny platby vám vrátíme do 14 dnů ode dne doručení vašeho rozhodnutí o odstoupení.',
	'The statutory warranty applies for 24 months from receipt of the goods. Please describe the defect as precisely as possible and attach photos if available.' => 'Zákonná záruka platí 24 měsíců od převzetí zboží. Závadu prosím popište co nejpřesněji a přiložte fotografie, pokud je máte.',
	'Legal notice on forms' => 'Právní poučení na formulářích',
	'Informational text shown on each request form (e.g. statutory withdrawal information). Have it reviewed by a lawyer.' => 'Informační text zobrazený na každém formuláři žádosti (např. poučení o odstoupení). Nechte si jej zkontrolovat právníkem.',
	'Notice: %s' => 'Poučení: %s',
	'Order not found or not accessible.' => 'Objednávka nebyla nalezena nebo není přístupná.',
	// Security / anti-spam
	'Too many attempts. Please try again later.' => 'Příliš mnoho pokusů. Zkuste to později.',
	'The maximum number of requests for this order has been reached.' => 'Byl dosažen maximální počet žádostí pro tuto objednávku.',
	'You have already submitted an identical request recently.' => 'Identickou žádost jste nedávno již odeslali.',
	'Security & anti-spam' => 'Bezpečnost a anti-spam',
	'Protect the public form from bots and abuse. Set 0 to disable a limit.' => 'Ochrana veřejného formuláře před boty a zneužitím. 0 = limit vypnutý.',
	'Max requests per order' => 'Max. počet žádostí na objednávku',
	'Minimum form fill time (seconds)' => 'Minimální čas vyplnění formuláře (sekundy)',
	'Submissions faster than this are treated as bots.' => 'Rychlejší odeslání se považují za boty.',
	'Rate limit per IP / hour' => 'Limit na IP / hodinu',
	'Block duplicate requests within (hours)' => 'Blokovat duplicitní žádosti po dobu (hodin)',
	// Automatic refunds
	'Automatic refunds' => 'Automatické refundace',
	'When a request reaches the selected status, a refund for the requested items is recorded on the order (amounts after discounts, tax, reports, order status). Money is never sent to the payment gateway — transfer it manually, e.g. to the IBAN from the request.' => 'Když žádost dosáhne zvoleného stavu, na objednávce se zaeviduje refundace požadovaných položek (částky po slevách, DPH, reporty, stav objednávky). Peníze se nikdy neposílají přes platební bránu — pošlete je ručně, např. na IBAN ze žádosti.',
	'Create refund: %s' => 'Vytvořit refundaci: %s',
	'Request status that records the refund. Each request creates at most one refund.' => 'Stav žádosti, při kterém se refundace zaeviduje. Každá žádost vytvoří nejvýše jednu refundaci.',
	'— Disabled —' => '— Vypnuto —',
	'Automatic refund was not created: %s' => 'Automatická refundace nebyla vytvořena: %s',
	'The linked order no longer exists.' => 'Propojená objednávka již neexistuje.',
	'The order is already fully refunded.' => 'Objednávka je již plně refundována.',
	'The request has no items to refund.' => 'Žádost neobsahuje položky k refundaci.',
	'Nothing is left to refund for the requested items.' => 'Z požadovaných položek už není co refundovat.',
	'The calculated refund amount is zero.' => 'Vypočtená částka refundace je nulová.',
	'Refund of %1$s recorded from customer request #%2$d. The money was NOT sent — transfer it manually.' => 'Ze zákaznické žádosti #%2$d byla zaevidována refundace %1$s. Peníze NEbyly odeslány — pošlete je ručně.',
	'Refund of %1$s recorded on order #%2$s. The money was NOT sent — transfer it manually.' => 'Na objednávce č. %2$s byla zaevidována refundace %1$s. Peníze NEbyly odeslány — pošlete je ručně.',
	// Form links in emails
	'Need to return or claim items from this order?' => 'Potřebujete vrátit zboží nebo reklamovat z této objednávky?',
	'Form links in e-mails' => 'Odkazy na formuláře v e-mailech',
	'Append a link to the request form at the end of the selected customer e-mails.' => 'Na konec vybraných zákaznických e-mailů přidá odkaz na formulář žádosti.',
	'Add “%s” link to e-mails' => 'Přidat odkaz „%s“ do e-mailů',
	// Custom e-mail texts
	'E-mail texts' => 'Texty v e-mailech',
	'Extra text added to the customer e-mails — typically the address the goods should be returned to, or instructions for the next step. Leave empty to add nothing. Placeholders: %s' => 'Doplňkový text přidaný do zákaznických e-mailů — typicky adresa, kam má zákazník vrátit zboží, nebo pokyny k dalšímu kroku. Prázdné pole nepřidá nic. Zástupné znaky: %s',
	'Confirmation: %s' => 'Potvrzení: %s',
	'Added to the e-mail sent right after the request is submitted.' => 'Přidá se do e-mailu odeslaného hned po odeslání žádosti.',
	'%1$s → status “%2$s”' => '%1$s → stav „%2$s“',
	'Added to the e-mail sent when the request changes to this status.' => 'Přidá se do e-mailu odeslaného při změně žádosti na tento stav.',
	// Private files
	'Invalid request.' => 'Neplatný požadavek.',
	'You are not allowed to view this file.' => 'Nemáte oprávnění zobrazit tento soubor.',
	'File not found.' => 'Soubor nenalezen.',
	// My Account / GDPR
	'My requests' => 'Moje žádosti',
	'Request #%s' => 'Žádost č. %s',
	'Endpoint for the customer requests page.' => 'Koncový bod pro stránku zákaznických žádostí.',
	'You have no requests yet.' => 'Zatím nemáte žádné žádosti.',
	'Request number' => 'Číslo žádosti',
	'Request not found.' => 'Žádost nebyla nalezena.',
	'Back to my requests' => 'Zpět na moje žádosti',
	'History & updates' => 'Historie a aktualizace',
	'No updates yet.' => 'Zatím žádné aktualizace.',
	'Note' => 'Poznámka',
	// Admin status metabox
	'Save the request to apply the status. The customer is notified by e-mail.' => 'Stav se uplatní uložením žádosti. Zákazník bude informován e-mailem.',
	// Button texts
	'Button texts' => 'Texty tlačítek',
	'Customise the request button labels.' => 'Přizpůsobte texty tlačítek žádostí.',
	'Action button: %s' => 'Akční tlačítko: %s',
	'Shown in My Account orders and e-mail links.' => 'Zobrazeno v Mém účtu u objednávek a v odkazech v e-mailech.',
	'Submit button: %s' => 'Odesílací tlačítko: %s',
	// Migrator
	'Import requests from WPify' => 'Import žádostí z WPify',
	'Import from WPify' => 'Import z WPify',
	'WPify Woo is active. Import its existing requests: %s' => 'WPify Woo je aktivní. Importujte jeho existující žádosti: %s',
	'Open importer' => 'Otevřít importér',
	'No WPify data found (table %s does not exist).' => 'Nebyla nalezena žádná WPify data (tabulka %s neexistuje).',
	'This is non-destructive and can be run repeatedly — already imported requests are skipped. Customers are NOT e-mailed.' => 'Operace je nedestruktivní a lze ji spustit opakovaně — již importované žádosti se přeskočí. Zákazníkům se NEodesílají e-maily.',
	'Total requests in WPify' => 'Celkem žádostí ve WPify',
	'Already imported' => 'Již importováno',
	'Import as status' => 'Importovat se stavem',
	'Dry run' => 'Zkušební běh',
	'Import' => 'Importovat',
	'Dry-run finished' => 'Zkušební běh dokončen',
	'Import finished' => 'Import dokončen',
	'imported' => 'importováno',
	'skipped' => 'přeskočeno',
	'errors' => 'chyby',
	'Request failed.' => 'Požadavek selhal.',
	'You are not allowed to do this.' => 'K této akci nemáte oprávnění.',
	'Row #%1$s: %2$s' => 'Řádek č. %1$s: %2$s',
	'Missing source id.' => 'Chybí zdrojové id.',
	'Imported from WPify (submitted %s).' => 'Importováno z WPify (podáno %s).',
);

// Czech plural forms (3): n==1 / 2..4 / other
$cs_plurals = array(
	'%s request processed.' => array(
		'Zpracována %s žádost.',
		'Zpracovány %s žádosti.',
		'Zpracováno %s žádostí.',
	),
	'%s request updated.' => array(
		'Aktualizována %s žádost.',
		'Aktualizovány %s žádosti.',
		'Aktualizováno %s žádostí.',
	),
	'%s request not updated, somebody is editing it.' => array(
		'%s žádost nebyla aktualizována, někdo ji právě upravuje.',
		'%s žádosti nebyly aktualizovány, někdo je právě upravuje.',
		'%s žádostí nebylo aktualizováno, někdo je právě upravuje.',
	),
	'%s request permanently deleted.' => array(
		'%s žádost trvale smazána.',
		'%s žádosti trvale smazány.',
		'%s žádostí trvale smazáno.',
	),
	'%s request moved to the Trash.' => array(
		'%s žádost přesunuta do koše.',
		'%s žádosti přesunuty do koše.',
		'%s žádostí přesunuto do koše.',
	),
	'%s request restored from the Trash.' => array(
		'%s žádost obnovena z koše.',
		'%s žádosti obnoveny z koše.',
		'%s žádostí obnoveno z koše.',
	),
);

// Slovenčina aj čeština majú rovnaké pravidlo množného čísla (1 / 2–4 / ostatné).
const PXER_PLURAL_RULE_CS_SK = 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;';

$locales = array(
	'sk_SK' => array(
		'label'     => 'Slovak',
		'rule'      => PXER_PLURAL_RULE_CS_SK,
		'singulars' => $sk,
		'plurals'   => $sk_plurals,
	),
	'cs_CZ' => array(
		'label'     => 'Czech',
		'rule'      => PXER_PLURAL_RULE_CS_SK,
		'singulars' => $cs,
		'plurals'   => $cs_plurals,
	),
);

// ---------------------------------------------------------------------------
// 3. Report missing (per locale)
// ---------------------------------------------------------------------------
$missing = array();
foreach ( $locales as $locale => $data ) {
	$missing[ $locale ] = array();

	foreach ( array_keys( $singulars ) as $msgid ) {
		if ( ! isset( $data['singulars'][ $msgid ] ) ) {
			$missing[ $locale ][] = $msgid;
		}
	}
	foreach ( array_keys( $plurals ) as $msgid ) {
		if ( ! isset( $data['plurals'][ $msgid ] ) ) {
			$missing[ $locale ][] = $msgid . ' (plural)';
		}
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

// PO + MO pre každý jazyk (.mo sa zapisuje nižšie, až po definícii kompilátora)
$mo_per_locale = array();

foreach ( $locales as $locale => $data ) {
	$po  = "# {$data['label']} translation for Pixeler Woo Requests.\nmsgid \"\"\nmsgstr \"\"\n";
	$po .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
	$po .= "\"Language: {$locale}\\n\"\n";
	$po .= "\"Plural-Forms: {$data['rule']}\\n\"\n\n";

	$mo_entries = array(); // original => translation (with \0 for plurals)

	foreach ( array_keys( $singulars ) as $msgid ) {
		$tr  = $data['singulars'][ $msgid ] ?? '';
		$po .= 'msgid "' . $po_escape( $msgid ) . "\"\nmsgstr \"" . $po_escape( $tr ) . "\"\n\n";
		if ( '' !== $tr ) {
			$mo_entries[ $msgid ] = $tr;
		}
	}
	foreach ( $plurals as $s1 => $s2 ) {
		$forms = $data['plurals'][ $s1 ] ?? array( '', '', '' );
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

	file_put_contents( $lang_dir . '/' . $domain . '-' . $locale . '.po', $po );
	$mo_per_locale[ $locale ] = $mo_entries;
}

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

foreach ( $locales as $locale => $data ) {
	$mo_headers = "Content-Type: text/plain; charset=UTF-8\n"
		. "Language: {$locale}\n"
		. "Plural-Forms: {$data['rule']}\n";

	file_put_contents(
		$lang_dir . '/' . $domain . '-' . $locale . '.mo',
		pxer_build_mo( $mo_per_locale[ $locale ], $mo_headers )
	);
}

// ---------------------------------------------------------------------------
// 5. Report
// ---------------------------------------------------------------------------
printf( "Extracted singulars: %d, plurals: %d\n", count( $singulars ), count( $plurals ) );

$has_missing = false;
foreach ( $locales as $locale => $data ) {
	printf( "%s: %d mo entries\n", $locale, count( $mo_per_locale[ $locale ] ) );

	if ( $missing[ $locale ] ) {
		$has_missing = true;
		printf(
			"  MISSING %s translations (%d):\n   - %s\n",
			$data['label'],
			count( $missing[ $locale ] ),
			implode( "\n   - ", $missing[ $locale ] )
		);
	}
}
if ( ! $has_missing ) {
	echo "All strings translated in every locale.\n";
}

$written = array( $domain . '.pot' );
foreach ( array_keys( $locales ) as $locale ) {
	$written[] = "{$domain}-{$locale}.po";
	$written[] = "{$domain}-{$locale}.mo";
}
echo 'Wrote: ' . implode( ', ', $written ) . "\n";
