# jllamas en Drupal 11

Nueva web de José Gabriel Llamas (jllamas), con el diseño del prototipo de Lovable y el contenido de la web antigua (jllamas-d7). La documentación del diseño y del plan está en jllamas-d7/plans/nueva_web.

- Local: https://jllamas-d11.ddev.site (ddev, Drupal 11.4, PHP 8.3, MariaDB 11.8).
- Gestión: https://jllamas-d11.ddev.site/admin/dashboard (rol Artista).

## Piezas

| Pieza | Dónde | Qué hace |
|---|---|---|
| Tema jllamas | web/themes/custom/jllamas | Diseño de Lovable con Tailwind 4: cabecera, menú de móvil, pie, ficha de obra, Sobre mí, Encargos, Contacto. JS sin dependencias en js/jllamas.js. |
| Módulo jllamas_site | web/modules/custom/jllamas_site | Inicio, páginas de sección con filtros, Retratos, datos de la ficha. Servicio Catalogo (equivale a lib/catalog.ts de Lovable). Clave de URL y texto alternativo automáticos. |
| Módulo jllamas_admin | web/modules/custom/jllamas_admin | Panel del pintor, acciones Destacar y Quitar de destacadas, formulario de obra simplificado. |
| Módulo jllamas_migrate | web/modules/custom/jllamas_migrate | Migración desde la web antigua: categorías, temáticas, imágenes, 109 obras, páginas y 132 redirecciones. |
| Configuración | config/sync | Exportada con drush cex. |
| Scripts | scripts | Montaje reproducible, capturas y pruebas. |

## Modelo de contenido

- Obra: título, imagen (obligatoria; el texto alternativo se rellena solo), categoría (obligatoria), temáticas (solo cuadros), medidas, descripción y destacada.
- Categorías: vocabulario en dos niveles. Las secciones (Cuadros, Ilustraciones, Decoración) son los términos raíz y crean sus propias rutas (/cuadros...). Sus hijos son las técnicas o tipos. Campos: clave de URL, introducción, nombre del filtro, singular y obras de portada.
- Temáticas: Paisaje, Retrato, Figura.
- Página: Sobre mí (nid 200) y Encargos (nid 201). La entradilla es el resumen del texto.
- Contacto: Webform "contacto" en /contacto, que se rellena desde la URL (?tipo= y ?mensaje=). Lleva honeypot y avisa al correo del sitio.
- Las obras conservan el nid de Drupal 7 (Besalú es el 20). Las referencias fijas están en jllamas_site.settings.

## Trabajar con el tema

- CSS: tras cambiar plantillas o clases, compilar con: ddev exec -d /var/www/html/web/themes/custom/jllamas npm run build
- Primera vez: ddev exec -d /var/www/html/web/themes/custom/jllamas npm install
- Fuentes Fraunces e Instrument Sans en fonts/ (alojadas en el servidor). Para actualizarlas: npm run fonts.
- Ojo: el núcleo de Drupal define una clase .hidden fuera de capas que gana a Tailwind. No uses "hidden md:block"; usa "max-md:hidden" y similares.

## Montar el sitio desde cero

1. ddev start y ddev composer install
2. ddev drush site:install standard --locale=es --existing-config (o instalar e importar con drush cim)
3. Copiar los originales: los 109 ficheros de jllamas-d7/var/www/sites/default/files a migrate-source/files (rutas en web/modules/custom/jllamas_migrate/data/obras.json).
4. ddev drush mim --group=jllamas --execute-dependencies
5. Usuario del pintor: ddev drush user:create (nombre) --mail=(correo) y ddev drush user:role:add artista (nombre)

Los scripts de scripts/ son los que crearon la configuración la primera vez. Se pueden repetir, pero con config/sync ya no hacen falta: crear_modelo.php, crear_pathauto.php, configurar_front.php, crear_contacto.php, crear_gestion.php y configurar_seo.php.

## Pruebas

- Front (filtros, visor, menú de móvil, hero y consola): node scripts/probar_front.mjs https://jllamas-d11.ddev.site
- Gestión con el rol Artista: ddev drush scr scripts/probar_gestion.php -- crear y después -- deshacer
- Capturas de página completa: node scripts/capturas.mjs https://jllamas-d11.ddev.site (carpeta) 1352 900 nombre:/ruta ... (con JL_LOGIN=enlace de drush uli para la gestión).

## Pendiente antes de publicar

- Correo del sitio real (los avisos del formulario van ahí) y usuario del pintor.
- Títulos reales de los retratos pop y de la serie X.
- Revisar los textos que escribió Lovable (pasos del encargo, introducciones, servicios).
- Dominio, HTTPS, copias de seguridad y SMTP en el servidor.
