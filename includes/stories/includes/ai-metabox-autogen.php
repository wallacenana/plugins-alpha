<?php
if (!defined('ABSPATH')) exit;

// Metabox “IA / Geração”
add_action('add_meta_boxes', function () {
  add_meta_box(
    'alpha_ai_box',
    'Gerar Stories',
    'alpha_ai_autogen_cb',
    ['post', 'posts_orion'], // ajuste o post type se precisar
    'side',
    'high'
  );
});

function alpha_ai_autogen_cb($post)
{
  // nonce específico do AJAX “gerar agora”
  $ajax_nonce = wp_create_nonce('alpha_ai_generate_now');

  // checa licença do módulo Stories
  $chk = class_exists('PluginsAlpha_License')
    ? PluginsAlpha_License::check('stories')
    : ['ok' => true, 'message' => ''];

  $disabled = empty($chk['ok']);

  $title = empty($chk['ok'])
    ? ($chk['message'] ?: __('Ative o módulo Alpha Stories para gerar automaticamente.', 'plugins-alpha'))
    : '';
?>

  <p>
    <button
      type="button"
      class="button button-primary"
      id="alpha_ai_generate_now"
      <?php echo $disabled ? 'disabled="disabled"' : ''; ?>
      <?php
      if ($title) {
        printf('title="%s"', esc_attr($title));
      }
      ?>>
      Gerar story agora
    </button>
    <span
      id="alpha_ai_generate_now_status"
      data-license-ok="<?php echo !empty($chk['ok']) ? '1' : '0'; ?>"
      data-license-message="<?php echo esc_attr($chk['message'] ?? ''); ?>"
      style="margin-left:8px;">
      <?php
      // mensagem visível do lado do botão (opcional)
      if (empty($chk['ok']) && !empty($chk['message'])) {
        echo esc_html($chk['message']);
      }
      ?>
    </span>

  </p>


  <!-- SweetAlert2 (carrega se não existir) -->
  <script>
    (function() {
      if (!window.Swal && !document.getElementById('swal2-cdn')) {
        var s = document.createElement('script');
        s.id = 'swal2-cdn';
        s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
        s.defer = true;
        document.head.appendChild(s);
      }
    })();
  </script>

  <script>
    (function() {
      const btn = document.getElementById('alpha_ai_generate_now');
      const st = document.getElementById('alpha_ai_generate_now_status');
      if (!btn) return;
      if (btn.disabled) return;

      // lê o que o PHP colocou no span
      const LICENSE_VALID = st.dataset.licenseOk === '1';
      const LICENSE_MSG = st.dataset.licenseMessage || '';

      async function ensureSwal() {

        for (let i = 0; i < 30; i++) {
          if (window.Swal) return true;
          await new Promise(r => setTimeout(r, 100));
        }
        return false;
      }

      btn.addEventListener('click', async () => {
        btn.disabled = true;
        st.textContent = ''; // status por SweetAlert

        try {
          // 1) Licença
          if (!LICENSE_VALID) {
            if (await ensureSwal()) {
              Swal.fire({
                icon: 'info',
                title: 'Licença necessária',
                html: 'Para gerar o Web Story, ative sua licença em <strong>Alpha Stories → Licença</strong>.<br><br>' +
                  '<a class="button button-primary" href="' + LICENSE_URL + '">Abrir configurações de licença</a>',
                confirmButtonText: 'OK'
              });
            } else {
              alert('Para gerar o Web Story, ative sua licença em Alpha Stories → Licença.');
            }
            return;
          }

          // 2) Loading “Gerando…”
          if (await ensureSwal()) {
            Swal.fire({
              title: 'Gerando…',
              allowOutsideClick: false,
              allowEscapeKey: false,
              showConfirmButton: false,
              didOpen: () => {
                Swal.showLoading();
              }
            });
          } else {
            st.textContent = 'Gerando…';
          }

          // 3) Chamada que você já tinha
          const res = await fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
              action: 'alpha_ai_generate_now',
              source_id: '<?php echo (int) $post->ID; ?>',
              nonce: '<?php echo esc_js($ajax_nonce); ?>',
              preview: '0'
            })
          });

          const raw = await res.text();
          let json;
          try {
            json = JSON.parse(raw);
          } catch (e) {
            throw new Error('Resposta não-JSON: ' + raw.slice(0, 200));
          }

          if (!json.success) {
            const msg = (json.data && (json.data.message || JSON.stringify(json.data))) || 'Falha';
            throw new Error(msg);
          }

          // 4) Sucesso — SweetAlert
          const count = json.data.count || 0;
          const edit = json.data.edit_url ? '<a href="' + json.data.edit_url + '" target="_blank" rel="noreferrer">editar</a>' : '';
          const view = json.data.view_url ? '<a href="' + json.data.view_url + '" target="_blank" rel="noreferrer">ver</a>' : '';
          const sep = (edit && view) ? ' · ' : '';

          if (window.Swal) {
            Swal.fire({
              icon: 'success',
              title: 'Story gerado com sucesso',
              html: '(<strong>' + count + '</strong> páginas) — ' + edit + sep + view,
              confirmButtonText: 'OK'
            });
          } else {
            st.innerHTML = 'OK (' + count + ' páginas) — ' + edit + sep + view;
          }

        } catch (e) {
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: 'Ops…',
              text: e.message || 'Erro inesperado',
              confirmButtonText: 'OK'
            });
          } else {
            st.textContent = 'Erro: ' + (e.message || 'Erro inesperado');
          }
          console.error(e);
        } finally {
          btn.disabled = false;
        }
      });
    })();
  </script>
<?php
}


add_action('wp_ajax_alpha_ai_generate_now', 'alpha_ajax_ai_generate_now');

function alpha_ajax_ai_generate_now()
{
  // valida nonce do AJAX
  check_ajax_referer('alpha_ai_generate_now', 'nonce');
  $source_id = 0;

  // source_id
  if (isset($_POST['source_id'])) {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $source_id = absint(wp_unslash($_POST['source_id']));
  }

  if (! $source_id && isset($_POST['post_id'])) {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $source_id = absint(wp_unslash($_POST['post_id']));
  }
  $preview   = !empty($_POST['preview']);

  if (!$source_id || !get_post($source_id)) {
    wp_send_json_error(['message' => 'Post de origem inválido.'], 400);
  }

  // permissão: vai gravar storys irmã => precisa editar o post de origem
  if (!current_user_can('edit_post', $source_id)) {
    wp_send_json_error(['message' => 'Permissão negada.'], 403);
  }

  if (!function_exists('alpha_ai_get_api_key') || !alpha_ai_get_api_key()) {
    wp_send_json_error(['message' => 'Configure a OpenAI API Key nas Configurações.'], 400);
  }

  // Gera (a função cria/atualiza a irmã alpha_storys e retorna target_id)
  $res = alpha_ai_generate_for_post($source_id);
  if (is_wp_error($res)) {
    wp_send_json_error(['message' => $res->get_error_message()], 500);
  }

  $target_id = (int)($res['target_id'] ?? 0);
  if (!$target_id) {
    // fallback: tenta descobrir a irmã
    if (function_exists('alpha_storys_get_or_create_storys')) {
      $tmp = alpha_storys_get_or_create_storys($source_id);
      if (!is_wp_error($tmp)) $target_id = (int)$tmp;
    }
  }

  wp_send_json_success([
    'preview'  => (bool)$preview,
    'count'    => (int)($res['count'] ?? 0),
    'storysId'  => $target_id,
    'edit_url' => $target_id ? get_edit_post_link($target_id, 'raw') : '',
    'view_url' => $target_id ? get_permalink($target_id) : '',
    'message'  => 'Story gerada/atualizada com sucesso.',
  ]);
}
