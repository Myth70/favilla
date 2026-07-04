<?php

/**
 * Error pages — German.
 */
return [
    'back_home'      => 'Zurück zur Startseite',
    'is_error_q'     => 'Glauben Sie, das ist ein Fehler?',
    'report_problem' => 'Problem melden',

    'not_found' => [
        'title'   => 'Seite nicht gefunden',
        'message' => 'Die gesuchte Seite existiert nicht oder wurde verschoben.<br>Überprüfen Sie die URL oder kehren Sie zur Startseite zurück.',
    ],
    'forbidden' => [
        'title'   => 'Zugriff verweigert',
        'message' => 'Sie haben nicht die erforderlichen Berechtigungen für den Zugriff auf diese Ressource.<br>Wenden Sie sich an den Administrator, wenn Sie glauben, dass dies ein Fehler ist.',
    ],
    'method_not_allowed' => [
        'title'   => 'Methode nicht erlaubt',
        'message' => 'Die verwendete HTTP-Methode wird für diese Ressource nicht unterstützt.<br>Stellen Sie sicher, dass Sie die richtige Methode verwenden (GET, POST usw.).',
    ],
    'server_error' => [
        'title'   => 'Interner Serverfehler',
        'message' => 'Auf dem Server ist ein unerwarteter Fehler aufgetreten.<br>Das Problem wurde protokolliert und wird so bald wie möglich untersucht.',
        'help'    => 'Helfen Sie uns bei der Lösung:',
        'report'  => 'diesen Fehler melden',
    ],
    'maintenance' => [
        'title'   => 'Aktualisierung läuft',
        'badge'   => 'In Wartung',
        'message' => 'Das System ist vorübergehend nicht verfügbar<br>wegen geplanter Wartung.<br>Bitte versuchen Sie es in einigen Minuten erneut.',
        'footer'  => 'Wir entschuldigen uns für die Unannehmlichkeiten.',
    ],
];
