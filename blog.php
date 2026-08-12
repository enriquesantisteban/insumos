<?php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/db_connection.php';

// Obtener noticia individual mediante parámetro GET 'slug'
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$singleNews = null;


if ($slug !== '') {
    $stmt = $mysqli->prepare('SELECT id, titulo, slug, resumen, contenido, imagen, fecha 
                              FROM blog 
                              WHERE slug = ?');    // Preparar la consulta para obtener una noticia específica por slug
    if ($stmt) {
        $stmt->bind_param('s', $slug);  
        $stmt->execute();
        $singleNews = $stmt->get_result()->fetch_assoc(); // Obtener la noticia específica
    }
}

// Si no se busca una noticia concreta, cargamos el listado general
$noticiasList = [];
if (!$singleNews) {
    // AÑADIDO 'contenido' A LA CONSULTA:
    $res = $mysqli->query('SELECT id, titulo, slug, resumen, contenido, imagen, fecha 
                           FROM blog 
                           ORDER BY fecha DESC'); // Consulta para obtener todas las noticias de forma descendiente
    if ($res) {
        while ($row = $res->fetch_assoc()) { // Guardamos cada noticia en el array $noticiasList
            $noticiasList[] = $row;
        }
    }
}

// Construcción de la URL actual para el módulo de compartir redes
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$currentUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="<?php echo isset($current_lang) ? $current_lang : 'es'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
            if ($singleNews) {
                echo htmlspecialchars(__tdb($mysqli, 'blog', $singleNews['id'], 'titulo', $singleNews['titulo']));
            } else {
                echo __t ('blog.title', 'Blog de Noticias');
            }
        ?> | Regenerative Agro Platform
    </title>    
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/style.css?v=<?php echo time(); ?>">   <!-- Agregamos un parámetro de versión para evitar problemas de caché -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php require_once __DIR__ . '/nav.php'; ?>

<?php if ($singleNews): 
    $titulo = __tdb($mysqli, 'blog', $singleNews['id'], 'titulo', $singleNews['titulo']);
    $contenido = __tdb($mysqli, 'blog', $singleNews['id'], 'contenido', $singleNews['contenido']);
?>
    <section class="hero-section" style="min-height: 45vh; padding: 4rem 2rem 6rem 2rem; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
        <div class="hero-content" style="max-width: 860px; margin: 0 auto; text-align: left;">
            <a href="<?php echo htmlspecialchars(__url('blog')); ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> <?php echo __t('blog.volver', 'Volver al blog'); ?>
            </a>
            <h1 class="hero-title" style="font-size: 2.6rem; margin-top: 1.5rem; line-height: 1.25; color: #ffffff;">
                <?php echo htmlspecialchars($titulo); ?>
            </h1>
        </div>
    </section>

    <div class="article-container">
        <article class="article-card">
            <div class="article-meta">
                <span class="article-meta-badge">
                    <i class="fas fa-tag"></i> <?php echo __t('blog.categoria', 'Actualidad'); ?>
                </span>
                <span>
                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($singleNews['fecha'])); ?>
                </span>
            </div>

            <?php if (!empty($singleNews['imagen'])): ?>
                <div class="article-img-wrapper">
                    <img src="<?php echo htmlspecialchars(__asset_url($singleNews['imagen'])); ?>" alt="<?php echo htmlspecialchars($titulo); ?>">
                </div>
            <?php endif; ?>

            <div class="article-content">
                <?php echo nl2br(htmlspecialchars($contenido)); ?>
            </div>

            <div class="share-container">
                <span class="share-title"><?php echo __t('blog.compartir', 'Compartir:'); ?></span>
                <div class="share-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($currentUrl); ?>" 
                    target="_blank" rel="noopener noreferrer" class="share-btn share-facebook" title="<?php echo __t('blog.share_facebook', 'Compartir en Facebook'); ?>">
                        <i class="fa-brands fa-facebook-f"></i>
                    </a>

                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($currentUrl); ?>" 
                    target="_blank" rel="noopener noreferrer" class="share-btn share-linkedin" title="<?php echo __t('blog.share_linkedin', 'Compartir en LinkedIn'); ?>">
                        <i class="fa-brands fa-linkedin-in"></i>
                    </a>

                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($currentUrl); ?>&text=<?php echo urlencode($titulo); ?>" 
                    target="_blank" rel="noopener noreferrer" class="share-btn share-x" title="<?php echo __t('blog.share_x', 'Compartir en X'); ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>

                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($titulo . ' - ' . $currentUrl); ?>" 
                    target="_blank" rel="noopener noreferrer" class="share-btn share-whatsapp" title="<?php echo __t('blog.share_whatsapp', 'Compartir en WhatsApp'); ?>">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>

                    <a href="mailto:?subject=<?php echo urlencode($titulo); ?>&body=<?php echo urlencode('Te comparto este artículo: ' . $currentUrl); ?>" 
                    class="share-btn share-email" title="Enviar por Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </article>
    </div>

<?php else: ?>
    <section class="hero-section" style="min-height: 35vh; padding: 3rem 2rem;">
        <div class="hero-content">
            <span class="hero-eyebrow"><?php echo __t('blog.eyebrow', 'Actualidad y Novedades'); ?></span>
            <h1 class="hero-title"><?php echo __t('blog.title', 'Blog de Noticias'); ?></h1>
            <p class="hero-desc"><?php echo __t('blog.desc', 'Mantente informado sobre los avances en agricultura regenerativa, bioinsumos y el entorno regulatorio.'); ?></p>
        </div>
    </section>

    <section class="section-pad">
        <div class="section-inner">
            <?php if (!empty($noticiasList)): ?>
                <div class="product-grid">
                    <?php foreach ($noticiasList as $item): 
                        $itemTitulo = __tdb($mysqli, 'blog', $item['id'], 'titulo', $item['titulo']);
                        $itemResumen = __tdb($mysqli, 'blog', $item['id'], 'resumen', $item['resumen']);
                    ?>
                        <article class="product-card">
                            <div class="card-img">
                                <?php if (!empty($item['imagen'])): ?>
                                    <img src="<?php echo htmlspecialchars(__asset_url($item['imagen'])); ?>" 
                                        alt="<?php echo htmlspecialchars($itemTitulo); ?>" 
                                        style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain;">
                                <?php else: ?>
                                    <i class="fas fa-newspaper" style="font-size:3rem; color:#cbd5e1;"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-body">
                                <span style="font-size: 0.75rem; color: var(--muted); font-weight: 600;">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($item['fecha'])); ?>
                                </span>

                                <h3><?php echo htmlspecialchars($itemTitulo); ?></h3>
                                
                                <p><?php echo htmlspecialchars($itemResumen); ?></p>
                                
                                <a href="<?php echo htmlspecialchars(__url('blog', ['slug' => $item['slug']])); ?>" class="btn btn-full">
                                    <?php echo __t('blog.leer_mas', 'Leer más'); ?> <i class="fas fa-arrow-right" aria-hidden="true" style="font-size:0.75rem; margin-left:4px;"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    <i class="fas fa-newspaper" style="font-size:3rem; color:#cbd5e1; display:block; margin-bottom:1rem;"></i>
                    <h3><?php echo __t('blog.empty_title', 'No hay noticias publicadas'); ?></h3>
                    <p><?php echo __t('blog.empty_desc', 'Próximamente añadiremos novedades sobre el sector.'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>

</body>
</html>