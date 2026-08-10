<?php
/**
 * Componente Footer Unificado
 */
require_once __DIR__ . '/i18n.php';

// Si $fabricantesData no existe en la página actual, realizamos una consulta rápida para el menú del footer
if (!isset($fabricantesData) && isset($mysqli)) {
    $fabricantesData = [];
    $resFabFooter = $mysqli->query('SELECT nombre, slug FROM fabricantes ORDER BY nombre');
    if ($resFabFooter) {
        while ($r = $resFabFooter->fetch_assoc()) {
            $fabricantesData[] = $r;
        }
    }
}

// Valores por defecto para estadísticas si no vienen calculados previamente
$totFab   = $totalFabricantes ?? (isset($fabricantesData) ? count($fabricantesData) : 0);
$totProd  = $totalProductos ?? (isset($mysqli) ? (int)$mysqli->query('SELECT COUNT(*) AS c FROM productos')->fetch_assoc()['c'] : 0);
?>

<footer>
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="footer-brand-name">
            <a href="<?php echo htmlspecialchars(__url('index')); ?>" class="footer-logo-link">
                    <img src="/insumos/media/logo.png" alt="Logo Regenerative Agro Platform" class="nav-brand-logo"> 
                    <span>Regenerative<span style="color:var(--teal);"> Agro Platform</span></span>
                </a>
            </div>
            <p><?php echo __t('index.footer_brand_desc', 'Catálogo unificado de insumos biológicos para la agricultura regenerativa. Productos evaluados con ensayos de inocuidad certificados.'); ?></p>
        </div>

        <div class="footer-col">
            <h4><?php echo __t('footer.nav_title', 'Navegación'); ?></h4>
            <ul>
                
                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>"><?php echo __t('nav.inicio', 'Inicio'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#impacto"><?php echo __t('nav.impacto', 'Impacto'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#fabricantes"><?php echo __t('nav.fabricantes', 'Fabricantes'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars(__url('index')); ?>#contacto"><?php echo __t('footer.contacto', 'Contacto'); ?></a></li>
                <li><a href="<?php echo htmlspecialchars(__url('blog')); ?>#blog"><?php echo __t('footer.blog', 'Blog'); ?></a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4><?php echo __t('footer.manufacturers_title', 'Fabricantes'); ?></h4>
            <ul>
                <?php if (!empty($fabricantesData)): ?>
                    <?php foreach ($fabricantesData as $fab): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(__url('fabricante', ['slug' => $fab['slug']])); ?>">
                                <?php echo htmlspecialchars($fab['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h4><?php echo __t('footer.platform_title', 'Plataforma'); ?></h4>
            <ul>
                <li><?php echo __t('footer.manufacturers_title', 'Fabricantes'); ?>: <span><?php echo $totFab; ?>+</span></li>
                <li><?php echo __t('footer.product_title', 'Productos'); ?>: <span><?php echo $totProd; ?>+</span></li>
                <li><?php echo __t('footer.protocol', 'Protocolo'); ?>: <span>MBG-EHB-01</span></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> Regenerative Agro Platform. <?php echo __t('footer.copyright', 'Todos los derechos reservados.'); ?></p>
    </div>
</footer>