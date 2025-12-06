/* global PGA_CFG, Swal */
(function ($) {
  const REST = PGA_CFG.rest;
  const NONCE = PGA_CFG.nonce;

  const PREF_KEY = 'pga_prefs_v1';
  const GROUPS_KEY = 'pga_gen_groups_v1';

  // Flag global pra saber se há geração em andamento
  window.PGA_IS_GENERATING = window.PGA_IS_GENERATING || false;

  // Registra o aviso ao tentar sair da página
  if (!window.PGA_BEFOREUNLOAD_BOUND) {
    window.PGA_BEFOREUNLOAD_BOUND = true;

    window.addEventListener('beforeunload', function (e) {
      if (!window.PGA_IS_GENERATING) return;

      const msg = 'O conteúdo ainda está sendo gerado. Sair da página pode interromper a criação. Deseja mesmo sair?';
      e.preventDefault();
      e.returnValue = msg; // compat com navegadores
      return msg;
    });
  }

  function loadPrefs() {
    try {
      const p = JSON.parse(localStorage.getItem(PREF_KEY) || '{}');

      // se ainda não existia nada salvo, não faz nada
      if (!p || typeof p !== 'object') return;

      const $box = $('#pga_gen_container .pga-gen-box').first();

      // ⬇️ compat com layout antigo (sem grupos)
      if (!$box.length) {
        if (p.locale) $('#pga_locale').val(p.locale);
        if (p.category_id) $('#pga_category').val(String(p.category_id));
        if (p.template_key) $('#pga_template_key').val(p.template_key);
        if (p.length) $('#pga_length').val(p.length);
        if (p.total) $('#pga_total').val(String(p.total));
        if (p.per_day) $('#pga_per_day').val(String(p.per_day));
        if (p.first_delay_hours) $('#pga_first_delay_hours').val(String(p.first_delay_hours));
        if (p.mode) $(`input[name="pga_mode"][value="${p.mode}"]`).prop('checked', true);
        return;
      }

      // ⬇️ novo: aplica no 1º grupo
      if (p.locale) $box.find('.pga_locale').val(p.locale);
      if (p.category_id) $box.find('.pga_category').val(String(p.category_id));
      if (p.template_key) $box.find('.pga_template_key').val(p.template_key);
      if (p.length) $box.find('.pga_length').val(p.length);
      if (p.total) $box.find('.pga_total').val(String(p.total));
      if (p.per_day) $box.find('.pga_per_day').val(String(p.per_day));
      if (p.first_delay_hours) $box.find('.pga_first_delay_hours').val(String(p.first_delay_hours));

      // interno: links
      if (p.internal_links && typeof p.internal_links === 'object') {
        const mode = p.internal_links.mode || 'none';
        const max = p.internal_links.max || 0;
        const ids = (p.internal_links.manual_ids || '')
          .split(',')
          .map(s => s.trim())
          .filter(Boolean);

        $box.find('.pga_link_mode').val(mode).trigger('change'); // dispara pra mostrar/ocultar campos
        $box.find('.pga_link_max').val(String(max));

        if (ids.length) {
          $box.find('.pga_link_manual').val(ids).trigger('change');
        }
      }

    } catch (e) {
      // silencioso
    }
  }


  // ===== Dropup "Concluídas" =====
  $(document).on('click', '#pga_done_toggle', function () {
    const $wrap = $('.pga-done-dropup');
    if (!$wrap.length) return;

    const isOpen = !$wrap.hasClass('is-open');
    $wrap.toggleClass('is-open', isOpen);

    $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    $('#pga_done_panel').attr('aria-hidden', isOpen ? 'false' : 'true');
  });

  // Fecha o dropup ao clicar fora
  $(document).on('click', function (e) {
    const $wrap = $('.pga-done-dropup');
    if (!$wrap.length || !$wrap.hasClass('is-open')) return;

    if ($wrap.is(e.target) || $wrap.has(e.target).length) return;

    $wrap.removeClass('is-open');
    $('#pga_done_toggle').attr('aria-expanded', 'false');
    $('#pga_done_panel').attr('aria-hidden', 'true');
  });

  // Toggle de campos de link interno por grupo
  $(document).on('change', '.pga_link_mode', function () {
    const $box = $(this).closest('.pga-gen-box');
    const mode = $(this).val() || 'none';

    const showExtras = mode !== 'none';
    const isManual = mode === 'manual';

    $box.find('.pga_link_extra').toggle(showExtras);
    $box.find('.pga_link_manual_wrapper').toggle(isManual);
  });

  function initLinkManualSelect2(context) {
    const $ctx = context ? $(context) : $(document);

    if (!$.fn.select2) return; // Select2 não carregou, não faz nada

    $ctx.find('.pga-link-manual-select').each(function () {
      const $sel = $(this);
      if ($sel.data('select2-initialized')) return;

      $sel.select2({
        width: '100%',
        placeholder: 'Selecione posts para link interno',
        allowClear: true
      });

      $sel.data('select2-initialized', true);
    });
  }


  // === Collapse toggle (qualquer grupo) ===
  $(document).on('click', '.pga-collapse-toggle', function () {
    const $box = $(this).closest('.pga-gen-box');
    $box.toggleClass('pga-collapse--open');

    const isOpen = $box.hasClass('pga-collapse--open');
    $(this).find('.dashicons')
      .toggleClass('dashicons-arrow-up-alt2', isOpen)
      .toggleClass('dashicons-arrow-down-alt2', !isOpen);
  });

  // ---------- Remover GRUPO (colapse inteiro) ----------
  $(document).off('click.pgaRemoveBox').on('click.pgaRemoveBox', '.pga_remove_box', async function () {
    const $box = $(this).closest('.pga-gen-box');
    const $container = $('#pga_gen_container');
    const totalBoxes = $container.find('.pga-gen-box').length;

    if (!$box.length) return;

    // se for o único grupo, em vez de remover, só limpamos os campos
    if (totalBoxes <= 1) {
      const ok = window.Swal
        ? (await Swal.fire({
          icon: 'warning',
          title: 'Limpar este grupo?',
          text: 'Este é o único grupo. Em vez de remover, vamos apenas limpar os campos.',
          showCancelButton: true,
        })).isConfirmed
        : confirm('Este é o único grupo. Limpar os campos deste grupo?');

      if (!ok) return;

      $box.find('.pga_keywords').val('');
      $box.find('.pga_total').val('6');
      $box.find('.pga_per_day').val('3');
      $box.find('.pga_template_key').val('discover_article');
      $box.find('.pga_locale').val('pt_BR');
      $box.find('.pga_length').val('short');
      $box.find('.pga_category').val('0');

      pgaUpdateBoxTitle($box);
      pgaSaveBoxesToLocal();
      return;
    }

    // confirmação para remover o grupo
    const ok = window.Swal
      ? (await Swal.fire({
        icon: 'warning',
        title: 'Remover grupo?',
        text: 'Este grupo de geração será removido (as keywords dentro dele não serão salvas).',
        showCancelButton: true,
        confirmButtonText: 'Remover',
        cancelButtonText: 'Cancelar',
      })).isConfirmed
      : confirm('Remover este grupo de geração?');

    if (!ok) return;

    // remove do DOM
    $box.remove();

    // reindexa data-gen e atualiza títulos
    const $boxes = $container.find('.pga-gen-box');
    $boxes.each(function (idx) {
      const $b = $(this);
      $b.attr('data-gen', idx + 1);
      pgaUpdateBoxTitle($b);
    });

    // garante que algum box fique ativo com os IDs "oficiais"
    const $first = $boxes.first();
    if ($first.length) {
      pgaActivateBox($first);
    }

    // salva snapshot no localStorage
    pgaSaveBoxesToLocal();
  });


  // === Atualiza título de UM box com base nos campos ===
  function pgaUpdateBoxTitle($box) {
    // Modelo
    const model = ($box.find('.pga_template_key option:selected').text() || '').trim() || 'Modelo';

    // Categoria (mais robusto pra wp_dropdown_categories)
    let cat = 'Sem categoria';
    const $catSel = $box.find('.pga_category').first();
    if ($catSel.length) {
      const el = $catSel[0];
      if (el.options && el.selectedIndex >= 0) {
        const txt = (el.options[el.selectedIndex].text || '').trim();
        if (txt) cat = txt;
      }
    }

    // Locale
    const loc = $box.find('.pga_locale').val() || 'pt_BR';

    // Quantidade total / por dia
    const total = $box.find('.pga_total').val() || '0';
    const perDay = $box.find('.pga_per_day').val() || '0';

    // Extensão
    const lengthLabel = ($box.find('.pga_length option:selected').text() || '').trim() || 'Extensão';

    // 🔹 título curto (visível)
    const visibleTitle = `${model} – ${cat}`;

    // 🔹 título completo (tooltip)
    const fullTitle = `${model} – ${cat} – ${loc} – ${total} posts – ${perDay}/dia – ${lengthLabel}`;

    $box
      .find('.pga-gen-title')
      .text(visibleTitle)
      .attr('title', fullTitle); // tooltip nativo do browser
  }


  // dispara update quando qualquer campo relevante muda
  $(document).on(
    'change keyup',
    '.pga_template_key, .pga_category, .pga_locale, .pga_total, .pga_per_day, .pga_length',
    function () {
      const $box = $(this).closest('.pga-gen-box');
      pgaSyncLinkOptionsForBox($box);
      pgaUpdateBoxTitle($box);
      pgaSaveBoxesToLocal();
    }
  );

  // serializa 1 box -> objeto JS
  function pgaSerializeBox($box) {
    // procura tanto pelo seletor com underscore quanto pelo com hífen (Select2)
    const $manualSel = $box.find('.pga_link_manual, .pga-link-manual-select');
    let manualVals = $manualSel.val() || []; // select[multiple] normal

    // se estiver usando Select2 e val() não tiver retornado, tenta extrair pelos dados do select2
    try {
      if ((!manualVals || manualVals.length === 0) && $manualSel.length && $manualSel.data('select2-initialized') && $.fn.select2) {
        const data = $manualSel.select2('data') || [];
        manualVals = data.map(d => (d && (d.id || d.text)) ? (d.id || d.text) : d);
      }
    } catch (e) { /* silencioso */ }

    return {
      keywords: $box.find('.pga_keywords').val() || '',
      locale: $box.find('.pga_locale').val() || 'pt_BR',
      template_key: $box.find('.pga_template_key').val() || 'discover_article',
      category: $box.find('.pga_category').val() || '0',
      total: parseInt($box.find('.pga_total').val() || '0', 10) || 0,
      per_day: parseInt($box.find('.pga_per_day').val() || '0', 10) || 0,
      first_delay: $box.find('.pga_first_delay_hours').val() || '',
      length: $box.find('.pga_length').val() || 'short',
      link_max: parseInt($box.find('.pga_link_max').val() || '2', 10) || 2,

      // 🔹 novo: salvar config de links internos por grupo
      internal_links: {
        mode: ($box.find('.pga_link_mode').val() || 'none'),
        max: parseInt($box.find('.pga_link_max').val() || '0', 10) || 0,
        manual_ids: Array.isArray(manualVals) ? manualVals.join(',') : String(manualVals || '')
      }
    };
  }

  // aplica objeto de config em 1 box
  function pgaApplyBoxConfig($box, cfg) {
    if (!cfg) return;

    $box.find('.pga_keywords').val(cfg.keywords || '');
    $box.find('.pga_locale').val(cfg.locale || 'pt_BR');
    $box.find('.pga_template_key').val(cfg.template_key || 'discover_article');
    $box.find('.pga_category').val(cfg.category || '0');
    $box.find('.pga_total').val(cfg.total || 0);
    $box.find('.pga_per_day').val(cfg.per_day || 0);
    if (cfg.first_delay) {
      $box.find('.pga_first_delay_hours').val(cfg.first_delay);
    }
    $box.find('.pga_length').val(cfg.length || 'short');

    // 🔹 links internos por grupo
    const il = cfg.internal_links || {};
    const mode = il.mode || 'none';
    const max = il.max || 0;
    const manualIds = (il.manual_ids || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean);

    // seta modo/max
    $box.find('.pga_link_mode').val(mode);
    $box.find('.pga_link_max').val(max ? String(max) : '');

    // mostra/esconde extras
    const showExtras = mode !== 'none';
    const isManual = mode === 'manual';
    $box.find('.pga_link_extra').toggle(showExtras);
    $box.find('.pga_link_manual_wrapper').toggle(isManual);

    // aplica valores no select manual (tenta tanto underscore quanto hífen)
    const $sel = $box.find('.pga_link_manual, .pga-link-manual-select');
    if ($sel.length) {
      $sel.val(manualIds).trigger('change');
      // se for select2, força atualização
      try { if ($sel.data('select2-initialized') && $.fn.select2) $sel.trigger('change.select2'); } catch (e) { /* silencioso */ }
    }

    pgaSyncLinkOptionsForBox($box);
    pgaUpdateBoxTitle($box);
  }
  // salva TODOS os grupos no localStorage
  function pgaSaveBoxesToLocal() {
    try {
      const all = [];
      $('#pga_gen_container .pga-gen-box').each(function () {
        all.push(pgaSerializeBox($(this)));
      });
      localStorage.setItem(GROUPS_KEY, JSON.stringify(all));
    } catch (e) { /* silencioso */ }
  }

  // recria os grupos a partir do localStorage
  function pgaLoadBoxesFromLocal() {
    let data = [];
    try {
      data = JSON.parse(localStorage.getItem(GROUPS_KEY) || '[]');
    } catch (e) {
      data = [];
    }

    const $container = $('#pga_gen_container');
    const $template = $container.find('.pga-gen-box').first();

    if (!Array.isArray(data) || !data.length || !$template.length) {
      // sem dados → só atualiza título do que já existe
      $container.find('.pga-gen-box').each(function () {
        pgaUpdateBoxTitle($(this));
      });
      return;
    }

    const $tplClone = $template.clone(true, true);
    $container.empty();

    data.forEach((cfg, idx) => {
      const n = idx + 1;
      const $box = $tplClone.clone(true, true);

      $box.attr('data-gen', n);

      // primeiro aberto, restantes fechados
      if (n === 1) {
        $box.addClass('pga-collapse--open');
        $box.find('.dashicons')
          .addClass('dashicons-arrow-up-alt2')
          .removeClass('dashicons-arrow-down-alt2');
      } else {
        $box.removeClass('pga-collapse--open');
        $box.find('.dashicons')
          .removeClass('dashicons-arrow-up-alt2')
          .addClass('dashicons-arrow-down-alt2');
      }

      // IDs só no primeiro grupo
      if (n > 1) {
        $box.find('#pga_keywords').removeAttr('id');
        $box.find('#pga_locale').removeAttr('id');
        $box.find('#pga_template_key').removeAttr('id');
        $box.find('#pga_category').removeAttr('id');
        $box.find('#pga_total').removeAttr('id');
        $box.find('#pga_per_day').removeAttr('id');
        $box.find('#pga_first_delay_hours').removeAttr('id');
        $box.find('#pga_length').removeAttr('id');
      }

      pgaApplyBoxConfig($box, cfg);
      $container.append($box);
    });
  }


  // === Botão "Adicionar grupo" ===
  $(document).on('click', '#pga_add_box', function () {
    const $container = $('#pga_gen_container');
    const $first = $container.find('.pga-gen-box').first();

    if (!$first.length) return;

    const count = $container.find('.pga-gen-box').length;
    const nextId = count + 1;

    // clona a caixa
    const $clone = $first.clone(true, true);

    $clone.attr('data-gen', nextId);
    $clone.removeClass('pga-collapse--open'); // começa fechada
    $clone.find('.pga-gen-title').text(`Geração ${nextId}`);

    // limpa valores de inputs/textarea
    $clone.find('.pga_keywords').val('');
    $clone.find('.pga_total').val('6');
    $clone.find('.pga_per_day').val('3');

    // reseta selects para o padrão (pode customizar)
    $clone.find('.pga_template_key').val('discover_article');
    $clone.find('.pga_locale').val('pt_BR');
    $clone.find('.pga_length').val('short');
    $clone.find('.pga_category').val('0'); // “sem categoria”

    // IMPORTANTE: remover IDs duplicados no clone
    $clone.find('#pga_keywords').removeAttr('id');
    $clone.find('#pga_locale').removeAttr('id');
    $clone.find('#pga_template_key').removeAttr('id');
    $clone.find('#pga_category').removeAttr('id');
    $clone.find('#pga_total').removeAttr('id');
    $clone.find('#pga_per_day').removeAttr('id');
    $clone.find('#pga_first_delay_hours').removeAttr('id');
    $clone.find('#pga_length').removeAttr('id');
    $clone.find('#pga_generate_keywords').removeAttr('id');
    $clone.find('#pga_save_this_colapse').removeAttr('id');

    // seta ícone para "fechado"
    $clone.find('.dashicons')
      .removeClass('dashicons-arrow-up-alt2')
      .addClass('dashicons-arrow-down-alt2');

    // reseta link interno
    $clone.find('.pga_link_mode').val('none');
    $clone.find('.pga_link_max').val('3');
    $clone.find('.pga_link_manual').val('');
    $clone.find('.pga_link_extra').hide();
    $clone.find('.pga_link_manual_wrapper').hide();


    $container.append($clone);

    pgaSyncLinkOptionsForBox($clone);
    initLinkManualSelect2($clone);
    pgaUpdateBoxTitle($clone);
    pgaSaveBoxesToLocal();
  });

  // Atualiza o título do primeiro grupo ao carregar
  $(function () {
    $('#pga_gen_container .pga-gen-box').each(function () {
      pgaUpdateBoxTitle($(this));
    });
  });

  // Marca um box como "ativo" movendo os IDs para ele
  function pgaActivateBox($box) {
    const map = [
      ['.pga_keywords', 'pga_keywords'],
      ['.pga_locale', 'pga_locale'],
      ['.pga_template_key', 'pga_template_key'],
      ['.pga_category', 'pga_category'],
      ['.pga_total', 'pga_total'],
      ['.pga_per_day', 'pga_per_day'],
      ['.pga_first_delay_hours', 'pga_first_delay_hours'],
      ['.pga_length', 'pga_length'],
    ];

    map.forEach(([cls, id]) => {
      $(`[id="${id}"]`).removeAttr('id');          // tira ID de onde estiver
      const $el = $box.find(cls).first();
      if ($el.length) $el.attr('id', id);          // põe ID nesse grupo
    });
  }


  // ------------------ utils ------------------
  async function fetchJSON(url, options = {}) {
    const { silent, ...fetchOpts } = options; // 👈 novo: flag silent

    const res = await fetch(url, fetchOpts);
    const text = await res.text();
    let data = null;

    try {
      data = JSON.parse(text);
    } catch (e) {
      if (!silent) {
        if (window.Swal) {
          await Swal.fire({
            icon: 'error',
            title: 'Resposta não-JSON',
            html: `<p><b>HTTP</b>: ${res.status}</p><pre style="white-space:pre-wrap;max-height:320px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:6px;">${text.replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]))}</pre>`
          });
        } else {
          alert('Erro: resposta não-JSON (' + res.status + ')');
        }
      }

      const err = new Error('Non-JSON ' + res.status);
      err.status = res.status;
      err.rawBody = text;
      throw err;
    }

    if (!res.ok) {
      const msg = (data && (data.message || data.code)) || `HTTP ${res.status}`;

      if (!silent) {
        if (window.Swal) {
          await Swal.fire({
            icon: 'error',
            title: 'Falha na chamada',
            text: String(msg)
          });
        } else {
          alert('Erro: ' + msg);
        }
      }

      const err = new Error(msg);
      err.status = res.status;
      err.body = data;
      throw err;
    }

    return data;
  }

  async function safeCloseSwal() {
    try {
      if (window.Swal && Swal.isVisible()) Swal.close();
    } catch (e) { }
  }

  function pgaMaxLinksForLength(len) {
    switch (len) {
      case 'short': return 3;
      case 'medium': return 6;
      case 'long': return 8;
      case 'extra-long': return 15;
      default: return 3;
    }
  }

  function pgaSyncLinkOptionsForBox($box) {
    const len = $box.find('.pga_length').val() || 'short';
    const maxAllowed = pgaMaxLinksForLength(len);

    const $sel = $box.find('.pga_link_max');
    if (!$sel.length) return;

    // desabilita tudo acima do limite
    $sel.find('option').each(function () {
      const val = parseInt($(this).val(), 10);
      const disabled = val > maxAllowed;
      $(this).prop('disabled', disabled).toggle(!disabled);
    });

    // se o valor atual está acima, força para o máximo permitido
    let cur = parseInt($sel.val() || '0', 10);
    if (!cur || cur > maxAllowed) {
      $sel.val(String(maxAllowed));
    }
  }

  function textareaToArray(txt) { return (txt || '').split(/\r?\n/).map(s => s.trim()).filter(Boolean); }
  function arrayToTextarea(arr) { return (arr || []).join('\n'); }
  function onSettingsPage() { return !!document.querySelector('form[action="options.php"]'); }
  function getQueryParam(name) { const u = new URL(window.location.href); return u.searchParams.get(name); }

  // ============================================================
  // =============== BLOCO: CONFIGURAÇÕES (settings) ============
  // ============================================================
  async function bootSettings() {
    // feedback rápido quando salvar no WP
    if (typeof getQueryParam === 'function' && getQueryParam('settings-updated') === '1') {
      if (window.Swal) {
        await Swal.fire({
          icon: 'success',
          title: 'Configurações salvas',
          timer: 1600,
          showConfirmButton: false
        });
      }
    }

    // helpers SweetAlert
    async function safeCloseSwal() {
      try { if (window.Swal && Swal.isVisible()) Swal.close(); } catch (e) { }
    }

    // se já existe o botão, não recria
    const keyEl = document.getElementById('pga_openai_key');
    if (!keyEl) return; // sem campo de chave, sem teste

    let testBtn = document.getElementById('pga_test_openai');
    if (!testBtn) {
      testBtn = document.createElement('button');
      testBtn.type = 'button';
      testBtn.id = 'pga_test_openai';
      testBtn.className = 'button';
      testBtn.textContent = 'Testar OpenAI';
      testBtn.style.marginLeft = '8px';
      keyEl.parentNode.insertBefore(testBtn, keyEl.nextSibling);
    }

    testBtn.addEventListener('click', async () => {
      const keyInput = document.getElementById('pga_openai_key');
      const modelInput = document.getElementById('pga_openai_model');
      const tempInput = document.getElementById('pga_openai_temp');
      const tokInput = document.getElementById('pga_openai_maxtok');

      const key = keyInput ? String(keyInput.value || '').trim() : '';
      const model = modelInput ? String(modelInput.value || '').trim() : 'gpt-4o-mini';
      const temp = tempInput ? parseFloat(tempInput.value || '0.6') : 0.6;
      const maxTok = tokInput ? parseInt(tokInput.value || '512', 10) : 512;

      if (!key) {
        await Swal.fire({
          icon: 'warning',
          title: 'Informe a chave',
          text: 'Digite a chave OpenAI antes de testar.',
          timer: 2200,
          showConfirmButton: false
        });
        return;
      }

      try {
        testBtn.disabled = true;

        await safeCloseSwal();
        Swal.fire({
          icon: 'info',
          title: 'Testando OpenAI…',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading()
        });

        const payload = {
          key: key,
          model: model || 'gpt-4o-mini',
          temperature: isNaN(temp) ? 0.6 : temp,
          max_tokens: Number.isFinite(maxTok) ? maxTok : 512
        };

        const res = await fetch(`${REST}/selftest`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE
          },
          body: JSON.stringify(payload)
        });

        let j = {};
        try { j = await res.json(); } catch (e) { }

        await safeCloseSwal();

        if (!res.ok) {
          const msg = j && (j.message || j.error || j.code) ? (j.message || j.error || j.code) : `HTTP ${res.status}`;
          await Swal.fire({ icon: 'error', title: 'Erro ao testar', text: msg });
          return;
        }

        await Swal.fire({
          icon: j.ok ? 'success' : 'warning',
          title: j.ok ? 'Conectado!' : 'Conexão incompleta',
          html: `
          <div style="text-align:left">
            <div><b>Modelo:</b> ${j.model || payload.model}</div>
            <div><b>Latência:</b> ${j.latencyMs ?? '?'} ms</div>
            <div><b>Retorno:</b> <code>${(j.sample || '').replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]))}</code></div>
          </div>
        `,
          timer: 2600,
          timerProgressBar: true,
          showConfirmButton: false
        });

      } catch (err) {
        await safeCloseSwal();
        await Swal.fire({
          icon: 'error',
          title: 'Falha no teste',
          text: err && err.message ? err.message : String(err || 'Erro desconhecido')
        });
      } finally {
        testBtn.disabled = false;
      }
    });
  }


  // ============================================================
  // ========== BLOCO: GERADOR (keywords/plan/generate) ==========
  // ============================================================
  async function bootGenerator() {
    const $kw = $('#pga_keywords');
    const $done = $('#pga_kw_done');
    if (!$kw.length) return;

    $(document).on('keyup', '.pga_keywords', function () {
      pgaSaveBoxesToLocal();
    });

    function buildSummaryHtml(okCount, failCount, editLinks, failedKeywords) {
      const okHtml = `<p style="margin:0 0 4px; font-size: large"><b>Sucesso:</b> ${okCount}</p>`;
      const failHeaderHtml = `<p style="margin:4px 0 0;"><b>Falhas:</b> ${failCount}</p>`;

      const failListHtml = (failedKeywords && failedKeywords.length)
        ? `<ul style="margin:4px 0 0 18px;padding:0;font-size:large;">${failedKeywords
          .map(f => `<li>${f.keyword} – ${f.error}</li>`)
          .join('')}</ul>`
        : '';

      const linksHtml = (editLinks && editLinks.length)
        ? `<p style="margin:8px 0 4px;"><b>Posts gerados</b></p><ul style="margin:0 0 0 18px;padding:0;font-size:large;">${editLinks.join('')}</ul>`
        : '';

      return `
        <div style="font-size:large; line-height:1.5;text-align:left;">
          ${okHtml}
          ${failHeaderHtml}
          ${failListHtml}
          ${linksHtml}
        </div>
      `;
    }

    // ---------- Preferências da UI (localStorage) ----------
    loadPrefs()

    function collectPrefs() {
      const $box = $('#pga_gen_container .pga-gen-box').first();
      if (!$box.length) {
        // fallback antigo se não houver box
        return {
          locale: $('#pga_locale').val(),
          length: $('#pga_length').val(),
          template_key: $('#pga_template_key').val(),
          source_url: '',
          category_id: parseInt($('#pga_category').val() || '0', 10),
          total: parseInt($('#pga_total').val() || '1', 10),
          per_day: parseInt($('#pga_per_day').val() || '1', 10),
          first_delay_hours: ($('#pga_first_delay_hours').val() || '').trim(),
          mode: $('input[name="pga_mode"]:checked').val() || 'multi',
        };
      }

      // pega tudo a partir do 1º grupo
      const manualVals = $box.find('.pga_link_manual').val() || []; // select[multiple]

      return {
        locale: $box.find('.pga_locale').val(),
        length: $box.find('.pga_length').val(),
        template_key: $box.find('.pga_template_key').val(),
        source_url: '',
        category_id: parseInt($box.find('.pga_category').val() || '0', 10),
        total: parseInt($box.find('.pga_total').val() || '1', 10),
        per_day: parseInt($box.find('.pga_per_day').val() || '1', 10),
        first_delay_hours: ($box.find('.pga_first_delay_hours').val() || '').trim(),
        mode: 'multi',

        internal_links: {
          mode: ($box.find('.pga_link_mode').val() || 'none'),
          max: parseInt($box.find('.pga_link_max').val() || '0', 10) || 0,
          manual_ids: manualVals.join(',')
        }
      };
    }

    function savePrefsToLocal() {
      try { localStorage.setItem(PREF_KEY, JSON.stringify(collectPrefs())); } catch (e) { }
    }
    loadPrefs();

    // ---------- Keywords: listar ----------
    async function refreshKeywords() {
      const j = await fetchJSON(`${REST}/keywords`, { headers: { 'X-WP-Nonce': NONCE } });
      $kw.val(arrayToTextarea(j.pending || []));

      $(document).on('keyup', '.pga_keywords', function () {
        pgaSaveBoxesToLocal();
      });

      renderDone(j.done || []);
    }

    function renderDone(list) {
      $done.empty();
      (list || []).forEach(k => $('<li/>').text(k).appendTo($done));
    }

    // ---------- Importar / Exportar / Limpar POR GRUPO ----------

    // input[type=file] único para todos os grupos
    let importTargetBox = null;
    let $file = $('#pga_kw_file');
    if (!$file.length) {
      $file = $('<input type="file" id="pga_kw_file" accept=".txt,text/plain" style="display:none">');
      $('body').append($file);
    }

    // Clique em "Importar .txt" dentro de um grupo
    $(document).off('click.pgaImport').on('click.pgaImport', '.pga_import_box', function () {
      importTargetBox = $(this).closest('.pga-gen-box');
      if (!importTargetBox.length) return;
      $file.trigger('click');
    });

    // Quando o arquivo é escolhido
    $file.off('change.pgaImport').on('change.pgaImport', function () {
      const f = this.files && this.files[0];
      if (!f || !importTargetBox) {
        this.value = '';
        importTargetBox = null;
        return;
      }

      const reader = new FileReader();
      reader.onload = async function (ev) {
        const text = String(ev.target.result || '');
        const $ta = importTargetBox.find('.pga_keywords');

        const cur = textareaToArray($ta.val());
        const neu = textareaToArray(text);
        const set = Array.from(new Set(cur.concat(neu)));

        $ta.val(set.join('\n'));
        $file.val('');
        importTargetBox = null;

        if (window.Swal) {
          await Swal.fire({
            icon: 'info',
            title: 'Importado',
            text: `${neu.length} linhas foram carregadas. Clique em "Salvar configurações" para persistir.`
          });
        }
        pgaSaveBoxesToLocal();
      };
      reader.readAsText(f, 'utf-8');
    });

    // Exportar .txt do grupo atual
    $(document).off('click.pgaExport').on('click.pgaExport', '.pga_export_box', function () {
      const $box = $(this).closest('.pga-gen-box');
      const txt = String($box.find('.pga_keywords').val() || '');
      const blob = new Blob([txt], { type: 'text/plain;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'keywords.txt';
      a.click();
      URL.revokeObjectURL(a.href);
    });

    // Limpar apenas o grupo atual
    $(document).off('click.pgaClearBox').on('click.pgaClearBox', '.pga_clear_box', async function () {
      const $box = $(this).closest('.pga-gen-box');
      const ok = window.Swal
        ? (await Swal.fire({
          icon: 'warning',
          title: 'Limpar keywords deste grupo?',
          showCancelButton: true
        })).isConfirmed
        : confirm('Limpar keywords deste grupo?');

      if (!ok) return;
      $box.find('.pga_keywords').val('');
    });

    // ---------- Salvar (botão global "Salvar" lá em cima) ----------
    $('#pga_save_keywords').off('click').on('click', async function () {
      const btn = this;
      btn.disabled = true;

      try {
        // continua salvando preferências do grupo "ativo" (primeiro, por padrão)
        savePrefsToLocal();
        pgaSaveBoxesToLocal();

        // junta keywords de TODOS os grupos para mandar pro backend
        const all = [];
        $('.pga_keywords').each(function () {
          all.push(...textareaToArray($(this).val()));
        });
        const pending_text = (all || []).join('\n');

        await safeCloseSwal();
        if (window.Swal) {
          Swal.fire({
            title: 'Salvando…',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
          });
        }

        const j = await fetchJSON(`${REST}/keywords`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body: JSON.stringify({ pending_text })
        });

        renderDone(j.done || []);

        await safeCloseSwal();
        if (window.Swal) {
          await Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Salvo',
            text: 'Keywords de todos os grupos foram salvas.',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            customClass: { popup: 'pga-toast-offset' }
          });
        }
      } catch (e) {
        await safeCloseSwal();
      } finally {
        btn.disabled = false;
      }
    });

    // "Salvar configurações" dentro do grupo apenas reaproveita o salvar global
    $(document).off('click.pgaSaveBox').on('click.pgaSaveBox', '.pga_save_box', function () {
      $('#pga_save_keywords').trigger('click');
      pgaSaveBoxesToLocal();
    });

    // ---------- Gerar SOMENTE deste grupo ----------
    $(document).off('click.pgaGenerateBox').on('click.pgaGenerateBox', '.pga_generate_box', function () {
      const $box = $(this).closest('.pga-gen-box');
      if (!$box.length) return;

      (async () => {
        pgaActivateBox($box);
        savePrefsToLocal();

        const res = await generateForActiveBox(); // com validações normais
        if (!res) return; // cancelado ou erro antes de gerar

        const html = buildSummaryHtml(res.okCount, res.failCount, res.editLinks, res.failedKeywords);

        await Swal.fire({
          icon: res.failCount ? 'warning' : 'success',
          title: 'Finalizado',
          html,
        });
      })();
    });


    $('#pga_kw_clear_done').off('click').on('click', async () => {
      const ok = window.Swal ? (await Swal.fire({ icon: 'warning', title: 'Limpar concluídas?', showCancelButton: true })).isConfirmed : confirm('Limpar concluídas?');
      if (!ok) return;
      await fetchJSON(`${REST}/keywords/clear`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        body: JSON.stringify({ who: 'done' })
      });
      await refreshKeywords();
    });

    // ---------- Planejar -> gerar sequencial ----------
    // ---------- GERAÇÃO: 1 BOX ATIVO ----------
    async function generateForActiveBox(options = {}) {
      const skipKwWarning = !!options.skipKeywordWarning; // usado no "modo global"

      const prefs = collectPrefs();
      const kwList = textareaToArray($('#pga_keywords').val());

      const transition = {
        strict: false,
        min_ratio: 0.30,
        words: ['por exemplo', 'em seguida', 'depois', 'antes', 'no entanto', 'portanto', 'assim', 'então']
      };

      // === 0) VALIDA LICENÇA + CHAVE API ANTES DE QUALQUER COISA ===
      try {
        const st = await fetchJSON(`${REST}/selftest`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': NONCE }
        });

        if (st && st.ok === false) {
          await Swal.fire({
            icon: 'error',
            title: 'Configuração necessária',
            text: st.message || 'Sua licença ou chave de API não está configurada. Verifique a tela de configurações do Plugins Alpha.'
          });
          return;
        }
      } catch (e) {
        let msg = 'Não foi possível validar a licença / chave de API.';
        let title = 'Erro de validação';

        if (e && typeof e === 'object') {
          if (e.code === 'pga_no_key') {
            title = 'Chave da API ausente';
            msg = e.message || 'Configure sua chave da API na tela de configurações do Plugins Alpha.';
          } else if (e.code === 'pga_lic_inactive') {
            title = 'Licença inativa';
            msg = e.message || 'Sua licença está inativa ou expirada. Verifique na tela de Licença do Plugins Alpha.';
          } else if (e.message) {
            msg = e.message;
          }
        }

        await Swal.fire({ icon: 'error', title, text: msg });
        return;
      }

      if (prefs.template_key !== "modelar") {
        // sem keywords
        if (prefs.mode === 'multi' && kwList.length === 0) {
          if (!skipKwWarning) {
            await Swal.fire({
              icon: 'warning',
              title: 'Sem palavras-chave',
              text: 'Insira ao menos 1 palavra-chave.'
            });
          }
          return;
        }

        // keywords < total
        if (prefs.mode === 'multi' && kwList.length < prefs.total) {
          if (!skipKwWarning) {
            const ok = (await Swal.fire({
              icon: 'question',
              title: 'Quantidade insuficiente',
              html: `<p style='font-size:16px;'>Você pediu <b>${prefs.total}</b> posts mas só tem <b>${kwList.length}</b> palavras. Gerar ${kwList.length}?</p>`,
              showCancelButton: true
            })).isConfirmed;
            if (!ok) return;
          }
          // no modo global, deixamos seguir e o PHP já corta pelos keywords disponíveis
        }
      }

      // === 2) PLANO ===
      let plan;
      try {
        plan = await fetchJSON(`${REST}/plan`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE
          },
          body: JSON.stringify({
            mode: prefs.mode,
            keywords: kwList.join('\n'),
            locale: prefs.locale,
            length: prefs.length,
            template_key: prefs.template_key,
            total: prefs.total,
            per_day: prefs.per_day,
            first_delay_hours: prefs.first_delay_hours,
            transition,
            category_id: prefs.category_id,
            internal_links: prefs.internal_links   // 🔹 chega no REST bonitinho
          })
        });
      } catch (e) {
        let msg = 'Ocorreu um erro ao montar o plano de geração.';
        let title = 'Erro ao gerar plano';

        if (e && typeof e === 'object') {
          if (e.code === 'pga_no_key') {
            title = 'Chave da API ausente';
            msg = e.message || 'Configure sua chave da API na tela de configurações do Plugins Alpha.';
          } else if (e.code === 'pga_lic_inactive') {
            title = 'Licença inativa';
            msg = e.message || 'Sua licença está inativa ou expirada. Verifique na tela de Licença do Plugins Alpha.';
          } else if (e.message) {
            msg = e.message;
          }
        }

        await Swal.fire({ icon: 'error', title, text: msg });
        return;
      }

      const jobs = plan.jobs || [];
      if (!jobs.length) {
        await Swal.fire({
          icon: 'info',
          title: 'Nada a gerar',
          text: 'Plano vazio.'
        });
        return;
      }

      // === 3) GERAÇÃO ===
      let okCount = 0, failCount = 0;
      let editLinks = [];
      const failedKeywords = [];

      function getJobKeyword(job) {
        if (job.keyword) return String(job.keyword).trim();
        if (Array.isArray(job.keywords) && job.keywords.length) {
          return String(job.keywords[0]).trim();
        }
        if (job.keywords) {
          return String(job.keywords).split(/\r?\n/)[0].trim();
        }
        return '';
      }

      function extractStatus(err) {
        if (!err) return 0;
        if (typeof err.status === 'number') return err.status;
        const m = String(err.message || '').match(/\b(\d{3})\b/);
        return m ? parseInt(m[1], 10) : 0;
      }

      async function runJobWithRetries(job, maxRetries = 3) {
        let attempt = 0;
        let lastError = null;

        while (attempt < maxRetries) {
          attempt++;
          try {
            const res = await generateExtraLongPost(job);
            return { ok: true, res, attempts: attempt };
          } catch (e) {
            lastError = e;
            const status = extractStatus(e);

            if (status && status < 500 && status !== 429) break;

            if (attempt < maxRetries) {
              await new Promise(r => setTimeout(r, attempt * 1500));
            }
          }
        }

        return { ok: false, error: lastError };
      }

      async function pgaFetchSectionWithRetry(params, maxRetries = 3) {
        let attempt = 0;

        while (attempt < maxRetries) {
          attempt++;

          try {
            const r = await fetch(`${REST}/orion/section`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': window.wpApiSettings.nonce,
              },
              body: JSON.stringify(params),
            });

            if (!r.ok) {
              console.warn(`SECTION ${params.section_id} → tentativa ${attempt} falhou: HTTP ${r.status}`);
              throw new Error('HTTP ' + r.status);
            }

            const json = await r.json();

            if (!json || json.error) {
              console.warn(`SECTION ${params.section_id} → JSON inválido na tentativa ${attempt}`, json);
              throw new Error('JSON inválido');
            }

            return { ok: true, data: json, attempt };
          } catch (err) {
            console.error(`Erro na section ${params.section_id} (tentativa ${attempt}):`, err);

            // última tentativa → falhou de vez
            if (attempt >= maxRetries) {
              return { ok: false, error: err };
            }

            // espera antes de tentar de novo
            await new Promise(res => setTimeout(res, 800 * attempt));
          }
        }
      }

      async function generateExtraLongPost(job) {
        const outlineRes = await fetchJSON(`${REST}/orion/outline`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body: JSON.stringify({
            keyword: job.keyword,
            keywords: [job.keyword],
            length: job.length,
            locale: job.locale,
            template: job.template_key,
            template_key: job.template_key,
            publish_time: job.publish_time,
            category_id: job.category_id,
            post_type: 'posts_orion',
          }),
          silent: true
        });

        if (!outlineRes || outlineRes.code) {
          throw new Error(outlineRes?.message || 'Erro ao gerar esboço');
        }

        const postId = outlineRes.post_id;
        const sections = outlineRes.sections || [];

        for (const section of sections) {
          const sid = section.id;

          const secRes = await pgaFetchSectionWithRetry({
            post_id: postId, section_id: sid
          });

          if (!secRes.ok) {
            errors.push(`Falha definitiva na seção ${secId} do post ${post_id}`);
            continue; // segue para próxima seção sem abortar
          }

        }

        const finRes = await fetchJSON(`${REST}/orion/finalize`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body: JSON.stringify({
            post_id: postId,
            keyword: job.keyword || '',
            internal_links: {
              mode: job.internal_links?.mode || 'none',
              max: job.internal_links?.max || 0,
              manual_ids: (job.internal_links?.manual_ids || []).join(',')
            }
          }),
          silent: true
        });

        if (finRes && finRes.code) {
          throw new Error(finRes.message || 'Erro ao finalizar post');
        }

        return finRes;
      }

      window.PGA_IS_GENERATING = true;

      try {
        await Swal.fire({
          title: 'Gerando posts…',
          html: `
            <div id="pga_loader" style="display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:6px">
              <svg width="22" height="22" viewBox="0 0 44 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <g fill="none" fill-rule="evenodd" stroke-width="4">
                  <circle cx="22" cy="22" r="18" stroke="#e5e7eb" />
                  <path d="M40 22c0-9.941-8.059-18-18-18" stroke="#3b82f6">
                    <animateTransform attributeName="transform" type="rotate" from="0 22 22" to="360 22 22" dur="0.9s" repeatCount="indefinite"/>
                  </path>
                </g>
              </svg>
              <div id="pga_step_label" style="font-weight:600">Aguarde…</div>
            </div>

            <div id="pga_prog" style="margin-top:8px">0 / ${jobs.length}</div>

            <div class="swal2-progress-steps" style="height:8px;background:#eee;border-radius:4px;overflow:hidden;margin-bottom:8px">
              <div id="pga_progbar" style="height:8px;width:0%;background:#3b82f6;transition:width .25s ease"></div>
            </div>

            <div id="pga_current" style="text-align:center;font-size:12px;color:#6b7280;min-height:16px"></div>
          `,
          showConfirmButton: false,
          showCancelButton: false,
          focusConfirm: false,
          allowOutsideClick: false,
          allowEscapeKey: false,
          didOpen: async () => {
            const $status = document.getElementById('pga_prog');
            const $bar = document.getElementById('pga_progbar');

            for (let i = 0; i < jobs.length; i++) {
              const j = jobs[i];
              const kw = getJobKeyword(j);

              try {
                const result = await runJobWithRetries(j, 3);

                if (!result.ok) {
                  failCount++;
                  failedKeywords.push({
                    keyword: kw || '(sem keyword)',
                    error: result.error && result.error.message ? result.error.message : 'Erro desconhecido'
                  });
                } else {
                  const r = result.res;

                  okCount++;

                  if (r.edit || r.post_id || r.view_link) {
                    let editUrl = '';

                    if (typeof r.edit === 'string' && r.edit.indexOf('http') === 0) {
                      editUrl = r.edit;
                    } else {
                      const postId = r.post_id || r.edit;
                      if (postId) {
                        const base = window.location.origin || '';
                        editUrl = `${base}/wp-admin/post.php?post=${postId}&action=edit`;
                      }
                    }

                    if (editUrl) {
                      const labelId = r.post_id || r.edit;
                      editLinks.push(
                        `<li><a target="_blank" rel="noopener" href="${editUrl}">Editar #${labelId}${kw ? ' – ' + kw : ''}</a></li>`
                      );
                    }
                  }

                  if (r.state) {
                    $('#pga_keywords').val((r.state.pending || []).join('\n'));
                    $('#pga_kw_done').empty().append(
                      (r.state.done || []).map(k => `<li>${k}</li>`).join('')
                    );
                  } else if (kw) {
                    const lines = $('#pga_keywords')
                      .val()
                      .split('\n')
                      .map(l => l.trim())
                      .filter(l => l && l !== kw);

                    $('#pga_keywords').val(lines.join('\n'));
                    $('#pga_kw_done').append(`<li>${kw}</li>`);
                  }
                }
              } catch (e) {
                failCount++;
                failedKeywords.push({
                  keyword: kw || '(sem keyword)',
                  error: e && e.message ? e.message : 'Erro desconhecido'
                });
              }

              const done = i + 1;
              const pct = Math.round((done / jobs.length) * 100);

              if ($status) $status.textContent = `${done} / ${jobs.length}`;
              if ($bar) $bar.style.width = pct + '%';

              await new Promise(r => setTimeout(r, 150));
            }

            Swal.close();
          }
        });
      } finally {
        window.PGA_IS_GENERATING = false;
      }

      // 👉 agora quem chama decide como mostrar o resumo
      return { okCount, failCount, editLinks, failedKeywords };
    };



    // ---------- Planejar & Gerar (TODOS os grupos) ----------
    // ---------- Planejar & Gerar (TODOS os grupos, 1 clique, 1 resumo) ----------
    $('#pga_plan').off('click').on('click', async () => {
      const $boxes = $('#pga_gen_container .pga-gen-box');
      if (!$boxes.length) return;

      const groups = [];
      let totalRequested = 0;
      let totalKeywords = 0;

      $boxes.each(function () {
        const $box = $(this);
        const kwsArr = textareaToArray($box.find('.pga_keywords').val());
        const templateKey = $box.find('.pga_template_key').val() || 'discover_article';
        const total = parseInt($box.find('.pga_total').val() || '0', 10) || 0;
        const titleText = $box.find('.pga-gen-title').text().trim() || 'Grupo';

        const g = {
          $box,
          templateKey,
          kwCount: kwsArr.length,
          total,
          titleText,
        };
        groups.push(g);

        // só conta keywords x total pros templates normais (não modelar)
        if (templateKey !== 'modelar') {
          totalRequested += Math.max(0, total);
          totalKeywords += kwsArr.length;
        }
      });

      // grupos normais sem keywords
      const groupsNoKw = groups.filter(g => g.templateKey !== 'modelar' && g.kwCount === 0);

      if (groupsNoKw.length) {
        const list = groupsNoKw.map(g => `<li>${g.titleText}</li>`).join('');
        const r = await Swal.fire({
          icon: 'warning',
          title: 'Grupos sem keywords',
          html: `
            <p>Os grupos abaixo não possuem keywords e serão ignorados:</p>
            <ul style="margin:4px 0 0 18px;padding:0;font-size:14px;">${list}</ul>
            <p style="margin-top:8px;">Deseja continuar com os demais grupos?</p>
          `,
          showCancelButton: true,
          confirmButtonText: 'Continuar',
          cancelButtonText: 'Cancelar',
        });
        if (!r.isConfirmed) return;
      }

      // checa insuficiência global (total de posts pedidos x total de keywords)
      if (totalRequested > 0 && totalKeywords < totalRequested) {
        const r = await Swal.fire({
          icon: 'question',
          title: 'Keywords insuficientes',
          html: `
            <p style='font-size:16px;'>Você pediu <b>${totalRequested}</b> posts no total, mas só tem <b>${totalKeywords}</b> palavras-chave somando todos os grupos.</p>
            <p style='font-size:16px;'>Deseja gerar mesmo assim, usando as keywords disponíveis?</p>
          `,
          showCancelButton: true,
          confirmButtonText: 'Sim, gerar',
          cancelButtonText: 'Cancelar',
        });
        if (!r.isConfirmed) return;
      }

      // agora roda todos os grupos em sequência, SEM avisos por grupo
      let totalOk = 0;
      let totalFail = 0;
      let allFailedKeywords = [];
      let allEditLinks = [];

      for (const g of groups) {
        // ignora grupos normais sem keywords
        if (g.templateKey !== 'modelar' && g.kwCount === 0) continue;

        pgaActivateBox(g.$box);
        savePrefsToLocal();

        const res = await generateForActiveBox({ skipKeywordWarning: true });
        if (!res) continue; // plano vazio, erro de licença, etc.

        totalOk += res.okCount;
        totalFail += res.failCount;
        allFailedKeywords = allFailedKeywords.concat(res.failedKeywords || []);
        allEditLinks = allEditLinks.concat(res.editLinks || []);
      }

      const html = buildSummaryHtml(totalOk, totalFail, allEditLinks, allFailedKeywords);

      await Swal.fire({
        icon: totalFail ? 'warning' : 'success',
        title: 'Geração concluída',
        html,
      });
    });



    try { await refreshKeywords(); } catch (e) { }
    pgaLoadBoxesFromLocal();
  }

  // boot
  $(async function () {
    if (onSettingsPage()) await bootSettings();
    await bootGenerator();
    initLinkManualSelect2();
    loadPrefs();
  });

  // Helpers SweetAlert2
  async function swalLoading(title = 'Processando…') {
    return Swal.fire({
      title,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading()
    });
  }
  async function swalSuccess(html, title = 'Tudo certo!') {
    return Swal.fire({ icon: 'success', title, html, confirmButtonText: 'Ok' });
  }
  async function swalError(html, title = 'Ops…') {
    return Swal.fire({ icon: 'error', title, html, confirmButtonText: 'Entendi' });
  }
  async function swalWarn(html, title = 'Atenção') {
    return Swal.fire({ icon: 'warning', title, html, confirmButtonText: 'Ok' });
  }

  // Fetch JSON com tratamento de erro padronizado
  async function restJson(path, opts = {}) {
    const res = await fetch(`${PGA_CFG.rest}${path}`, {
      method: 'GET',
      headers: { 'X-WP-Nonce': PGA_CFG.nonce, 'Content-Type': 'application/json' },
      ...opts
    });
    let body = null;
    try { body = await res.json(); } catch (_) { body = null; }

    if (!res.ok) {
      const msg = body && (body.message || body.error || body.code) ? (body.message || body.error || body.code) : `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return body;
  }


  // Atualiza UI de status/mensagem da licença
  function updateLicenseUI(lic) {
    if (!lic) return;
    const st = (lic.status || 'INACTIVE').toString();
    const msg = (lic.message || '').toString();
    $('#pga_license_status').text(st);
    $('#pga_license_msg').text(msg);
  }

  // Botão: ATIVAR
  $(document).on('click', '#pga_license_activate', async function () {
    const $btn = $(this);
    const $email = $('input[name="pga_license[email]"]');
    const $pid = $('input[name="pga_license[purchase_id]"]');
    const email = ($email.val() || '').trim();
    const pid = ($pid.val() || '').trim();

    if (!email || !pid) {
      await swalWarn('Preencha <b>e-mail</b> e <b>ID da compra</b> antes de ativar.');
      return;
    }

    try {
      $btn.prop('disabled', true);
      await swalLoading('Ativando licença…');

      const data = await fetchJSON(`${REST}/license/activate`, {
        method: 'POST',
        body: JSON.stringify({ email, purchase_id: pid })
      });

      Swal.close();

      updateLicenseUI(data.license);

      if (data.ok) {
        const html = `
          <div style="text-align:left">
            <div><b>Status:</b> ${data.license?.status || '-'}</div>
            <div><b>E-mail:</b> ${data.license?.email || '-'}</div>
            <div><b>Compra:</b> ${data.license?.purchase_id || '-'}</div>
            ${data.license?.message ? `<div style="margin-top:6px">${data.license.message}</div>` : ''}
          </div>
        `;
        await swalSuccess(html, 'Licença ativada!');
      } else {
        const html = `
          <div style="text-align:left">
            <div><b>Status:</b> ${data.license?.status || 'INACTIVE'}</div>
            ${data.license?.message ? `<div style="margin-top:6px">${data.license.message}</div>` : '<div style="margin-top:6px">Não foi possível ativar. Verifique os dados.</div>'}
          </div>
        `;
        await swalWarn(html, 'Licença não ativa');
      }
    } catch (err) {
      Swal.close();
      await swalError(`${(err && err.message) ? err.message : 'Erro desconhecido.'}<br><small>Tente novamente em instantes.</small>`);
    } finally {

      $btn.prop('disabled', false);
    }
  });

  // (Opcional) Checar status ao abrir a página de Configurações
  $(document).ready(async function () {
    const $status = $('#pga_license_status');
    if (!$status.length) return;
    try {
      const data = await fetchJSON(`${REST}/license/status`, { method: 'GET' });
      updateLicenseUI(data.license);
    } catch (_) {
      /* silencioso */
    }
  });


})(jQuery);

