<?php

declare(strict_types=1);

namespace Drupal\jllamas_admin\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Marca obras como destacadas (salen en el hero y la fila de Inicio).
 */
#[Action(
  id: 'jllamas_destacar_obra',
  label: new TranslatableMarkup('Destacar en Inicio'),
  type: 'node'
)]
final class DestacarObra extends FieldUpdateActionBase {

  protected function getFieldsToUpdate(): array {
    return ['field_destacada' => 1];
  }

  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->bundle() === 'obra'
      ? $object->access('update', $account, TRUE)
      : AccessResult::forbidden('Solo se pueden destacar obras.');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
