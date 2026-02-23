<?php
if (! defined('ABSPATH')) {
  exit;
}

class PluginsAlpha_Dashboard
{

  public static function render(): void
  {
    $items = PluginsAlpha_Remote::catalog();
    if (! is_array($items)) {
      $items = [];
    }
?>
    <div class="wrap pa-wrap">
      <h1 class="pa-title"><?php echo esc_html__('Alpha Suite — Dashboard', 'alpha-suite'); ?></h1>
      <p class="pa-subtitle"><?php echo esc_html__('Gerencie seus geradores e descubra novos módulos.', 'alpha-suite'); ?></p>

      <?php if (empty($items)) : ?>
        <div class="pa-panel">
          <p><?php echo esc_html__('Nenhum item encontrado no catálogo. Verifique suas configurações ou tente novamente mais tarde.', 'alpha-suite'); ?></p>
          <p>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=alpha-suite-settings')); ?>">
              <?php echo esc_html__('Abrir Configurações', 'alpha-suite'); ?>
            </a>
          </p>
        </div>
      <?php else : ?>
        <div class="pa-grid">
          <?php
          foreach ($items as $it) :
            if (! is_array($it)) {
              continue;
            }

            $slug   = isset($it['slug']) ? (string) $it['slug'] : '';
            $name   = isset($it['name']) ? (string) $it['name'] : 'Módulo';
            $desc   = isset($it['desc']) ? (string) $it['desc'] : '';
            $logo   = isset($it['logo']) ? (string) $it['logo'] : '';
            $price  = isset($it['price']) ? (float) $it['price'] : 0.0;
            $promo  = array_key_exists('promo_price', $it) ? $it['promo_price'] : null;
            $buy    = isset($it['buy_url']) ? (string) $it['buy_url'] : '';

            $status       = isset($it['status']) ? (string) $it['status'] : '';
            $status_label = isset($it['status_label']) ? (string) $it['status_label'] : '';
            $badge        = isset($it['badge']) ? (string) $it['badge'] : '';

            $admin_url  = isset($it['admin_url']) ? (string) $it['admin_url'] : '';
            $manage_url = isset($it['manage_url']) ? (string) $it['manage_url'] : '';
            $docs_url   = isset($it['docs_url']) ? (string) $it['docs_url'] : '';
            $learn_url  = isset($it['learn_more_url']) ? (string) $it['learn_more_url'] : '';
          ?>
            <div class="pa-card">
              <div class="pa-card-header">
                <?php if ($logo) : ?>
                  <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?>" class="pa-logo">
                <?php endif; ?>

                <div class="pa-card-title-wrap">
                  <h3><?php echo esc_html($name); ?></h3>

                  <?php if ($badge !== '') : ?>
                    <span class="pa-badge"><?php echo esc_html($badge); ?></span>
                  <?php endif; ?>

                  <?php if ($status_label !== '') : ?>
                    <span class="pa-status pa-status--<?php echo esc_attr($status); ?>">
                      <?php echo esc_html($status_label); ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($desc !== '') : ?>
                <p class="pa-desc"><?php echo esc_html($desc); ?></p>
              <?php endif; ?>

              <div class="pa-pricebox">
                <?php if ($promo !== null) : ?>
                  <span class="pa-price-promo">
                    R$ <?php echo esc_html(number_format((float) $promo, 2, ',', '.')); ?>
                  </span>
                  <span class="pa-price-old">
                    R$ <?php echo esc_html(number_format($price, 2, ',', '.')); ?>
                  </span>
                <?php else : ?>
                  <span class="pa-price">
                    R$ <?php echo esc_html(number_format($price, 2, ',', '.')); ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="pa-actions">
                <?php
                // Botão principal: prioridade = manage_url > admin_url > slug fallback
                $primary_href  = '';
                $primary_label = '';

                if ($manage_url) {
                  $primary_href  = $manage_url;
                  $primary_label = __('Abrir', 'alpha-suite');
                } elseif ($admin_url) {
                  $primary_href  = $admin_url;
                  $primary_label = __('Abrir', 'alpha-suite');
                } elseif ($slug === 'orion-posts') {
                  $primary_href  = admin_url('admin.php?page=alpha-suite-orion-posts');
                  $primary_label = __('Abrir Gerar Posts', 'alpha-suite');
                } elseif ($slug === 'alpha-stories') {
                  // fallback extra, caso alguém use slug antigo sem manage_url
                  $primary_href  = admin_url('edit.php?post_type=alpha_storys');
                  $primary_label = __('Abrir Web Stories', 'alpha-suite');
                } else {
                  // fallback genérico: dashboard
                  $primary_href  = admin_url('admin.php?page=alpha-suite-dashboard');
                  $primary_label = __('Abrir', 'alpha-suite');
                }

                if ($primary_href) {
                  echo '<a class="button button-primary" href="' . esc_url($primary_href) . '">'
                    . esc_html($primary_label) .
                    '</a>';
                }

                // Comprar / Assinar
                if ($buy) {
                  echo ' <a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url($buy) . '">'
                    . esc_html__('Comprar / Assinar', 'alpha-suite') .
                    '</a>';
                }

                // Documentação (se o remoto mandar)
                if ($docs_url) {
                  echo ' <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="' . esc_url($docs_url) . '">'
                    . esc_html__('Documentação', 'alpha-suite') .
                    '</a>';
                }

                // Saiba mais (landing / página externa)
                if ($learn_url) {
                  echo ' <a class="button button-link" target="_blank" rel="noopener noreferrer" href="' . esc_url($learn_url) . '">'
                    . esc_html__('Saiba mais', 'alpha-suite') .
                    '</a>';
                }

                /**
                 * Ação para módulos injetarem botões extras no card.
                 * Ex.: add_action('plugins_alpha/dashboard/card_actions', function($slug,$item){ ... });
                 */
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
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
