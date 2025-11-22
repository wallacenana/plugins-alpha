<?php
if (!defined('ABSPATH')) exit;


/** Registrar settings */
add_action('admin_init', function () {
  register_setting('alpha_storys_group', 'alpha_storys_options', [
    'type' => 'array',
    'sanitize_callback' => function ($in) {
      $out = is_array($in) ? $in : [];
      // Publisher
      $out['publisher_name']   = sanitize_text_field($out['publisher_name'] ?? '');
      $out['publisher_logo_id'] = (int) ($out['publisher_logo_id'] ?? 0);
      // Estilo/Playback
      $allowed_styles = ['clean', 'dark-left', 'card', 'split'];
      $out['default_style']    = in_array(($out['default_style'] ?? ''), $allowed_styles, true) ? $out['default_style'] : 'clean';
      $allowed_fonts  = ['system', 'inter', 'poppins', 'merriweather'];
      $out['default_font']     = in_array(($out['default_font'] ?? ''), $allowed_fonts, true) ? $out['default_font'] : 'inter';
      $out['accent_color']     = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', ($out['accent_color'] ?? '')) ? $out['accent_color'] : '#ffffff';
      $out['autoplay']         = !empty($out['autoplay']) ? 1 : 0;
      $out['duration']         = in_array(($out['duration'] ?? '7'), ['5', '7', '10', '12'], true) ? $out['duration'] : '7';
      // Analytics
      $mode = $out['ga_mode'] ?? 'auto';
      $out['ga_mode']          = in_array($mode, ['auto', 'manual', 'off'], true) ? $mode : 'auto';
      $id = trim((string) ($out['ga_manual_id'] ?? ''));
      $out['ga_manual_id']     = preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
      $out['ai_api_key']      = sanitize_text_field($out['ai_api_key'] ?? '');
      $out['ai_model']        = sanitize_text_field($out['ai_model'] ?? 'gpt-4o-mini');
      $out['ai_temperature']  = is_numeric($out['ai_temperature'] ?? null) ? (float)$out['ai_temperature'] : 0.4;
      $out['ai_brief_default'] = wp_kses_post($out['ai_brief_default'] ?? '');
      return $out;
    }
  ]);
});

/** Enqueue uploader só na tela de settings do plugin */
add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook !== 'toplevel_page_alpha-storys' && $hook !== 'alpha-storys_page_alpha-storys-settings') return;
  wp_enqueue_media();
  wp_enqueue_script('alpha-storyS-admin', ALPHA_STORYS_URL . 'assets/js/admin.js', ['jquery'], '1.0.1', true);
});

/** Settings screen */
function alpha_admin_settings_screen()
{
  if (!current_user_can('manage_options')) return;
  $o = alpha_storys_options();
  $logo_url = alpha_get_publisher_logo_url();
?>
  <div class="wrap">
    <h1>Alpha Stories · Configurações</h1>
    <form method="post" action="options.php">
      <?php settings_fields('alpha_storys_group'); ?>
      <?php $o = alpha_storys_options(); ?>

      <h2 class="title">Publisher</h2>
      <table class="form-table">
        <tr>
          <th scope="row"><label for="publisher_name">Nome do publisher</label></th>
          <td><input name="alpha_storys_options[publisher_name]" id="publisher_name" type="text" class="regular-text" value="<?php echo esc_attr($o['publisher_name'] ?? get_bloginfo('name')); ?>"></td>
        </tr>
        <tr>
          <th scope="row">Logo (96x96)</th>
          <td>
            <div style="margin-bottom:8px;">
              <img id="alpha_logo_preview" src="<?php echo esc_url($logo_url ?: ''); ?>" style="max-width:96px;height:auto;<?php echo $logo_url ? '' : 'display:none'; ?>">
            </div>
            <input type="hidden" id="publisher_logo_id" name="alpha_storys_options[publisher_logo_id]" value="<?php echo (int) ($o['publisher_logo_id'] ?? 0); ?>">
            <button type="button" class="button" id="alpha_logo_btn">Selecionar imagem</button>
            <button type="button" class="button" id="alpha_logo_clear" style="margin-left:8px;">Remover</button>
          </td>
        </tr>
      </table>

      <h2 class="title">Estilo & Playback (padrão)</h2>
      <table class="form-table">
        <tr>
          <th scope="row"><label for="default_style">Preset de estilo</label></th>
          <td>
            <select name="alpha_storys_options[default_style]" id="default_style">
              <?php
              $choices = ['clean' => 'Clean', 'dark-left' => 'Dark Left', 'card' => 'Card', 'split' => 'Split'];
              foreach ($choices as $val => $lab) {
                printf('<option value="%s"%s>%s</option>', esc_attr($val), selected(($o['default_style'] ?? 'clean'), $val, false), esc_html($lab));
              }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="default_font">Fonte</label></th>
          <td>
            <select name="alpha_storys_options[default_font]" id="default_font">
              <?php
              $fonts = ['system' => 'System UI', 'inter' => 'Inter', 'poppins' => 'Poppins', 'merriweather' => 'Merriweather', 'plusjakarta' => 'Plus Jakarta Sans',];
              foreach ($fonts as $val => $lab) {
                printf('<option value="%s"%s>%s</option>', esc_attr($val), selected(($o['default_font'] ?? 'plusjakarta'), $val, false), esc_html($lab));
              }
              ?>
            </select>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="accent_color">Cor de destaque</label></th>
          <td><input name="alpha_storys_options[accent_color]" id="accent_color" type="text" class="regular-text" value="<?php echo esc_attr($o['accent_color'] ?? '#ffffff'); ?>" placeholder="#ffffff"></td>
        </tr>
        <tr>
          <th scope="row">Autoplay</th>
          <td>
            <label><input type="checkbox" name="alpha_storys_options[autoplay]" value="1" <?php checked(!empty($o['autoplay'])); ?>> Ativar</label>
            &nbsp;&nbsp;
            <label for="duration">Tempo por página (s)</label>
            <select name="alpha_storys_options[duration]" id="duration">
              <?php foreach (['5', '7', '10', '12'] as $d) : ?>
                <option value="<?php echo esc_attr($d); ?>" <?php selected(($o['duration'] ?? '7'), $d); ?>>
                  <?php echo esc_html($d); ?>s
                </option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      </table>

      <h2 class="title">Analytics</h2>
      <table class="form-table">
        <tr>
          <th scope="row">Modo</th>
          <td>
            <label><input type="radio" name="alpha_storys_options[ga_mode]" value="auto" <?php checked(($o['ga_mode'] ?? 'auto'), 'auto'); ?>> Auto (tentar Site Kit)</label><br>
            <label><input type="radio" name="alpha_storys_options[ga_mode]" value="manual" <?php checked(($o['ga_mode'] ?? 'auto'), 'manual'); ?>> Manual</label><br>
            <label><input type="radio" name="alpha_storys_options[ga_mode]" value="off" <?php checked(($o['ga_mode'] ?? 'auto'), 'off'); ?>> Desativado</label>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="ga_manual_id">GA4 Measurement ID (Manual)</label></th>
          <td>
            <input name="alpha_storys_options[ga_manual_id]" id="ga_manual_id" type="text" class="regular-text" placeholder="G-XXXXXXXXXX" value="<?php echo esc_attr($o['ga_manual_id'] ?? ''); ?>">
            <p class="description">Usado apenas se “Manual” estiver selecionado.</p>
          </td>
        </tr>
      </table>

      <h2 class="title">ChatGPT / IA</h2>
      <table class="form-table">
        <tr>
          <th scope="row"><label for="ai_api_key">OpenAI API Key</label></th>
          <td>
            <input name="alpha_storys_options[ai_api_key]" id="ai_api_key" type="password" class="regular-text" value="<?php echo esc_attr($o['ai_api_key'] ?? ''); ?>" placeholder="sk-...">
            <p class="description">Você também pode definir a constante <code>ALPHA_OPENAI_KEY</code> no wp-config.php.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="ai_model">Modelo</label></th>
          <td>
            <input name="alpha_storys_options[ai_model]" id="ai_model" type="text" class="regular-text" value="<?php echo esc_attr($o['ai_model'] ?? 'gpt-4o-mini'); ?>">
            <p class="description">Ex.: gpt-4o-mini (rápido e econômico) — pode trocar depois.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="ai_temperature">Temperatura</label></th>
          <td>
            <input name="alpha_storys_options[ai_temperature]" id="ai_temperature" type="number" min="0" max="1" step="0.1" value="<?php echo esc_attr($o['ai_temperature'] ?? '0.4'); ?>">
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="ai_brief_default">Brief padrão</label></th>
          <td>
            <textarea name="alpha_storys_options[ai_brief_default]" id="ai_brief_default" class="large-text" rows="4" placeholder="tom, público, CTA padrão, nº ideal de slides etc."><?php echo esc_textarea($o['ai_brief_default'] ?? ''); ?></textarea>
          </td>
        </tr>
      </table>


      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

add_action('admin_init', function () {
  /* Salvar em alpha_storys_options[ai_prompt_template] */
  register_setting('alpha_storys_settings_group', 'alpha_storys_options', [
    'type'              => 'array',
    'sanitize_callback' => function ($in) {
      $out = is_array($in) ? $in : [];
      // aceita texto livre; se quiser, troque por sanitize_textarea_field
      $out['ai_prompt_template'] = isset($in['ai_prompt_template']) ? wp_kses_post($in['ai_prompt_template']) : '';
      return $out;
    },
    'default' => [],
  ]);

  add_settings_section('alpha_storys_main', '', '__return_false', 'alpha_storys_settings_page');

  add_settings_field(
    'ai_prompt_template',
    'Prompt do Gerador',
    function () {
      $o   = get_option('alpha_storys_options', []);
      $val = isset($o['ai_prompt_template']) ? (string)$o['ai_prompt_template'] : '';
      $placeholder = function_exists('alpha_storys_default_prompt_template')
        ? alpha_storys_default_prompt_template()
        :
        "Digite o seu prompt";
  ?>
    <textarea name="alpha_storys_options[ai_prompt_template]"
      rows="14"
      class="large-text code"
      placeholder="<?php echo esc_attr($placeholder); ?>"><?php echo esc_textarea($val); ?></textarea>
    <p class="description">Deixe em branco para usar o prompt padrão do plugin.</p>
  <?php
    },
    'alpha_storys_settings_page',
    'alpha_storys_main'
  );
});

function alpha_storys_default_prompt_template()
{
  $tpl  = '';
  $tpl .= "Nunca coloque nada sobre perguntas frequentes ou FAQ, como títulos sobre \"perguntas frequentes\".\n";
  $tpl .= "Transforme posts em Web stories AMP. Gere slides concisos (no máximo com 240 caracteres no corpo).\n";
  $tpl .= "O conteúdo completo deve ter pelo menos 30% de palavras de transição no padrão do Yoast, como mas, porém, entretanto, por isso, em resumo — coisas nesse sentido.\n";
  $tpl .= "No máximo 10 páginas; a primeira página deve ter um título mais chamativo, no padrão de título Discovery, que realmente desperte muita curiosidade (nada de informativo como \"introdução a x\").\n";
  $tpl .= "A página 5 deve ser um CTA para um grupo do WhatsApp com conteúdos do site, com link para o grupo no final.\n";
  $tpl .= "O primeiro item fica sem CTA, daí o segundo tem CTA e vai intercalando assim: slide sem CTA, slide com CTA. O CTA é um texto linkado no final do body; exemplo: saiba mais (onde \"saiba mais\" é um link como <a href=\"#\">Saiba mais</a>).\n";
  $tpl .= "Gere CTAs variados; a conclusão deve ter um CTA desses também. Todos os links (com exceção do CTA para o grupo) devem mandar para o post em questão.\n";
  $tpl .= "Gere um prompt para que seja gerada a imagem daquele conteúdo; o prompt da imagem deve ser realista, não contendo elementos voltados para ilustração;\n";
  $tpl .= "o prompt deve ser completo (mínimo de 200 caracteres).\n";
  $tpl .= "Repetindo: (no máximo 240 caracteres no corpo de cada slide).\n";
  $tpl .= "A conclusão é sempre um CTA para ver mais conteúdos desse tipo no site em questão.\n\n";

  $tpl .= "Sobre o título: leia todas as informações abaixo e comporte-se como um jornalista sênior e escritor especialista, com vocabulário rico.\n";
  $tpl .= "Seu estilo de escrita é intrigante e consegue surpreender os leitores com opiniões bem elaboradas EM TÍTULOS CURTOS.\n";
  $tpl .= "Especificidade: títulos que fornecem detalhes, números ou nomes específicos tendem a capturar atenção (ex.: \"15 melhores empresas\" ou \"Este homem de 32 anos estava ganhando US$ 17/hora\").\n";
  $tpl .= "Emoção e curiosidade: títulos que evocam resposta emocional ou curiosidade são bem-vindos.\n";
  $tpl .= "Rentabilidade e relevância: foque em tópicos com apelo amplo ou relevância atual (ex.: trabalho híbrido, IA, ChatGPT etc.).\n";
  $tpl .= "Autoridade: títulos que parecem confiáveis ou citam especialistas tendem a engajar.\n";
  $tpl .= "Clareza: não sacrifique clareza; o título deve dar ideia clara sobre o conteúdo.\n";
  $tpl .= "Problema e solução: títulos que destacam um problema e oferecem solução são envolventes.\n";
  $tpl .= "E NÃO ESQUEÇA O ASPECTO DE NOTÍCIA.\n\n";

  $tpl .= "Oportunidade e relevância: o conteúdo deve parecer atual, relevante ou ligado a algo em destaque.\n";
  $tpl .= "Engajamento: o leitor deve sentir que vai receber uma atualização importante ou uma nova perspectiva.\n";
  $tpl .= "Personalização: pense em como esse conteúdo poderia ser interessante para quem já se interessa por esse tema.\n";
  $tpl .= "Urgência: quando fizer sentido, use uma sensação leve de urgência, sem clickbait barato.\n";
  $tpl .= "Repetindo: corpo de cada slide com no máximo 240 caracteres.\n";

  return $tpl;
}

function alpha_storys_json_format_block()
{
  $s  = '';
  $s .= "Responda APENAS em JSON UTF-8 válido, sem markdown, sem comentários e sem texto fora do JSON.\n";
  $s .= "Siga exatamente esta estrutura:\n\n";

  $s .= "{\n";
  $s .= "  \"pages\": [\n";
  $s .= "    {\n";
  $s .= "      \"heading\": \"Título do slide...\",\n";
  $s .= "      \"body\": \"Texto do slide (até 240 caracteres)...\",\n";
  $s .= "      \"cta_text\": \"Texto do CTA ou string vazia\",\n";
  $s .= "      \"cta_url\": \"URL do CTA ou string vazia\",\n";
  $s .= "      \"prompt\": \"Prompt detalhado para gerar a imagem desse slide (mínimo ~200 caracteres)\"\n";
  $s .= "    }\n";
  $s .= "  ]\n";
  $s .= "}\n\n";

  $s .= "Não inclua campos extras, não inclua quebras de linha fora das strings, não escreva nada antes ou depois do JSON.\n";

  return $s;
}

/* Render do formulário simples */
function alpha_storys_settings_page_render()
{ ?>
  <div class="wrap">
    <h1>Alpha Stories</h1>
    <form method="post" action="options.php">
      <?php
      settings_fields('alpha_storys_settings_group');
      do_settings_sections('alpha_storys_settings_page');
      submit_button();
      ?>
    </form>
    <h2>Formato final do JSON (obrigatório no final do prompt):</h2>
    <p>{\"pages\":[{\"heading\":\"\",\"body\":\"\",\"cta_text\":\"\",\"cta_url\":\"\",\"prompt\":\"\"}]}</p>
  </div>
<?php }
