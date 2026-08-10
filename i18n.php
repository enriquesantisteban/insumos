<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ruta base del sitio si no está en la raíz del dominio (ej. '/insumos').
// Déjalo vacío ('') si el sitio vive en la raíz, ej. http://localhost/producto.php

// Si la web va a ser insumos.com por ejemplo, tengo que quitar /insumos y dejarlo ('')
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/insumos');
}

// Idiomas soportados
$allowed_langs = ['es', 'en', 'pt', 'fr', 'ca'];

// 1. Detectar si el usuario cambia de idioma mediante la URL (?lang=en)
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed_langs)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('user_lang', $_GET['lang'], time() + (86400 * 30), "/"); // Cookie por 30 días
}

// 2. Definir el idioma activo
if (isset($_SESSION['lang'])) {
    $current_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['user_lang']) && in_array($_COOKIE['user_lang'], $allowed_langs)) {
    $current_lang = $_COOKIE['user_lang'];
} else {
    $current_lang = 'es'; // Idioma por defecto
}

// 3. Cargar el diccionario estático de interfaz si existe
$lang_file = __DIR__ . "/lang/{$current_lang}.php";
$translations = file_exists($lang_file) ? require $lang_file : [];

// 4. Mapa de rutas de archivo por idioma (solo para páginas SIN slug, ej. 'index').
$url_map = [
    'es' => ['index' => ''],
    'en' => ['index' => ''],
    'pt' => ['index' => ''],
    'fr' => ['index' => ''],
    'ca' => ['index' => ''],
];
$current_url_map = $url_map[$current_lang] ?? $url_map['es'];


// 5. Segmento de URL "bonito" por idioma para fabricante/producto (sin .php ni id):
//    /es/fabricante/kenogard   /en/manufacturer/kenogard   /fr/fabricant/kenogard ...
//    Estas son las claves que también debe reconocer el .htaccess.
$page_segment_map = [
    'es' => ['fabricante' => 'fabricante',   'producto' => 'producto', 'blog' => 'blog'],
    'en' => ['fabricante' => 'manufacturer', 'producto' => 'product',  'blog' => 'news'],
    'pt' => ['fabricante' => 'fabricante',   'producto' => 'produto',  'blog' => 'noticias'],
    'fr' => ['fabricante' => 'fabricant',    'producto' => 'produit',  'blog' => 'nouvelles'],
    'ca' => ['fabricante' => 'fabricant',    'producto' => 'producte', 'blog' => 'noticies'],
];
$current_page_segment_map = $page_segment_map[$current_lang] ?? $page_segment_map['es'];

// Los scripts reales a los que apunta cada clave de página, siempre en español
// (todas las variantes de idioma se reescriben hacia el mismo archivo canónico).
// Se usa para saber "en qué página estoy", sea cual sea la URL bonita que se usó.
$canonical_script_map = [
    'index.php'      => 'index',
    'fabricante.php' => 'fabricante',
    'producto.php'   => 'producto',
    'blog.php'       => 'blog',
    'contacto.php'   => 'contacto',
];

/**
 * Traduce textos estáticos del código (interfaz).
 */
function __t($key, $default = '') {
    global $translations;
    return $translations[$key] ?? ($default ?: $key);
}

/**
 * Devuelve la clave de página (ej. 'fabricante', 'producto', 'index') correspondiente
 * al script que se está ejecutando actualmente. Como todas las variantes de idioma
 * reescriben siempre hacia el mismo archivo canónico (fabricante.php, producto.php...),
 * basta con mirar el nombre real del script en ejecución.
 */
function __current_page_key() {
    global $canonical_script_map;
    $current_script = basename($_SERVER['SCRIPT_NAME']);
    return $canonical_script_map[$current_script] ?? null;
}

/**
 * Convierte un texto (ej. el nombre de un fabricante o producto) en un slug apto
 * para URL: minúsculas, sin acentos, espacios y símbolos convertidos en guiones.
 * Ej: "Kenogard S.A." -> "kenogard-s-a"
 * (Nota: si ya tienes una columna 'slug' en la base de datos, úsala directamente;
 * esta función queda disponible como respaldo o para generar slugs nuevos.)
 */
function __slugify($text) {
    $text = trim((string)$text);
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    if ($transliterated !== false) {
        $text = $transliterated;
    }
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

/**
 * Genera la URL de la página EQUIVALENTE en otro idioma, manteniendo el slug (para
 * fabricante/producto) o el resto de parámetros GET, y con el prefijo /es/, /en/...
 * y el segmento de página ya traducido (fabricante -> manufacturer, etc.).
 * Se usa para construir el selector de idioma en nav.php.
 */
function __lang_switch_url($lang) {
    global $url_map, $page_segment_map;

    $page_key = __current_page_key();
    $query = $_GET;
    unset($query['lang']);

    // Páginas de detalle (fabricante/producto): ruta bonita con slug y segmento traducido
    if (in_array($page_key, ['fabricante', 'producto'], true) && !empty($query['slug'])) {
        $target_segments = $page_segment_map[$lang] ?? $page_segment_map['es'];
        $segment = $target_segments[$page_key] ?? $page_key;
        $slug = $query['slug'];
        unset($query['slug']);
        $url = BASE_PATH . '/' . $lang . '/' . $segment . '/' . rawurlencode($slug);
        return $url . (!empty($query) ? '?' . http_build_query($query) : '');
    }

    // Resto de páginas (ej. index.php)
    $target_map = $url_map[$lang] ?? $url_map['es'];
    $path = ($page_key !== null && isset($target_map[$page_key]))
        ? $target_map[$page_key]
        : basename($_SERVER['SCRIPT_NAME']); // fallback: mismo nombre de archivo

    $url = BASE_PATH . '/' . $lang . '/' . $path;
    return $url . (!empty($query) ? '?' . http_build_query($query) : '');
}

/**
 * Genera una URL localizada para una página clave.
 * - Para 'fabricante' o 'producto' con ['slug' => ...]: ruta bonita y con el
 *   segmento ya traducido al idioma activo, ej. /es/fabricante/kenogard,
 *   /en/manufacturer/kenogard, /fr/fabricant/kenogard.
 * - Para el resto (ej. 'index'): ruta de archivo, ej. /es/index.php.
 *
 * @param string $page_key La clave de la página (ej. 'fabricante', 'producto', 'index').
 * @param array $params Parámetros. Usa 'slug' para fabricante/producto.
 * @return string La URL completa y localizada.
 */
function __url($page_key, $params = []) {
    global $current_url_map, $current_lang, $current_page_segment_map;

    if (in_array($page_key, ['fabricante', 'producto'], true) && !empty($params['slug'])) {
        $segment = $current_page_segment_map[$page_key] ?? $page_key;
        $slug = $params['slug'];
        unset($params['slug']);
        $url = BASE_PATH . '/' . $current_lang . '/' . $segment . '/' . rawurlencode($slug);
        return $url . (!empty($params) ? '?' . http_build_query($params) : '');
    }

    $path = $current_url_map[$page_key] ?? ($page_key . '.php');
    $url = BASE_PATH . '/' . $current_lang . '/' . $path;
    return $url . (!empty($params) ? '?' . http_build_query($params) : '');
}

/**
 * Resuelve una ruta de imagen/archivo guardada en la Base de Datos para que funcione
 * bajo cualquier URL (ej. /insumos/es/producto/algo), sin importar la profundidad de la URL.
 * Si la ruta ya es absoluta (http://, https:// o empieza por /), se devuelve tal cual.
 * Si es relativa (ej. 'uploads/logo.png'), se le antepone BASE_PATH para que apunte
 * siempre a la carpeta real del sitio y no a la carpeta del idioma/segmento actual.
 */
function __asset_url($path) {
    if (empty($path)) {
        return $path;
    }
    if (preg_match('#^(https?:)?//#i', $path) || $path[0] === '/') {
        return $path; // ya es una URL absoluta o una ruta absoluta del servidor
    }
    return BASE_PATH . '/' . ltrim($path, '/');
}

/**
 * Traduce campos dinámicos almacenados en la Base de Datos.
 * Si el idioma es 'es' o la traducción no existe en la tabla, devuelve el texto base.
 */
function __tdb($mysqli, $tabla, $traduccion_id, $campo, $textoOriginal) {
    global $current_lang;

    if ($current_lang === 'es' || empty($textoOriginal)) {
        return $textoOriginal;
    }

    $stmt = $mysqli->prepare('SELECT texto FROM traducciones WHERE tabla = ? AND traduccion_id = ? AND campo = ? AND idioma = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('siss', $tabla, $traduccion_id, $campo, $current_lang);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return $row['texto']; // Retorna tu traducción revisada de MySQL
        }
        $GLOBALS['__tdb_debug'] = "prepare OK, pero 0 filas encontradas para tabla={$tabla} traduccion_id={$traduccion_id} campo={$campo} idioma={$current_lang}";
    } else {
        // Captura el motivo real por el que prepare() falló (ej. columna inexistente)
        $GLOBALS['__tdb_debug'] = "PREPARE FALLÓ: " . $mysqli->error;
    }

    return $textoOriginal; // Retorna el idioma original si no hay traducción cargada aún
}
?>
