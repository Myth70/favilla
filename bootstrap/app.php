<?php

/**
 * Bootstrap dell'applicazione.
 * Carica autoload, crea e avvia Application.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = new \App\Core\Application(dirname(__DIR__));
$app->boot();

// i18n: register the Translator singleton and resolve the active locale for
// this request. Done here — after boot() has started the session and before
// the request is handled by index.php — because the core Application exposes
// no pluggable global-middleware slot to hook a LocaleMiddleware into.
if (PHP_SAPI !== 'cli') {
    try {
        $container = $app->getContainer();
        if (!$container->has(\App\Services\Translator::class)) {
            $container->instance(\App\Services\Translator::class, new \App\Services\Translator());
        }
        $resolver = new \App\Services\LocaleResolver(
            $container->make(\App\Services\Translator::class)
        );
        $resolver->resolve();
        $container->instance(\App\Services\LocaleResolver::class, $resolver);
    } catch (\Throwable $e) {
        app_log('error', '[i18n] Locale resolution failed: ' . $e->getMessage());
    }
}

return $app;
