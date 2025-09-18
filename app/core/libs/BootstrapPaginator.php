<?php

namespace boctulus\TutorNewCourses\core\libs;

use boctulus\TutorNewCourses\core\libs\Url;

/*
    Componente

    A diferencia de paginadores en Bt5Form, este paginador tiene logica incluida
*/

class BootstrapPaginator {
    /*
        @param bool $show_last muestra version del paginador que exhibe la ultima pagina
    */
    static function render($data, int $short_after = 5, bool $show_last = false)
    {
        $page_key    = config()['paginator']['params']['page'] ?? 'page';
        $current_url = Url::currentUrl();
    ?>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php if ($data['paginator']['last_page'] <= $short_after) : ?>
                    <!-- Mostrar todos los enlaces si hay {$short_after} o menos páginas -->
                    <?php for ($i = 1; $i <= $data['paginator']['last_page']; $i++) :
                        $page_link = Url::addQueryParam($current_url, $page_key, $i);
                    ?>
                        <li class="page-item"><a class="page-link" href="<?= $page_link ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php else : ?>

                    <!-- Mostrar botones especiales si hay más de {$short_after} páginas -->
                    <?php
                        $currentPage = $data['paginator']['current_page'];
                        $lastPage = $data['paginator']['last_page'];
                    ?>

                    <?php if ($currentPage > 1) : ?>
                        <!-- Si no estamos en la primera página, mostrar enlace a la página anterior -->
                        <li class="page-item"><a class="page-link" href="<?= Url::addQueryParam($current_url, $page_key, $currentPage - 1) ?>"><</a></li>
                    <?php endif; ?>

                    <?php if ($show_last && $currentPage > $short_after) : ?>
                        <!-- Si $show_last es true y estamos más allá de las primeras páginas, mostrar enlace a la primera página -->
                        <li class="page-item"><a class="page-link" href="<?= Url::addQueryParam($current_url, $page_key, 1) ?>">1</a></li>
                        <!-- Mostrar puntos suspensivos (...) -->
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>

                    <!-- Mostrar enlaces a las páginas actuales y circundantes -->
                    <?php for ($i = max(1, $currentPage - 2); $i <= min($lastPage, $currentPage + 2); $i++) :
                        $page_link = Url::addQueryParam($current_url, $page_key, $i);
                    ?>
                        <li class="page-item <?= ($i == $data['paginator']['current_page']) ? 'active' : '' ?>"><a class="page-link" href="<?= $page_link ?>"<?= ($i == $currentPage) ? ' class="active"' : '' ?>><?= $i ?></a></li>
                    <?php endfor; ?>

                    <?php if ($show_last && $currentPage < $lastPage - $short_after + 1) : ?>
                        <!-- Mostrar puntos suspensivos (...) -->
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <!-- Si $show_last es true y no estamos en las últimas páginas, mostrar enlace a la última página -->
                        <li class="page-item"><a class="page-link" href="<?= Url::addQueryParam($current_url, $page_key, $lastPage) ?>"><?= $lastPage ?></a></li>
                    <?php endif; ?>

                    <?php if ($currentPage < $lastPage) : ?>
                        <!-- Si no estamos en la última página, mostrar enlace a la página siguiente -->
                        <li class="page-item"><a class="page-link" href="<?= Url::addQueryParam($current_url, $page_key, $currentPage + 1) ?>">></a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    <?php
    }
}