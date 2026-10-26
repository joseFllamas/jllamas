<?php

declare(strict_types=1);

namespace Drupal\jllamas_admin\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Últimos mensajes del formulario de contacto.
 */
#[Block(
  id: 'jllamas_ultimos_mensajes',
  admin_label: new TranslatableMarkup('Últimos mensajes de contacto de jllamas'),
  category: new TranslatableMarkup('jllamas')
)]
final class UltimosMensajes extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'), $container->get('date.formatter'));
  }

  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'view any webform submission');
  }

  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('webform_submission');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('webform_id', 'contacto')->sort('created', 'DESC')->range(0, 6)->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $submission) {
      $datos = $submission->getData();
      $rows[] = [
        $this->dateFormatter->format($submission->getCreatedTime(), 'short'),
        ['data' => ['#type' => 'link', '#title' => $datos['nombre'] ?? 'Mensaje', '#url' => $submission->toUrl()]],
        $datos['tipo'] ?? '',
        mb_strimwidth((string) ($datos['mensaje'] ?? ''), 0, 80, '…'),
      ];
    }
    return [
      'tabla' => [
        '#type' => 'table',
        '#header' => ['Fecha', 'Nombre', 'Tipo', 'Mensaje'],
        '#rows' => $rows,
        '#empty' => 'Todavía no ha llegado ningún mensaje.',
      ],
      'todos' => [
        '#type' => 'link',
        '#title' => 'Ver todos los mensajes',
        '#url' => Url::fromRoute('entity.webform.results_submissions', ['webform' => 'contacto']),
        '#attributes' => ['class' => ['button', 'button--small']],
      ],
      '#cache' => ['tags' => ['webform_submission_list'], 'contexts' => ['user.permissions']],
    ];
  }

}
