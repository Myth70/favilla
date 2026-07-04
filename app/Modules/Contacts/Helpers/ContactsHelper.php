<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Helpers;

class ContactsHelper
{
    /**
     * Restituisce l'URL della foto di un contatto (o null se assente).
     * uploads/contacts non è servita da Apache: si passa dalla route
     * contatti.foto, che applica la stessa visibilità della scheda.
     */
    public static function avatarUrl(array $item): ?string
    {
        if (empty($item['avatar']) || empty($item['id'])) {
            return null;
        }

        return route('contacts.foto', ['id' => (int) $item['id']]);
    }

    /**
     * Descrittori delle icone di contatto/social per il dock della scheda.
     * Ogni voce ritorna sempre label, icon, value, href, display, target.
     * Se value e' vuoto, href e' null (icona grigia, non cliccabile).
     *
     * Ordine: canali diretti prima (email, tel), poi messaggistica, poi social.
     */
    public static function socialDescriptors(array $item): array
    {
        $phone = static function (?string $raw): ?string {
            if (!$raw) {
                return null;
            }
            $clean = preg_replace('/[^\d+]/', '', $raw);
            return $clean !== '' ? $clean : null;
        };
        $waDigits = static function (?string $raw): ?string {
            if (!$raw) {
                return null;
            }
            $clean = preg_replace('/\D+/', '', $raw);
            return $clean !== '' ? $clean : null;
        };
        $handle = static fn (?string $raw): ?string => $raw ? ltrim($raw, '@') : null;

        $email    = $item['email']        ?? null;
        $tel      = $item['telefono']     ?? null;
        $telAlt   = $item['telefono_alt'] ?? null;
        $sito     = $item['sito_web']     ?? null;
        $wa       = $item['whatsapp']     ?? null;
        $tg       = $item['telegram']     ?? null;
        $li       = $item['linkedin']     ?? null;
        $tw       = $item['twitter']      ?? null;
        $ig       = $item['instagram']    ?? null;
        $fb       = $item['facebook']     ?? null;

        return [
            [
                'key'     => 'email',
                'label'   => 'Email',
                'icon'    => 'fa-solid fa-envelope',
                'value'   => $email,
                'href'    => $email ? 'mailto:' . $email : null,
                'display' => $email,
                'target'  => '_self',
            ],
            [
                'key'     => 'telefono',
                'label'   => 'Telefono',
                'icon'    => 'fa-solid fa-phone',
                'value'   => $tel,
                'href'    => $tel ? 'tel:' . $phone($tel) : null,
                'display' => $tel,
                'target'  => '_self',
            ],
            [
                'key'     => 'telefono_alt',
                'label'   => 'Telefono alternativo',
                'icon'    => 'fa-solid fa-phone-flip',
                'value'   => $telAlt,
                'href'    => $telAlt ? 'tel:' . $phone($telAlt) : null,
                'display' => $telAlt,
                'target'  => '_self',
            ],
            [
                'key'     => 'sito_web',
                'label'   => 'Sito web',
                'icon'    => 'fa-solid fa-globe',
                'value'   => $sito,
                'href'    => $sito ?: null,
                'display' => $sito,
                'target'  => '_blank',
            ],
            [
                'key'     => 'whatsapp',
                'label'   => 'WhatsApp',
                'icon'    => 'fa-brands fa-whatsapp',
                'value'   => $wa,
                'href'    => $wa ? 'https://wa.me/' . $waDigits($wa) : null,
                'display' => $wa,
                'target'  => '_blank',
            ],
            [
                'key'     => 'telegram',
                'label'   => 'Telegram',
                'icon'    => 'fa-brands fa-telegram',
                'value'   => $tg,
                'href'    => $tg ? 'https://t.me/' . $handle($tg) : null,
                'display' => $tg,
                'target'  => '_blank',
            ],
            [
                'key'     => 'linkedin',
                'label'   => 'LinkedIn',
                'icon'    => 'fa-brands fa-linkedin-in',
                'value'   => $li,
                'href'    => $li ?: null,
                'display' => $li,
                'target'  => '_blank',
            ],
            [
                'key'     => 'twitter',
                'label'   => 'Twitter / X',
                'icon'    => 'fa-brands fa-x-twitter',
                'value'   => $tw,
                'href'    => $tw ? 'https://twitter.com/' . $handle($tw) : null,
                'display' => $tw,
                'target'  => '_blank',
            ],
            [
                'key'     => 'instagram',
                'label'   => 'Instagram',
                'icon'    => 'fa-brands fa-instagram',
                'value'   => $ig,
                'href'    => $ig ? 'https://instagram.com/' . $handle($ig) : null,
                'display' => $ig,
                'target'  => '_blank',
            ],
            [
                'key'     => 'facebook',
                'label'   => 'Facebook',
                'icon'    => 'fa-brands fa-facebook-f',
                'value'   => $fb,
                'href'    => $fb ?: null,
                'display' => $fb,
                'target'  => '_blank',
            ],
        ];
    }

    /**
     * Trova la prossima ricorrenza (urgenza diversa da "passato")
     * con il numero minimo di giorni mancanti.
     * Ritorna null se nessuna ricorrenza futura.
     */
    public static function prossimaRicorrenza(array $ricorrenze): ?array
    {
        $prossima = null;
        foreach ($ricorrenze as $r) {
            if (($r['urgenza'] ?? null) === 'passato') {
                continue;
            }
            $g = $r['giorni_mancanti'] ?? null;
            if ($g === null) {
                continue;
            }
            if ($prossima === null || $g < ($prossima['giorni_mancanti'] ?? PHP_INT_MAX)) {
                $prossima = $r;
            }
        }
        return $prossima;
    }
}
