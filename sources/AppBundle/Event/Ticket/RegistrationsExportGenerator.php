<?php

namespace AppBundle\Event\Ticket;

use Afup\Site\Association\Cotisations;
use AppBundle\Association\Model\Repository\UserRepository;
use AppBundle\Association\Model\User;
use AppBundle\Event\Model\Event;
use AppBundle\Event\Model\Repository\InvoiceRepository;
use AppBundle\Event\Model\Repository\TicketRepository;
use AppBundle\Event\Model\Ticket;
use AppBundle\Offices\OfficeFinder;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class RegistrationsExportGenerator
{
    /**
     * @var OfficeFinder
     */
    private $officeFinder;

    /**
     * @var Cotisations
     */
    private $cotisations;

    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * @var TicketRepository
     */
    private $ticketRepository;

    /**
     * @param OfficeFinder $officeFinder
     * @param Cotisations $cotisations
     * @param TicketRepository $ticketRepository
     * @param InvoiceRepository $invoiceRepository
     * @param UserRepository $userRepository
     */
    public function __construct(OfficeFinder $officeFinder, Cotisations $cotisations, TicketRepository $ticketRepository, InvoiceRepository $invoiceRepository, UserRepository $userRepository)
    {
        $this->officeFinder = $officeFinder;
        $this->cotisations = $cotisations;
        $this->ticketRepository = $ticketRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param Event $event
     * @param \SplFileObject $toFile
     */
    public function export(Event $event, \SplFileObject $toFile)
    {
        $columns = [
            'id',
            'reference',
            'prenom',
            'nom',
            'societe',
            'tags',
            'type_pass',
            'email',
            'member_since',
            'office'
        ];

        $toFile->fputcsv($columns);

        foreach ($this->getFromRegistrationsOnEvent($event) as $row) {
            $preparedRow = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \RuntimeException(sprintf('Colonne "%s" non trouvée : %s', $column, var_export($row, true)));
                }
                $preparedRow[] = $row[$column];
            }
            $toFile->fputcsv($preparedRow);
        }
    }

    /**
     * @param Event $event
     *
     * @return \Generator
     */
    protected function getFromRegistrationsOnEvent(Event $event)
    {
        $tickets = $this->ticketRepository->getByEvent($event);

        foreach ($tickets as $ticket) {
            if ($ticket->getStatus() == Ticket::STATUS_CANCELLED) {
                // On n'exporte pas les billets inscriptions annulées
                continue;
            }

            $invoice = $this->invoiceRepository->getByReference($ticket->getReference());

            try {
                $user = $this->userRepository->loadUserByUsername($ticket->getEmail());
            } catch (UsernameNotFoundException $exception) {
                $user = null;
            }

            if (null === ($office = $ticket->getNearestOffice())) {
                $office = $this->officeFinder->findOffice($invoice, $user);
            }

            yield [
                'id' => $ticket->getId(),
                'reference' => $invoice->getReference(),
                'prenom' => $ticket->getFirstname(),
                'nom' => $ticket->getLastname(),
                'societe' => $invoice->getCompany(),
                'tags' => $this->extractAndCleanTags($ticket->getComments()),
                'type_pass' => $this->getTypePass($ticket->getTicketTypeId()),
                'email' => $ticket->getEmail(),
                'member_since' => null !== $user ? $this->comptureSeniority($user) : null,
                'office' => $office,
                'distance' => null,
                'error' => null,
                'city' => null !== $user ? $user->getCity() : $invoice->getCity(),
                'zip_code' => null !== $user ? $user->getZipCode() : $invoice->getZipCode(),
                'country' => null !== $user ? $user->getCountry() : $invoice->getCountryId(),
            ];
        }
    }

    /**
     * @param string $comments
     *
     * @return string
     */
    private function extractAndCleanTags($comments)
    {
        preg_match('@\<tag\>(.*)\</tags?\>@i', $comments, $matches);
        $tags =  isset($matches[1]) ? $matches[1] : '';
        $tags = explode(';', $tags);

        return implode(' - ', array_filter($tags));
    }

    /**
     * @param User $user
     *
     * @return int
     */
    private function comptureSeniority(User $user)
    {
        $cotis = $this->cotisations->obtenirListe(AFUP_PERSONNES_PHYSIQUES, $user->getId());
        $now = new \DateTime();
        $diffs = [];

        foreach ($cotis as $coti) {
            $from = \DateTimeImmutable::createFromFormat('U', $coti['date_debut']);
            $to = \DateTimeImmutable::createFromFormat('U', $coti['date_fin']);
            $to = min($now, $to);
            $diffs[] = $from->diff($to);
        }

        $reference = new \DateTimeImmutable();
        $lastest = clone $reference;
        foreach ($diffs as $dif) {
            $lastest = $lastest->add($dif);
        }

        $totalDiffs = $reference->diff($lastest);

        return $totalDiffs->y;
    }

    /**
     * @param $type
     * @return mixed|null|string
     */
    private function getTypePass($type)
    {
        switch ($type) {
            case AFUP_FORUM_PREMIERE_JOURNEE:
            case AFUP_FORUM_LATE_BIRD_PREMIERE_JOURNEE:
                return 'PASS JOUR 1';
                break;
            case AFUP_FORUM_DEUXIEME_JOURNEE:
            case AFUP_FORUM_LATE_BIRD_DEUXIEME_JOURNEE:
                return 'PASS JOUR 2';
                break;
            case AFUP_FORUM_2_JOURNEES:
            case AFUP_FORUM_2_JOURNEES_AFUP:
            case AFUP_FORUM_2_JOURNEES_ETUDIANT:
            case AFUP_FORUM_2_JOURNEES_PREVENTE:
            case AFUP_FORUM_2_JOURNEES_AFUP_PREVENTE:
            case AFUP_FORUM_2_JOURNEES_ETUDIANT_PREVENTE:
            case AFUP_FORUM_2_JOURNEES_COUPON:
            case AFUP_FORUM_INVITATION:
            case AFUP_FORUM_EARLY_BIRD:
            case AFUP_FORUM_EARLY_BIRD_AFUP:
            case AFUP_FORUM_LATE_BIRD:
            case AFUP_FORUM_LATE_BIRD_AFUP:
            case AFUP_FORUM_CFP_SUBMITTER:
            case AFUP_FORUM_SPECIAL_PRICE:
                return 'PASS 2 JOURS';
                break;
            case AFUP_FORUM_ORGANISATION:
                return 'ORGANISATION';
                break;
            case AFUP_FORUM_PRESSE:
                return 'PRESSE';
                break;
            case AFUP_FORUM_CONFERENCIER:
                return 'CONFERENCIER';
                break;
            case AFUP_FORUM_SPONSOR:
                return 'SPONSOR';
                break;
            default:
                throw new \RuntimeException(sprintf('Libellé du type %s non trouvé', var_export($type, true)));
                break;
        }
    }
}
