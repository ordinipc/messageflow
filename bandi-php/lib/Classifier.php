<?php
/**
 * Riconoscimento dei bandi per personale docente di scuole e università.
 *
 * Filtro puramente lessicale: ogni voce riceve un punteggio dai segnali
 * positivi (ruoli di insegnamento e ricerca) e da quelli negativi
 * (ruoli non docenti, appalti, forniture). Sopra la soglia è pertinente.
 */
class BandiClassifier
{
    const SEGNALI_DOCENZA = [
        ['~\bprofessor(?:e|essa|i)\b~', 5],
        ['~\bprofessore (?:ordinario|associato|di (?:prima|i|seconda|ii) fascia)\b~', 7],
        ['~\bdocent(?:e|i|a)\b~', 4],
        ['~\binsegnant(?:e|i)\b~', 5],
        ['~\bcattedr(?:a|e)\b~', 4],
        ['~\bricercator(?:e|i)\b~', 5],
        ['~\brtd[ab]?\b|\brtt\b|\bricercatore a tempo determinato\b~', 6],
        ['~\bassegn(?:o|i) di ricerca\b|\bassegnist(?:a|i)\b~', 4],
        ['~\bdottorat(?:o|i) di ricerca\b~', 2],
        ['~\bsupplenz(?:a|e)\b|\bsupplent(?:e|i)\b~', 5],
        ['~\bmessa a disposizione\b|\bm\.a\.d\.\b|\bmad\b~', 5],
        ['~\bclasse di concorso\b|\b[ab]-\d{2}\b~', 5],
        ['~\bgps\b|\bgraduatorie provinciali per le supplenze\b~', 4],
        ['~\bincarico di insegnamento\b|\bincarichi di insegnamento\b~', 6],
        ['~\bcontratt(?:o|i) di docenza\b|\bdocenza a contratto\b|\bdocente a contratto\b~', 6],
        ['~\battivita didattica\b|\battivita di docenza\b|\bcarico didattico\b~', 3],
        ['~\bespert(?:o|i)\b.*\b(?:pnrr|formazione|corso|percorso)\b~', 2],
        ['~\btutor\b.*\b(?:pnrr|didattic|orientament)~', 2],
        ['~\bcultore della materia\b~', 3],
        ['~\bconcorso (?:ordinario|straordinario)\b.*\bdocent~', 6],
        ['~\bpersonale docente\b~', 5],
        ['~\bselezione pubblica\b.*\b(?:docenz|insegnament|professor|ricercator)~', 5],
        ['~\bprocedura (?:comparativa|di selezione|valutativa|di chiamata)\b.*\b(?:professor|ricercator|docent)~', 6],
        ['~\bchiamata\b.*\b(?:art\.? ?18|art\.? ?24)\b~', 4],
    ];

    const SEGNALI_ESCLUSIONE = [
        ['~\bpersonale ata\b~', -6],
        ['~\bcollaborator(?:e|i) scolastic(?:o|i)\b~', -6],
        ['~\bassistente amministrativ~', -5],
        ['~\bassistente tecnic~', -4],
        ['~\bdirettore dei servizi generali\b|\bdsga\b~', -5],
        ['~\bpersonale tecnico[- ]amministrativo\b|\bcategoria [bcd]\b~', -4],
        ['~\bdirigente scolastic~', -3],
        ['~\boperatore socio[- ]sanitario\b|\binfermier~', -5],
        ['~\bautist(?:a|i)\b|\bcuoc(?:o|hi)\b|\bmanutentore\b~', -5],
        ['~\bagente di polizia\b|\bvigil(?:e|i) urban~', -6],
        ['~\bfornitura\b|\baffidamento (?:del )?servizio\b|\bappalto\b|\bgara\b~', -4],
        ['~\bnoleggio\b|\bacquisto\b|\bmanutenzione\b~', -4],
        ['~\bborsa di studio\b(?!.*docen)~', -1],
    ];

    const SEGNALI_UNIVERSITA = [
        '~\buniversita\b~', '~\bateneo\b~', '~\bpolitecnico\b~', '~\bdipartimento di\b~',
        '~\bprofessore (?:ordinario|associato)\b~', '~\brtd[ab]?\b|\brtt\b~', '~\bassegno di ricerca\b~',
        '~\bsettore (?:scientifico[- ]disciplinare|concorsuale)\b~', '~\bssd\b~', '~\bafam\b~',
        '~\bconservatorio\b~', '~\baccademia di belle arti\b~',
    ];

    const SEGNALI_SCUOLA = [
        '~\bistituto comprensivo\b~', '~\bistituto (?:tecnico|professionale|superiore)\b~', '~\bliceo\b~',
        '~\bscuola (?:dell\'infanzia|primaria|secondaria|paritaria|statale)\b~', '~\bdirezione didattica\b~',
        '~\bclasse di concorso\b~', '~\bsupplenz~', '~\bmessa a disposizione\b~', '~\bgps\b~',
        '~\bufficio scolastico (?:regionale|provinciale|territoriale)\b~', '~\bambito territoriale\b~',
        '~\bconcorso (?:ordinario|straordinario)\b~', '~\bposto comune\b~', '~\bsostegno\b~',
    ];

    const CATEGORIE = [
        ['supplenza', '~\bsupplenz|\bmessa a disposizione\b|\bmad\b|\bgps\b|\bgraduatori(?:a|e) d\'istituto\b~'],
        ['concorso', '~\bconcorso\b|\bconcorsi\b|\bprova (?:scritta|orale)\b~'],
        ['ricerca', '~\bassegno di ricerca\b|\bricercator|\brtd[ab]?\b|\brtt\b|\bborsa di ricerca\b~'],
        ['chiamata', '~\bprocedura (?:valutativa|di chiamata)\b|\bart\.? ?(?:18|24)\b|\btrasferiment|\bmobilita\b~'],
        ['contratto', '~\bdocenza a contratto\b|\bincarico di insegnamento\b|\bcontratt(?:o|i) di docenza\b|\bcollaborazione\b~'],
        ['formazione', '~\bcorso di formazione\b|\bpercorso formativo\b|\bpnrr\b|\btutor\b|\bespert~'],
    ];

    const REGIONI = [
        'abruzzo', 'basilicata', 'calabria', 'campania', 'emilia-romagna', 'emilia romagna',
        'friuli-venezia giulia', 'friuli venezia giulia', 'lazio', 'liguria', 'lombardia', 'marche',
        'molise', 'piemonte', 'puglia', 'sardegna', 'sicilia', 'toscana', 'trentino-alto adige',
        'trentino alto adige', 'umbria', 'valle d\'aosta', 'veneto',
    ];

    /** Minuscole senza accenti: i bandi scrivono "università" e "universita" indifferentemente. */
    public static function normalizza($testo)
    {
        $testo = mb_strtolower((string) $testo, 'UTF-8');
        $senzaAccenti = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $testo);
        if ($senzaAccenti !== false) {
            $testo = strtolower($senzaAccenti);
        }
        return trim(preg_replace('/\s+/u', ' ', $testo));
    }

    private static function punteggioTesto($testo)
    {
        $punteggio = 0;
        $segnali = [];

        foreach (array_merge(self::SEGNALI_DOCENZA, self::SEGNALI_ESCLUSIONE) as $regola) {
            if (preg_match($regola[0], $testo)) {
                $punteggio += $regola[1];
                $segnali[] = $regola[0];
            }
        }

        return ['punteggio' => $punteggio, 'segnali' => $segnali];
    }

    public static function livello($testo)
    {
        $uni = 0;
        $scuola = 0;
        foreach (self::SEGNALI_UNIVERSITA as $modello) {
            $uni += preg_match($modello, $testo) ? 1 : 0;
        }
        foreach (self::SEGNALI_SCUOLA as $modello) {
            $scuola += preg_match($modello, $testo) ? 1 : 0;
        }

        if ($uni === 0 && $scuola === 0) {
            return 'sconosciuto';
        }
        if ($uni > $scuola) {
            return 'universita';
        }
        return $scuola > $uni ? 'scuola' : 'sconosciuto';
    }

    public static function categoria($testo)
    {
        foreach (self::CATEGORIE as $categoria) {
            if (preg_match($categoria[1], $testo)) {
                return $categoria[0];
            }
        }
        return 'altro';
    }

    public static function regione($testo)
    {
        foreach (self::REGIONI as $regione) {
            if (strpos($testo, $regione) !== false) {
                return str_replace(' ', '-', $regione);
            }
        }
        return null;
    }

    public static function ente($testoOriginale)
    {
        $modelli = [
            '~Universit[àa](?:\s+degli\s+Studi)?(?:\s+(?:di|del|della|dell\'|per))?\s+[A-ZÀ-Ù][\w\'’.-]*(?:\s+[A-ZÀ-Ù][\w\'’.-]*){0,3}~u',
            '~Politecnico\s+di\s+[A-ZÀ-Ù][\w\'’.-]*~u',
            '~Istituto\s+(?:Comprensivo|Tecnico|Professionale|Superiore|d\'Istruzione\s+Superiore)[\s\w\'’.-]{0,40}~ui',
            '~Liceo\s+[\w\'’.-]+(?:\s+[\w\'’.-]+){0,3}~ui',
            '~Ufficio\s+Scolastico\s+(?:Regionale|Provinciale|Territoriale)[\s\w\'’.-]{0,30}~ui',
            '~Conservatorio(?:\s+(?:di|statale))?\s+[\w\'’.-]+(?:\s+[\w\'’.-]+){0,3}~ui',
            '~Accademia\s+di\s+Belle\s+Arti[\s\w\'’.-]{0,30}~ui',
        ];

        foreach ($modelli as $modello) {
            if (!preg_match($modello, (string) $testoOriginale, $m)) {
                continue;
            }
            // Il match può sbordare oltre il nome: si taglia al primo separatore.
            // Il punto taglia solo dopo una parola intera, per non spezzare "S. Giovanni".
            $parti = preg_split('~\s[-–—]\s|[,:(]|\s\|\s|(?<=[a-zà-ù]{2})\.\s~u', $m[0]);
            return trim(preg_replace('/[.\s]+$/u', '', preg_replace('/\s+/u', ' ', $parti[0])));
        }

        return null;
    }

    public static function classiConcorso($testoOriginale)
    {
        if (!preg_match_all('~\b([AB])-(\d{2})\b~i', (string) $testoOriginale, $m, PREG_SET_ORDER)) {
            return [];
        }
        $classi = [];
        foreach ($m as $trovato) {
            $classi[strtoupper($trovato[1]) . '-' . $trovato[2]] = true;
        }
        return array_keys($classi);
    }

    public static function ssd($testoOriginale)
    {
        $modello = '~\b(?:MAT|FIS|CHIM|INF|ING-INF|ING-IND|L-LIN|L-FIL-LET|M-PED|M-PSI|M-STO|SECS-P|SECS-S|IUS|BIO|MED|GEO|AGR|SPS|L-ART)/\d{2}\b~';
        if (!preg_match_all($modello, (string) $testoOriginale, $m)) {
            return [];
        }
        return array_values(array_unique(array_map('strtoupper', $m[0])));
    }

    /**
     * @param array{title?: string, context?: string, summary?: string} $voce
     * @return array dati di classificazione, con 'pertinente' come esito
     */
    public static function classifica(array $voce, $soglia = 5)
    {
        $titoloGrezzo = (string) ($voce['title'] ?? '');
        $contestoGrezzo = trim(((string) ($voce['summary'] ?? '')) . ' ' . ((string) ($voce['context'] ?? '')));

        $titolo = self::normalizza($titoloGrezzo);
        $contesto = self::normalizza($contestoGrezzo);
        $insieme = trim($titolo . ' ' . $contesto);

        // Il titolo pesa il doppio: il contesto di pagina è più rumoroso.
        $puntiTitolo = self::punteggioTesto($titolo);
        $puntiContesto = self::punteggioTesto($contesto);
        $punteggio = $puntiTitolo['punteggio'] * 2 + $puntiContesto['punteggio'];

        return [
            'pertinente' => $punteggio >= $soglia,
            'punteggio' => $punteggio,
            'livello' => self::livello($insieme),
            'categoria' => self::categoria($insieme),
            'regione' => self::regione($insieme),
            'ente' => self::ente($titoloGrezzo . ' ' . $contestoGrezzo),
            'classiConcorso' => self::classiConcorso($titoloGrezzo . ' ' . $contestoGrezzo),
            'ssd' => self::ssd($titoloGrezzo . ' ' . $contestoGrezzo),
            'segnali' => array_values(array_unique(array_merge($puntiTitolo['segnali'], $puntiContesto['segnali']))),
        ];
    }
}
