<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Container;

/**
 * Helper per pilotare il Container DI nei test: registrare mock e ottenere un
 * container pulito senza ripetere il boilerplate di Container::setInstance().
 *
 * I service del progetto risolvono le dipendenze con app(ClassName::class), che
 * legge dal container globale: registrare qui un mock fa sì che il codice sotto
 * test usi il doppio invece dell'implementazione reale.
 *
 * Uso:
 *   $repo = $this->createMock(RoleRepository::class);
 *   $this->bindInstance(RoleRepository::class, $repo);
 *   $service = new RoleService(); // risolve il mock via app(RoleRepository::class)
 */
trait MakesContainer
{
    /**
     * Restituisce il container globale corrente, creandone uno nuovo se assente.
     */
    protected function container(): Container
    {
        $container = Container::getInstance();
        // Garantisce che l'istanza statica e quella locale coincidano.
        Container::setInstance($container);

        return $container;
    }

    /**
     * Sostituisce il container globale con uno vuoto e lo restituisce.
     */
    protected function freshContainer(): Container
    {
        $container = new Container();
        Container::setInstance($container);

        return $container;
    }

    /**
     * Registra un'istanza già pronta (tipicamente un mock) nel container globale.
     */
    protected function bindInstance(string $abstract, mixed $object): void
    {
        $this->container()->instance($abstract, $object);
    }
}
