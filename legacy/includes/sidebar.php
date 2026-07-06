<?php
// Sidebar menu partial. Expects $logo_html, $pick_icon_tone, $role_name, $user_avatar, $notif_count, $notif_list.
?>
<nav id="sidebar" class="d-flex flex-column shadow">
    <div class="sidebar-logo-container">
        <?= $logo_html ?>
        <?php $sidebar_company_name = trim((string)($company_name ?? 'MMS System')); ?>
        <?php
            $software_name = 'MMS Soft';
            $software_version = 'v1.0.0';
        ?>
        <?php if ($sidebar_company_name !== ''): ?>
            <?php
                $company_words = preg_split('/\s+/', $sidebar_company_name, -1, PREG_SPLIT_NO_EMPTY);
                $company_line_1 = $sidebar_company_name;
                $company_line_2 = '';
                if (is_array($company_words) && count($company_words) > 3) {
                    $company_line_1 = implode(' ', array_slice($company_words, 0, 3));
                    $company_line_2 = implode(' ', array_slice($company_words, 3));
                }
            ?>
            <h5 class="mt-3 mb-0 text-center text-uppercase" style="font-size: 96%; line-height: 1.25;">
                <?= clean($company_line_1) ?>
                <?php if ($company_line_2 !== ''): ?><br><?= clean($company_line_2) ?><?php endif; ?>
            </h5>
        <?php endif; ?>
        <small class="d-block mt-2 text-center" style="font-size: 72%; line-height: 1.2;">
            <?= clean($software_name . ' ' . $software_version) ?>
        </small>
    </div>
    <ul class="list-unstyled components mb-auto">
        <?php 
        $theme_value = '';
        if (isset($_GET['theme'])) {
            $theme_query = strtolower(trim((string)$_GET['theme']));
            $themes = function_exists('mms_get_available_themes') ? mms_get_available_themes() : [];
            if ($theme_query !== '' && isset($themes[$theme_query])) {
                $theme_value = $theme_query;
            }
        }
        $append_theme = function($url) use ($theme_value) {
            $url = (string)$url;
            if ($theme_value === '' || $url === '' || stripos($url, 'theme=') !== false) return $url;
            return $url . (strpos($url, '?') !== false ? '&' : '?') . 'theme=' . rawurlencode($theme_value);
        };
        $menus = get_sidebar_menus(); 
        $current_page = $_GET['page'] ?? 'dashboard'; 
        foreach($menus as $menu): 
            if (isset($menu['submenu'])): 
                $isParentActive = false; 
                foreach($menu['submenu'] as $sub) { 
                    if (strpos($sub['url'], "page=$current_page") !== false) { $isParentActive = true; break; } 
                } 
                $parentTone = $pick_icon_tone($menu['id'] ?? $menu['label']);
                $collapsedClass = $isParentActive ? 'show' : '';
                $ariaExpanded = $isParentActive ? 'true' : 'false';
                $parentDevClass = !empty($menu['is_dev']) ? 'fst-italic' : '';
        ?>
            <li><a href="#<?= $menu['id'] ?>" data-bs-toggle="collapse" aria-expanded="<?= $ariaExpanded ?>" class="dropdown-toggle d-flex align-items-center text-dark"><span class="sidebar-menu-icon <?= $parentTone ?> me-2"><i class="bi <?= !empty($menu['icon']) ? $menu['icon'] : 'bi-circle-fill' ?>"></i></span> <span class="<?= $parentDevClass ?>"><?= $menu['label'] ?></span></a>
                <ul class="collapse list-unstyled sidebar-submenu <?= $collapsedClass ?>" id="<?= $menu['id'] ?>">
                    <?php foreach($menu['submenu'] as $sub): $isSubActive = (strpos($sub['url'], "page=$current_page") !== false) ? 'active fw-bold text-primary' : ''; $subTone = $pick_icon_tone(($menu['id'] ?? $menu['label']) . '-' . ($sub['url'] ?? $sub['label'])); $subUrl = $append_theme($sub['url'] ?? ''); $subDevClass = !empty($sub['is_dev']) ? 'fst-italic' : ''; ?>
                    <li><a href="<?= $subUrl ?>" class="nav-link <?= $isSubActive ?>"><span class="sidebar-menu-icon <?= $subTone ?>"><i class="bi <?= !empty($sub['icon']) ? $sub['icon'] : 'bi-dot' ?>"></i></span><span class="<?= $subDevClass ?>"><?= $sub['label'] ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php else: $isActive = (strpos($menu['url'], "page=$current_page") !== false) ? 'active fw-bold text-primary' : ''; ?>
            <?php $singleTone = $pick_icon_tone($menu['url'] ?? $menu['label']); ?>
            <?php $menuUrl = $append_theme($menu['url'] ?? ''); ?>
            <?php $singleDevClass = !empty($menu['is_dev']) ? 'fst-italic' : ''; ?>
            <li><a href="<?= $menuUrl ?>" class="<?= $isActive ?> text-dark d-flex align-items-center"><span class="sidebar-menu-icon <?= $singleTone ?> me-2"><i class="bi <?= !empty($menu['icon']) ? $menu['icon'] : 'bi-circle-fill' ?>"></i></span> <span class="<?= $singleDevClass ?>"><?= $menu['label'] ?></span></a></li>
        <?php endif; endforeach; ?>
    </ul>
</nav>
