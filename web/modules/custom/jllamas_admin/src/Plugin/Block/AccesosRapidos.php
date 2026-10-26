<?php

declare(strict_types=1);

namespace Drupal\jllamas_admin\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Botones grandes del panel: añadir obra, gestionar obras y categorías.
 */
#[Block(
  id: 'jllamas_accesos_rapidos',
  admin_label: new TranslatableMarkup('Accesos rápidos de jllamas'),
  category: new TranslatableMarkup('jllamas')
)]
final class AccesosRapidos extends BlockBase {

  public function build(): array {
    $accesos = [
      ['Añadir obra', 'Sube una obra nueva: foto, título y categoría.', Url::fromRoute('node.add', ['node_type' => 'obra']), TRUE],
      ['Gestionar obras', 'Busca, edita, publica o destaca las obras.', Url::fromRoute('view.obras_admin.page_1'), FALSE],
      ['Editar categorías', 'Secciones, técnicas y tipos, y su orden.', Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'categorias']), FALSE],
      ['Temáticas', 'Paisaje, Retrato, Figura...', Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'tematicas']), FALSE],
      ['Mensajes de contacto', 'Encargos recibidos desde el formulario.', Url::fromRoute('entity.webform.results_submissions', ['webform' => 'contacto']), FALSE],
      ['Ver la web', 'Abre la portada tal como la ven los visitantes.', Url::fromRoute('<front>'), FALSE],
    ];
    $items = [];
    foreach ($accesos as [$titulo, $texto, $url, $principal]) {
      if (!$url->access()) {
        continue;
      }
      $items[] = [
        '#type' => 'link',
        '#title' => [
          '#markup' => '<strong class="jl-acceso__titulo">' . $titulo . '</strong><span class="jl-acceso__texto">' . $texto . '</span>',
        ],
        '#url' => $url,
        '#attributes' => ['class' => ['jl-acceso', $principal ? 'jl-acceso--principal' : '']],
      ];
    }
    // La lista va dentro de un elemento propio: las clases de la raíz del
    // bloque acaban en su envoltorio, no en la lista.
    return [
      'lista' => [
        '#theme' => 'item_list',
        '#items' => $items,
        '#attributes' => ['class' => ['jl-accesos']],
      ],
      '#attached' => ['library' => ['jllamas_admin/panel']],
      '#cache' => ['contexts' => ['user.permissions']],
    ];
  }

}
