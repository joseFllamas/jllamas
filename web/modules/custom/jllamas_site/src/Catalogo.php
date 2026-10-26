<?php

declare(strict_types=1);

namespace Drupal\jllamas_site;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consultas del catálogo de obras y datos para las plantillas.
 *
 * Equivale a src/lib/catalog.ts del prototipo de Lovable: secciones (términos
 * raíz de Categorías), categorías (sus hijos), temáticas, filtros y recuentos.
 */
final class Catalogo {

  /**
   * Caché por petición de las obras de cada sección.
   *
   * @var array<string, \Drupal\node\NodeInterface[]>
   */
  private array $obrasPorSeccion = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    #[Autowire(service: 'transliteration')]
    private readonly TransliterationInterface $transliteration,
  ) {}

  /**
   * Secciones (términos raíz de Categorías) por orden, con su clave de índice.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   */
  public function secciones(): array {
    $terms = $this->termStorage()->loadTree('categorias', 0, 1, TRUE);
    $out = [];
    foreach ($terms as $term) {
      if ($term->isPublished()) {
        $out[$this->clave($term)] = $term;
      }
    }
    return $out;
  }

  public function seccion(string $clave): ?TermInterface {
    return $this->secciones()[$clave] ?? NULL;
  }

  /**
   * Categorías (técnicas o tipos) de una sección, por orden.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   */
  public function categorias(TermInterface $seccion): array {
    $out = [];
    foreach ($this->termStorage()->loadTree('categorias', (int) $seccion->id(), 1, TRUE) as $term) {
      if ($term->isPublished()) {
        $out[$this->clave($term)] = $term;
      }
    }
    return $out;
  }

  /**
   * Busca una categoría por su clave en cualquier sección.
   */
  public function categoria(string $clave): ?TermInterface {
    foreach ($this->secciones() as $seccion) {
      if (isset($this->categorias($seccion)[$clave])) {
        return $this->categorias($seccion)[$clave];
      }
    }
    return NULL;
  }

  /**
   * Temáticas por orden.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   */
  public function tematicas(): array {
    $out = [];
    foreach ($this->termStorage()->loadTree('tematicas', 0, NULL, TRUE) as $term) {
      if ($term->isPublished()) {
        $out[$this->slug($term->getName())] = $term;
      }
    }
    return $out;
  }

  /**
   * Obras publicadas de una sección, de la más reciente a la más antigua.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function obras(TermInterface $seccion): array {
    $clave = $this->clave($seccion);
    if (!isset($this->obrasPorSeccion[$clave])) {
      $tids = array_map(static fn(TermInterface $t) => $t->id(), $this->categorias($seccion));
      $tids[] = $seccion->id();
      $this->obrasPorSeccion[$clave] = $this->cargarObras(['field_categoria' => array_values($tids)]);
    }
    return $this->obrasPorSeccion[$clave];
  }

  /**
   * Obras publicadas de una categoría.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function obrasDeCategoria(TermInterface $categoria): array {
    return $this->cargarObras(['field_categoria' => [$categoria->id()]]);
  }

  /**
   * Obras destacadas, de la más reciente a la más antigua.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function destacadas(): array {
    return $this->cargarObras(['field_destacada' => [1]]);
  }

  /**
   * Obras de portada de un término, solo las publicadas.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function obrasPortada(TermInterface $term): array {
    return array_values(array_filter(
      $term->get('field_obras_portada')->referencedEntities(),
      static fn($n) => $n instanceof NodeInterface && $n->isPublished(),
    ));
  }

  public function categoriaDe(NodeInterface $obra): ?TermInterface {
    $term = $obra->get('field_categoria')->entity;
    return $term instanceof TermInterface ? $term : NULL;
  }

  /**
   * Sección de una obra: el término raíz de su categoría.
   */
  public function seccionDe(NodeInterface $obra): ?TermInterface {
    $categoria = $this->categoriaDe($obra);
    if (!$categoria) {
      return NULL;
    }
    $parents = $this->termStorage()->loadAllParents($categoria->id());
    $root = end($parents);
    return $root instanceof TermInterface ? $root : $categoria;
  }

  /**
   * Clave de un término (campo field_clave, o el nombre convertido).
   */
  public function clave(TermInterface $term): string {
    if ($term->hasField('field_clave') && !$term->get('field_clave')->isEmpty()) {
      return (string) $term->get('field_clave')->value;
    }
    return $this->slug($term->getName());
  }

  /**
   * Nombre del parámetro de URL del filtro de categoría: tecnica o tipo.
   */
  public function claveFiltro(TermInterface $seccion): string {
    $etiqueta = (string) ($seccion->get('field_etiqueta_filtro')->value ?: 'Tipo');
    return $this->slug($etiqueta);
  }

  public function urlSeccion(TermInterface $seccion, array $query = []): Url {
    return Url::fromRoute('jllamas_site.seccion.' . $this->clave($seccion), [], ['query' => $query]);
  }

  /**
   * Grupos de filtros de una sección con sus opciones y recuentos.
   *
   * Los recuentos tienen en cuenta el resto de filtros activos, como en
   * countFor() de Lovable.
   *
   * @param \Drupal\taxonomy\TermInterface $seccion
   *   Sección.
   * @param \Drupal\node\NodeInterface[] $obras
   *   Obras de la sección.
   * @param array<string, string> $activos
   *   Filtros activos: clave del grupo => clave de la opción.
   */
  public function grupos(TermInterface $seccion, array $obras, array $activos = []): array {
    $grupos = [];
    $etiqueta = (string) ($seccion->get('field_etiqueta_filtro')->value ?: 'Tipo');
    $grupos[$this->claveFiltro($seccion)] = [
      'clave' => $this->claveFiltro($seccion),
      'etiqueta' => $etiqueta,
      'campo' => 'categoria',
      'opciones' => array_map(fn(TermInterface $t) => ['valor' => $this->clave($t), 'etiqueta' => $t->getName()], array_values($this->categorias($seccion))),
    ];
    // La temática solo aparece en las secciones que tienen obras con temática.
    $con_tematica = array_filter($obras, static fn(NodeInterface $o) => !$o->get('field_tematica')->isEmpty());
    if ($con_tematica) {
      $grupos['tematica'] = [
        'clave' => 'tematica',
        'etiqueta' => 'Temática',
        'campo' => 'tematica',
        'opciones' => array_map(fn(TermInterface $t) => ['valor' => $this->slug($t->getName()), 'etiqueta' => $t->getName()], array_values($this->tematicas())),
      ];
    }
    $definicion = $grupos;
    foreach ($grupos as $clave => $grupo) {
      foreach ($grupo['opciones'] as $i => $opcion) {
        $grupos[$clave]['opciones'][$i]['total'] = count($this->filtrar($obras, $definicion, [$clave => $opcion['valor']] + $activos));
        $grupos[$clave]['opciones'][$i]['activa'] = ($activos[$clave] ?? NULL) === $opcion['valor'];
      }
    }
    return $grupos;
  }

  /**
   * Filtra obras según los filtros activos.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  public function filtrar(array $obras, array $grupos, array $activos): array {
    return array_values(array_filter($obras, function (NodeInterface $obra) use ($grupos, $activos) {
      foreach ($activos as $clave => $valor) {
        if (!isset($grupos[$clave]) || $valor === '') {
          continue;
        }
        $valores = $grupos[$clave]['campo'] === 'categoria' ? [$this->claveCategoria($obra)] : $this->clavesTematica($obra);
        if (!in_array($valor, $valores, TRUE)) {
          return FALSE;
        }
      }
      return TRUE;
    }));
  }

  public function claveCategoria(NodeInterface $obra): string {
    $categoria = $this->categoriaDe($obra);
    return $categoria ? $this->clave($categoria) : '';
  }

  /**
   * @return string[]
   */
  public function clavesTematica(NodeInterface $obra): array {
    return array_map(fn(TermInterface $t) => $this->slug($t->getName()), $obra->get('field_tematica')->referencedEntities());
  }

  /**
   * Datos de una obra para la tarjeta del mosaico y otras plantillas.
   */
  public function tarjeta(NodeInterface $obra, string $estilo = 'obra_mosaico', bool $diferida = TRUE, array $attributes = []): array {
    $categoria = $this->categoriaDe($obra);
    $imagen = $obra->get('field_imagen')->first();
    return [
      'id' => $obra->id(),
      'titulo' => $obra->getTitle(),
      'url' => $obra->toUrl()->toString(),
      'categoria' => $categoria ? $categoria->getName() : '',
      'clave_categoria' => $this->claveCategoria($obra),
      'tematicas' => implode(' ', $this->clavesTematica($obra)),
      'ancho' => $imagen ? (int) $imagen->width : 0,
      'alto' => $imagen ? (int) $imagen->height : 0,
      'imagen' => $this->imagen($obra, $estilo, $attributes + ['loading' => $diferida ? 'lazy' : 'eager']),
    ];
  }

  /**
   * Imagen responsive de una obra (srcset en WebP, con ancho y alto).
   */
  public function imagen(NodeInterface $obra, string $estilo, array $attributes = []): array {
    $item = $obra->get('field_imagen')->first();
    if (!$item || !$item->entity) {
      return [];
    }
    return [
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => $estilo,
      '#uri' => $item->entity->getFileUri(),
      '#width' => $item->width,
      '#height' => $item->height,
      '#attributes' => $attributes + ['alt' => $item->alt, 'decoding' => 'async'],
      '#cache' => ['tags' => $obra->getCacheTags()],
    ];
  }

  /**
   * URL de la imagen grande para el visor (2400 px de ancho como máximo).
   */
  public function urlImagenGrande(NodeInterface $obra): string {
    $item = $obra->get('field_imagen')->first();
    if (!$item || !$item->entity) {
      return '';
    }
    $style = ImageStyle::load('obra_2400');
    $uri = $item->entity->getFileUri();
    return $style ? $this->fileUrlGenerator->transformRelative($style->buildUrl($uri)) : $this->fileUrlGenerator->generateString($uri);
  }

  /**
   * Tipo de encargo para el formulario de contacto, como tipoContacto() de Lovable.
   */
  public function tipoEncargo(NodeInterface $obra): string {
    $seccion = $this->seccionDe($obra);
    $clave = $seccion ? $this->clave($seccion) : '';
    if ($clave === 'ilustraciones') {
      return 'Ilustración';
    }
    if ($clave === 'decoracion') {
      $texto = mb_strtolower((string) $obra->get('body')->value);
      return str_contains($texto, 'restaur') ? 'Restauración' : 'Mural o decoración';
    }
    return $this->claveCategoria($obra) === $this->config('retratos_categoria') ? 'Retrato' : 'Cuadro';
  }

  /**
   * Enlace al formulario de contacto con el tipo y el mensaje ya rellenos.
   */
  public function urlEncargo(string $tipo, string $mensaje = ''): string {
    $query = ['tipo' => $tipo];
    if ($mensaje !== '') {
      $query['mensaje'] = $mensaje;
    }
    return Url::fromUserInput('/contacto', ['query' => $query])->toString();
  }

  public function config(string $key): mixed {
    return $this->configFactory->get('jllamas_site.settings')->get($key);
  }

  public function obra(int|string|null $nid): ?NodeInterface {
    if (!$nid) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    return $node instanceof NodeInterface && $node->isPublished() ? $node : NULL;
  }

  /**
   * Convierte un texto en clave de URL: "Óleo" > "oleo".
   */
  public function slug(string $text): string {
    $text = mb_strtolower($this->transliteration->transliterate($text, 'es'));
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
  }

  /**
   * Carga obras publicadas con condiciones simples (campo => valores).
   *
   * @return \Drupal\node\NodeInterface[]
   */
  private function cargarObras(array $condiciones): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'obra')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC');
    foreach ($condiciones as $campo => $valores) {
      $query->condition($campo, $valores, 'IN');
    }
    $nids = $query->execute();
    return $nids ? array_values($this->entityTypeManager->getStorage('node')->loadMultiple($nids)) : [];
  }

  private function termStorage() {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

}
