# Regenerative Agro Platform (proyecto "insumos")

Catálogo web en PHP + MySQL de fabricantes e insumos biológicos para agricultura regenerativa. Incluye fichas de producto con gráfico radar de microorganismos, blog de noticias, formulario de contacto y soporte multi-idioma con URLs amigables (`/es/`, `/en/`, `/pt/`, `/fr/`, `/ca/`).

## Características

- Listado de fabricantes con filtro por tipo de registro y contador de productos.
- Ficha de fabricante y ficha de producto, con relación a microorganismos (gráfico radar vía Chart.js).
- Blog de noticias con vista de listado y de artículo individual, botones para compartir en redes.
- Formulario de contacto por email (PHPMailer).
- Sistema de traducciones: textos de interfaz estáticos (`lang/*.php`) y contenido dinámico de la base de datos (tabla `traducciones`).
- URLs amigables por idioma mediante `mod_rewrite` (p. ej. `/es/producto/trichoderma`, `/en/product/trichoderma`).

## Estructura del proyecto

```
├── index.php              # Página principal: listado de fabricantes + contacto
├── fabricante.php         # Ficha de fabricante y sus productos
├── producto.php           # Ficha de producto (specs + microorganismos)
├── blog.php               # Listado de noticias y vista de artículo
├── nav.php                # Cabecera / menú de navegación
├── footer.php             # Pie de página
├── i18n.php                # Lógica de idiomas, helpers __t(), __url(), __asset_url(), __tdb()
├── db_connection.php      # Conexión a MySQL (mysqli)
├── style.css               # Estilos generales del sitio
├── schema.sql              # Estructura + datos de ejemplo de la base de datos
├── .htaccess                # Reglas de reescritura de URL (Apache)
├── guia-despliegue-nginx.md # Guía para migrar las reglas de .htaccess a Nginx
│
├── manufacturer.php        # Alias EN -> fabricante.php
├── fabricant.php           # Alias FR/CA -> fabricante.php
├── product.php              # Alias EN -> producto.php (si existe)
├── produto.php              # Alias PT -> producto.php
├── produit.php              # Alias FR -> producto.php
├── producte.php             # Alias CA -> producto.php
└── noticias.php / nouvelles.php / noticies.php / news.php  # Alias de blog.php (si existen)
```

Los alias por idioma solo cargan el archivo canónico correspondiente (`fabricante.php`, `producto.php`, `blog.php`); el idioma real se determina por parámetro `lang` o por la ruta reescrita.

## Requisitos

- PHP 8.1+ con extensión `mysqli`
- MySQL / MariaDB
- Servidor Apache con `mod_rewrite` habilitado (o Nginx, ver guía de despliegue) para las URLs amigables
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) para el envío del formulario de contacto

## Instalación en local (USBWebServer / XAMPP / similar)

1. Copia todos los archivos del proyecto dentro de la carpeta web de tu servidor local, por ejemplo:
   ```
   E:\USBWebServer\root\insumos
   ```
2. Inicia Apache y MySQL desde tu panel (USBWebServer, XAMPP, etc.).
3. Abre `phpMyAdmin`, crea la base de datos `insumos` y ejecuta el contenido de `schema.sql` (ya incluye tablas y datos de ejemplo).
4. Revisa `db_connection.php` y ajusta host / usuario / contraseña / nombre de base de datos si tu entorno local no usa los valores por defecto.
5. Revisa `BASE_PATH` en `i18n.php`: debe coincidir con la subcarpeta donde vive el sitio (`/insumos` en local; vacío `''` si el sitio vive en la raíz del dominio).
6. Descarga [PHPMailer](https://github.com/PHPMailer/PHPMailer) y colócalo en una carpeta `PHPMailer/` dentro del proyecto (o instálalo vía Composer y deja `vendor/autoload.php`), y configura tus credenciales SMTP en `index.php`.
7. Abre en el navegador:
   ```
   http://localhost/insumos/es/
   ```

## Despliegue en producción (Nginx)

Para desplegar el sitio en un servidor con Nginx (sirviendo el dominio desde la raíz, sin subcarpeta `/insumos/`), sigue `guia-despliegue-nginx.md`, que traduce las reglas de `.htaccess` a bloques `location` / `try_files` equivalentes y explica el ajuste de `BASE_PATH` a vacío.

## Idiomas y URLs

- Idiomas soportados: `es`, `en`, `pt`, `fr`, `ca`.
- Las rutas de fabricante/producto/blog se traducen automáticamente por idioma (ej. `fabricante` → `manufacturer` en inglés, `fabricant` en francés/catalán).
- El idioma activo se guarda en sesión y en cookie (`user_lang`, 30 días) al usar `?lang=xx` o el selector de idioma.
- Los textos de interfaz se gestionan con `__t('clave', 'valor por defecto')` y los ficheros `lang/{idioma}.php`.
- El contenido dinámico (nombres, descripciones, etc.) se traduce con `__tdb()` contra la tabla `traducciones`.