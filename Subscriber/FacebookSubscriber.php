<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * {@inheritdoc}
 */
class FacebookSubscriber implements EventSubscriberInterface {
	/*
	 * Inject router interface and locales
	 *
	 * @param RouterInterface $router The router instance
	 * @param array $locales The supported locales
	 */
	public function __construct(protected RouterInterface $router, protected array $locales) {
	}

	/**
	 * Change locale for request with ?fb_locale=xx
	 *
	 * @param RequestEvent The request event
	 */
	public function onKernelRequest(RequestEvent $event): void {
		//Without main request
		if (!$event->isMainRequest()) {
			return;
		}

		//Retrieve request
		$request = $event->getRequest();

		//Check for facebook locale
		if (
			$request->query->has('fb_locale') &&
			in_array($locale = $request->query->get('fb_locale'), $this->locales)
		) {
			//Set locale
			$request->setLocale($locale);

			//Set default locale
			$request->setDefaultLocale($locale);

			//Get router context
			$context = $this->router->getContext();

			//Set context locale
			$context->setParameter('_locale', $locale);

			//Set back router context
			$this->router->setContext($context);
		}
	}

	/**
	 * Get subscribed events
	 *
	 * @return array The subscribed events
	 */
	public static function getSubscribedEvents(): array {
		return [
			// must be registered before the default locale listener
			KernelEvents::REQUEST => [['onKernelRequest', 10]]
		];
	}
}
