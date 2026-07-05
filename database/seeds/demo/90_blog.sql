-- ============================================================================
-- Demo — Blog (news interne: 3 articoli pubblicati, commenti, tag, like)
-- Caricato solo se il modulo Blog è abilitato.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `blog_categories` (`id`, `name`, `slug`, `description`, `sort_order`, `created_by`) VALUES
    (9001, 'Comunicazioni',  'comunicazioni',  'Annunci ufficiali dello studio.', 1, 1),
    (9002, 'Vita in studio', 'vita-in-studio', 'Traguardi, eventi e momenti del team.', 2, 1);

INSERT IGNORE INTO `blog_tags` (`id`, `name`, `slug`) VALUES
    (9001, 'benvenuto', 'benvenuto'),
    (9002, 'procedure', 'procedure'),
    (9003, 'traguardi', 'traguardi');

INSERT IGNORE INTO `blog_articles`
    (`id`, `title`, `slug`, `excerpt`, `content`, `category_id`, `status`, `is_pinned`, `visibility`, `reading_time`, `view_count`, `published_at`, `created_by`, `created_at`)
VALUES
    (9001, 'Benvenuti nella intranet di Aurora Studio', 'benvenuti-nella-intranet',
     'La nostra nuova casa digitale: cosa trovate e da dove cominciare.',
     '<p>Da oggi tutte le informazioni dello studio vivono qui: attività, calendario condiviso, documenti con workflow di approvazione e la chat di team.</p><p>Tre cose da fare subito:</p><ul><li>Completate il vostro profilo e scegliete il tema che preferite;</li><li>Date un''occhiata alla dashboard: i widget si riordinano col drag &amp; drop;</li><li>Le richieste ferie passano dal calendario condiviso (vedi la procedura in Documenti).</li></ul><p>Per qualunque dubbio c''è il punto interrogativo in alto a destra: la guida contestuale risponde su ogni pagina.</p>',
     9001, 'published', 1, 'all', 2, 47, (NOW() - INTERVAL 30 DAY), 1, (NOW() - INTERVAL 31 DAY)),
    (9002, 'Nuova procedura ferie e permessi', 'nuova-procedura-ferie',
     'Dal mese prossimo le richieste passano dal calendario condiviso: ecco come funziona.',
     '<p>Abbiamo semplificato la gestione di ferie e permessi: la richiesta si fa direttamente dal calendario condiviso, il responsabile approva entro 3 giorni lavorativi.</p><p>Il documento completo — <em>Procedura gestione ferie e permessi</em> — è in approvazione nel modulo Documenti e sarà pubblicato a breve. Le chiusure collettive restano comunicate entro maggio.</p>',
     9001, 'published', 0, 'all', 1, 23, (NOW() - INTERVAL 4 DAY), 1, (NOW() - INTERVAL 4 DAY)),
    (9003, 'Campagna Bottega Verde: +38% di iscritti', 'campagna-bottega-verde-risultati',
     'Si chiude la campagna di lancio della linea bio: i numeri e cosa abbiamo imparato.',
     '<p>La campagna per il lancio della nuova linea bio di Bottega Verde si è chiusa con un +38% di iscritti alla newsletter e un tasso di conversione della landing del 4,2%.</p><p>Cosa ha funzionato: il piano editoriale costruito sui contenuti della community e la collaborazione con una content creator del settore. Il report completo è nei file del progetto.</p><p>Complimenti a Isabella e Micaela per il lavoro!</p>',
     9002, 'published', 0, 'all', 2, 35, (NOW() - INTERVAL 5 DAY), 8, (NOW() - INTERVAL 5 DAY));

INSERT IGNORE INTO `blog_article_tags` (`article_id`, `tag_id`) VALUES
    (9001, 9001),
    (9002, 9002),
    (9003, 9003);

INSERT IGNORE INTO `blog_comments` (`id`, `article_id`, `user_id`, `parent_id`, `body`, `status`, `created_at`) VALUES
    (9001, 9001, 6,  NULL, 'La dashboard configurabile è comodissima, ho già messo i task in cima.', 'approved', (NOW() - INTERVAL 29 DAY)),
    (9002, 9001, 10, NULL, 'Molto meglio delle mail! Dove trovo la guida per le notifiche Telegram?', 'approved', (NOW() - INTERVAL 28 DAY)),
    (9003, 9001, 1,  9002, 'Nel tuo profilo → Preferenze notifiche: colleghi il bot e scegli quali eventi ricevere.', 'approved', (NOW() - INTERVAL 28 DAY)),
    (9004, 9002, 8,  NULL, 'Finalmente una procedura chiara, grazie!', 'approved', (NOW() - INTERVAL 3 DAY)),
    (9005, 9003, 3,  NULL, 'Numeri ottimi — portiamoli alla riunione commerciale di giovedì.', 'approved', (NOW() - INTERVAL 4 DAY));

INSERT IGNORE INTO `blog_article_likes` (`article_id`, `user_id`) VALUES
    (9001, 3), (9001, 6), (9001, 8), (9001, 11),
    (9003, 1), (9003, 3), (9003, 5), (9003, 9), (9003, 10);
