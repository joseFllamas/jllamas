<?php

declare(strict_types=1);

namespace Drupal\jllamas_site\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\jllamas_site\Catalogo;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inicio, páginas de sección con filtros y Retratos por encargo.
 */
final class PaginasController extends ControllerBase {

  public function __construct(private readonly Catalogo $catalogo) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get(Catalogo::class));
  }

  /**
   * Inicio: hero, destacadas, secciones, retratos, artista y proceso.
   */
  public function inicio(): array {
    $destacadas = $this->catalogo->destacadas();
    $secciones = [];
    $n = 0;
    foreach ($this->catalogo->secciones() as $clave => $seccion) {
      $obras = $this->catalogo->obras($seccion);
      $portada = $this->catalogo->obrasPortada($seccion)[0] ?? ($obras[0] ?? NULL);
      $secciones[] = [
        'numero' => sprintf('%02d', ++$n),
        'clave' => $clave,
        'titulo' => $seccion->getName(),
        'intro' => $seccion->get('field_intro')->value,
        'url' => $this->catalogo->urlSeccion($seccion)->toString(),
        'total' => count($obras),
        'portada' => $portada ? $this->catalogo->tarjeta($portada, 'obra_mosaico') : NULL,
        'grupos' => $this->gruposConEnlaces($seccion, $obras),
      ];
    }

    $retratos = [];
    $categoria_retratos = $this->catalogo->categoria((string) $this->catalogo->config('retratos_categoria'));
    if ($categoria_retratos) {
      foreach ($this->catalogo->obrasPortada($categoria_retratos) as $obra) {
        $retratos[] = $this->catalogo->tarjeta($obra, 'obra_retrato');
      }
    }
    $autor = $this->catalogo->obra($this->catalogo->config('autorretrato'));

    $build = [
      '#theme' => 'jllamas_inicio',
      '#hero' => array_map(fn(NodeInterface $o, int $k) => $this->catalogo->tarjeta($o, 'obra_hero', $k > 0, [
        'class' => ['absolute', 'inset-0', 'h-full', 'w-full', 'object-contain', 'object-center', 'transition-opacity', 'duration-[2000ms]', 'lg:object-right-bottom', $k === 0 ? 'opacity-100' : 'opacity-0'],
        'data-hero-img' => '',
      ]), $destacadas, array_keys($destacadas)),
      '#destacadas' => array_map(fn(NodeInterface $o) => $this->catalogo->tarjeta($o, 'obra_fila'), $destacadas),
      '#secciones' => $secciones,
      '#retratos' => $retratos,
      '#autor' => $autor ? $this->catalogo->tarjeta($autor, 'obra_retrato') : NULL,
      '#proceso' => ['#theme' => 'jllamas_proceso'],
    ];
    $this->cacheCatalogo($build);
    return $build;
  }

  /**
   * Página de una sección con sus filtros (?tecnica=, ?tipo=, ?tematica=).
   */
  public function seccion(string $clave, Request $request): array {
    $seccion = $this->catalogo->seccion($clave);
    if (!$seccion) {
      throw new NotFoundHttpException();
    }
    $obras = $this->catalogo->obras($seccion);
    $grupos = $this->catalogo->grupos($seccion, $obras);
    $activos = [];
    foreach ($grupos as $clave_grupo => $grupo) {
      $valor = (string) $request->query->get($clave_grupo, '');
      if ($valor !== '' && in_array($valor, array_column($grupo['opciones'], 'valor'), TRUE)) {
        $activos[$clave_grupo] = $valor;
      }
    }
    $grupos = $this->catalogo->grupos($seccion, $obras, $activos);
    $visibles = array_map(static fn(NodeInterface $o) => $o->id(), $this->catalogo->filtrar($obras, $grupos, $activos));

    $tarjetas = [];
    foreach ($obras as $obra) {
      $tarjeta = $this->catalogo->tarjeta($obra);
      $tarjeta['oculta'] = !in_array($obra->id(), $visibles, TRUE);
      $tarjetas[] = $tarjeta;
    }

    $url = $this->catalogo->urlSeccion($seccion)->toString();
    foreach ($grupos as $clave_grupo => &$grupo) {
      $grupo['url_todas'] = $this->urlCon($url, array_diff_key($activos, [$clave_grupo => 1]));
      foreach ($grupo['opciones'] as &$opcion) {
        $query = $opcion['activa'] ? array_diff_key($activos, [$clave_grupo => 1]) : [$clave_grupo => $opcion['valor']] + $activos;
        $opcion['url'] = $this->urlCon($url, $query);
      }
      unset($opcion);
    }
    unset($grupo);

    $build = [
      '#theme' => 'jllamas_seccion',
      '#titulo' => $seccion->getName(),
      '#intro' => $seccion->get('field_intro')->value,
      '#total' => count($visibles),
      '#grupos' => $grupos,
      '#activos' => $activos,
      '#obras' => $tarjetas,
      '#url' => $url,
      '#attached' => [
        'html_head' => [[
          ['#tag' => 'meta', '#attributes' => ['name' => 'description', 'content' => $this->descripcionSeccion($seccion->getName(), (string) $seccion->get('field_intro')->value)]],
          'description',
        ]],
      ],
    ];
    $this->cacheCatalogo($build);
    $build['#cache']['contexts'][] = 'url.query_args';
    return $build;
  }

  /**
   * Retratos por encargo: las obras de la categoría Retrato pop.
   */
  public function retratos(): array {
    $categoria = $this->catalogo->categoria((string) $this->catalogo->config('retratos_categoria'));
    if (!$categoria) {
      throw new NotFoundHttpException();
    }
    $obras = $this->catalogo->obrasDeCategoria($categoria);
    $build = [
      '#theme' => 'jllamas_retratos',
      '#categoria' => $categoria->getName(),
      '#total' => count($obras),
      '#obras' => array_map(fn(NodeInterface $o) => $this->catalogo->tarjeta($o), $obras),
      '#url_encargo' => $this->catalogo->urlEncargo('Retrato', 'Retrato pop por encargo. '),
      '#proceso' => ['#theme' => 'jllamas_proceso', '#titulo' => 'Cómo encargar tu retrato'],
      '#attached' => [
        'html_head' => [[
          ['#tag' => 'meta', '#attributes' => ['name' => 'description', 'content' => 'Retratos pop personalizados de personas y mascotas, en colores planos, pintados por encargo por José Gabriel Llamas para clientes de cualquier lugar.']],
          'description',
        ]],
      ],
    ];
    $this->cacheCatalogo($build);
    return $build;
  }

  /**
   * Grupos de filtros con enlaces a la sección filtrada (para Inicio).
   */
  private function gruposConEnlaces($seccion, array $obras): array {
    $grupos = $this->catalogo->grupos($seccion, $obras);
    $url = $this->catalogo->urlSeccion($seccion)->toString();
    foreach ($grupos as $clave => &$grupo) {
      foreach ($grupo['opciones'] as &$opcion) {
        $opcion['url'] = $this->urlCon($url, [$clave => $opcion['valor']]);
      }
      unset($opcion);
    }
    unset($grupo);
    return array_values($grupos);
  }

  private function urlCon(string $url, array $query): string {
    return $query ? $url . '?' . http_build_query($query) : $url;
  }

  private function descripcionSeccion(string $titulo, string $intro): string {
    return sprintf('%s de José Gabriel Llamas: %s Por encargo, para clientes de cualquier lugar.', $titulo, $intro);
  }

  /**
   * La página depende de todas las obras y términos: se invalida al editarlos.
   */
  private function cacheCatalogo(array &$build): void {
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheTags(['node_list:obra', 'taxonomy_term_list:categorias', 'taxonomy_term_list:tematicas', 'config:jllamas_site.settings'])
      ->applyTo($build);
  }

}
