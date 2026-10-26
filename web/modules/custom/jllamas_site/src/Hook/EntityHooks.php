<?php

declare(strict_types=1);

namespace Drupal\jllamas_site\Hook;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Automatismos al guardar términos y obras.
 */
final class EntityHooks {

  public function __construct(
    #[Autowire(service: 'transliteration')]
    private readonly TransliterationInterface $transliteration,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Genera la clave de URL de una categoría a partir del nombre si está vacía.
   */
  #[Hook('taxonomy_term_presave')]
  public function termPresave(TermInterface $term): void {
    if ($term->bundle() !== 'categorias' || !$term->hasField('field_clave')) {
      return;
    }
    if (trim((string) $term->get('field_clave')->value) === '') {
      $term->set('field_clave', $this->slug($term->getName()));
    }
  }

  /**
   * Rellena el texto alternativo de la imagen de una obra si va vacío.
   *
   * Formato: "{título}, {categoría}, obra de José Gabriel Llamas".
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() !== 'obra' || $node->get('field_imagen')->isEmpty()) {
      return;
    }
    $item = $node->get('field_imagen')->first();
    if (trim((string) $item->alt) !== '') {
      return;
    }
    $categoria = '';
    if (!$node->get('field_categoria')->isEmpty()) {
      $tid = $node->get('field_categoria')->target_id;
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      $categoria = $term ? $term->getName() : '';
    }
    $item->alt = $categoria !== ''
      ? sprintf('%s, %s, obra de José Gabriel Llamas', $node->getTitle(), $categoria)
      : sprintf('%s, obra de José Gabriel Llamas', $node->getTitle());
  }

  /**
   * Convierte un texto en clave de URL: "Negocios y tiendas" > "negocios-y-tiendas".
   */
  private function slug(string $text): string {
    $text = mb_strtolower($this->transliteration->transliterate($text, 'es'));
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $text), '-');
  }

}
