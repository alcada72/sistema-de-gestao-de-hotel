<?php
$temTabmenus = false;

foreach ($menus as $menu) {
  if (!empty($menu['items'])) {
    $temTabmenus = true;
    break;
  }
}
?>

<?php if ($temTabmenus): ?>

  <div class="tab_menu">
    <div class="tab_menu_navigation">

      <?php foreach ($menus as $menuIndex => $menu): ?>

        <?php if (!empty($menu['items'])): ?>

          <div class="tabmenu-group" data-parent="<?= htmlspecialchars($menu['chave'], ENT_QUOTES, 'UTF-8') ?>" hidden>

            <?php foreach ($menu['items'] as $itemIndex => $item): ?>

              <button type="button" class="tab_menu_link <?= $itemIndex === 0 ? 'is-active' : '' ?>"
                data-section="<?= htmlspecialchars($item['link'], ENT_QUOTES, 'UTF-8') ?>">
                <span class="tab_menu_icon">
                  <?= $item['icone'] ?>
                </span>

                <span class="tab_menu_text">
                  <?= htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </button>

            <?php endforeach; ?>

          </div>

        <?php endif; ?>

      <?php endforeach; ?>

    </div>
  </div>

<?php endif; ?>