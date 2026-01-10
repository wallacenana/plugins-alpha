/* global PGA_CFG, Swal, wp */
const i18n = (window.wp && wp.i18n) ? wp.i18n : null;

const __ = i18n ? i18n.__ : (s) => s;
const _x = i18n ? i18n._x : (s) => s;
const _n = i18n ? i18n._n : (s) => s;
const sprintf = (window.wp && wp.i18n && wp.i18n.sprintf)
  ? wp.i18n.sprintf
  : (fmt, ...args) => String(fmt).replace(/%s/g, () => String(args.shift() ?? ''));

(function ($) {
  const REST = PGA_CFG.rest;
  const NONCE = PGA_CFG.nonce;

  const PREF_KEY = 'pga_prefs_v1';
  const pillarId = window.PGA_PILLAR_ID || 0;
  const GROUPS_KEY = `pga_gen_groups_v1_${pillarId}`;
  let PGA_LOADING_GROUPS = false;

  // Flag global pra saber se há geração em andamento
  window.PGA_IS_GENERATING = window.PGA_IS_GENERATING || false;

  if (!window.PGA_BEFOREUNLOAD_BOUND) {
    window.PGA_BEFOREUNLOAD_BOUND = true;

    window.addEventListener('beforeunload', function (e) {
      if (!window.PGA_IS_GENERATING) return;

      const msg = __('O conteúdo ainda está sendo gerado. Sair da página pode interromper a criação. Deseja mesmo sair?', 'plugins-alpha');
      e.preventDefault();
      e.returnValue = msg;
      return msg;
    });
  }

  function pgaToast(icon, title, timer = 1800) {
    if (!window.Swal) return;
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon || 'success',
      title: title || '',
      timer,
      timerProgressBar: true,
      showConfirmButton: false,
      customClass: { popup: 'pga-toast-offset' }
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
        placeholder: __('Selecione posts para link interno', 'plugins-alpha'),
        allowClear: true
      });

      $sel.data('select2-initialized', true);
    });
  }

  // === Collapse toggle (qualquer grupo) ===
  $(document).on('click', '.pga-collapse-toggle', function () {
    const $box = $(this).closest('.pga-gen-box');
    $box.toggleClass('pga-collapse--open');
  });

  // ---------- Remover GRUPO (colapse inteiro) ----------
  $(document).off('click.pgaRemoveBox').on('click.pgaRemoveBox', '.pga_remove_box', async function () {
    const $box = $(this).closest('.pga-gen-box');
    const $container = $('#pga_gen_container');
    const totalBoxes = $container.find('.pga-gen-box').length;

    if (!$box.length) return;

    // se for o único grupo, em vez de remover, só limpamos os campos
    if (totalBoxes <= 1) {
      const ok = await pgaConfirm({
        icon: 'warning',
        title: __('Limpar este grupo?', 'plugins-alpha'),
        text: __('Este é o único grupo. Em vez de remover, vamos apenas limpar os campos.', 'plugins-alpha'),
        confirmButtonText: __('Limpar', 'plugins-alpha'),
        cancelButtonText: __('Cancelar', 'plugins-alpha')
      });

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
    const ok = await pgaConfirm({
      icon: 'warning',
      title: __('Remover grupo?', 'plugins-alpha'),
      text: __('Este grupo de geração será removido (as keywords dentro dele não serão salvas).', 'plugins-alpha'),
      confirmButtonText: __('Remover', 'plugins-alpha'),
      cancelButtonText: __('Cancelar', 'plugins-alpha')
    });

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
    const model = ($box.find('.pga_template_key option:selected').text() || '').trim() || __('Modelo', 'plugins-alpha');

    // Categoria (mais robusto pra wp_dropdown_categories)
    let cat = __('Sem categoria', 'plugins-alpha');
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
    const lengthLabel = ($box.find('.pga_length option:selected').text() || '').trim() || __('Extensão', 'plugins-alpha');

    // 🔹 título curto (visível)
    const visibleTitle = `<span class="pga-model">${model}</span> <span class="pga-category-colapse">${cat}</span>`;

    // 🔹 título completo (tooltip)
    const postsLabel = sprintf(_n('%s post', '%s posts', Number(total), 'plugins-alpha'), total);

    const fullTitle = sprintf(
      __('%1$s – %2$s – %3$s – %4$s – %5$s/dia – %6$s', 'plugins-alpha'),
      model,
      cat,
      loc,
      postsLabel,
      perDay,
      lengthLabel
    );

    $box
      .find('.pga-gen-title')
      .html(visibleTitle)
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

  function pgaGetTabId() {
    try {
      const u = new URL(window.location.href);
      return (u.searchParams.get('tab') || '').trim();
    } catch (e) {
      return '';
    }
  }

  // Se não tiver ?tab=, usa "default" (não quebra nada e mantém compatível)
  function pgaGroupsStorageKey() {
    const tabId = pgaGetTabId() || 'default';
    return `${GROUPS_KEY}_${tabId}`;
  }


  // salva TODOS os grupos no localStorage
  function pgaSaveBoxesToLocal() {
    if (PGA_LOADING_GROUPS) return; // <<<<<< essencial

    try {
      const all = [];
      $('#pga_gen_container .pga-gen-box').each(function () {
        all.push(pgaSerializeBox($(this)));
      });

      // se por algum motivo não achou boxes, NÃO grava [] (evita matar legacy/tab)
      if (!all.length) return;

      localStorage.setItem(pgaGroupsStorageKey(), JSON.stringify(all));
      window.PGA_IS_GENERATING = false;
    } catch (e) {
      // não silencie agora enquanto testa:
      console.warn('[PGA] save failed', e);
    }
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
    $clone.find('.pga-gen-title').text(
      sprintf(__('Geração %d', 'plugins-alpha'), nextId)
    );

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
    window.PGA_IS_GENERATING = true;
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
    // opções: method, headers, body, silent
    const { silent, method = 'GET', headers = {}, body, ...rest } = options || {};

    const res = await fetch(url, { method, headers, body, ...rest });

    const text = await res.text();
    let data = null;

    try {
      data = text ? JSON.parse(text) : null;
    } catch (e) {
      if (!silent) {
        if (window.Swal) {
          const safe = String(text || '').replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]));
          await Swal.fire({
            icon: 'error',
            title: __('Resposta não-JSON', 'plugins-alpha'),
            html: sprintf(
              __('<p><b>HTTP</b>: %d</p><pre style="white-space:pre-wrap;max-height:320px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:6px;">%s</pre>', 'plugins-alpha'),
              res.status,
              safe
            )
          });
        } else {
          alert(sprintf(__('Erro: resposta não-JSON (%d)', 'plugins-alpha'), res.status));
        }
      }

      const err = new Error(sprintf(__('Non-JSON %d', 'plugins-alpha'), res.status));
      err.status = res.status;
      err.rawBody = text;
      throw err;
    }

    if (!res.ok) {
      const msg = (data && (data.message || data.code)) || sprintf(__('HTTP %d', 'plugins-alpha'), res.status);
      if (!silent) {
        if (window.Swal) {
          await Swal.fire({ icon: 'error', title: __('Falha na chamada', 'plugins-alpha'), text: String(msg) });
        } else {
          alert(sprintf(__('Erro: %s', 'plugins-alpha'), String(msg)));
        }
      }
      const err = new Error(String(msg));
      err.status = res.status;
      err.body = data;
      throw err;
    }

    return data;
  }

  function pgaMaxLinksForLength(len) {
    switch (len) {
      case 'short': return 5;
      case 'medium': return 8;
      case 'long': return 10;
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
          icon: __('success', 'plugins-alpha'),
          title: __('Configurações salvas', 'plugins-alpha'),
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
      testBtn.textContent = __('Testar OpenAI', 'plugins-alpha');
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
          title: __('Informe a chave', 'plugins-alpha'),
          text: __('Digite a chave OpenAI antes de testar.', 'plugins-alpha'),
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
          title: __('Testando OpenAI…', 'plugins-alpha'),
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
          await Swal.fire({ icon: 'error', title: __('Erro ao testar', 'plugins-alpha'), text: msg });
          return;
        }

        const safeSample = String(j.sample || '').replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]));

        await Swal.fire({
          icon: j.ok ? 'success' : 'warning',
          title: j.ok ? __('Conectado!', 'plugins-alpha') : __('Conexão incompleta', 'plugins-alpha'),
          html: sprintf(
            __(
              '<div style="text-align:left">' +
              '<div><b>%s</b> %s</div>' +
              '<div><b>%s</b> %s ms</div>' +
              '<div><b>%s</b> <code>%s</code></div>' +
              '</div>',
              'plugins-alpha'
            ),
            __('Modelo:', 'plugins-alpha'),
            (j.model || payload.model || ''),
            __('Latência:', 'plugins-alpha'),
            (j.latencyMs ?? '?'),
            __('Retorno:', 'plugins-alpha'),
            safeSample
          ),
          timer: 2600,
          timerProgressBar: true,
          showConfirmButton: false
        });

      } catch (err) {
        await safeCloseSwal();
        await Swal.fire({
          icon: 'error',
          title: __('Falha no teste', 'plugins-alpha'),
          text: err && err.message ? err.message : String(err || __('Erro desconhecido', 'plugins-alpha'))
        });
      } finally {
        testBtn.disabled = false;
      }
    });
  }


  function pgaMarkKeywordDoneInBox(rawKw, boxEl) {
    const kw = (rawKw || '').trim();
    if (!kw || !boxEl) return false;

    const $box = $(boxEl);
    const $ta = $box.find('.pga_keywords').first();
    if (!$ta.length) return false;

    const orig = $ta.val() || '';
    if (!orig) return false;

    const lines = orig
      .split('\n')
      .map(l => l.trim())
      .filter(l => l.length > 0);

    // match case-insensitive
    const idx = lines.findIndex(l => l.localeCompare(kw, undefined, { sensitivity: 'accent' }) === 0);
    if (idx === -1) return false;

    lines.splice(idx, 1);
    $ta.val(lines.join('\n'));

    // UI done
    const $done = $('#pga_kw_done');
    if ($done.length) {
      const li = document.createElement('li');
      li.textContent = kw;
      $done.append(li);
    }

    pgaSaveBoxesToLocal();
    return true;
  }

  // ✅ monta fila global por PESO (perDay) sem tocar no DOM
  // exemplo (total=9, perDay: [2,1]) => 1,1,2,1,1,2...
  function pgaBuildPlannedKeywordsByPerDay(groups, totalGlobal) {
    const out = [];
    const idx = groups.map(() => 0);

    while (out.length < totalGlobal) {
      let added = 0;

      for (let gi = 0; gi < groups.length && out.length < totalGlobal; gi++) {
        const g = groups[gi];

        const w = Math.max(0, parseInt(g.perDay || 0, 10) || 0);
        if (!w) continue;

        for (let k = 0; k < w && out.length < totalGlobal; k++) {
          const pos = idx[gi];
          const kw = String((g.keywords[pos] || '')).trim();

          // ✅ se tiver linha vazia no meio, pula sem travar o grupo
          if (!kw) {
            idx[gi] = pos + 1;
            continue;
          }

          out.push({
            kw,
            boxEl: g.boxEl,     // ✅ origem
            cfg: g.cfg,         // ✅ config do colapse (snapshot)
          });

          idx[gi] = pos + 1;
          added++;
        }
      }

      if (added === 0) break;
    }

    return out;
  }


  function pgaGlobalIsOn() {
    return !!document.getElementById('pga_plan_global_toggle')?.checked;
  }

  function pgaGlobalToggleUI() {
    const on = pgaGlobalIsOn();
    $('#pga_plan_custom_top').css('display', on ? 'flex' : 'none');

    // quando GLOBAL ligado: esconde total/per_day/inicio dentro dos geradores
    $('#pga_gen_container .pga-field-total').css('display', on ? 'none' : '');
    $('#pga_gen_container .pga-field-program').css('display', on ? 'none' : '');

    // mostra checkbox "incluir na geração" por gerador (se você quiser usar)
    $('#pga_gen_container .pga_custom_wrap').css('display', on ? '' : 'none');
  }

  $(document).off('change.pgaGlobal').on('change.pgaGlobal', '#pga_plan_global_toggle', function () {
    pgaGlobalToggleUI();
  });

  // init
  pgaGlobalToggleUI();

  // =========================
  // STORAGE OFICIAL DE GRUPOS (ÚNICO) + MIGRAÇÃO
  // =========================

  // legado antigo (você já tem acima, mas deixo aqui pra ficar claro)
  const LEGACY_PILLAR_GROUPS_KEY = GROUPS_KEY; // pga_gen_groups_v1_${pillarId}

  // legado "por tab" antigo (aquele que usava pgaGroupsStorageKey)
  function legacyTabGroupsKey_fromOldSystem() {
    const tabId = (getTabIdFromUrl() || localStorage.getItem(KEY_ACTIVE_TAB) || 'default');
    return `${LEGACY_PILLAR_GROUPS_KEY}_${tabId}`;
  }

  function safeGetLS(key) {
    try { return localStorage.getItem(key) || ''; } catch (e) { return ''; }
  }
  function safeSetLS(key, val) {
    try { localStorage.setItem(key, val); return true; } catch (e) { return false; }
  }

  function loadGroupsForTab(tabId) {
    const raw = safeGetLS(tabGroupsKey(tabId));
    if (!raw) return [];
    try {
      const v = JSON.parse(raw);
      return Array.isArray(v) ? v : [];
    } catch (e) {
      return [];
    }
  }

  function saveGroupsForTab(tabId, groups) {
    if (!tabId) return false;
    if (!Array.isArray(groups) || !groups.length) return false;
    return safeSetLS(tabGroupsKey(tabId), JSON.stringify(groups));
  }

  // pega grupos do DOM (fonte única do autosave)
  function serializeAllGroupsFromDom() {
    const out = [];
    $('#pga_gen_container .pga-gen-box').each(function () {
      out.push(pgaSerializeBox($(this)));
    });
    return out;
  }

  // aplica grupos no DOM
  function applyGroupsToDom(groups) {
    if (!Array.isArray(groups) || !groups.length) {
      // só garante título coerente
      $('#pga_gen_container .pga-gen-box').each(function () {
        pgaSyncLinkOptionsForBox($(this));
        pgaUpdateBoxTitle($(this));
      });
      return;
    }

    ensureBoxesCount(groups.length); // você já tem

    const $boxes = $('#pga_gen_container .pga-gen-box');

    groups.forEach((g, i) => {
      const $box = $boxes.eq(i);
      if (!$box.length) return;
      pgaApplyBoxConfig($box, g);
    });

    // garante IDs no primeiro (pra teu fluxo atual não quebrar)
    const $first = $('#pga_gen_container .pga-gen-box').first();
    if ($first.length) pgaActivateBox($first);
  }

  // migração 1 vez: só roda se a tab NOVA ainda não tiver grupos salvos
  function migrateLegacyGroupsToTabOnce(tabId) {
    if (!tabId) return false;

    // já tem no novo? não faz nada
    if (loadGroupsForTab(tabId).length) return false;

    // tenta 1) legado por tab antigo
    let raw = safeGetLS(legacyTabGroupsKey_fromOldSystem());
    let data = [];
    if (raw) {
      try { data = JSON.parse(raw || '[]'); } catch (e) { data = []; }
      if (!Array.isArray(data)) data = [];
    }

    // tenta 2) legado pillar antigo (se 1 falhou)
    if (!data.length) {
      raw = safeGetLS(LEGACY_PILLAR_GROUPS_KEY);
      if (raw) {
        try { data = JSON.parse(raw || '[]'); } catch (e) { data = []; }
        if (!Array.isArray(data)) data = [];
      }
    }

    if (!data.length) return false;

    // grava no novo padrão
    return saveGroupsForTab(tabId, data);
  }


  // ============================================================
  // ========== BLOCO: GERADOR (keywords/plan/generate) ==========
  // ============================================================

  async function bootGenerator() {
    const $kw = $('#pga_keywords');
    const $done = $('#pga_kw_done');
    if (!$kw.length) return;

    function buildSummaryHtml(okCount, failCount, editLinks, failedKeywords) {
      const okHtml = `<p style="margin:0 0 4px; font-size: large"><b>${__('Sucesso:', 'plugins-alpha')}</b> ${okCount}</p>`;
      const failHeaderHtml = `<p style="margin:4px 0 0;"><b>${__('Falhas:', 'plugins-alpha')}</b> ${failCount}</p>`;

      const failListHtml = (failedKeywords && failedKeywords.length)
        ? `<ul style="margin:4px 0 0 18px;padding:0;font-size:large;">${failedKeywords
          .map(f => `<li>${f.keyword} – ${f.error}</li>`)
          .join('')}</ul>`
        : '';

      const linksHtml = (editLinks && editLinks.length)
        ? `<p style="margin:8px 0 4px;"><b>${__('Posts gerados', 'plugins-alpha')}</b></p><ul style="margin:0 0 0 18px;padding:0;font-size:large;">${editLinks.join('')}</ul>`
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
    function prefsFromBox($box) {
      const manualVals = $box.find('.pga_link_manual, .pga-link-manual-select').val() || [];

      return {
        locale: $box.find('.pga_locale').val() || 'pt_BR',
        length: $box.find('.pga_length').val() || 'short',
        template_key: $box.find('.pga_template_key').val() || 'discover_article',
        source_url: '',
        category_id: parseInt($box.find('.pga_category').val() || '0', 10) || 0,

        // ✅ esses 3 serão setados no global por job
        total: 1,
        per_day: 1,
        first_delay_hours: '',

        mode: 'multi',

        internal_links: {
          mode: ($box.find('.pga_link_mode').val() || 'none'),
          max: parseInt($box.find('.pga_link_max').val() || '0', 10) || 0,
          manual_ids: Array.isArray(manualVals) ? manualVals.join(',') : String(manualVals || '')
        }
      };
    }
    function pgaAddDays(startStr, days) {
      // aceita "YYYY-MM-DD HH:MM" ou "YYYY-MM-DDTHH:MM"
      const s = String(startStr || '').trim().replace(' ', 'T');
      const d = new Date(s);

      if (isNaN(d.getTime())) return startStr; // fallback: não quebra

      d.setDate(d.getDate() + (parseInt(days || 0, 10) || 0));

      const pad = (n) => String(n).padStart(2, '0');
      const yyyy = d.getFullYear();
      const mm = pad(d.getMonth() + 1);
      const dd = pad(d.getDate());
      const hh = pad(d.getHours());
      const mi = pad(d.getMinutes());

      // volta no mesmo formato que você já usa nos inputs
      return `${yyyy}-${mm}-${dd} ${hh}:${mi}`;
    }

    function collectPrefs() {
      // tenta achar o BOX que está "ativo" (aquele que tem o #pga_keywords dentro)
      let $box = $('#pga_gen_container .pga-gen-box').filter(function () {
        return $(this).find('#pga_keywords').length > 0;
      }).first();

      // se por algum motivo não tiver nenhum com #pga_keywords, cai no 1º (fallback)
      if (!$box.length) {
        $box = $('#pga_gen_container .pga-gen-box').first();
      }

      // se ainda assim não tiver box, usa o fallback antigo (ids soltos)
      if (!$box.length) {
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

    // ---------- Keywords: listar ----------
    async function refreshKeywords() {
      const j = await fetchJSON(`${REST}/keywords`, { headers: { 'X-WP-Nonce': NONCE } });

      // ✅ NÃO sobrescreve mais o campo #pga_keywords (isso joga tudo no primeiro gerador)
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
            title: __('Importado', 'plugins-alpha'),
            text: sprintf(
              _n(
                '%d linha foi carregada. Clique em "Salvar configurações" para persistir.',
                '%d linhas foram carregadas. Clique em "Salvar configurações" para persistir.',
                neu.length,
                'plugins-alpha'
              ),
              neu.length
            )
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
    $(document).off('click.pgaDeleteGenerator').on('click.pgaDeleteGenerator', '.pga_clear_box', async function () {
      const $box = $(this).closest('.pga-gen-box');
      const $container = $('#pga_gen_container');
      const totalBoxes = $container.find('.pga-gen-box').length;

      if (!$box.length) return;

      // se for o único, só limpa
      if (totalBoxes <= 1) {
        const ok = await pgaConfirm({
          icon: 'warning',
          title: __('Limpar este gerador?', 'plugins-alpha'),
          text: __('Este é o único gerador. Vamos apenas limpar os campos.', 'plugins-alpha'),
          confirmButtonText: __('Limpar', 'plugins-alpha'),
          cancelButtonText: __('Cancelar', 'plugins-alpha')
        });

        if (!ok) return;

        $box.find('.pga_keywords').val('');
        $box.find('.pga_total').val('6');
        $box.find('.pga_per_day').val('3');
        $box.find('.pga_template_key').val('discover_article');
        $box.find('.pga_locale').val('pt_BR');
        $box.find('.pga_length').val('short');
        $box.find('.pga_category').val('0');

        pgaUpdateBoxTitle($box);
        window.PGA_IS_GENERATING = true;
        return;
      }

      const ok = await pgaConfirm({
        icon: 'warning',
        title: __('Excluir gerador?', 'plugins-alpha'),
        text: __('Este gerador será removido.', 'plugins-alpha'),
        confirmButtonText: __('Excluir', 'plugins-alpha'),
        cancelButtonText: __('Cancelar', 'plugins-alpha')
      });

      if (!ok) return;

      $box.remove();

      // reindexa
      const $boxes = $container.find('.pga-gen-box');
      $boxes.each(function (idx) {
        const $b = $(this);
        $b.attr('data-gen', idx + 1);
        pgaUpdateBoxTitle($b);
      });

      // garante IDs no primeiro
      const $first = $boxes.first();
      if ($first.length) pgaActivateBox($first);

      window.PGA_IS_GENERATING = true;
    });

    // ---------- Salvar (botão global "Salvar" lá em cima) ----------
    $('#pga_save_keywords').off('click').on('click', async function () {
      const btn = this;
      btn.disabled = true;

      try {
        savePrefsToLocal();
        // ✅ salva grupos da tab atual no storage oficial
        try {
          const tabId = localStorage.getItem(KEY_ACTIVE_TAB) || getTabIdFromUrl() || '';
          if (tabId) saveGroupsForTab(tabId, serializeAllGroupsFromDom());
        } catch (e) { }

        // ✅ agora salvar keywords = salvar grupos (local), e servidor só mantém "done" (se precisar)
        try {
          const tabId = localStorage.getItem(KEY_ACTIVE_TAB) || getTabIdFromUrl() || '';
          if (tabId) saveGroupsForTab(tabId, serializeAllGroupsFromDom());
        } catch (e) { }

        // opcional: se você ainda quer manter o "done" vindo do servidor
        try {
          const j = await fetchJSON(`${REST}/keywords`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
            // manda vazio pra não sobrescrever pending no backend (ou remove o POST se não precisar)
            body: JSON.stringify({ pending_text: '' }),
            silent: true
          });
          renderDone(j.done || []);
        } catch (e) {
          // ignora
        }


        // ✅ marcou como salvo
        window.PGA_IS_GENERATING = false;
        pgaToast('success', __('Salvo', 'plugins-alpha'));

      } catch (e) {
        pgaToast('error', e, 2200);
      } finally {
        btn.disabled = false;
      }
    });


    // "Salvar configurações" dentro do grupo apenas reaproveita o salvar global
    $(document).off('click.pgaSaveBox').on('click.pgaSaveBox', '.pga_save_box', function () {
      $('#pga_save_keywords').trigger('click');
      try {
        const tabId = localStorage.getItem(KEY_ACTIVE_TAB) || getTabIdFromUrl() || '';
        if (tabId) saveGroupsForTab(tabId, serializeAllGroupsFromDom());
      } catch (e) { }
    });

    // ---------- Gerar SOMENTE deste grupo ----------
    $(document).off('click.pgaGenerateBox').on('click.pgaGenerateBox', '.pga_generate_box', function () {
      const $box = $(this).closest('.pga-gen-box');
      if (!$box.length) return;

      (async () => {
        pgaActivateBox($box);
        savePrefsToLocal();

        const titleText = $box.find('.pga-gen-title').text().trim() || __('Grupo atual', 'plugins-alpha');

        const res = await generateForActiveBox({ groupTitle: titleText });
        if (!res) return;

        const html = buildSummaryHtml(res.okCount, res.failCount, res.editLinks, res.failedKeywords);

        await Swal.fire({
          icon: res.failCount ? 'warning' : 'success',
          title: __('Finalizado', 'plugins-alpha'),
          html,
        });
      })();
    });

    $('#pga_kw_clear_done').off('click').on('click', async () => {
      const ok = window.Swal ? (await Swal.fire({ icon: 'warning', title: __('Limpar concluídas?', 'plugins-alpha'), showCancelButton: true })).isConfirmed : confirm(__('Limpar concluídas?', 'plugins-alpha'));
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
      const {
        skipKeywordWarning = false,
        groupTitle = '',
        plannedOrigins = null,

        // ✅ novos
        kwOverride = null,
        prefsOverride = null,
      } = options;

      const prefs = (options && options.prefsOverride) ? options.prefsOverride : collectPrefs();

      const kwList = (options && Array.isArray(options.kwOverride))
        ? options.kwOverride.map(s => String(s || '').trim()).filter(Boolean)
        : textareaToArray($('#pga_keywords').val());

      const transition = {
        strict: false,
        min_ratio: 0.30,
        words: [__('por exemplo', 'plugins-alpha'), __('em seguida', 'plugins-alpha'), __('depois', 'plugins-alpha'), __('antes', 'plugins-alpha'), __('no entanto', 'plugins-alpha'), __('portanto', 'plugins-alpha'), __('assim', 'plugins-alpha'), __('então', 'plugins-alpha')]
      };

      // === 0) VAL IDA LICENÇA + CHAVE API ANTES DE QUALQUER COISA ===
      try {
        const st = await fetchJSON(`${REST}/selftest`, {
          method: 'GET',
          headers: { 'X-WP-Nonce': NONCE }
        });

        if (st && st.ok === false) {
          await Swal.fire({
            icon: 'error',
            title: __('Configuração necessária', 'plugins-alpha'),
            text: st.message || __('Sua licença ou chave de API não está configurada. Verifique a tela de configurações do Plugins Alpha.', 'plugins-alpha')
          });
          return;
        }
      } catch (e) {
        let msg = __('Não foi possível validar a licença / chave de API.', 'plugins-alpha');
        let title = __('Erro de validação', 'plugins-alpha');

        if (e && typeof e === 'object') {
          if (e.code === 'pga_no_key') {
            title = __('Chave da API ausente', 'plugins-alpha');
            msg = e.message || __('Configure sua chave da API na tela de configurações do Plugins Alpha.', 'plugins-alpha');
          } else if (e.code === 'pga_lic_inactive') {
            title = __('Licença inativa', 'plugins-alpha');
            msg = e.message || __('Sua licença está inativa ou expirada. Verifique na tela de Licença do Plugins Alpha.', 'plugins-alpha');
          } else if (e.message) {
            msg = e.message;
          }
        }

        await Swal.fire({ icon: 'error', title, text: msg });
        return;
      }

      if (prefs.template_key === 'modelar_youtube') {
        // 1) Garante que há pelo menos uma URL de YouTube nas keywords
        const hasYoutubeUrl = kwList.some((line) => {
          const v = String(line || '').trim().toLowerCase();
          return v.includes('youtube.com/watch') || v.includes('youtu.be/');
        });

        if (!hasYoutubeUrl) {
          await Swal.fire({
            icon: 'warning',
            title: __('URLs do YouTube necessárias', 'plugins-alpha'),
            text: __('Para “Modelar vídeo do YouTube”, insira pelo menos 1 URL completa de vídeo do YouTube nas palavras-chave (uma por linha).', 'plugins-alpha')
          });
          return;
        }

        // 2) Valida a chave da API do YouTube via REST
        try {
          const yt = await fetchJSON(`${REST}/youtube/selftest`, {
            method: 'GET',
            headers: { 'X-WP-Nonce': NONCE }
          });

          if (yt && yt.ok === false) {
            await Swal.fire({
              icon: 'error',
              title: __('Chave do YouTube necessária', 'plugins-alpha'),
              text: yt.message || __('Configure sua chave da API do YouTube na tela de configurações do Plugins Alpha.', 'plugins-alpha')
            });
            return;
          }
        } catch (e) {
          await Swal.fire({
            icon: 'error',
            title: __('Erro ao validar YouTube', 'plugins-alpha'),
            text: (e && e.message) ? e.message : __('Não foi possível validar a chave da API do YouTube.', 'plugins-alpha')
          });
          return;
        }
      }

      if (prefs.template_key !== "modelar") {
        // sem keywords
        if (prefs.mode === 'multi' && kwList.length === 0) {
          if (!skipKeywordWarning) {
            await Swal.fire({
              icon: 'warning',
              title: __('Sem palavras-chave', 'plugins-alpha'),
              text: __('Insira ao menos 1 palavra-chave.', 'plugins-alpha')
            });
          }
          return;
        }

        // keywords < total
        if (prefs.mode === 'multi' && kwList.length < prefs.total) {
          if (!skipKeywordWarning) {

            const html = sprintf(
              _n(
                "<p style='font-size:16px;'>Você pediu <b>%d</b> post, mas só tem <b>%d</b> palavra. Gerar <b>%d</b>?</p>",
                "<p style='font-size:16px;'>Você pediu <b>%d</b> posts, mas só tem <b>%d</b> palavras. Gerar <b>%d</b>?</p>",
                kwList.length,
                'plugins-alpha'
              ),
              prefs.total,
              kwList.length,
              kwList.length
            );

            const ok = (await Swal.fire({
              icon: 'question',
              title: __('Quantidade insuficiente', 'plugins-alpha'),
              html,
              showCancelButton: true
            })).isConfirmed;

            if (!ok) return;
          }
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
        let msg = __('Ocorreu um erro ao montar o plano de geração.', 'plugins-alpha');
        let title = __('Erro ao gerar plano', 'plugins-alpha');

        if (e && typeof e === 'object') {
          if (e.code === 'pga_no_key') {
            title = __('Chave da API ausente', 'plugins-alpha');
            msg = e.message || __('Configure sua chave da API na tela de configurações do Plugins Alpha.', 'plugins-alpha');
          } else if (e.code === 'pga_lic_inactive') {
            title = __('Licença inativa', 'plugins-alpha');
            msg = e.message || __('Sua licença está inativa ou expirada. Verifique na tela de Licença do Plugins Alpha.', 'plugins-alpha');
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
          title: __('Nada a gerar', 'plugins-alpha'),
          text: __('Plano vazio.', 'plugins-alpha')
        });
        return;
      }

      // ✅ GLOBAL: aplica config correta por origem (template/category/length/locale/internal_links)
      if (Array.isArray(plannedOrigins) && plannedOrigins.length) {
        for (let i = 0; i < jobs.length; i++) {
          const origin = plannedOrigins[i];
          const boxEl = origin?.boxEl || null;
          const $b = boxEl ? $(boxEl) : null;

          // origem do done
          jobs[i].boxEl = boxEl || activeBoxEl;

          // se achar o box, aplica as prefs dele no job (sem mexer em inputs)
          if ($b && $b.length) {
            const p = prefsFromBox($b);

            jobs[i].locale = p.locale;
            jobs[i].length = p.length;
            jobs[i].template_key = p.template_key;
            jobs[i].category_id = p.category_id;
            jobs[i].internal_links = p.internal_links;
          }

          jobs[i].__originKw = origin?.kw || '';
        }
      }

      // ✅ box ativo = o que recebeu #pga_keywords via pgaActivateBox()
      const activeBoxEl = document.getElementById('pga_keywords')?.closest('.pga-gen-box') || null;

      // ✅ Se veio do planejamento global, aplica origem por job (mesma ordem)
      if (Array.isArray(plannedOrigins) && plannedOrigins.length) {
        for (let i = 0; i < jobs.length; i++) {
          const origin = plannedOrigins[i];
          jobs[i].boxEl = origin?.boxEl || activeBoxEl;

          // opcional: guarda também o kw original pra debug
          jobs[i].__originKw = origin?.kw || '';
        }
      } else {
        // fallback: geração normal de um box só
        for (const j of jobs) {
          j.boxEl = activeBoxEl;
        }
      }

      // === 3) GERAÇÃO ===
      let okCount = 0, failCount = 0;
      let editLinks = [];
      const failedKeywords = [];

      async function pgaFetchSectionWithRetry(params, maxRetries = 3) {
        let attempt = 0;

        // usa o NONCE do PGA_CFG, com fallback opcional pro wpApiSettings se existir
        const nonce =
          (window.wpApiSettings && window.wpApiSettings.nonce) ||
          NONCE;

        while (attempt < maxRetries) {
          attempt++;

          try {
            const r = await fetch(`${REST}/orion/section`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
              },
              body: JSON.stringify(params),
            });

            if (!r.ok) {
              console.warn(
                `SECTION ${params.section_id} → tentativa ${attempt} falhou: HTTP ${r.status}`
              );
              throw new Error('HTTP ' + r.status);
            }

            const json = await r.json();

            if (!json || json.error) {
              console.warn(
                `SECTION ${params.section_id} → JSON inválido na tentativa ${attempt}`,
                json
              );
              throw new Error('JSON inválido');
            }

            return { ok: true, data: json, attempt };
          } catch (err) {
            console.error(
              `Erro na section ${params.section_id} (tentativa ${attempt}):`,
              err
            );

            if (attempt >= maxRetries) {
              return { ok: false, error: err };
            }

            await new Promise(res => setTimeout(res, 800 * attempt));
          }
        }
      }

      async function generateExtraLongPost(job, opts = {}) {
        const onStatus = typeof opts.onStatus === 'function' ? opts.onStatus : () => { };

        // 1) OUTLINE -------------------------------------------------
        onStatus(__('Gerando outline…', 'plugins-alpha'));

        const outlineRes = await fetchJSON(`${REST}/orion/outline`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE
          },
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
          throw new Error(outlineRes?.message || __('Erro ao gerar esboço', 'plugins-alpha'));
        }

        const postId = outlineRes.post_id;
        const sections = outlineRes.sections || [];
        const errors = [];

        // 2) SEÇÕES ---------------------------------------------------
        const totalSections = sections.length;
        let doneSections = 0;

        for (const section of sections) {
          const sid = section.id;

          onStatus(
            sprintf(__('Gerando seções… (%d/%d)', 'plugins-alpha'), doneSections, totalSections)
          );

          const secRes = await pgaFetchSectionWithRetry({
            post_id: postId,
            section_id: sid,
          });

          if (!secRes.ok) {
            errors.push(
              sprintf(__('Falha definitiva na seção %s do post %s', 'plugins-alpha'), sid, postId)
            );
          }

          doneSections++;
        }

        onStatus(
          sprintf(__('Gerando seções… (%d/%d)', 'plugins-alpha'), doneSections, totalSections)
        );

        // 3) FINALIZE (SEM IMAGEM) -----------------------------------
        onStatus(__('Finalizando conteúdo…', 'plugins-alpha'));

        const il = job.internal_links || {};
        const rawManual = il.manual_ids;

        const finRes = await fetchJSON(`${REST}/orion/finalize`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': NONCE
          },
          body: JSON.stringify({
            post_id: postId,
            internal_links: {
              mode: il.mode || 'none',
              max: typeof il.max === 'number' ? il.max : (parseInt(il.max || '0', 10) || 0),
              manual_ids: Array.isArray(rawManual) ? rawManual.join(',') : (rawManual ? String(rawManual) : ''),
            },
          }),
          silent: true
        });


        if (finRes && finRes.code) {
          throw new Error(finRes.message || __('Erro ao finalizar post', 'plugins-alpha'));
        }

        // 4) IMAGEM EM ENDPOINT SEPARADO ------------------------------
        onStatus(__('Gerando imagem destacada…', 'plugins-alpha'));

        let imgRes = null;

        try {
          imgRes = await fetchJSON(`${REST}/orion/image`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': NONCE
            },
            body: JSON.stringify({
              post_id: postId,
              keyword: job.keyword || '',
              locale: job.locale,
              template: job.template_key,
            }),
            silent: true
          });
        } catch (e) {
          console.warn('Falha ao gerar imagem para o post', postId, e);
          onStatus(__('Conteúdo pronto. Imagem falhou (pode tentar depois).', 'plugins-alpha'));
        }

        if (imgRes && !imgRes.error) {
          onStatus(__('Conteúdo e imagem gerados com sucesso.', 'plugins-alpha'));
        }

        return {
          ...finRes,
          image: imgRes,
          section_errors: errors,
        };
      }



      window.PGA_IS_GENERATING = true;

      try {
        const totalJobs = jobs.length;

        await Swal.fire({
          title: __('Gerando posts…', 'plugins-alpha'),
          html: `
            <div id="pga_group" style="text-align:center;font-weight:600;font-size:13px;margin-bottom:4px;"></div>

            <div id="pga_loader" style="display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
              <div class="swal2-loader" style="display:block;border-width:3px;width:20px;height:20px;"></div>
            </div>

            <div id="pga_prog" style="text-align:center;font-size:13px;margin-bottom:4px;">
              ${sprintf(__('Progresso: %d de %d', 'plugins-alpha'), 0, totalJobs)}
            </div>

            <div class="swal2-progress-steps" style="height:8px;background:#eee;border-radius:4px;overflow:hidden;margin-bottom:8px">
              <div id="pga_progbar" style="height:8px;width:0%;background:#3b82f6;transition:width .25s ease"></div>
            </div>

            <div id="pga_current" style="text-align:center;font-size:12px;color:#6b7280;min-height:16px;">
              ${__('Preparando geração…', 'plugins-alpha')}
            </div>
          `,
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: async () => {
            const $group = document.getElementById('pga_group');
            const $status = document.getElementById('pga_prog');
            const $bar = document.getElementById('pga_progbar');
            const $cur = document.getElementById('pga_current');

            if ($group && groupTitle) {
              $group.textContent = groupTitle;
            }

            okCount = 0;
            failCount = 0;
            editLinks.length = 0;
            failedKeywords.length = 0;

            for (let i = 0; i < jobs.length; i++) {
              const job = jobs[i];
              const kw = (job.keyword || '').trim();

              try {
                const result = await generateExtraLongPost(job, {
                  onStatus: msg => {
                    if ($cur) $cur.textContent = msg;
                  }
                });

                const r = result && result.res ? result.res : result;

                if (!r || r.error) {
                  failCount++;
                  if (kw) failedKeywords.push(kw);
                } else {
                  okCount++;
                  if (kw) pgaMarkKeywordDoneInBox(kw, job.boxEl);

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
                      const suffix = kw ? sprintf(__(' – %s', 'plugins-alpha'), kw) : '';

                      editLinks.push(
                        `<li><a target="_blank" rel="noopener" href="${editUrl}">${sprintf(
                          __('Editar #%s%s', 'plugins-alpha'),
                          labelId,
                          suffix
                        )}</a></li>`
                      );

                    }
                  }
                }
              } catch (e) {
                failCount++;
                failedKeywords.push({
                  keyword: kw || __('(sem keyword)', 'plugins-alpha'),
                  error: e && e.message ? e.message : __('Erro desconhecido', 'plugins-alpha'),
                });
              }

              const done = i + 1;
              const pct = Math.round((done / jobs.length) * 100);

              if ($status) {
                $status.textContent = sprintf(
                  __('Progresso: %1$s de %2$s', 'plugins-alpha'),
                  done,
                  jobs.length
                );
              }
              if ($bar) $bar.style.width = pct + '%';

              await new Promise(r => setTimeout(r, 150));
            }

            Swal.close();
          }
        });

      } finally {
        window.PGA_IS_GENERATING = false;
      }

      // ✅ salva no final: prefs + grupos (porque agora KW já foi removida/movida)

      return { okCount, failCount, editLinks, failedKeywords };
    };



    // ---------- Planejar & Gerar (TODOS os grupos) ----------
    $('#pga_plan').off('click').on('click', async () => {

      // ==========================
      // MODO NORMAL (não-global)
      // ==========================
      if (!pgaGlobalIsOn()) {
        const $active = $('#pga_gen_container .pga-gen-box').filter(function () {
          return $(this).find('#pga_keywords').length > 0;
        }).first();

        pgaActivateBox($active.length ? $active : $('#pga_gen_container .pga-gen-box').first());

        const res = await generateForActiveBox({ groupTitle: __('Gerador atual', 'plugins-alpha') });
        if (!res) return;

        const html = buildSummaryHtml(res.okCount, res.failCount, res.editLinks, res.failedKeywords);
        await Swal.fire({
          icon: res.failCount ? 'warning' : 'success',
          title: __('Finalizado', 'plugins-alpha'),
          html
        });
        return;
      }

      // ==========================
      // MODO GLOBAL (planejamento)
      // ==========================
      const $boxes = $('#pga_gen_container .pga-gen-box');
      if (!$boxes.length) return;

      const totalGlobal = Math.max(1, parseInt($('#pga_plan_total').val() || '1', 10) || 1);
      const startGlobal = String($('#pga_plan_start').val() || '').trim();

      if (!startGlobal) {
        await Swal.fire({
          icon: 'warning',
          title: __('Início necessário', 'plugins-alpha'),
          text: __('Defina a data/hora de início no planejamento global.', 'plugins-alpha')
        });
        return;
      }

      // monta lista de groups (snapshot 1x, SEM ALTERAR DOM)
      const groups = [];
      $boxes.each(function () {
        const $box = $(this);
        const templateKey = $box.find('.pga_template_key').val() || 'article';
        const kwsArr = textareaToArray($box.find('.pga_keywords').val());
        const perDay = parseInt($box.find('.pga_per_day').val() || '0', 10) || 0;

        // ignora grupos sem KW ou sem peso (exceto template modelar)
        if (templateKey !== 'modelar' && (!kwsArr.length || perDay <= 0)) return;

        groups.push({
          $box,
          templateKey,
          keywords: kwsArr,
          perDay,              // ✅ PESO da fila
          boxEl: $box[0],
        });
      });

      if (!groups.length) {
        await Swal.fire({
          icon: 'warning',
          title: __('Nada a gerar', 'plugins-alpha'),
          text: __('Adicione keywords e defina “Posts por dia” (>= 1) em pelo menos um colapse.', 'plugins-alpha')
        });
        return;
      }

      // ✅ fila global (ordem 2,1,2,1...)
      // precisa que sua função retorne {kw, boxEl}
      const plannedKw = pgaBuildPlannedKeywordsByPerDay(groups, totalGlobal);

      console.log(plannedKw);

      if (!plannedKw.length) {
        await Swal.fire({
          icon: 'info',
          title: __('Fila vazia', 'plugins-alpha'),
          text: __('Não foi possível montar a fila com os “Posts por dia” atuais.', 'plugins-alpha')
        });
        return;
      }

      if (plannedKw.length < totalGlobal) {
        const r = await Swal.fire({
          icon: 'question',
          title: __('Keywords insuficientes', 'plugins-alpha'),
          html: sprintf(
            __(
              '<p style="font-size:15px;">Você pediu <b>%d</b> posts, mas dá pra gerar <b>%d</b> com as keywords disponíveis.<br>Continuar?</p>',
              'plugins-alpha'
            ),
            totalGlobal,
            plannedKw.length
          ),
          showCancelButton: true,
          confirmButtonText: __('Sim, gerar', 'plugins-alpha'),
          cancelButtonText: __('Cancelar', 'plugins-alpha'),
        });
        if (!r.isConfirmed) return;
      }

      // acumuladores globais
      let okAll = 0, failAll = 0;
      const editAll = [];
      const failedAll = [];

      // ✅ 1 chamada só: mantém “0 de N” e marca done no colapse certo
      const masterBoxEl = plannedKw[0]?.boxEl;
      const $master = masterBoxEl ? $(masterBoxEl) : $boxes.first();

      const masterPrefs = prefsFromBox($master);

      // ⚠️ calendário global: 1 por dia (como você queria) e startGlobal vem do input global
      masterPrefs.total = plannedKw.length;
      masterPrefs.per_day = 1;
      masterPrefs.first_delay_hours = startGlobal;

      // lista final de keywords (ordem 2,1,2,1…)
      const kwAll = plannedKw.map(x => String(x.kw || '').trim()).filter(Boolean);

      // plannedOrigins precisa ser o “espelho” (mesma ordem da lista)
      const originsAll = plannedKw.map(x => ({
        kw: String(x.kw || '').trim(),
        boxEl: x.boxEl
      }));

      const res = await generateForActiveBox({
        skipKeywordWarning: true,
        groupTitle: __('Planejamento global', 'plugins-alpha'),

        // ✅ não mexe no textarea
        kwOverride: kwAll,

        // ✅ prefs “master” só pra conduzir plan + calendário
        prefsOverride: masterPrefs,

        // ✅ marca done por origem correta
        plannedOrigins: originsAll,
      });

      if (res) {
        okAll += (res.okCount || 0);
        failAll += (res.failCount || 0);
        if (Array.isArray(res.editLinks)) editAll.push(...res.editLinks);
        if (Array.isArray(res.failedKeywords)) failedAll.push(...res.failedKeywords);
      }


      const html = buildSummaryHtml(okAll, failAll, editAll, failedAll);

      await Swal.fire({
        icon: failAll ? 'warning' : 'success',
        title: __('Geração concluída', 'plugins-alpha'),
        html
      });

    });
    // try { await refreshKeywords(); } catch (e) { }

    $(document)
      .off('input.pgaDirty change.pgaDirty')
      .on('input.pgaDirty change.pgaDirty',
        '#pga_gen_container input, #pga_gen_container textarea, #pga_gen_container select',
        function (e) {
          // só marca se veio do usuário (não de .trigger('change') do JS)
          if (e && e.isTrigger) return;

          window.PGA_IS_GENERATING = true;
        }
      );

  }

  // boot
  $(async function () {
    if (onSettingsPage()) await bootSettings();
    await bootGenerator();
    initLinkManualSelect2();
    loadPrefs();
  });

  // Helpers SweetAlert2
  async function swalLoading(title = __('Processando…', 'plugins-alpha')) {
    return Swal.fire({
      title,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading()
    });
  }
  async function swalSuccess(html, title = __('Tudo certo!', 'plugins-alpha')) {
    return Swal.fire({ icon: 'success', title, html, confirmButtonText: __('Ok', 'plugins-alpha') });
  }
  async function swalError(html, title = __('Ops…', 'plugins-alpha')) {
    return Swal.fire({ icon: 'error', title, html, confirmButtonText: __('Entendi', 'plugins-alpha') });
  }
  async function swalWarn(html, title = __('Atenção', 'plugins-alpha')) {
    return Swal.fire({ icon: 'warning', title, html, confirmButtonText: __('Ok', 'plugins-alpha') });
  }

  // Helper unificado para confirmações (Swal ou fallback confirm)
  async function pgaConfirm(opts = {}) {
    const {
      title = '',
      text = '',
      icon = 'warning',
      confirmButtonText = __('Ok', 'plugins-alpha'),
      cancelButtonText = __('Cancelar', 'plugins-alpha')
    } = opts || {};

    if (window.Swal) {
      const res = await Swal.fire({
        icon,
        title,
        html: text,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText
      });
      return !!res.isConfirmed;
    }

    // fallback simples
    return confirm(String((title || text) || __('Confirma?', 'plugins-alpha')));
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
      await swalWarn(__('Preencha <b>e-mail</b> e <b>ID da compra</b> antes de ativar.', 'plugins-alpha'));
      return;
    }

    try {
      $btn.prop('disabled', true);
      await swalLoading(__('Ativando licença…', 'plugins-alpha'));

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
            <div><b>${__('Compra:', 'plugins-alpha')}</b> ${data.license?.purchase_id || '-'}</div>
            ${data.license?.message ? `<div style="margin-top:6px">${data.license.message}</div>` : ''}
          </div>
        `;
        await swalSuccess(html, __('Licença ativada!', 'plugins-alpha'));
      } else {
        const html = `
          <div style="text-align:left">
            <div><b>Status:</b> ${data.license?.status || 'INACTIVE'}</div>
            ${data.license?.message ? `<div style="margin-top:6px">${data.license.message}</div>` : `<div style="margin-top:6px">${__('Não foi possível ativar. Verifique os dados.', 'plugins-alpha')}</div>`}
          </div>
        `;
        await swalWarn(html, __('Licença não ativa', 'plugins-alpha'));
      }
    } catch (err) {
      Swal.close();
      const fallbackMsg = __('Erro desconhecido.<br><small>Tente novamente em instantes.</small>', 'plugins-alpha');
      await swalError((err && err.message) ? String(err.message) : fallbackMsg);
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


  // DUPLICAR GRUPO (FIX)
  $(document).off('click.pgaCopyBox').on('click.pgaCopyBox', '.pga-copy-box', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $box = $(this).closest('.pga-gen-box');
    const $container = $('#pga_gen_container');
    if (!$box.length || !$container.length) return;

    // 1) captura config do box atual (isso garante copiar TUDO)
    const cfg = pgaSerializeBox($box);

    // 2) clona template visual (com eventos e data)
    const $clone = $box.clone(true, true);

    // 3) gera novo data-gen
    const gens = $container.find('.pga-gen-box').map(function () {
      return parseInt($(this).attr('data-gen') || '0', 10) || 0;
    }).get();
    const nextGen = (gens.length ? Math.max.apply(null, gens) : 0) + 1;

    $clone.attr('data-gen', String(nextGen));

    // 4) remove IDs duplicados (mantém classes)
    $clone.find('[id]').removeAttr('id');

    // 5) aplica config no clone (aqui entra links internos / select2 / etc)
    pgaApplyBoxConfig($clone, cfg);

    // 6) abre o collapse do clone (opcional)
    $clone.addClass('pga-collapse--open');

    // 7) insere após o atual
    $box.after($clone);

    // 8) reinit select2 do clone e atualiza título
    initLinkManualSelect2($clone);
    pgaUpdateBoxTitle($clone);

    window.PGA_IS_GENERATING = true;
  });


  $(document).on('click', '.pga_generate_keywords', async function () {
    const $box = $(this).closest('.pga-gen-box');
    const $ta = $box.find('.pga_keywords');
    const cmd = ($ta.val() || '').trim();

    const ok = await pgaConfirm({
      icon: 'question',
      title: __('Gerar keywords?', 'plugins-alpha'),
      text: __('Isso vai substituir o conteúdo do campo por keywords geradas. Tem certeza?', 'plugins-alpha'),
      confirmButtonText: __('Gerar', 'plugins-alpha'),
      cancelButtonText: __('Cancelar', 'plugins-alpha')
    });

    if (!ok) return;

    // opcional: evita clique duplo
    const $btn = $(this);
    if ($btn.data('loading')) return;
    $btn.data('loading', 1).prop('disabled', true);

    // ✅ abre o loading ANTES do fetch
    Swal.fire({
      title: __('Gerando keywords...', 'plugins-alpha'),
      text: __('Aguarde um instante.', 'plugins-alpha'),
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        Swal.showLoading();
      },
    });

    try {
      const payload = {
        command: cmd,
        locale: ($box.find('.pga_locale').val() || 'pt_BR'),
        template: ($box.find('.pga_template_key').val() || 'article'),
        category: ($box.find('.pga_category').val() || ''),
      };

      const j = await fetchJSON(PGA_CFG.rest + '/orion/keywords', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': PGA_CFG.nonce,
        },
        body: JSON.stringify(payload),
        silent: true
      });

      if (!j || !j.ok) {
        throw new Error((j && j.message) ? j.message : __('Falha ao gerar keywords.', 'plugins-alpha'));
      }

      $ta.val(j.keywords_text || '');

      // fecha loading e mostra sucesso
      Swal.close();
      await Swal.fire({ icon: 'success', title: __('Pronto', 'plugins-alpha'), text: __('Keywords geradas.', 'plugins-alpha') });

      if (typeof window.PGA_saveGroupsToStorage === 'function') {
        window.PGA_saveGroupsToStorage();
      }
      pgaSaveBoxesToLocal();

    } catch (err) {
      Swal.close();
      Swal.fire({ icon: 'error', title: __('Erro', 'plugins-alpha'), text: String(err.message || err) });
    } finally {
      $btn.data('loading', 0).prop('disabled', false);
    }
  });

  function cfg() {
    const c = window.PGA_PROMPTS_EXPORT || {};
    return {
      ajaxurl: c.ajaxurl || window.ajaxurl || '',
      nonce: c.nonce || ''
    };
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }

  // safeJson removed: use fetchJSON(...) instead for unified handling

  function pad2(n) { return String(n).padStart(2, '0'); }
  function exportFilename(prefix = 'orion-prompts') {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = pad2(d.getMonth() + 1);
    const dd = pad2(d.getDate());
    const hh = pad2(d.getHours());
    const mi = pad2(d.getMinutes());
    const ss = pad2(d.getSeconds());
    return `${prefix}-${yyyy}-${mm}-${dd}_${hh}-${mi}-${ss}.json`;
  }

  // =========================
  // EXPORT
  // =========================
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('#pga-prompts-export');
    if (!btn) return;

    const { ajaxurl, nonce } = cfg();
    if (!ajaxurl || !nonce) { alert(__('Config export ausente (ajaxurl/nonce).', 'plugins-alpha')); return; }

    try {
      if (window.Swal) {
        Swal.fire({ title: __('Exportando…', 'plugins-alpha'), allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
      }

      const j = await fetchJSON(ajaxurl + '?action=pga_orion_prompts_export', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ _ajax_nonce: nonce })
      });
      if (!j || !j.success) throw new Error(j?.data?.message || __('Falha no export.', 'plugins-alpha'));

      const blob = new Blob([JSON.stringify(j.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);

      const a = document.createElement('a');
      a.href = url;
      a.download = (j.data?._meta?.filename) ? String(j.data._meta.filename) : exportFilename('orion-prompts');
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);

      if (window.Swal) Swal.close();
    } catch (err) {
      if (window.Swal) Swal.fire({ icon: 'error', title: 'Erro', text: String(err.message || err) });
      else alert(String(err.message || err));
    }
  });

  // =========================
  // IMPORT (picker)
  // =========================
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#pga-prompts-import');
    if (!btn) return;

    const input = document.getElementById('pga-prompts-import-file');
    if (!input) return;

    input.value = '';
    input.click();
  });

  // =========================
  // IMPORT (prepare + modal + apply)
  // =========================
  document.getElementById('pga-prompts-import-file')?.addEventListener('change', async function () {
    const file = this.files?.[0];
    if (!file) return;

    const { ajaxurl, nonce } = cfg();
    if (!ajaxurl || !nonce) { alert(__('Config import ausente (ajaxurl/nonce).', 'plugins-alpha')); return; }

    try {
      if (window.Swal) {
        Swal.fire({ title: __('Lendo arquivo…', 'plugins-alpha'), allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
      }

      // 1) PREPARE
      const fd = new FormData();
      fd.append('action', 'pga_orion_prompts_import_prepare');
      fd.append('_ajax_nonce', nonce);
      fd.append('file', file);

      const j = await fetchJSON(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd });
      if (!j || !j.success) throw new Error(j?.data?.message || __('Falha ao ler o JSON.', 'plugins-alpha'));

      const token = j.data?.token || '';
      const items = Array.isArray(j.data?.items) ? j.data.items : [];
      if (!token) throw new Error(__('Token não retornado no prepare.', 'plugins-alpha'));
      if (!items.length) throw new Error(__('Nada importável encontrado no arquivo.', 'plugins-alpha'));

      // 2) MODAL
      // items: [{key, type:'template|prompt', tpl, stage, hasExisting, size}]

      // agrupa por tpl
      const groups = {};
      for (const it of items) {
        const tpl = it.tpl || 'unknown';
        groups[tpl] = groups[tpl] || [];
        groups[tpl].push(it);
      }

      // ordena templates (article primeiro)
      const order = Object.keys(groups).sort((a, b) => {
        const prio = { article: 0, modelar_youtube: 1 };
        const pa = (prio[a] ?? 99), pb = (prio[b] ?? 99);
        if (pa !== pb) return pa - pb;
        return a.localeCompare(b);
      });

      // cria HTML com <details> (colapse nativo, leve)
      const htmlGroups = order.map((tpl, gi) => {
        const list = groups[tpl];

        const headerExists = list.some(x => x.hasExisting);
        const headerMeta = headerExists
          ? `<span style="color:#b45309;margin-left:6px">${__('tem itens existentes', 'plugins-alpha')}</span>`
          : `<span style="color:#15803d;margin-left:6px">${__('novo', 'plugins-alpha')}</span>`;

        // lista interna (prompts)
        const inner = list.map((it) => {
          // você pode esconder a linha "template" e só mostrar prompts
          if (it.type === 'template') return '';

          const meta = it.hasExisting ? __('já existe', 'plugins-alpha') : __('novo', 'plugins-alpha');
          const metaColor = it.hasExisting ? '#b45309' : '#15803d';
          const small = it.size ? ` <span style="color:#666">(${Number(it.size)} chars)</span>` : '';

          return `
      <label style="display:flex;gap:10px;align-items:flex-start;padding:6px 0;border-bottom:1px solid #f1f1f1">
        <input type="checkbox" class="pga-import-item" data-key="${escapeHtml(it.key)}" checked style="margin-top:3px">
        <div style="line-height:1.2">
          <div>
            <code>${escapeHtml(it.stage)}</code>
            — <span style="color:${metaColor}">${meta}</span>${small}
          </div>
        </div>
      </label>
    `;
        }).join('');

        return `
    <div style="border:1px solid #eee;border-radius:12px;padding:10px;margin:10px 0">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
        <label style="display:flex;gap:10px;align-items:center">
          <input type="checkbox" class="pga-import-tpl-all" data-tpl="${escapeHtml(tpl)}" checked>
          <b>${escapeHtml(tpl)}</b>
          ${headerMeta}
        </label>

        <small style="color:#666">${list.filter(x => x.type === 'prompt').length} prompts</small>
      </div>

      <details style="margin-top:8px">
        <summary style="cursor:pointer;color:#111">${__('ver itens', 'plugins-alpha')}</summary>
        <div style="margin-top:8px;max-height:260px;overflow:auto;padding-right:6px">
          ${inner || `<div style="color:#666">${__('Nenhum prompt encontrado neste modelo.', 'plugins-alpha')}</div>`}
        </div>
      </details>
    </div>
  `;
      }).join('');

      const modalHtml = `
  <div style="text-align:left">
    <div style="margin-bottom:10px;color:#444;font-size:13px">
      ${__('Selecione o(s) modelo(s) para importar. Você pode abrir e desmarcar stages específicos.', 'plugins-alpha')}
    </div>

    <div style="max-height:380px;overflow:auto">
      ${htmlGroups}
    </div>

    <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <label style="display:flex;gap:8px;align-items:center;font-size:13px">
        <input type="checkbox" id="pga-import-overwrite" />
        ${__('Sobrescrever itens existentes', 'plugins-alpha')}
      </label>

      <button type="button" class="button" id="pga-import-select-all">${__('Marcar tudo', 'plugins-alpha')}</button>
      <button type="button" class="button" id="pga-import-select-none">${__('Desmarcar tudo', 'plugins-alpha')}</button>
    </div>
  </div>
`;


      let res;
      if (window.Swal) {
        res = await Swal.fire({
          title: __('Importar (seleção)', 'plugins-alpha'),
          html: modalHtml,
          width: 760,
          showCancelButton: true,
          confirmButtonText: __('Importar selecionados', 'plugins-alpha'),
          cancelButtonText: __('Cancelar', 'plugins-alpha'),
          focusConfirm: false,
          didOpen: () => {
            // marcar/desmarcar geral
            document.getElementById('pga-import-select-all')?.addEventListener('click', () => {
              document.querySelectorAll('.pga-import-item, .pga-import-tpl-all').forEach(cb => cb.checked = true);
            });
            document.getElementById('pga-import-select-none')?.addEventListener('click', () => {
              document.querySelectorAll('.pga-import-item, .pga-import-tpl-all').forEach(cb => cb.checked = false);
            });

            // marcar/desmarcar por template
            document.querySelectorAll('.pga-import-tpl-all').forEach(cbTpl => {
              cbTpl.addEventListener('change', () => {
                const tpl = cbTpl.getAttribute('data-tpl');
                if (!tpl) return;

                // marca/desmarca todos os itens daquele tpl
                // (como a key é pr:TPL:stage, a gente filtra por prefixo)
                document.querySelectorAll('.pga-import-item').forEach(cb => {
                  const key = cb.getAttribute('data-key') || '';
                  if (key.startsWith(`pr:${tpl}:`)) {
                    cb.checked = cbTpl.checked;
                  }
                });
              });
            });
          },

          preConfirm: () => {
            const keys = Array.from(document.querySelectorAll('.pga-import-item'))
              .filter(cb => cb.checked)
              .map(cb => cb.getAttribute('data-key'))
              .filter(Boolean);

            const overwrite = !!document.getElementById('pga-import-overwrite')?.checked;

            if (!keys.length) {
              Swal.showValidationMessage(__('Selecione ao menos 1 item.', 'plugins-alpha'));
              return false;
            }
            return { keys, overwrite };
          }
        });
      } else {
        res = { isConfirmed: true, value: { keys: [items[0].key], overwrite: false } };
      }

      if (!res.isConfirmed) return;

      // 3) APPLY
      if (window.Swal) {
        Swal.fire({ title: __('Importando…', 'plugins-alpha'), allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
      }

      const body = new URLSearchParams();
      body.set('action', 'pga_orion_prompts_import_apply');
      body.set('_ajax_nonce', nonce);
      body.set('token', token);
      body.set('overwrite', res.value.overwrite ? '1' : '0');
      body.set('keys', JSON.stringify(res.value.keys || []));

      const j2 = await fetchJSON(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body
      });
      if (!j2 || !j2.success) throw new Error(j2?.data?.message || __('Falha ao aplicar import.', 'plugins-alpha'));

      if (window.Swal) {
        Swal.fire({
          icon: 'success',
          title: __('Importado!', 'plugins-alpha'),
          text: j2.data?.message || __('Itens aplicados.', 'plugins-alpha'),
        }).then(() => {
          window.location.reload();
        });
      } else {
        alert(j2.data?.message || __('Importado!', 'plugins-alpha'));
        window.location.reload();
      }

    } catch (err) {
      if (window.Swal) Swal.fire({ icon: 'error', title: __('Erro', 'plugins-alpha'), text: String(err.message || err) });
      else alert(String(err.message || err));
    } finally {
      try { this.value = ''; } catch (e) { }
    }
  });

  document.addEventListener('click', async function (e) {
    const rm = e.target.closest('.pga-remove-tpl-row');
    if (!rm) return;

    const tr = rm.closest('tr');
    const slug = tr?.getAttribute('data-slug');
    if (!slug) return;

    const cfg = window.PGA_PROMPTS_EXPORT || {};
    const ajaxurl = cfg.ajaxurl || window.ajaxurl || '';
    const nonce = cfg.nonce || '';

    const go = async () => {
      const body = new URLSearchParams({
        action: 'pga_orion_template_delete',
        _ajax_nonce: nonce,
        slug
      });

      const j = await fetchJSON(ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body
      });
      if (!j || !j.success) throw new Error(j?.data?.message || __('Falha ao remover.', 'plugins-alpha'));
      tr.remove();
    };

    const ok = await pgaConfirm({
      icon: 'warning',
      title: __('Remover modelo?', 'plugins-alpha'),
      text: __('Isso apaga do banco o modelo e TODOS os prompts dele.', 'plugins-alpha'),
      confirmButtonText: __('Remover de vez', 'plugins-alpha'),
      cancelButtonText: __('Cancelar', 'plugins-alpha')
    });
    if (!ok) return;
    try {
      if (window.Swal) Swal.fire({ title: __('Removendo…', 'plugins-alpha'), allowOutsideClick: false, didOpen: () => Swal.showLoading() });
      await go();
      if (window.Swal) Swal.fire({ icon: 'success', title: __('Removido', 'plugins-alpha'), timer: 900, showConfirmButton: false });
    } catch (err) {
      if (window.Swal) Swal.fire({ icon: 'error', title: __('Erro', 'plugins-alpha'), text: String(err.message || err) });
      else alert(String(err.message || err));
    }
  });


  const KEY_TABS_INDEX = 'pga_orion_tabs_index_v1';
  const KEY_ACTIVE_TAB = 'pga_orion_active_tab_v1';

  function tabGroupsKey(tabId) {
    return `pga_orion_tab_${tabId}_groups_v1`;
  }

  function parseJson(raw, fallback) {
    try {
      const v = raw ? JSON.parse(raw) : null;
      return v ?? fallback;
    } catch (e) {
      return fallback;
    }
  }

  function makeId() {
    return 't_' + Date.now() + '_' + Math.random().toString(16).slice(2);
  }

  function getTabIdFromUrl() {
    const u = new URL(window.location.href);
    return u.searchParams.get('tab') || '';
  }

  function setUrlTabNoReload(tabId) {
    const u = new URL(window.location.href);
    u.searchParams.set('tab', tabId);
    history.replaceState({}, '', u.toString());
  }

  function buildTabUrl(tabId) {
    const u = new URL(window.location.href);
    u.searchParams.set('tab', tabId);
    return u.toString();
  }

  function getDefaultTemplatesFromButton() {
    const btn = document.getElementById('pga_tab_add');
    if (!btn) return ['article'];

    const raw = btn.getAttribute('data-default-templates') || '';
    if (!raw) return ['article'];

    try {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length) return arr.map(s => String(s || '').trim()).filter(Boolean);
    } catch (e) { /* ignore */ }

    return ['article'];
  }

  function loadTabs() {
    const tabs = parseJson(localStorage.getItem(KEY_TABS_INDEX), []);
    return Array.isArray(tabs) ? tabs : [];
  }

  function saveTabs(tabs) {
    localStorage.setItem(KEY_TABS_INDEX, JSON.stringify(tabs || []));
  }

  function ensureTabsAndTabId() {
    let tabs = loadTabs();

    if (!tabs.length) {
      const first = { id: makeId(), title: __('Projeto 1', 'plugins-alpha') };
      tabs = [first];
      saveTabs(tabs);
    }

    let tabId = getTabIdFromUrl();

    if (!tabId) {
      tabId = localStorage.getItem(KEY_ACTIVE_TAB) || tabs[0].id;
      setUrlTabNoReload(tabId);
    }

    if (!tabs.some(t => t.id === tabId)) {
      tabId = tabs[0].id;
      setUrlTabNoReload(tabId);
    }

    localStorage.setItem(KEY_ACTIVE_TAB, tabId);
    return { tabs, tabId };
  }

  function renderTabsUI(tabs, tabId) {
    const $wrap = $('#pga_tabs');
    if (!$wrap.length) return;

    $wrap.empty();

    tabs.forEach(t => {
      const active = (t.id === tabId);

      const $a = $('<a/>', {
        class: 'button ' + (active ? 'button-primary' : ''),
        href: buildTabUrl(t.id),
      });

      // conteúdo interno do botão
      const $label = $('<span/>', {
        class: 'pga-tab-label',
        text: t.title || 'Projeto',
        css: { display: 'inline-flex', alignItems: 'center' }
      });

      // lixeira dentro do botão
      const $trash = $('<span/>', {
        class: 'pga-tab-trash',
        html: 'x',
        title: 'Excluir aba',
      });

      // hover só no ícone (fica mais “limpo”)
      $trash.on('mouseenter', function () { $(this).css('opacity', '1'); });
      $trash.on('mouseleave', function () { $(this).css('opacity', '0.75'); });

      // clicar na lixeira NÃO navega, só exclui
      $trash.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        pgaDeleteTab(t.id);
      });

      // monta
      $a.append($label, $trash);
      $wrap.append($a);
    });
  }

  function buildGroupsForNewProject() {
    const defaults = getDefaultTemplatesFromButton(); // ['pilar', 'artigo_topo', ...] ou ['article']
    // monta estrutura igual serialize usa, só o necessário
    return defaults.map((tplKey) => ({
      keywords: '',
      locale: 'pt_BR',
      template_key: tplKey || 'article',
      category: '0',
      total: 6,
      per_day: 3,
      first_delay: '',
      length: 'short',
      link_max: 2,
      internal_links: { mode: 'none', max: 0, manual_ids: '' }
    }));
  }

  function addTabAndGo(name) {
    const tabs = loadTabs();
    const nextNum = tabs.length + 1;

    const title = String(name || '').trim() || (__('Projeto ', 'plugins-alpha') + nextNum);

    const tab = { id: makeId(), title };
    tabs.push(tab);
    saveTabs(tabs);

    // ✅ cria grupos default já na tab nova
    try {
      const groups = buildGroupsForNewProject();
      saveGroupsForTab(tab.id, groups);
    } catch (e) { }
    localStorage.setItem(KEY_ACTIVE_TAB, tab.id);
    window.location.href = buildTabUrl(tab.id);
  }

  function ensureBoxesCount(targetCount) {
    // conta boxes já existentes
    let $boxes = $('#pga_gen_container .pga-gen-box');
    let n = $boxes.length;

    if (n >= targetCount) return;

    // clica no seu botão de adicionar grupo até bater
    const $add = $('#pga_add_box');
    if (!$add.length) return;

    while (n < targetCount) {
      $add.trigger('click');
      n++;
    }
  }

  function loadTabGroups(tabId) {
    // 1) migra 1 vez (se necessário)
    migrateLegacyGroupsToTabOnce(tabId);

    // 2) carrega do storage oficial
    const groups = loadGroupsForTab(tabId);

    // 3) aplica no DOM
    applyGroupsToDom(groups);
  }


  function saveCurrentTabGroups(tabId) {
    const groups = serializeAllGroupsFromDom();
    saveGroupsForTab(tabId, groups);
  }

  function bindAutoSave(tabId) {
    // evita duplicar handlers se esse init rodar mais de uma vez
    $(document).off('change.pgaTab click.pgaTab');
    // beforeunload: não dá pra "off" fácil, então guardamos flag
    if (!window.PGA_TAB_BEFOREUNLOAD_BOUND) {
      window.PGA_TAB_BEFOREUNLOAD_BOUND = true;
      window.addEventListener('beforeunload', function () {
        try {
          const tid = localStorage.getItem(KEY_ACTIVE_TAB) || getTabIdFromUrl() || '';
          if (tid) saveCurrentTabGroups(tid);
        } catch (e) { }
      });
    }

    // debounce simples
    let t = null;
    const scheduleSave = () => {
      clearTimeout(t);
      t = setTimeout(() => {
        try { saveCurrentTabGroups(tabId); } catch (e) { }
      }, 450);
    };

    // salva em mudanças
    $(document).on('change.pgaTab', '#pga_gen_container .pga-gen-box :input', function (e) {
      if (e && e.isTrigger) return;
      scheduleSave();
    });

  }


  async function pgaDeleteTab(tabId) {
    if (!tabId) return;

    const tabs = loadTabs(); // << seu nome
    const idx = tabs.findIndex(t => t.id === tabId);
    if (idx === -1) return;

    const name = tabs[idx].title || __('Projeto', 'plugins-alpha');

    const ok = await pgaConfirm({
      title: __('Excluir aba?', 'plugins-alpha'),
      text: sprintf(
        __('Você tem certeza que deseja excluir <b>%s</b>?<br><br><small>Isso apaga os grupos salvos dessa aba.</small>', 'plugins-alpha'),
        escapeHtml(name)
      ),
      icon: 'warning',
      confirmButtonText: __('Sim, excluir', 'plugins-alpha'),
      cancelButtonText: __('Cancelar', 'plugins-alpha')
    });

    if (!ok) return;

    // apaga storage dessa tab
    try {
      localStorage.removeItem(`pga_orion_tab_${tabId}_groups_v1`);
    } catch (e) { }

    // remove do index
    tabs.splice(idx, 1);

    // se ficou vazio, recria uma default
    if (!tabs.length) {
      const first = { id: makeId(), title: __('Projeto 1', 'plugins-alpha') };
      tabs.push(first);
      saveTabs(tabs);

      const u = new URL(window.location.href);
      u.searchParams.set('tab', first.id);
      window.location.href = u.toString();
      return;
    }

    // salva index
    saveTabs(tabs);

    // se estava na tab deletada, vai pra primeira
    const cur = (typeof pgaGetTabId === 'function')
      ? pgaGetTabId()
      : (new URL(window.location.href).searchParams.get('tab') || '');

    if (cur === tabId) {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', tabs[0].id);
      window.location.href = u.toString();
      return;
    }

    // senão, só re-renderiza
    if (typeof renderTabsUI === 'function') {
      renderTabsUI(tabs, cur);
    }
  }



  $(function () {
    // 1) garante tabId
    const st = ensureTabsAndTabId();

    // 2) render tabs se existir o container (depois a gente põe no PHP)
    renderTabsUI(st.tabs, st.tabId);

    // 3) botão adicionar tab (se existir no PHP)
    $('#pga_tab_add').off('click').on('click', async function () {
      // salva antes de criar (pra não perder ajustes)
      try { saveCurrentTabGroups(st.tabId); } catch (e) { }

      let name = '';
      if (window.Swal) {
        const res = await Swal.fire({
          html: `
            <div class="pga-modal-content">
              <h3 style="margin:0">${__('Novo projeto', 'plugins-alpha')}</h3>
              <div class="pga-descricao">
                ${__('Crie um novo projeto para organizar seus geradores de conteúdo.', 'plugins-alpha')}
              </div>
              <div class="pga-field">
                <label for="pga_new_project_name">${__('Nome do Projeto', 'plugins-alpha')}</label>
                <input id="pga_new_project_name" class="swal2-input" placeholder="${__('Ex: Blog de Marketing', 'plugins-alpha')}" style="width:100%;margin:0" />
              </div>
            </div>
          `,
          showCancelButton: true,
          focusConfirm: false,
          cancelButtonText: __('Cancelar', 'plugins-alpha'),
          confirmButtonText: __('Criar Projeto', 'plugins-alpha'),
          preConfirm: () => {
            const v = document.getElementById('pga_new_project_name')?.value || '';
            return String(v).trim();
          }
        });

        if (!res.isConfirmed) return;
        name = res.value || '';
      } else {
        name = prompt(__('Nome do projeto:', 'plugins-alpha')) || '';
        if (!String(name).trim()) return;
      }

      addTabAndGo(name);
    });


    // 4) carrega dados dessa tab nos colapses existentes
    loadTabGroups(st.tabId);

    // 5) autosave
    bindAutoSave(st.tabId);
    setTimeout(() => {
      try { saveCurrentTabGroups(st.tabId); } catch (e) { }
    }, 800);

  });

})(jQuery);
