<?php

namespace Modules\RefreshGlobal\Services;

/**
 * First and last name of the customer of a ticket (1.25.0), for the text a reply starts with: the customer's record first,
 * completed or corrected by the signature of the last messages of this customer. The e-mail address never gives a name by
 * itself ("doe.jane@" and "jane.doe@" look the same): it only confirms or completes a name that was found.
 *
 * A doubt gives nothing: a missing first name is better than a wrong one. Nothing is stored.
 */
class CustomerName
{
    const MESSAGES = 3;
    const MAX_BODY = 200000;
    /** lines looked at, from the end of the message */
    const LINES = 25;

    /** Closing words, lower case (Latin, Cyrillic and Greek scripts). A line that starts with one opens a signature. */
    const CLOSINGS = [
        // fr
        'bien cordialement', 'très cordialement', 'cordialement', 'cordiales salutations', 'sincères salutations', 'meilleures salutations', 'mes salutations',
        'avec mes salutations', 'avec mes meilleures salutations', 'avec nos meilleures salutations', 'salutations distinguées', 'salutations cordiales', 'salutations',
        'bien à vous', 'bien à toi', 'amicalement', 'sincèrement', 'respectueusement', 'à bientôt', 'bonne journée', 'bonne soirée', 'belle journée', 'bon week-end', 'cdlt', 'cdt',
        'merci d\'avance', 'merci beaucoup', 'merci', 'avec mes remerciements',
        // en
        'kind regards', 'best regards', 'warm regards', 'kindest regards', 'with kind regards', 'with best regards', 'regards', 'best wishes', 'all the best', 'best', 'sincerely',
        'yours sincerely', 'sincerely yours', 'yours faithfully', 'yours truly', 'cheers', 'many thanks', 'thanks in advance', 'thanks and regards', 'thanks', 'thank you', 'respectfully', 'cordially',
        // de
        'mit freundlichen grüßen', 'mit freundlichen grüssen', 'mit freundlichem gruß', 'mit freundlichem gruss', 'mit besten grüßen', 'mit besten grüssen', 'mit herzlichen grüßen',
        'mit herzlichen grüssen', 'freundliche grüße', 'freundliche grüsse', 'freundlicher gruss', 'beste grüße', 'beste grüsse', 'besten gruß', 'besten gruss', 'liebe grüße', 'liebe grüsse',
        'lieben gruß', 'lieben gruss', 'viele grüße', 'viele grüsse', 'herzliche grüße', 'herzliche grüsse', 'schöne grüße', 'schöne grüsse', 'hochachtungsvoll', 'grüße', 'grüsse', 'gruß', 'gruss',
        'mfg', 'lg', 'vielen dank', 'besten dank', 'danke',
        // es
        'saludos cordiales', 'cordiales saludos', 'un cordial saludo', 'cordialmente', 'atentamente', 'muy atentamente', 'saludos', 'un saludo', 'atte', 'muchas gracias', 'gracias',
        // it
        'cordiali saluti', 'distinti saluti', 'cari saluti', 'un cordiale saluto', 'un saluto', 'saluti', 'cordialità', 'grazie mille', 'grazie',
        // nl
        'met vriendelijke groeten', 'met vriendelijke groet', 'vriendelijke groeten', 'vriendelijke groet', 'met hartelijke groet', 'hartelijke groeten', 'groeten', 'groet', 'groetjes', 'hoogachtend', 'mvg', 'alvast bedankt', 'bedankt', 'dank u',
        // pt
        'atenciosamente', 'com os melhores cumprimentos', 'melhores cumprimentos', 'cumprimentos', 'cordiais saudações', 'saudações', 'abraços', 'abraço', 'att', 'obrigado', 'obrigada', 'muito obrigado', 'muito obrigada',
        // ca, ro
        'salutacions', 'atentament', 'cu stimă', 'cu respect', 'o zi bună', 'mulțumesc',
        // da, no, sv, fi
        'med venlig hilsen', 'venlig hilsen', 'med vennlig hilsen', 'vennlig hilsen', 'hilsen', 'mvh', 'med vänliga hälsningar', 'med vänlig hälsning', 'vänliga hälsningar', 'hälsningar', 'ystävällisin terveisin', 'terveisin', 'tack', 'takk', 'tak', 'kiitos',
        // pl, cs, sk, hu, hr/sr/sl
        'z poważaniem', 'pozdrawiam serdecznie', 'pozdrawiam', 'serdecznie pozdrawiam', 'dziękuję', 's pozdravem', 's pozdravom', 'děkuji', 'ďakujem', 'üdvözlettel', 'tisztelettel', 'köszönöm', 'srdačan pozdrav', 'lijep pozdrav', 'lep pozdrav', 'lp', 'hvala',
        // tr, sq, et, lv, lt
        'saygılarımla', 'iyi çalışmalar', 'teşekkürler', 'me respekt', 'lugupidamisega', 'ar cieņu', 'pagarbiai',
        // ru, uk, bg, el
        'с уважением', 'с наилучшими пожеланиями', 'спасибо', 'з повагою', 'дякую', 'с уважение', 'поздрави', 'με εκτίμηση', 'ευχαριστώ',
    ];

    /** Titles removed before a name. */
    const TITLES = ['m', 'mr', 'mrs', 'ms', 'miss', 'mme', 'mlle', 'monsieur', 'madame', 'mademoiselle', 'dr', 'prof', 'me', 'maître', 'herr', 'frau', 'dipl', 'ing', 'mag', 'sr', 'sra', 'srta', 'don', 'doña',
        'señor', 'señora', 'sig', 'signor', 'signora', 'dott', 'dottssa', 'avv', 'dhr', 'mevr', 'mevrouw', 'drs', 'ir', 'senhor', 'senhora', 'dra', 'pan', 'pani', 'hr', 'fr', 'sir', 'phd', 'mba', 'msc', 'bsc'];

    /** Small words inside a last name. */
    const PARTICLES = ['de', 'du', 'des', 'da', 'das', 'do', 'dos', 'del', 'della', 'dello', 'di', 'la', 'le', 'van', 'von', 'vom', 'zu', 'zum', 'zur', 'der', 'den', 'ter', 'ten', 'te', 'el', 'al', 'bin', 'ben', 'ibn', 'af', 'av', 'y', 'e', 'st', 'mac'];

    /** Words that are never part of a person's name: functions, departments, companies, devices. */
    const NOT_NAMES = ['info', 'contact', 'kontakt', 'contacto', 'contatto', 'contato', 'support', 'service', 'services', 'servizio', 'servicio', 'serviço', 'kundendienst', 'kundenservice', 'client', 'clients', 'customer', 'team', 'équipe',
        'equipe', 'equipo', 'squadra', 'admin', 'administrator', 'administration', 'administración', 'amministrazione', 'administração', 'verwaltung', 'office', 'büro', 'bureau', 'noreply', 'no-reply', 'donotreply', 'newsletter', 'webmaster', 'postmaster',
        'comptabilité', 'facturation', 'buchhaltung', 'accounting', 'billing', 'contabilidad', 'contabilità', 'boekhouding', 'accueil', 'réception', 'reception', 'empfang', 'secrétariat', 'sekretariat', 'secretariat', 'segreteria', 'secretaría', 'direction',
        'directeur', 'directrice', 'director', 'direktor', 'manager', 'responsable', 'leiter', 'leiterin', 'geschäftsführer', 'geschäftsführerin', 'ceo', 'cto', 'cfo', 'assistant', 'assistante', 'assistent', 'assistentin', 'ingénieur', 'engineer', 'ingenieur', 'technicien',
        'technician', 'techniker', 'vertrieb', 'ventes', 'vente', 'marketing', 'achats', 'einkauf', 'purchasing', 'logistique', 'logistics', 'logistik', 'département', 'department', 'abteilung', 'ressources', 'humaines', 'human', 'resources', 'hr', 'rh', 'it', 'informatique', 'informatik',
        'ville', 'commune', 'mairie', 'gemeinde', 'stadt', 'city', 'hôtel', 'hotel', 'restaurant', 'association', 'verein', 'fondation', 'stiftung', 'foundation', 'école', 'schule', 'school', 'université', 'universität', 'university', 'hôpital', 'hospital', 'spital', 'clinique', 'cabinet',
        'pharmacie', 'apotheke', 'garage', 'group', 'groupe', 'gruppe', 'grupo', 'gruppo', 'solutions', 'consulting', 'company', 'société', 'firma', 'holding', 'international', 'agence', 'agency', 'agentur', 'banque', 'bank', 'assurance', 'assurances', 'versicherung', 'shop', 'boutique', 'studio',
        'mobile', 'mobil', 'tel', 'tél', 'téléphone', 'telefon', 'phone', 'fax', 'email', 'e-mail', 'mail', 'web', 'website', 'www', 'iphone', 'ipad', 'android', 'samsung', 'outlook', 'gmail', 'yahoo', 'sent', 'envoyé', 'gesendet', 'enviado', 'inviato', 'verzonden', 'rue', 'avenue', 'route',
        'chemin', 'place', 'strasse', 'straße', 'street', 'road', 'via', 'calle', 'rua', 'suisse', 'schweiz', 'svizzera', 'switzerland', 'france', 'deutschland', 'germany', 'italia', 'españa', 'portugal', 'brasil', 'confidential', 'confidentiel', 'disclaimer', 'notice', 'attention', 'achtung',
        'bonjour', 'bonsoir', 'hello', 'hi', 'dear', 'hallo', 'guten', 'hola', 'ciao', 'olá', 'your', 'votre', 'ihr', 'ihre', 'the', 'les', 'und', 'and', 'et', 'ps', 'p.s', 'oui', 'non', 'yes', 'no', 'ok'];

    const LEGAL = ['gmbh', 'sarl', 'sàrl', 'ltd', 'limited', 'llc', 'inc', 'corp', 'srl', 'ltda', 'lda', 'sas', 'sasu', 'eurl', 'kg', 'ohg', 'ug', 'plc', 'oy', 'ab', 'aps'];
    /** these only in capitals: "Sa" is a name */
    const LEGAL_CAPITALS = ['SA', 'AG', 'NV', 'BV', 'SL', 'SPA', 'SE', 'AS'];

    /** Last messages of the customer in the ticket (html, the newest first). */
    public static function bodies($conversation)
    {
        $customer = $conversation->customer;
        if (!$customer) {
            return [];
        }
        try {
            return \App\Thread::where('conversation_id', $conversation->id)->where('type', \App\Thread::TYPE_CUSTOMER)
                ->where('created_by_customer_id', $customer->id)->orderBy('id', 'desc')->limit(self::MESSAGES)->pluck('body')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array ['first' => , 'last' => , 'full' => ] of the customer of a ticket ('' when not known) */
    public static function forTicket($conversation, array $bodies)
    {
        $customer = $conversation->customer;
        $first = $customer ? self::squeeze($customer->first_name) : '';
        $last = $customer ? self::squeeze($customer->last_name) : '';
        try {
            return self::find($first, $last, (string) $conversation->customer_email, $bodies, $customer ? (string) $customer->company : '');
        } catch (\Throwable $e) {
            return ['first' => $first, 'last' => $last, 'full' => trim($first.' '.$last)];
        }
    }

    /** The name from a record, an address and messages (html, the newest first). */
    public static function find($first, $last, $email = '', array $bodies = [], $company = '')
    {
        $first = self::squeeze($first);
        $last = self::squeeze($last);
        $record = trim($first.' '.$last);
        $tokens = self::emailTokens($email);
        $known = self::record($first, $last, $tokens);
        $signed = null;
        foreach ($bodies as $body) {
            $signed = self::signature((string) $body, $tokens, $known, $company);
            if ($signed) {
                break;
            }
        }
        $name = $known ?: ['first' => '', 'last' => ''];
        if ($signed) {
            $name = $known ? self::merge($known, $signed) : $signed;
        }
        $name = self::complete($name, $tokens);
        $full = trim($name['first'].' '.$name['last']);

        // a record that is not a person (company, department) stays the full name when nothing better is known
        return ['first' => $name['first'], 'last' => $name['last'], 'full' => $full !== '' ? $full : $record];
    }

    /** The record as a person's name, or null (address, company, department, digits). */
    protected static function record($first, $last, array $tokens)
    {
        $record = trim($first.' '.$last);
        if ($record === '' || preg_match('/[@\d]|https?:/u', $record)) {
            return null;
        }
        $words = explode(' ', $record);
        $end = rtrim(end($words), '.');
        if (in_array(self::lower($words[0]), self::NOT_NAMES, true)
            || (count($words) > 1 && (in_array(self::lower($end), self::LEGAL, true) || in_array($end, self::LEGAL_CAPITALS, true)))) {
            return null;
        }
        // both parts given in their own fields: kept ("Jean Pierre" + "Dupont")
        if ($first !== '' && $last !== '' && strpos($record, ',') === false) {
            $clean = self::untitle(explode(' ', $first));

            return $clean ? ['first' => self::tidyCase(implode(' ', $clean)), 'last' => self::tidyCase($last)] : ['first' => '', 'last' => self::tidyCase($last)];
        }
        $name = self::parse($record, $tokens, true);
        // another script or form (no capitals, one word): as FreeScout has it
        return $name ?: ['first' => $first, 'last' => $last];
    }

    /** What the signature adds to the record: the order, the missing part; never another person. */
    protected static function merge(array $known, array $signed)
    {
        $k = self::keys($known['first'].' '.$known['last']);
        $s = self::keys($signed['first'].' '.$signed['last']);
        if (!array_intersect($k, $s)) {
            return $known;
        }
        $kf = self::keys($known['first']);
        $kl = self::keys($known['last']);
        $sf = self::keys($signed['first']);
        // "DOE Jane" in the record, "Jane DOE" or "Jane" in the signature
        if ($known['first'] !== '' && $known['last'] !== '' && $sf && $sf === $kl && (!$signed['last'] || (!empty($signed['sure']) && self::keys($signed['last']) === $kf))) {
            return ['first' => $known['last'], 'last' => $known['first']];
        }
        if (($known['first'] === '' || $known['last'] === '') && count($s) > count($k) && !array_diff($k, $s)) {
            return ['first' => $signed['first'], 'last' => $signed['last']];
        }

        return $known;
    }

    /** "Jane" and jane.doe@: the last name is in the address. */
    protected static function complete(array $name, array $tokens)
    {
        if (count($tokens) === 2 && $name['first'] !== '' && $name['last'] === '') {
            $key = self::keys($name['first']);
            $at = count($key) === 1 ? array_search($key[0], $tokens, true) : false;
            if ($at !== false && mb_strlen($tokens[1 - $at]) > 2) {
                $name['last'] = self::tidyCase($tokens[1 - $at]);
            }
        }

        return ['first' => $name['first'], 'last' => $name['last']];
    }

    /** Name in the signature of a message, or null. */
    protected static function signature($html, array $tokens, $known, $company)
    {
        $lines = array_slice(self::lines($html), -self::LINES);
        $n = count($lines);
        $own = self::keys($company);
        $accept = function ($line) use ($tokens, $own) {
            $name = self::parse($line, $tokens, false);
            if (!$name || ($own && !array_diff(self::keys($line), $own))) {
                return null;
            }

            return $name;
        };
        // after the last closing words or "-- " that a name follows
        for ($i = $n - 1; $i >= 0; $i--) {
            $rest = preg_match('/^(--|—|__+)$/u', $lines[$i]) ? '' : self::afterClosing($lines[$i]);
            if ($rest === null) {
                continue;
            }
            $next = $rest !== '' ? [$rest] : array_slice($lines, $i + 1, 2);
            foreach ($next as $line) {
                if ($name = $accept($line)) {
                    return $name;
                }
            }
        }
        // without closing words: a whole line that the address or the record confirms
        $sure = array_merge($tokens, $known ? self::keys($known['first'].' '.$known['last']) : []);
        for ($i = $n - 1; $i >= max(0, $n - 12); $i--) {
            $name = $accept($lines[$i]);
            if ($name && $name['last'] !== '' && array_intersect(self::keys($name['first'].' '.$name['last']), $sure)) {
                return $name;
            }
        }

        return null;
    }

    /** What follows closing words on their line ('' for nothing), null when the line does not start with them. */
    protected static function afterClosing($line)
    {
        static $sorted = null;
        if ($sorted === null) {
            $sorted = self::CLOSINGS;
            usort($sorted, function ($a, $b) {
                return mb_strlen($b) - mb_strlen($a);
            });
        }
        $low = str_replace('’', "'", self::lower($line));
        foreach ($sorted as $words) {
            if (strpos($low, $words) !== 0) {
                continue;
            }
            $rest = mb_substr($line, mb_strlen($words));
            if ($rest !== '' && preg_match('/^[\p{L}\p{N}]/u', $rest)) {
                continue;
            }
            $rest = trim(preg_replace('/^[\s,;.:!\-–—]+/u', '', $rest));
            // "Kind regards and thanks," is a closing line; "Merci pour votre aide" too, without a name
            return $rest;
        }

        return null;
    }

    /** Lines of the message itself: without quoted messages, scripts and empty lines. */
    public static function lines($html)
    {
        $html = mb_strcut($html, 0, self::MAX_BODY, 'UTF-8');
        $html = preg_replace('#<(script|style|head)\b.*?</\1>#isu', ' ', $html) ?: '';
        $cut = preg_split('#<blockquote\b|<[^>]+(?:gmail_quote|gmail_extra|moz-cite-prefix|yahoo_quoted|divRplyFwdMsg|OLK_SRC_BODY_SECTION|appendonsend)#iu', $html, 2);
        $text = preg_replace('#<br\s*/?>|</?(?:p|div|tr|td|li|table|h[1-6]|hr)\b[^>]*>#iu', "\n", $cut[0]);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES, 'UTF-8');
        $out = [];
        foreach (explode("\n", str_replace("\r", "\n", $text)) as $line) {
            $line = self::squeeze($line);
            if ($line === '') {
                continue;
            }
            // header of a quoted message
            if ($line[0] === '>' || preg_match('/^-{3,}.*-{3,}$|^_{8,}$/u', $line)
                || preg_match('/^(from|von|de|da|van|från|fra|od|от|lähettäjä)\s?:\s/iu', $line)
                || preg_match('/^(on|le|am|el|il|op|em|den|dnia)\s.{6,200}(wrote|a écrit|schrieb|escribió|ha scritto|schreef|escreveu|skrev|napisał\(a\)|napisał)\b.{0,80}:$/iu', $line)) {
                break;
            }
            $out[] = $line;
        }

        return $out;
    }

    /**
     * A line as a person's name, or null.
     *
     * @return array|null ['first' => , 'last' => , 'sure' => the order is certain]
     */
    protected static function parse($line, array $tokens, $fromRecord)
    {
        // "Jane Doe | Manager", "Jane Doe (Sales)", "Jane Doe - Company", "*Jane Doe*"
        $line = preg_split('/\s[|–—\/-]\s|\||[(<\[•·]/u', trim($line, " \t*_~\"“”«»'"))[0];
        $line = trim($line, " \t.,;:!*_");
        if ($line === '' || mb_strlen($line) > 50 || preg_match('/[@\d#:;?!&+=]/u', $line)) {
            return null;
        }
        $comma = null;
        if (strpos($line, ',') !== false) {
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                return null;
            }
            if (!$fromRecord) {
                // "Jane Doe, Manager"
                $line = $parts[0];
                if (strpos($line, ' ') === false) {
                    return null;
                }
            } else {
                // "Doe, Jane"
                $comma = ['last' => $parts[0], 'first' => $parts[1]];
                $line = $parts[1].' '.$parts[0];
            }
        }
        $words = self::untitle(explode(' ', $line));
        $count = count($words);
        if (!$count || $count > 5 || (!$fromRecord && self::afterClosing(implode(' ', $words)) !== null)) {
            return null;
        }
        $lowAll = self::lower($line) === $line;
        $names = 0;
        foreach ($words as $i => $w) {
            $low = self::lower(rtrim($w, '.'));
            if (in_array($low, self::NOT_NAMES, true) || in_array($low, self::LEGAL, true) || ($i > 0 && in_array($w, self::LEGAL_CAPITALS, true))) {
                return null;
            }
            if (!preg_match("/^[\p{L}\p{M}]+(?:['’\-][\p{L}\p{M}]+)*\.?$/u", $w)) {
                return null;
            }
            if ($i > 0 && in_array($low, self::PARTICLES, true)) {
                continue;
            }
            // a name has a capital letter, unless the record or the address says it is one
            if (!preg_match("/^(?:[\p{Ll}]{1,2}['’])?\p{Lu}/u", $w)) {
                if (!(($fromRecord && $lowAll) || ($count <= 2 && in_array(self::ascii($w), $tokens, true)))) {
                    return null;
                }
            }
            $names++;
        }
        if (!$names || in_array(self::lower(end($words)), self::PARTICLES, true)) {
            return null;
        }
        if ($count === 1) {
            $w = rtrim($words[0], '.');
            // initials ("JD") are not a first name
            if (mb_strlen($w) < 2 || (mb_strlen($w) <= 3 && mb_strtoupper($w, 'UTF-8') === $w)) {
                return null;
            }

            return ['first' => self::tidyCase($w), 'last' => '', 'sure' => false];
        }
        if ($comma) {
            return ['first' => self::tidyCase(implode(' ', self::untitle(explode(' ', $comma['first'])))), 'last' => self::tidyCase($comma['last']), 'sure' => true];
        }
        // capitals for the last name only: "Jane DOE", "DOE Jane", "VAN DER BERG Jan"
        $upper = [];
        foreach ($words as $i => $w) {
            $upper[$i] = mb_strlen($w) > 1 && substr($w, -1) !== '.' && mb_strtoupper($w, 'UTF-8') === $w;
        }
        $up = count(array_filter($upper));
        if ($up && $up < $count) {
            if ($upper[$count - 1] && !$upper[0]) {
                $at = array_search(true, $upper, true);
                while ($at > 1 && in_array(self::lower($words[$at - 1]), self::PARTICLES, true)) {
                    $at--;
                }

                return self::split($words, $at, true);
            }
            if ($upper[0] && !$upper[$count - 1]) {
                $at = array_search(false, $upper, true);
                while ($at < $count - 1 && in_array(self::lower($words[$at]), self::PARTICLES, true)) {
                    $at++;
                }

                return ['first' => self::tidyCase(implode(' ', array_slice($words, $at))), 'last' => self::tidyCase(implode(' ', array_slice($words, 0, $at))), 'sure' => true];
            }
        }
        // the address tells where the first name ends ("jean-pierre.dupont@", "mariajose.garcia@")
        $at = 1;
        if (count($tokens) === 2) {
            for ($i = 1; $i < $count; $i++) {
                if (self::ascii(implode('', array_slice($words, 0, $i))) === preg_replace('/[^\p{L}]/u', '', $tokens[0])
                    && self::ascii(implode('', array_slice($words, $i))) === preg_replace('/[^\p{L}]/u', '', $tokens[1])) {
                    $at = $i;
                }
            }
        }
        // "John M. Doe": the initial stays with the first name
        while ($at < $count - 1 && preg_match('/^\p{Lu}\.?$/u', $words[$at])) {
            $at++;
        }

        return self::split($words, $at, false);
    }

    protected static function split(array $words, $at, $sure)
    {
        return ['first' => self::tidyCase(implode(' ', array_slice($words, 0, $at))), 'last' => self::tidyCase(implode(' ', array_slice($words, $at))), 'sure' => $sure];
    }

    protected static function untitle(array $words)
    {
        $words = array_values(array_filter($words, 'strlen'));
        while ($words) {
            $bare = str_replace('.', '', self::lower($words[0]));
            // "M" or "Me" without a point may be a name
            if (!in_array($bare, self::TITLES, true) || (mb_strlen($bare) < 2 && substr($words[0], -1) !== '.') || count($words) === 1) {
                break;
            }
            array_shift($words);
        }

        return $words;
    }

    /** Words of the local part of an address: jane.doe@ => [jane, doe]. */
    protected static function emailTokens($email)
    {
        $local = explode('+', explode('@', self::lower($email))[0])[0];
        $out = [];
        foreach (preg_split('/[._]+/', $local) as $t) {
            $t = self::ascii($t);
            if (mb_strlen($t) > 1 && preg_match('/^[\p{L}]+$/u', $t) && !in_array($t, self::NOT_NAMES, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** Comparable words of a name: lower case, without accents. */
    protected static function keys($name)
    {
        $out = [];
        foreach (preg_split('/[^\p{L}\p{M}]+/u', (string) $name) as $w) {
            if ($w !== '') {
                $out[] = self::ascii($w);
            }
        }

        return $out;
    }

    protected static function ascii($word)
    {
        $word = strtr(self::lower($word), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ø' => 'o', 'å' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ă' => 'a', 'ą' => 'a',
            'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ę' => 'e', 'ě' => 'e', 'ğ' => 'g', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ı' => 'i', 'ł' => 'l',
            'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ő' => 'o', 'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's', 'ť' => 't', 'ț' => 't', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ů' => 'u', 'ű' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z']);

        return preg_replace('/[^\p{L}]/u', '', $word);
    }

    /** "JANE" and "jane" => "Jane"; a name that already mixes both is left as it is. */
    protected static function tidyCase($name)
    {
        $out = [];
        foreach (explode(' ', trim($name)) as $i => $w) {
            $w = trim($w, ',');
            if (mb_strtoupper($w, 'UTF-8') === $w || mb_strtolower($w, 'UTF-8') === $w) {
                $low = self::lower($w);
                $w = in_array($low, self::PARTICLES, true) ? $low : preg_replace_callback("/(^|['’\-])(\p{L})/u", function ($m) {
                    return $m[1].mb_strtoupper($m[2], 'UTF-8');
                }, $low);
            }
            $out[] = $w;
        }

        return implode(' ', $out);
    }

    protected static function lower($text)
    {
        return mb_strtolower((string) $text, 'UTF-8');
    }

    protected static function squeeze($text)
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', ' ', (string) $text));
    }
}
