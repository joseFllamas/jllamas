<?php

declare(strict_types=1);

namespace Drupal\jllamas_site\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jllamas_site\Catalogo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Crea la ruta de cada sección a partir de su clave: /cuadros, /ilustraciones...
 */
final class RutasSecciones implements ContainerInjectionInterface {

  public function __construct(private readonly Catalogo $catalogo) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get(Catalogo::class));
  }

  public function routes(): RouteCollection {
    $routes = new RouteCollection();
    foreach ($this->catalogo->secciones() as $clave => $seccion) {
      $routes->add('jllamas_site.seccion.' . $clave, new Route(
        '/' . $clave,
        [
          '_controller' => '\Drupal\jllamas_site\Controller\PaginasController::seccion',
          '_title' => $seccion->getName(),
          'clave' => $clave,
        ],
        ['_permission' => 'access content'],
      ));
    }
    return $routes;
  }

}
