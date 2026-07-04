<?php

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Repositories\RecurrencesRepository;
use App\Modules\Contacts\Services\ContactsReminderService;
use App\Modules\Contacts\Services\RecurrencesService;
use PHPUnit\Framework\TestCase;

/**
 * processForUser() decide se inviare reminder (anticipo / giorno stesso) con
 * deduplica per anno. L'invio effettivo (NotificationService statico) è racchiuso
 * in try/catch silenzioso, quindi il conteggio resta deterministico nei test.
 * ricService usa il modello Recurrence (logica di date pura).
 */
class ContactsReminderServiceTest extends TestCase
{
    private RecurrencesRepository $ricRepo;
    private ContactsReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ricRepo = $this->createMock(RecurrencesRepository::class);
        $ricService = new RecurrencesService($this->createMock(RecurrencesRepository::class));
        $this->service = new ContactsReminderService($this->ricRepo, $ricService);
    }

    /** @return array<string,mixed> */
    private function recurrence(array $overrides): array
    {
        return array_merge([
            'id' => 1, 'contatto_id' => 10, 'nome' => 'Mario', 'cognome' => 'Rossi',
            'tipo' => 'compleanno', 'titolo' => 'Compleanno', 'note' => '',
            'annuale' => 0, 'promemoria_giorni_prima' => 7, 'notifica_giorno_stesso' => 0,
            'last_notified_year' => 0,
        ], $overrides);
    }

    public function testNotifiesAdvanceReminderOnMatchingDay(): void
    {
        $in7 = (new \DateTime('today'))->modify('+7 days')->format('Y-m-d');
        $this->ricRepo->method('allForUserWithContatto')->willReturn([
            $this->recurrence(['data_ricorrenza' => $in7, 'promemoria_giorni_prima' => 7]),
        ]);
        $this->ricRepo->expects($this->once())->method('updateLastNotified');

        $this->assertSame(1, $this->service->processForUser(1));
    }

    public function testSkipsWhenAlreadyNotifiedThisYear(): void
    {
        $in7 = (new \DateTime('today'))->modify('+7 days')->format('Y-m-d');
        $this->ricRepo->method('allForUserWithContatto')->willReturn([
            $this->recurrence([
                'data_ricorrenza' => $in7,
                'promemoria_giorni_prima' => 7,
                'last_notified_year' => (int) date('Y'),
            ]),
        ]);
        $this->ricRepo->expects($this->never())->method('updateLastNotified');

        $this->assertSame(0, $this->service->processForUser(1));
    }

    public function testNotifiesSameDayWhenEnabled(): void
    {
        $today = (new \DateTime('today'))->format('Y-m-d');
        $this->ricRepo->method('allForUserWithContatto')->willReturn([
            $this->recurrence([
                'data_ricorrenza' => $today,
                'promemoria_giorni_prima' => 0,
                'notifica_giorno_stesso' => 1,
            ]),
        ]);

        $this->assertSame(1, $this->service->processForUser(1));
    }

    public function testNoNotificationWhenOutsideWindow(): void
    {
        $in20 = (new \DateTime('today'))->modify('+20 days')->format('Y-m-d');
        $this->ricRepo->method('allForUserWithContatto')->willReturn([
            $this->recurrence(['data_ricorrenza' => $in20, 'promemoria_giorni_prima' => 7]),
        ]);

        $this->assertSame(0, $this->service->processForUser(1));
    }

    public function testGetProssimeFiltersAndSortsByDaysRemaining(): void
    {
        $in3  = (new \DateTime('today'))->modify('+3 days')->format('Y-m-d');
        $in10 = (new \DateTime('today'))->modify('+10 days')->format('Y-m-d');
        $in60 = (new \DateTime('today'))->modify('+60 days')->format('Y-m-d');

        $this->ricRepo->method('allForUserWithContatto')->willReturn([
            $this->recurrence(['id' => 1, 'data_ricorrenza' => $in10]),
            $this->recurrence(['id' => 2, 'data_ricorrenza' => $in3]),
            $this->recurrence(['id' => 3, 'data_ricorrenza' => $in60]), // oltre 30gg → escluso
        ]);

        $prossime = $this->service->getProssime(1, 30);

        $this->assertCount(2, $prossime);
        // Ordinati per giorni_mancanti crescente: id 2 (3gg) prima di id 1 (10gg).
        $this->assertSame(2, (int) $prossime[0]['id']);
        $this->assertSame(1, (int) $prossime[1]['id']);
    }
}
