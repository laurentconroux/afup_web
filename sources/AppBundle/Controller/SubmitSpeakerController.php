<?php

namespace AppBundle\Controller;

use AppBundle\Event\Form\SubmitSpeakerType;
use AppBundle\Event\Model\Event;
use AppBundle\Event\Model\Repository\SpeakerSuggestionRepository;
use AppBundle\Event\Model\SpeakerSuggestion;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubmitSpeakerController extends EventBaseController
{
    /**
     * @param string $eventSlug
     *
     * @return Response
     */
    public function indexAction(Request $request, $eventSlug)
    {
        $event = $this->checkEventSlug($eventSlug);

        $form = $this->createForm(SubmitSpeakerType::class);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $speakerSuggestion = $this->createSpeakerSuggestion($event, $form->getData());

            $this
                ->get('ting')
                ->get(SpeakerSuggestionRepository::class)
                ->save($speakerSuggestion)
            ;

            $this->sendMail($event, $speakerSuggestion);

            return $this->render(
                'event/submit-speaker/submit_success.html.twig',
                [
                    'event' => $event,
                ]
            );
        }

        return $this->render(
            'event/submit-speaker/submit.html.twig',
            [
                'form' => $form->createView(),
                'event' => $event,
            ]
        );
    }

    /**
     * @param Event $event
     * @param array $data
     *
     * @return SpeakerSuggestion
     */
    private function createSpeakerSuggestion(Event $event, array $data)
    {
        return (new SpeakerSuggestion())
            ->setEventId($event->getId())
            ->setSuggesterEmail($data['suggester_email'])
            ->setSuggesterName($data['suggester_name'])
            ->setSpeakerName($data['speaker_name'])
            ->setComment($data['comment'])
            ->setCreatedAt(new \DateTime('now'))
        ;
    }

    /**
     * @param Event $event
     * @param SpeakerSuggestion $speakerSuggestion
     */
    private function sendMail(Event $event, SpeakerSuggestion $speakerSuggestion)
    {
        $subject = sprintf('%s - Nouvelle suggestion de speaker', $event->getTitle());

        $content = $this->renderView(
            'event/submit-speaker/mail.txt.twig',
            [
                'event' => $event,
                'speaker_suggestion' => $speakerSuggestion,
            ]
        );

        $this->get('app.mail')->sendSimpleMessageViaSmtp(
            $subject,
            $content,
            'conferences@afup.org'
        );
    }
}
