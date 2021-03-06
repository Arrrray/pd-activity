<?php

/**
 * This file is part of the pd-admin pd-activity package.
 *
 * @package     pd-activity
 * @license     LICENSE
 * @author      Ramazan APAYDIN <apaydin541@gmail.com>
 * @link        https://github.com/appaydin/pd-activity
 */

namespace Pd\ActivityBundle\Listener;

use Pd\ActivityBundle\Message\HttpLog;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RequestListener implements EventSubscriberInterface
{
    public function __construct(
        private ParameterBagInterface $bag,
        private TokenStorageInterface $tokenStorage,
        private MessageBusInterface $bus
    )
    {
    }

    /**
     * Listen Request.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if ($event->isMainRequest()) {
            if (!$this->bag->get('pd_activity.log_request') ||
                \in_array($request->getMethod(), $this->bag->get('pd_activity.request_exclude_methods'), true)) {
                return;
            }

            // Check Uri Match
            if ($this->bag->get('pd_activity.request_match_uri') &&
                !preg_match("{{$this->bag->get('pd_activity.request_match_uri')}}", $request->getRequestUri())) {
                return;
            }

            // Check Ajax Request
            if (!$this->bag->get('pd_activity.log_ajax_request') && $request->isXmlHttpRequest()) {
                return;
            }

            // Check Content Disposion
            if ($event->getResponse()->headers->has('Content-Disposition')){
                return;
            }

            // Send JOB
            $this->bus->dispatch(new HttpLog(
                $request->getMethod(),
                $request->getRequestUri(),
                $request->request->all(),
                $request->getClientIp(),
                $request->getLocale(),
                $this->tokenStorage->getToken()?->getUser()->getId()
            ));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [['onKernelResponse']],
        ];
    }
}
