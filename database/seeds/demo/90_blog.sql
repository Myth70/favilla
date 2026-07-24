-- ============================================================================
-- Demo — Blog (internal news, EN). International remote team → lingua franca EN.
-- 10 articoli (1 pinnato, 1 programmato/scheduled, 3 con cover_image), categorie
-- e tag EN, commenti EN (uno in moderazione), like. Cover copiate in
-- public/uploads/blog/ dal DemoSeeder. Caricato solo se il modulo Blog è abilitato.
-- ============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `blog_categories` (`id`, `name`, `slug`, `description`, `sort_order`, `created_by`) VALUES
    (9001, 'Announcements',    'announcements',    'Official studio announcements.',        1, 1),
    (9002, 'Studio Life',      'studio-life',      'Milestones, events and team moments.',  2, 1),
    (9003, 'Product & Process', 'product-process', 'How we build and how we work.',         3, 1);

INSERT IGNORE INTO `blog_tags` (`id`, `name`, `slug`) VALUES
    (9001, 'welcome',  'welcome'),
    (9002, 'policy',   'policy'),
    (9003, 'results',  'results'),
    (9004, 'remote',   'remote'),
    (9005, 'team',     'team'),
    (9006, 'product',  'product');

INSERT IGNORE INTO `blog_articles`
    (`id`, `title`, `slug`, `excerpt`, `content`, `cover_image`, `category_id`, `status`, `is_pinned`, `visibility`, `reading_time`, `view_count`, `published_at`, `publish_at`, `created_by`, `created_at`)
VALUES
    (9001, 'Aurora Studio intranet is live', 'aurora-studio-intranet-is-live',
     'Our new digital home: what you''ll find and where to start.',
     '<p>From today, everything about the studio lives here: tasks, a shared calendar, documents with an approval workflow, and team chat.</p><p>Three things to do right now:</p><ul><li>Complete your profile and pick the theme you like — the UI follows your language;</li><li>Take a look at the dashboard: widgets rearrange with drag &amp; drop;</li><li>Time-off requests go through the shared calendar (see the procedure under Documenti).</li></ul><p>Any doubts? The help button (top-right) answers contextually on every page.</p>',
     'demo-blog-welcome.png', 9001, 'published', 1, 'all', 2, 214, CONCAT(YEAR(CURDATE()),'-01-10 09:00:00'), NULL, 1, CONCAT(YEAR(CURDATE()),'-01-09 18:00:00')),

    (9002, 'New leave & time-off policy', 'new-leave-time-off-policy',
     'From next month, requests go through the shared calendar. Here''s how it works.',
     '<p>We''ve simplified leave and time-off: requests are made directly from the shared calendar, and your manager approves within 3 working days.</p><p>The full document — <em>Procedura gestione ferie e permessi</em> — is under review in Documenti and will be published shortly. Collective closures are still announced in advance.</p>',
     NULL, 9001, 'published', 0, 'all', 1, 46, (NOW() - INTERVAL 4 DAY), NULL, 1, (NOW() - INTERVAL 4 DAY)),

    (9003, 'Bottega Verde campaign: +38% sign-ups', 'bottega-verde-campaign-results',
     'The bio-line launch campaign wraps up: the numbers and the lessons.',
     '<p>The launch campaign for Bottega Verde''s new bio line closed with <strong>+38% newsletter sign-ups</strong> and a <strong>4.2% landing conversion rate</strong>.</p><p>What worked: an editorial plan built on community content and a collaboration with a niche content creator. The full report is in the project files.</p><p>Congrats to Lucía and Emily for the great work!</p>',
     'demo-blog-campaign.png', 9002, 'published', 0, 'all', 2, 187, CONCAT(YEAR(CURDATE()),'-03-14 11:00:00'), NULL, 12, CONCAT(YEAR(CURDATE()),'-03-14 10:00:00')),

    (9004, 'How we work: async &amp; remote culture', 'how-we-work-async-remote',
     'A distributed team across five countries: our simple rules for working well.',
     '<p>We''re a remote team spread across Italy, the UK, France, Germany and Spain. English is our shared working language; between teammates who share a language, direct chats happen in the mother tongue.</p><p>Our rules of thumb: default to async, write things down, keep meetings short, and over-communicate context. The weekly sync keeps everyone aligned without endless calls.</p>',
     NULL, 9002, 'published', 0, 'all', 3, 92, CONCAT(YEAR(CURDATE()),'-04-22 09:00:00'), NULL, 4, CONCAT(YEAR(CURDATE()),'-04-22 08:30:00')),

    (9005, 'Meet the team: new joiners', 'meet-the-team-new-joiners',
     'A quick hello to the people who make Aurora Studio.',
     '<p>Say hello to the crew: project leads, developers, designers, content and social. Different countries, one studio.</p><p>Drop by the <em>Aurora — General</em> channel and introduce yourself — and don''t forget to set your avatar!</p>',
     NULL, 9002, 'published', 0, 'all', 1, 61, CONCAT(YEAR(CURDATE()),'-05-06 10:00:00'), NULL, 3, CONCAT(YEAR(CURDATE()),'-05-06 09:30:00')),

    (9006, 'Q2 results and what''s next', 'q2-results-and-whats-next',
     'A look back at the quarter and the roadmap ahead.',
     '<p>Q2 was solid: the Bottega Verde campaign closed above target and the Rossetti e-commerce moved into the catalog milestone. The internal management tool reached its first release.</p><p>Next up: Rossetti go-live and the Nordlys rebrand kickoff. Details at the next commercial review.</p>',
     NULL, 9001, 'published', 0, 'all', 2, 99, CONCAT(YEAR(CURDATE()),'-07-02 09:00:00'), NULL, 1, CONCAT(YEAR(CURDATE()),'-07-02 08:30:00')),

    (9007, 'How we use the dashboard', 'how-we-use-the-dashboard',
     'Tips to make the Home dashboard work for your role.',
     '<p>The Home dashboard is per-user: rearrange widgets with drag &amp; drop, hide what you don''t need, resize the rest. Developers keep tasks and project boards on top; content folks pin the blog and calendar.</p><p>Pro tip: the weekly timeline widget gives you a quick glance at what''s coming across every module.</p>',
     NULL, 9003, 'published', 0, 'all', 2, 74, CONCAT(YEAR(CURDATE()),'-09-03 09:00:00'), NULL, 6, CONCAT(YEAR(CURDATE()),'-09-03 08:30:00')),

    (9008, 'New client: Nordlys Interiors', 'new-client-nordlys-interiors',
     'We''re starting a rebrand and multilingual site for a Berlin studio.',
     '<p>We''re thrilled to welcome <strong>Nordlys Interiors</strong> (Berlin) as a new client. The project: a full rebrand plus a multilingual site (DE/EN). Kickoff is scheduled for November.</p><p>Follow along in the <em>Nordlys Rebrand</em> channel.</p>',
     NULL, 9001, 'published', 0, 'all', 1, 53, (NOW() - INTERVAL 6 DAY), NULL, 4, (NOW() - INTERVAL 6 DAY)),

    (9009, 'Team offsite recap', 'team-offsite-recap',
     'Two days by the lake: workshops, planning and a lot of laughs.',
     '<p>Our annual offsite brought the whole studio together for two days by Lake Garda. We ran product workshops, sketched the roadmap, and — most importantly — finally met in person.</p><p>Thanks everyone for making it special. Photos are in the shared files!</p>',
     'demo-blog-offsite.png', 9002, 'published', 0, 'all', 2, 141, CONCAT(YEAR(CURDATE()),'-06-21 18:00:00'), NULL, 7, CONCAT(YEAR(CURDATE()),'-06-21 17:00:00')),

    (9010, 'Coming soon: knowledge base refresh', 'knowledge-base-refresh',
     'We''re reorganising the internal help. Sneak peek next week.',
     '<p>We''re refreshing the online help and internal knowledge base: clearer structure, better search, more examples. This post is scheduled — you''re reading a preview of what''s coming.</p>',
     NULL, 9003, 'scheduled', 0, 'all', 1, 0, NULL, (NOW() + INTERVAL 5 DAY), 1, (NOW() - INTERVAL 1 DAY));

INSERT IGNORE INTO `blog_article_tags` (`article_id`, `tag_id`) VALUES
    (9001, 9001), (9002, 9002), (9003, 9003), (9004, 9004),
    (9005, 9005), (9006, 9003), (9007, 9006), (9008, 9005), (9009, 9005), (9010, 9006);

INSERT IGNORE INTO `blog_comments` (`id`, `article_id`, `user_id`, `parent_id`, `body`, `status`, `created_at`) VALUES
    (9001, 9001, 6,  NULL, 'The configurable dashboard is brilliant — already moved my tasks to the top.', 'approved', CONCAT(YEAR(CURDATE()),'-01-11 09:00:00')),
    (9002, 9001, 10, NULL, 'Much better than email! Where do I find the guide for Telegram notifications?', 'approved', CONCAT(YEAR(CURDATE()),'-01-11 11:00:00')),
    (9003, 9001, 1,  9002, 'In your profile → Notification preferences: connect the bot and pick which events to receive.', 'approved', CONCAT(YEAR(CURDATE()),'-01-11 11:30:00')),
    (9004, 9002, 8,  NULL, 'Finally a clear procedure, thanks!', 'approved', (NOW() - INTERVAL 3 DAY)),
    (9005, 9003, 3,  NULL, 'Great numbers — let''s bring them to Thursday''s commercial review.', 'approved', CONCAT(YEAR(CURDATE()),'-03-14 15:00:00')),
    (9006, 9004, 11, NULL, 'Love the async-first approach. Could we document the meeting-notes template too?', 'pending', (NOW() - INTERVAL 6 HOUR));

INSERT IGNORE INTO `blog_article_likes` (`article_id`, `user_id`) VALUES
    (9001, 3), (9001, 6), (9001, 8), (9001, 11), (9001, 12),
    (9003, 1), (9003, 3), (9003, 5), (9003, 7), (9003, 12),
    (9009, 1), (9009, 4), (9009, 6), (9009, 9);
