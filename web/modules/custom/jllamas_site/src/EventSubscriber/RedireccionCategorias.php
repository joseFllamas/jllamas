<?php

declare(strict_types=1);

namespace Drupal\jllamas_site\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\jllamas_site\Catalogo;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Las páginas de término llevan a la sección filtrada.
 *
 * /taxonomy/term/5 (Acuarela) > /cuadros?tecnica=acuarela. Así no hay páginas
 * duplicadas y los enlaces automáticos de Drupal acaban en el diseño bueno.
 */
final class RedireccionCategorias implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    private readonly Catalogo $catalogo,
  ) {}

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 25]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest() || $this->routeMatch->getRouteName() !== 'entity.taxonomy_term.canonical') {
      return;
    }
    $term = $this->routeMatch->getParameter('taxonomy_term');
    if (!$term instanceof TermInterface) {
      return;
    }
    $url = NULL;
    if ($term->bundle() === 'categorias') {
      foreach ($this->catalogo->secciones() as $seccion) {
        if ($seccion->id() === $term->id()) {
          $url = $this->catalogo->urlSeccion($seccion);
        }
        elseif (isset($this->catalogo->categorias($seccion)[$this->catalogo->clave($term)])) {
          $url = $this->catalogo->urlSeccion($seccion, [$this->catalogo->claveFiltro($seccion) => $this->catalogo->clave($term)]);
        }
      }
    }
    elseif ($term->bundle() === 'tematicas' && ($seccion = $this->catalogo->seccion('cuadros'))) {
      $url = $this->catalogo->urlSeccion($seccion, ['tematica' => $this->catalogo->slug($term->getName())]);
    }
    if ($url) {
      $event->setResponse(new TrustedRedirectResponse($url->setAbsolute()->toString(), 301));
    }
  }

}
