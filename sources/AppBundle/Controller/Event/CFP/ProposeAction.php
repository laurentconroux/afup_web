<?php

namespace AppBundle\Controller\Event\CFP;

use AppBundle\CFP\SpeakerFactory;
use AppBundle\Controller\Event\EventActionHelper;
use AppBundle\Event\Form\TalkType;
use AppBundle\Event\Model\Talk;
use AppBundle\Event\Talk\TalkFormHandler;
use DateTime;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Twig_Environment;

class ProposeAction
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;
    /** @var Twig_Environment */
    private $twig;
    /** @var SpeakerFactory */
    private $speakerFactory;
    /** @var FormFactoryInterface */
    private $formFactory;
    /** @var TranslatorInterface */
    private $translator;
    /** @var TalkFormHandler */
    private $talkFormHandler;
    /** @var SidebarRenderer */
    private $sidebarRenderer;
    /** @var EventActionHelper */
    private $eventActionHelper;
    /** @var FlashBagInterface */
    private $flashBag;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        Twig_Environment $twig,
        FormFactoryInterface $formFactory,
        TalkFormHandler $talkFormHandler,
        SpeakerFactory $speakerFactory,
        FlashBagInterface $flashBag,
        TranslatorInterface $translator,
        SidebarRenderer $sidebarRenderer,
        EventActionHelper $eventActionHelper
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->twig = $twig;
        $this->speakerFactory = $speakerFactory;
        $this->formFactory = $formFactory;
        $this->translator = $translator;
        $this->talkFormHandler = $talkFormHandler;
        $this->sidebarRenderer = $sidebarRenderer;
        $this->eventActionHelper = $eventActionHelper;
        $this->flashBag = $flashBag;
    }

    public function __invoke(Request $request)
    {
        $event = $this->eventActionHelper->getEvent($request->attributes->get('eventSlug'));
        if ($event->getDateEndCallForPapers() < new DateTime()) {
            return new Response($this->twig->render('event/cfp/closed.html.twig', ['event' => $event]));
        }
        $speaker = $this->speakerFactory->getSpeaker($event);
        if ($speaker->getId() === null) {
            $this->flashBag->add('error', $this->translator->trans('Vous devez remplir votre profil conférencier afin de pouvoir soumettre un sujet.'));

            return new RedirectResponse($this->urlGenerator->generate('cfp_speaker', ['eventSlug' => $event->getPath()]));
        }

        $talk = new Talk();
        $talk->setForumId($event->getId());
        $form = $this->formFactory->create(TalkType::class, $talk);
        if ($this->talkFormHandler->handle($request, $event, $form, $speaker)) {
            $this->flashBag->add('success', $this->translator->trans('Proposition enregistrée !'));

            return new RedirectResponse($this->urlGenerator->generate('cfp_edit', [
                'eventSlug' => $event->getPath(),
                'talkId' => $talk->getId(),
            ]));
        }

        return new Response($this->twig->render('event/cfp/propose.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
            'talk' => $talk,
            'sidebar' => $this->sidebarRenderer->render($event),
        ]));
    }
}
