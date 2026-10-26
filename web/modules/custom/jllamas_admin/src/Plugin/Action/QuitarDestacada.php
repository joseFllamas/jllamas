<?php

declare(strict_types=1);

namespace Drupal\jllamas_admin\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Quita obras de las destacadas de Inicio.
 */
#[Action(
  id: 'jllamas_quitar_destacada',
  label: new TranslatableMarkup('Quitar de destacadas'),
  type: 'node'
)]
final class QuitarDestacada extends FieldUpdateActionBase {

  protected function getFieldsToUpdate(): array {
    return ['field_destacada' => 0];
  }

  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->bundle() === 'obra'
      ? $object->access('update', $account, TRUE)
      : AccessResult::forbidden('Solo se pueden quitar de destacadas las obras.');
    return $return_as_object ? $access : $access->isAllowed();
  }

}
