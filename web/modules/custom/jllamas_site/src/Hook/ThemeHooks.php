<?php

declare(strict_types=1);

namespace Drupal\jllamas_site\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\jllamas_site\Catalogo;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Plantillas de jllamas y datos para la ficha de obra, Sobre mí y Encargos.
 */
final class ThemeHooks {

  public function __construct(
    private readonly Catalogo $catalogo,
    #[Autowire(service: 'router.builder')]
    private readonly RouteBuilderInterface $routeBuilder,
  ) {}

  #[Hook('theme')]
  public function theme(): array {
    return [
      'jllamas_inicio' => [
        'variables' => ['hero' => [], 'destacadas' => [], 'secciones' => [], 'retratos' => [], 'autor' => NULL, 'proceso' => []],
      ],
      'jllamas_seccion' => [
        'variables' => ['titulo' => '', 'intro' => '', 'total' => 0, 'grupos' => [], 'activos' => [], 'obras' => [], 'url' => ''],
      ],
      'jllamas_retratos' => [
        'variables' => ['categoria' => '', 'total' => 0, 'obras' => [], 'url_encargo' => '', 'proceso' => []],
      ],
      'jllamas_tarjeta' => [
        'variables' => ['obra' => []],
      ],
      'jllamas_proceso' => [
        'variables' => ['titulo' => 'Cómo funciona un encargo'],
      ],
    ];
  }

  /**
   * Datos de la ficha de obra y de las páginas Sobre mí y Encargos.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'];
    if (!$node instanceof NodeInterface || $variables['view_mode'] !== 'full') {
      return;
    }
    $cache = CacheableMetadata::createFromRenderArray($variables);
    $cache->addCacheTags(['node_list:obra', 'config:jllamas_site.settings']);

    if ($node->bundle() === 'obra') {
      $variables['jl'] = $this->datosObra($node);
    }
    elseif ((int) $node->id() === (int) $this->catalogo->config('sobre_mi')) {
      $autor = $this->catalogo->obra($this->catalogo->config('autorretrato'));
      $variables['jl'] = [
        'autor' => $autor ? $this->catalogo->tarjeta($autor, 'obra_ficha', FALSE) : NULL,
        'entradilla' => $node->get('body')->summary,
      ];
    }
    elseif ((int) $node->id() === (int) $this->catalogo->config('encargos')) {
      $servicios = [];
      foreach ((array) $this->catalogo->config('servicios') as $servicio) {
        $obra = $this->catalogo->obra($servicio['obra'] ?? NULL);
        $servicios[] = [
          'titulo' => $servicio['titulo'],
          'texto' => $servicio['texto'],
          'obra' => $obra ? $this->catalogo->tarjeta($obra, 'obra_retrato') : NULL,
          'url' => $this->catalogo->urlEncargo($servicio['tipo']),
        ];
      }
      $variables['jl'] = [
        'entradilla' => $node->get('body')->summary,
        'servicios' => $servicios,
        'proceso' => ['#theme' => 'jllamas_proceso'],
      ];
    }
    $cache->applyTo($variables);
  }

  /**
   * Sugerencias de plantilla para las páginas especiales.
   */
  #[Hook('theme_suggestions_node_alter')]
  public function suggestionsNode(array &$suggestions, array $variables): void {
    $node = $variables['elements']['#node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'page') {
      return;
    }
    if ((int) $node->id() === (int) $this->catalogo->config('sobre_mi')) {
      $suggestions[] = 'node__page__sobre_mi';
    }
    if ((int) $node->id() === (int) $this->catalogo->config('encargos')) {
      $suggestions[] = 'node__page__encargos';
    }
  }

  /**
   * Si cambia una sección (nombre o clave), hay que regenerar sus rutas.
   */
  #[Hook('taxonomy_term_update')]
  #[Hook('taxonomy_term_insert')]
  #[Hook('taxonomy_term_delete')]
  public function termChanged(TermInterface $term): void {
    if ($term->bundle() === 'categorias') {
      $this->routeBuilder->setRebuildNeeded();
    }
  }

  private function datosObra(NodeInterface $obra): array {
    $seccion = $this->catalogo->seccionDe($obra);
    $categoria = $this->catalogo->categoriaDe($obra);
    $datos = [
      'seccion' => NULL,
      'categoria' => NULL,
      'datos' => [],
      'descripcion' => $obra->get('body')->isEmpty() ? '' : ['#type' => 'processed_text', '#text' => $obra->get('body')->value, '#format' => $obra->get('body')->format],
      'imagen' => $this->catalogo->imagen($obra, 'obra_ficha', ['loading' => 'eager', 'fetchpriority' => 'high']),
      'imagen_grande' => $this->catalogo->urlImagenGrande($obra),
      'proporcion' => 1,
      'url_encargo' => $this->catalogo->urlEncargo($this->catalogo->tipoEncargo($obra), sprintf('Encargo similar a «%s». ', $obra->getTitle())),
      'anterior' => NULL,
      'siguiente' => NULL,
      'relacionadas' => [],
    ];
    $item = $obra->get('field_imagen')->first();
    if ($item && $item->height) {
      $datos['proporcion'] = round($item->width / $item->height, 4);
    }
    if (!$seccion) {
      return $datos;
    }
    $clave_filtro = $this->catalogo->claveFiltro($seccion);
    $es_cuadros = $this->catalogo->clave($seccion) === 'cuadros';
    $datos['seccion'] = [
      'titulo' => $seccion->getName(),
      'singular' => $seccion->get('field_singular')->value ?: $seccion->getName(),
      'url' => $this->catalogo->urlSeccion($seccion)->toString(),
    ];
    if ($categoria) {
      $datos['categoria'] = [
        'titulo' => $categoria->getName(),
        'url' => $this->catalogo->urlSeccion($seccion, [$clave_filtro => $this->catalogo->clave($categoria)])->toString(),
      ];
      $datos['datos'][] = ['etiqueta' => $es_cuadros ? 'Técnica' : 'Categoría', 'valor' => $categoria->getName()];
    }
    $tematicas = array_map(static fn(TermInterface $t) => $t->getName(), $obra->get('field_tematica')->referencedEntities());
    if ($tematicas) {
      $datos['datos'][] = ['etiqueta' => 'Temática', 'valor' => implode(', ', $tematicas)];
    }
    if (!$obra->get('field_medidas')->isEmpty()) {
      $datos['datos'][] = ['etiqueta' => 'Medidas', 'valor' => $obra->get('field_medidas')->value];
    }

    // Anterior y siguiente dentro de la sección, en bucle.
    $lista = $this->catalogo->obras($seccion);
    $ids = array_map(static fn(NodeInterface $o) => (int) $o->id(), $lista);
    $i = array_search((int) $obra->id(), $ids, TRUE);
    if ($i !== FALSE && count($lista) > 1) {
      $prev = $lista[($i - 1 + count($lista)) % count($lista)];
      $next = $lista[($i + 1) % count($lista)];
      $datos['anterior'] = ['titulo' => $prev->getTitle(), 'url' => $prev->toUrl()->toString()];
      $datos['siguiente'] = ['titulo' => $next->getTitle(), 'url' => $next->toUrl()->toString()];
    }

    // Hasta 6 obras más de la misma categoría.
    if ($categoria) {
      $relacionadas = array_filter($this->catalogo->obrasDeCategoria($categoria), static fn(NodeInterface $o) => $o->id() !== $obra->id());
      $datos['relacionadas'] = array_map(fn(NodeInterface $o) => $this->catalogo->tarjeta($o), array_slice(array_values($relacionadas), 0, 6));
      $datos['titulo_relacionadas'] = 'Más ' . mb_strtolower($categoria->getName());
    }
    return $datos;
  }

}
