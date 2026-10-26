<?php

declare(strict_types=1);

namespace Drupal\jllamas_admin\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\jllamas_site\Catalogo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Últimas obras modificadas, con miniatura y enlace para editar.
 */
#[Block(
  id: 'jllamas_ultimas_obras',
  admin_label: new TranslatableMarkup('Últimas obras de jllamas'),
  category: new TranslatableMarkup('jllamas')
)]
final class UltimasObras extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Catalogo $catalogo,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get(Catalogo::class));
  }

  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'obra')->sort('changed', 'DESC')->range(0, 8)->execute();
    $style = ImageStyle::load('obra_miniatura');
    $rows = [];
    foreach ($storage->loadMultiple($nids) as $obra) {
      $item = $obra->get('field_imagen')->first();
      $miniatura = ($item && $item->entity && $style) ? [
        '#theme' => 'image',
        '#uri' => $style->buildUrl($item->entity->getFileUri()),
        '#alt' => '',
        '#width' => 60,
        '#height' => 60,
      ] : [];
      $categoria = $this->catalogo->categoriaDe($obra);
      $seccion = $this->catalogo->seccionDe($obra);
      $rows[] = [
        ['data' => $miniatura],
        ['data' => ['#type' => 'link', '#title' => $obra->getTitle(), '#url' => $obra->toUrl('edit-form')]],
        trim(($seccion && $seccion !== $categoria ? $seccion->getName() . ' / ' : '') . ($categoria ? $categoria->getName() : '')),
        $obra->get('field_destacada')->value ? 'Destacada' : '',
        $obra->isPublished() ? 'Publicada' : 'Sin publicar',
      ];
    }
    return [
      'tabla' => [
        '#type' => 'table',
        '#header' => ['', 'Obra', 'Categoría', '', 'Estado'],
        '#rows' => $rows,
        '#empty' => 'Todavía no hay obras.',
      ],
      'todas' => [
        '#type' => 'link',
        '#title' => 'Ver todas las obras',
        '#url' => Url::fromRoute('view.obras_admin.page_1'),
        '#attributes' => ['class' => ['button', 'button--small']],
      ],
      '#cache' => ['tags' => ['node_list:obra'], 'contexts' => ['user.permissions']],
    ];
  }

}
