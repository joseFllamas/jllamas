<?php

declare(strict_types=1);

namespace Drupal\jllamas_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Metadatos de páginas que Metatag no cubre (el formulario de contacto).
 */
final class PageHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $webform = $this->routeMatch->getRawParameter('webform');
    if ($this->routeMatch->getRouteName() !== 'entity.webform.canonical' || $webform !== 'contacto') {
      return;
    }
    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'description',
          'content' => 'Cuéntale a José Gabriel Llamas qué necesitas: cuadros, retratos, ilustración, murales o restauración. Presupuesto cerrado y sin compromiso.',
        ],
      ],
      'jllamas_contacto_description',
    ];
  }

}
