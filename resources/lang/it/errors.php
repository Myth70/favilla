<?php

/**
 * Error pages — Italian (canonical). Messages may contain <br> (trusted,
 * developer-authored) and are echoed without e().
 */
return [
    'back_home'      => 'Torna alla home',
    'is_error_q'     => 'Pensi sia un errore?',
    'report_problem' => 'Segnala il problema',

    'not_found' => [
        'title'   => 'Pagina non trovata',
        'message' => "La pagina che stai cercando non esiste o è stata spostata.<br>Controlla l'URL oppure torna alla home.",
    ],
    'forbidden' => [
        'title'   => 'Accesso negato',
        'message' => "Non hai i permessi necessari per accedere a questa risorsa.<br>Contatta l'amministratore se pensi sia un errore.",
    ],
    'method_not_allowed' => [
        'title'   => 'Metodo non consentito',
        'message' => 'Il metodo HTTP utilizzato non è supportato per questa risorsa.<br>Verifica di star usando il metodo corretto (GET, POST, ecc.).',
    ],
    'server_error' => [
        'title'   => 'Errore interno del server',
        'message' => 'Si è verificato un errore imprevisto sul server.<br>Il problema è stato registrato e verrà analizzato al più presto.',
        'help'    => 'Aiutaci a risolvere:',
        'report'  => 'segnala questo errore',
    ],
    'maintenance' => [
        'title'   => 'Aggiornamento in corso',
        'badge'   => 'In manutenzione',
        'message' => 'Il sistema è temporaneamente non disponibile<br>per manutenzione programmata.<br>Riprova tra qualche minuto.',
        'footer'  => 'Ci scusiamo per il disagio.',
    ],
];
