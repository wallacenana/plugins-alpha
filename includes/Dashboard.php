<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Dashboard {
  public static function render() : void {
    $items = PluginsAlpha_Remote::catalog();
    if (!is_array($items)) $items = [];
    ?>
    <div class="wrap pa-wrap">
      <h1 class="pa-title"><?php echo esc_html__('Plugins Alpha — Dashboard', 'plugins-alpha'); ?></h1>
      <p class="pa-subtitle"><?php echo esc_html__('Gerencie seus geradores e descubra novos módulos.', 'plugins-alpha'); ?></p>

      <?php if (empty($items)) : ?>
        <div class="pa-panel">
          <p><?php echo esc_html__('Nenhum item encontrado no catálogo. Verifique suas configurações ou tente novamente mais tarde.', 'plugins-alpha'); ?></p>
          <p>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=plugins-alpha-settings')); ?>">
              <?php echo esc_html__('Abrir Configurações', 'plugins-alpha'); ?>
            </a>
          </p>
        </div>
      <?php else: ?>
        <div class="pa-grid">
          <?php
          // Fallback de logo local
          $fallback_logo = PGA_URL . 'assets/images/alpha-ico.png?v=' . pga_asset_ver('assets/images/alpha-ico.png');

          foreach ($items as $it):
            $slug   = isset($it['slug']) ? (string)$it['slug'] : '';
            $name   = isset($it['name']) ? (string)$it['name'] : 'Módulo';
            $desc   = isset($it['desc']) ? (string)$it['desc'] : '';
            $logo   = !empty($it['logo']) ? (string)$it['logo'] : $fallback_logo;
            $price  = isset($it['price']) ? (float)$it['price'] : 0.0;
            $promo  = isset($it['promo_price']) && $it['promo_price'] !== '' ? (float)$it['promo_price'] : null;
            $buy    = !empty($it['buy_url']) ? (string)$it['buy_url'] : '';
          ?>
            <div class="pa-card">
              <div class="pa-card-header">
                <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?>" class="pa-logo">
                <h3><?php echo esc_html($name); ?></h3>
              </div>

              <?php if ($desc !== ''): ?>
                <p class="pa-desc"><?php echo esc_html($desc); ?></p>
              <?php endif; ?>

              <div class="pa-pricebox">
                <?php if ($promo !== null): ?>
                  <span class="pa-price-promo">R$ <?php echo esc_html(number_format($promo, 2, ',', '.')); ?></span>
                  <span class="pa-price-old">R$ <?php echo esc_html(number_format($price, 2, ',', '.')); ?></span>
                <?php else: ?>
                  <span class="pa-price">R$ <?php echo esc_html(number_format($price, 2, ',', '.')); ?></span>
                <?php endif; ?>
              </div>

              <div class="pa-actions">
                <?php
                  // Botões por slug conhecido
                  if ($slug === 'gpt-posts') {
                    $href = admin_url('admin.php?page=plugins-alpha-gpt-posts');
                    echo '<a class="button button-primary" href="'.esc_url($href).'">'.esc_html__('Abrir Gerar Posts','plugins-alpha').'</a>';
                  } elseif ($slug === 'alpha-stories') {
                    $href = admin_url('admin.php?page=plugins-alpha-alpha-stories');
                    echo '<a class="button button-primary" href="'.esc_url($href).'">'.esc_html__('Abrir Web Stories','plugins-alpha').'</a>';
                  } else {
                    // Botão genérico para módulos novos: vai para o dashboard
                    $href = admin_url('admin.php?page=plugins-alpha-dashboard');
                    echo '<a class="button" href="'.esc_url($href).'">'.esc_html__('Abrir', 'plugins-alpha').'</a>';
                  }

                  // Link de compra/assinatura
                  if ($buy) {
                    echo ' <a class="button" target="_blank" rel="noopener noreferrer" href="'.esc_url($buy).'">'.esc_html__('Comprar / Assinar','plugins-alpha').'</a>';
                  }

                  /**
                   * Ação para módulos injetarem botões extras no card.
                   * Ex.: add_action('plugins_alpha/dashboard/card_actions', function($slug,$item){ ... });
                   */
                  do_action('plugins_alpha/dashboard/card_actions', $slug, $it);
                ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }
}
