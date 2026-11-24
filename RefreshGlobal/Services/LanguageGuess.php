<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Language a message is written in (1.26.0), for the text a reply starts with. Done here, on the server of FreeScout: the
 * script first (Greek, Arabic, Japanese…), then the common words of each language. In doubt: '' (no language).
 */
class LanguageGuess
{
    const MAX_WORDS = 400;
    const MAX_LINES = 40;

    /** code => name of the language in itself */
    const LANGUAGES = ['fr' => 'Français', 'de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano', 'es' => 'Español', 'pt' => 'Português', 'nl' => 'Nederlands', 'da' => 'Dansk', 'sv' => 'Svenska', 'no' => 'Norsk',
        'fi' => 'Suomi', 'pl' => 'Polski', 'cs' => 'Čeština', 'sk' => 'Slovenčina', 'hu' => 'Magyar', 'ro' => 'Română', 'hr' => 'Hrvatski', 'tr' => 'Türkçe', 'ru' => 'Русский', 'uk' => 'Українська', 'bg' => 'Български',
        'el' => 'Ελληνικά', 'ar' => 'العربية', 'he' => 'עברית', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어', 'th' => 'ไทย'];

    /** Common words; a word of several languages counts less. */
    const WORDS = [
        'fr' => 'le la les un une des du de et est pas que qui je vous nous il elle ne pour dans avec sur ce cette mon ma mes votre vos notre nos bonjour bonsoir merci mais ou où être avoir suis avez avons été fait très plus bien aussi peut pouvez puis j\'ai c\'est d\'un qu\'il n\'est cordialement au aux par sont comme si madame monsieur svp salut colis commande arrivé arrivée reçu cassé panne depuis hier toujours jamais souhaite voudrais aimerais annuler rembourser remboursement livraison facture abonnement problème demande aide besoin réponse rappeler compte adresse mot passe connecter fonctionne marche encore déjà veuillez pourriez pouvons svp une quand comment pourquoi quelle quel été trouve deux trois quatre cette il manque',
        'de' => 'der die das ein eine einen einem und ist nicht ich sie wir er es mit für auf von zu den dem des im in an haben habe hat sind war wird werden kann können mein meine ihre ihr ihnen uns unser bitte danke hallo guten tag aber oder wenn wie noch auch nur schon sehr mehr bei nach aus um dass grüße grüsse gruß freundliche freundlichen geehrte herr frau vielen dank paket kam kommt beschädigt bestellung rechnung lieferung kündigen möchte möchten würde gerne rückruf rückerstattung konto passwort funktioniert leider problem hilfe brauche brauchen benötige frage antwort seit gestern immer wieder hallo erhalten bekommen bezahlt können warum wann wo welche keine kein habe wurde wurden geht hier zwei drei vier fehlt fehlen',
        'en' => 'the a an and is are was not i you we he she it they with for on of to in at have has had be been will would can could my your our their this that these please thanks thank hello hi dear but or if how when what which from by as do does did there regards about just any am i\'m don\'t it\'s parcel package arrived damaged order invoice delivery cancel subscription refund account password works working problem issue help need want would like question answer since yesterday still never received paid back call why where who no yes got again could should them his her me myself two three four missing',
        'it' => 'il lo la i gli le un uno una di del della dei delle e è non che chi io voi noi per con su nel nella sono ho ha abbiamo avete essere mio mia vostro vostra nostro questo questa buongiorno buonasera salve grazie ma o anche più molto come quando dove posso potete vorrei cordiali saluti da al alla ci si gentile pacco arrivato rotto ordine fattura consegna disdire annullare abbonamento rimborso conto funziona problema aiuto bisogno domanda risposta ieri sempre mai ricevuto pagato perché vorrei vorremmo richiedere indirizzo ancora già due tre quattro manca mancano',
        'es' => 'el la los las un una unos de del y es no que quien yo usted ustedes nosotros para por con en su sus mi mis nuestro este esta estoy está son he ha hemos tengo tiene hola buenos buenas días tardes gracias pero o también más muy como cuando donde puedo puede pueden quisiera saludos al se lo le si ya señor estimado estimados paquete llegó roto pedido factura entrega cancelar suscripción reembolso cuenta contraseña funciona problema ayuda necesito quiero queremos pregunta respuesta ayer siempre nunca recibido pagado cuándo cómo dónde quisiera dirección todavía aún dos tres cuatro falta faltan',
        'pt' => 'o a os as um uma de do da dos das e é não que quem eu você vocês nós para por com em no na meu minha seu sua nosso este esta estou está são tenho tem temos olá bom boa dia tarde noite obrigado obrigada mas ou também mais muito como quando onde posso pode podem gostaria cumprimentos atenciosamente ao se já prezado prezados isso foi pacote chegou danificado encomenda pedido fatura entrega cancelar assinatura reembolso conta senha funciona problema ajuda preciso quero queremos pergunta resposta ontem sempre nunca recebi recebido pago quando onde gostaria endereço ainda não dois duas três quatro falta faltam',
        'nl' => 'de het een en is niet ik u je wij we hij zij met voor op van te in aan heb heeft hebben zijn was wordt worden kan kunnen mijn uw onze deze dit dat hallo goedemorgen goedemiddag beste bedankt dank maar of als hoe wanneer ook nog al zeer meer bij naar uit om graag groet groeten vriendelijke alvast geen wel er pakket kwam beschadigd bestelling factuur levering opzeggen abonnement terugbetaling rekening wachtwoord werkt probleem hulp nodig wil willen vraag antwoord gisteren altijd nooit ontvangen betaald waarom waar wie hier twee drie vier ontbreekt ontbreken',
        'da' => 'og er ikke jeg vi du de en et den det der som til på med for af har havde kan vil min mit din jeres vores hej tak men eller hvis hvordan hvornår også meget mere fra om at venlig hilsen være blevet noget kære pakken kom frem stykker gerne penge tilbage ordre faktura ikke hvorfor hjælp problem',
        'sv' => 'och är inte jag vi du ni en ett den det som till på med för av har hade kan vill min mitt din er vår hej tack men eller om hur när också mycket mer från att vänliga hälsningar vara blivit något detta paketet kom skadat gärna pengar tillbaka beställning faktura varför hjälp',
        'no' => 'og er ikke jeg vi du dere en et den det som til på med for av har hadde kan vil min mitt din deres vår hei takk men eller hvis hvordan når også mye mer fra om å vennlig hilsen være blitt noe pakken kom frem gjerne ha pengene tilbake bestilling faktura hvorfor hjelp ødelagt',
        'fi' => 'ja on ei minä me te hän että se tämä mutta tai jos kun miten myös hyvin enemmän kanssa hei kiitos olen olemme olette voi voitte minun meidän teidän ole ovat oli ystävällisin terveisin päivää haluaisin',
        'pl' => 'i jest nie że ja my wy pan pani na w z do od dla po się to ten ta czy jak kiedy ale lub jeśli także bardzo więcej mam ma mamy są był dzień dobry proszę dziękuję pozdrawiam mój moja nasz państwa o za przez może paczka dotarła zamówienie faktura zwrot pieniędzy chcę dlaczego problem pomoc',
        'cs' => 'a je není že já my vy na v s z do od pro po se to ten ta jak kdy ale nebo pokud také velmi více mám má máme jsou byl dobrý den prosím děkuji pozdravem můj moje náš vás jsem jsme bych který ještě balík dorazil objednávka faktura chci vrátit peníze proč pomoc',
        'sk' => 'a je nie že ja my vy na v s z do od pre po sa to ten tá ako kedy ale alebo ak tiež veľmi viac mám má máme sú bol dobrý deň prosím ďakujem pozdravom môj moje náš vás som sme by ktorý ešte balík dorazil objednávka faktúra chcem vrátiť peniaze prečo pomoc',
        'hu' => 'a az és is nem hogy én mi ön van vagyok vannak egy ez ha de vagy mint mikor hogyan nagyon több kérem köszönöm üdvözlettel jó napot szeretném kell volt lesz tisztelt már még csak meg',
        'ro' => 'și este nu că eu noi voi dumneavoastră un o la în cu de pe pentru din sunt am are avem avea dar sau dacă cum când foarte mai mult bună ziua mulțumesc vă rog stimă meu mea nostru acest această care să nu-i',
        'hr' => 'i je nije da ja mi vi na u s za od do se to taj ta kako kada ali ili ako također vrlo više imam ima imamo su bio dobar dan molim hvala poštovani pozdrav lijep moj moja naš vaš sam smo koji još',
        'tr' => 've bir bu için ile değil ben biz siz var yok mı mi ne nasıl zaman ama veya eğer çok daha merhaba teşekkürler teşekkür ederim lütfen iyi günler benim bizim sizin olarak olan gibi kadar sayın saygılarımla',
        'ru' => 'и не я вы мы он она это что как на в с по для от до но или если когда где очень мой моя наш ваш есть был быть здравствуйте спасибо пожалуйста добрый день уважением меня вас нам у за из к можно также',
        'uk' => 'і й не я ви ми він вона це що як на в у з по для від до але або якщо коли де дуже мій моя наш ваш є був бути вітаю дякую будь ласка добрий день повагою мене вас нам за із також та чи',
        'bg' => 'и не аз вие ние той тя това че как на в с по за от до но или ако кога къде много моя моят наш ваш е съм са беше здравейте благодаря моля добър ден уважение ме ви ни да се също ще',
    ];

    /**
     * Language of messages (html, the newest first), then of a subject: the first one that says it.
     *
     * @return string code of LANGUAGES, '' when not known
     */
    public static function find(array $bodies, $subject = '')
    {
        foreach ($bodies as $body) {
            $lines = [];
            foreach (array_slice(CustomerName::lines((string) $body), 0, self::MAX_LINES) as $line) {
                if (preg_match('/^(--|—|__+)$/u', $line)) {
                    break;
                }
                $lines[] = $line;
            }
            $code = self::text(implode(' ', $lines));
            if ($code !== '') {
                return $code;
            }
        }

        return self::text((string) $subject);
    }

    public static function text($text)
    {
        $text = (string) preg_replace('#https?://\S+|\S+@\S+|www\.\S+#u', ' ', (string) $text);
        $letters = preg_match_all('/\p{L}/u', $text);
        if (!$letters) {
            return '';
        }
        // scripts of one language
        $kana = preg_match_all('/[\p{Hiragana}\p{Katakana}]/u', $text);
        foreach (['ko' => '\p{Hangul}', 'el' => '\p{Greek}', 'ar' => '\p{Arabic}', 'he' => '\p{Hebrew}', 'th' => '\p{Thai}', 'zh' => '\p{Han}'] as $code => $script) {
            $n = preg_match_all('/['.$script.']/u', $text);
            if ($code === 'zh') {
                $n += $kana;
            }
            if ($n >= 2 && $n * 2 > $letters) {
                return $code === 'zh' && $kana * 10 >= $n ? 'ja' : $code;
            }
        }
        $text = str_replace('’', "'", mb_strtolower($text, 'UTF-8'));
        preg_match_all("/[\p{L}\p{M}]+(?:['\-][\p{L}\p{M}]+)*/u", $text, $m);
        $words = array_slice($m[0], 0, self::MAX_WORDS);
        list($of, $weight) = self::index();
        $score = [];
        $hits = [];
        foreach ($words as $w) {
            foreach ($of[$w] ?? [] as $code) {
                $score[$code] = ($score[$code] ?? 0) + $weight[$w];
                $hits[$code] = ($hits[$code] ?? 0) + 1;
            }
        }
        // letters that only some languages have
        foreach (['fr' => '[èêùœ]', 'it' => '[òì]', 'de' => 'ß', 'es' => '[ñ¿¡]', 'pt' => '[ãõ]', 'pl' => '[ąęłśźż]', 'cs' => '[ěřů]', 'sk' => '[ľĺŕô]', 'hu' => '[őű]', 'ro' => '[șțăşţ]', 'tr' => '[ğış]', 'uk' => '[іїєґ]', 'ru' => '[ыэё]', 'bg' => 'ъ', 'da' => '[æø]', 'no' => '[æø]'] as $code => $set) {
            if ($n = preg_match_all('/'.$set.'/u', $text)) {
                $score[$code] = ($score[$code] ?? 0) + min(2, $n * 0.4);
            }
        }
        if (!$score) {
            return '';
        }
        arsort($score);
        $codes = array_keys($score);
        $best = $score[$codes[0]];
        $next = isset($codes[1]) ? $score[$codes[1]] : 0;

        // the less there is, the clearer it must be
        return ($best >= 0.95 || ($best >= 0.65 && ($hits[$codes[0]] ?? 0) > 1)) && $best >= $next * ($best < 1.5 ? 1.5 : 1.2) && $best >= count($words) * 0.06 ? $codes[0] : '';
    }

    /** @return array [word => codes, word => weight] */
    protected static function index()
    {
        static $index = null;
        if ($index === null) {
            $of = [];
            foreach (self::WORDS as $code => $list) {
                foreach (array_unique(explode(' ', $list)) as $w) {
                    $of[$w][] = $code;
                }
            }
            $weight = [];
            foreach ($of as $w => $codes) {
                // alone, "a" or "i" says little
                $weight[$w] = (mb_strlen($w) > 3 ? 1 : (mb_strlen($w) > 1 ? 0.7 : 0.3)) / count($codes);
            }
            $index = [$of, $weight];
        }

        return $index;
    }
}
