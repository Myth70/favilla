<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Models;

class Recurrence
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Restituisce l'array arricchito con campi calcolati:
     *  - giorni_mancanti  (int|null)
     *  - prossima_data    (string Y-m-d|null)
     *  - eta_prossima     (int|null)
     *  - urgenza          ('oggi'|'urgente'|'prossimo'|'lontano'|'passato')
     */
    public function toArray(): array
    {
        return $this->enrich();
    }

    public function enrich(): array
    {
        $today    = new \DateTime('today');
        $prossima = $this->calcolaProssimaData($today);

        if ($prossima === null) {
            $this->data['giorni_mancanti'] = null;
            $this->data['prossima_data']   = null;
            $this->data['eta_prossima']    = null;
            $this->data['urgenza']         = 'passato';
            return $this->data;
        }

        $giorni = (int) $today->diff($prossima)->format('%r%a');

        $this->data['giorni_mancanti'] = $giorni;
        $this->data['prossima_data']   = $prossima->format('Y-m-d');
        $this->data['eta_prossima']    = null;

        if ($this->data['tipo'] === 'compleanno' && !empty($this->data['anno_riferimento'])) {
            $this->data['eta_prossima'] = (int) $prossima->format('Y') - (int) $this->data['anno_riferimento'];
        }

        $this->data['urgenza'] = match (true) {
            $giorni === 0  => 'oggi',
            $giorni <= 7   => 'urgente',
            $giorni <= 30  => 'prossimo',
            default        => 'lontano',
        };

        return $this->data;
    }

    public function calcolaProssimaData(\DateTime $today): ?\DateTime
    {
        $base = \DateTime::createFromFormat('Y-m-d', $this->data['data_ricorrenza']);
        if (!$base) {
            return null;
        }

        if (!$this->data['annuale']) {
            return $base >= $today ? $base : null;
        }

        $prossima = (clone $today)->setDate(
            (int) $today->format('Y'),
            (int) $base->format('m'),
            (int) $base->format('d')
        );

        if ($prossima < $today) {
            $prossima->modify('+1 year');
        }

        return $prossima;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
