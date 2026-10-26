<?php

declare(strict_types=1);

namespace Drupal\jllamas_admin\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\jllamas_site\Catalogo;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Formularios más sencillos para el pintor.
 */
final class FormHooks {

  public function __construct(private readonly Catalogo $catalogo) {}

  /**
   * Formulario de obra: categorías agrupadas por sección y temáticas solo en cuadros.
   */
  #[Hook('form_node_form_alter')]
  public function nodeForm(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->getFormObject()->getEntity();
    if (!$node instanceof NodeInterface || $node->bundle() !== 'obra') {
      return;
    }

    $grupos = [];
    $tids_cuadros = [];
    foreach ($this->catalogo->secciones() as $clave => $seccion) {
      foreach ($this->catalogo->categorias($seccion) as $categoria) {
        $grupos[$seccion->getName()][$categoria->id()] = $categoria->getName();
        if ($clave === 'cuadros') {
          $tids_cuadros[] = (string) $categoria->id();
        }
      }
    }
    if (isset($form['field_categoria']['widget'])) {
      $form['field_categoria']['widget']['#options'] = ['_none' => '- Elige la técnica o el tipo -'] + $grupos;
    }

    // La temática solo tiene sentido en los cuadros.
    if ($tids_cuadros && isset($form['field_tematica'])) {
      $condiciones = [];
      foreach ($tids_cuadros as $tid) {
        if ($condiciones) {
          $condiciones[] = 'or';
        }
        $condiciones[] = [':input[name="field_categoria"]' => ['value' => $tid]];
      }
      $form['field_tematica']['#states'] = ['visible' => $condiciones];
    }

    // Lo técnico va plegado: el pintor solo necesita lo de arriba.
    foreach (['revision_information', 'path_settings', 'meta', 'author', 'options'] as $grupo) {
      if (isset($form[$grupo]) && ($form[$grupo]['#type'] ?? '') === 'details') {
        $form[$grupo]['#open'] = FALSE;
      }
    }
    $form['#attached']['library'][] = 'jllamas_admin/panel';
  }

  /**
   * Términos de Categorías: los campos de sección solo en las secciones.
   */
  #[Hook('form_taxonomy_term_categorias_form_alter')]
  public function termForm(array &$form, FormStateInterface $form_state): void {
    $term = $form_state->getFormObject()->getEntity();
    if (!$term instanceof TermInterface) {
      return;
    }
    $padre = (int) ($term->get('parent')->target_id ?? 0);
    $es_seccion = $term->isNew() ? !\Drupal::request()->query->get('parent') : $padre === 0;
    if (!$es_seccion) {
      foreach (['field_intro', 'field_etiqueta_filtro', 'field_singular'] as $campo) {
        if (isset($form[$campo])) {
          $form[$campo]['#access'] = FALSE;
        }
      }
      if (isset($form['field_obras_portada'])) {
        $form['field_obras_portada']['widget']['#description'] = 'Opcional. Solo se usa en Retrato pop: las cuatro obras del bloque de retratos de Inicio.';
      }
    }
    if (isset($form['field_clave'])) {
      $form['field_clave']['widget'][0]['value']['#description'] = 'Es la palabra que sale en la dirección y en los filtros (por ejemplo, acuarela). Si la dejas vacía se crea sola.';
    }
  }

}
