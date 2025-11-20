/* global PGA_CFG, Swal */
(function ($) {
  const REST = PGA_CFG.rest;
  const NONCE = PGA_CFG.nonce;

  // ------------------ utils ------------------
  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); }
    catch (e) {
      if (window.Swal) {
        await Swal.fire({
          icon: 'error', title: 'Resposta não-JSON',
          html: `<p><b>HTTP</b>: ${res.status}</p><pre style="white-space:pre-wrap;max-height:320px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:6px;">${text.replace(/[<>&]/g, s => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[s]))}</pre>`
        });
      } else { alert('Erro: resposta não-JSON (' + res.status + ')'); }
      throw new Error('Non-JSON ' + res.status);
    }
    if (!res.ok) {
      const msg = (data && (data.message || data.code)) || `HTTP ${res.status}`;
      if (window.Swal) await Swal.fire({ icon: 'error', title: 'Falha na chamada', text: String(msg) });
      else alert('Erro: ' + msg);
      throw new Error(msg);
    }
    return data;
  }

  async function safeCloseSwal() {
    try {
      if (window.Swal && Swal.isVisible()) Swal.close();
    } catch (e) { }
  }

  function showLoading(title = 'Processando…') {
    try {
      // não await!
      Swal.fire({
        title, allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false,
        didOpen: () => Swal.showLoading()
      });
    } catch (e) { }
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
    if (!$kw.length) return; // não é a tela do gerador

    // ---------- Preferências da UI (localStorage) ----------
    const PREF_KEY = 'pga_prefs_v1';
    function loadPrefs() {
      try {
        const p = JSON.parse(localStorage.getItem(PREF_KEY) || '{}');
        if (p.locale) $('#pga_locale').val(p.locale);
        if (p.category_id) $('#pga_category').val(String(p.category_id));
        if (p.template_key) $('#pga_template_key').val(p.template_key);
        if (p.length) $('#pga_length').val(p.length);
        if (p.source_url) $('#pga_source_url').val(p.source_url);
        if (p.total) $('#pga_total').val(String(p.total));
        if (p.per_day) $('#pga_per_day').val(String(p.per_day));
        if (p.first_delay_hours) $('#pga_first_delay_hours').val(String(p.first_delay_hours));
        if (p.mode) $(`input[name="pga_mode"][value="${p.mode}"]`).prop('checked', true);
      } catch (e) { }
    }
    function collectPrefs() {
      return {
        locale: $('#pga_locale').val(),
        length: $('#pga_length').val(),
        template_key: $('#pga_template_key').val(),
        source_url: ($('#pga_source_url').val() || '').trim(),
        category_id: parseInt($('#pga_category').val() || '0', 10),
        total: parseInt($('#pga_total').val() || '1', 10),
        per_day: parseInt($('#pga_per_day').val() || '1', 10),
        first_delay_hours: parseInt($('#pga_first_delay_hours').val() || '2', 10),
        mode: $('input[name="pga_mode"]:checked').val() || 'multi'
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
      renderDone(j.done || []);
    }

    function renderDone(list) {
      $done.empty();
      (list || []).forEach(k => $('<li/>').text(k).appendTo($done));
    }

    // ---------- Importar via .txt ----------
    // cria input[type=file] invisível e acopla ao botão Importar
    if (!$('#pga_kw_file').length) {
      const $file = $('<input type="file" id="pga_kw_file" accept=".txt,text/plain" style="display:none">');
      $('body').append($file);
      $('#pga_kw_import').off('click').on('click', () => $('#pga_kw_file').trigger('click'));
      $file.on('change', function () {
        const f = this.files && this.files[0];
        if (!f) return;
        const reader = new FileReader();
        reader.onload = async function (ev) {
          const text = String(ev.target.result || '');
          const cur = textareaToArray($kw.val());
          const neu = textareaToArray(text);
          const set = Array.from(new Set(cur.concat(neu)));
          $kw.val(set.join('\n'));
          // não salva automaticamente — só quando clicar em "Salvar"
          $('#pga_kw_file').val('');
          await Swal.fire({ icon: 'info', title: 'Importado', text: `${neu.length} linhas foram carregadas. Clique em "Salvar" para persistir.` });
        };
        reader.readAsText(f, 'utf-8');
      });
    }

    // ---------- Exportar .txt ----------
    $('#pga_kw_export').off('click').on('click', () => {
      const blob = new Blob([$kw.val() || ''], { type: 'text/plain;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'keywords.txt';
      a.click();
      URL.revokeObjectURL(a.href);
    });

    // ---------- Salvar (sem autosave) ----------
    $('#pga_save_keywords').off('click').on('click', async function () {
      const btn = this;
      btn.disabled = true;

      try {
        savePrefsToLocal(); // salva preferências da UI (localStorage)

        await safeCloseSwal();
        // 🔧 NÃO usar await aqui!
        Swal.fire({
          title: 'Salvando…',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading()
        });

        const pending_text = $('#pga_keywords').val();
        const j = await fetchJSON(`${PGA_CFG.rest}/keywords`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': PGA_CFG.nonce },
          body: JSON.stringify({ pending_text })
        });

        renderDone(j.done || []);

        await safeCloseSwal(); // fecha o loading
        await Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Salvo',
          text: 'Keywords salvas e preferências guardadas nesta máquina.',
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false,
          customClass: {
            popup: 'pga-toast-offset'
          }
        });


      } catch (e) {
        await safeCloseSwal();
        // fetchJSON já mostrou erro (se quiser, pode exibir outro Swal aqui)
      } finally {
        btn.disabled = false;
      }
    });


    // ---------- Limpar listas ----------
    $('#pga_kw_clear_pending').off('click').on('click', async () => {
      const ok = window.Swal ? (await Swal.fire({ icon: 'warning', title: 'Limpar pendentes?', showCancelButton: true })).isConfirmed : confirm('Limpar pendentes?');
      if (!ok) return;
      await fetchJSON(`${REST}/keywords/clear`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
        body: JSON.stringify({ who: 'pending' })
      });
      await refreshKeywords();
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
    $('#pga_plan').off('click').on('click', async () => {
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

        // Se seu endpoint retornar algo tipo { ok: true }, você pode checar aqui:
        if (st && st.ok === false) {
          await Swal.fire({
            icon: 'error',
            title: 'Configuração necessária',
            text: st.message || 'Sua licença ou chave de API não está configurada. Verifique a tela de configurações do Plugins Alpha.'
          });
          return;
        }
      } catch (e) {
        // Se o selftest já devolve WP_Error com code/message, tratamos aqui:
        let msg = 'Não foi possível validar a licença / chave de API.';

        if (e && typeof e === 'object') {
          // se seu fetchJSON devolver { code, message }
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
        // Não continua se não passou na validação
        return;
      }

      // === 1) REGRAS BÁSICAS DE KEYWORDS ===
      if (prefs.mode === 'multi' && kwList.length === 0) {
        await Swal.fire({
          icon: 'warning',
          title: 'Sem palavras-chave',
          text: 'Insira ao menos 1 palavra-chave.'
        });
        return;
      }

      if (prefs.mode === 'multi' && kwList.length < prefs.total) {
        const ok = (await Swal.fire({
          icon: 'question',
          title: 'Quantidade insuficiente',
          html: `Você pediu <b>${prefs.total}</b> posts mas só tem <b>${kwList.length}</b> palavras. Gerar ${kwList.length}?`,
          showCancelButton: true
        })).isConfirmed;
        if (!ok) return;
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
            source_url: prefs.source_url,
            total: prefs.total,
            per_day: prefs.per_day,
            first_delay_hours: prefs.first_delay_hours,
            transition,
            category_id: prefs.category_id
          })
        });
      } catch (e) {
        // AQUI É ONDE HOJE VOCÊ SÓ DAVA "return" – AGORA VAMOS MOSTRAR UMA MENSAGEM BONITA
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

        await Swal.fire({
          icon: 'error',
          title,
          text: msg
        });

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


      async function generateExtraLongPost(job) {
        // 1) OUTLINE – manda TUDO que o planner calculou, inclusive publish_time
        const outlineRes = await fetchJSON(`${REST}/orion/outline`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body: JSON.stringify({
            // compat com o PHP (ele aceita keyword OU keywords)
            keyword: job.keyword,
            keywords: [job.keyword],
            length: job.length,
            locale: job.locale,
            template: job.template_key,
            template_key: job.template_key,
            source_url: job.source_url,
            publish_time: job.publish_time,
            category_id: job.category_id,
            post_type: 'posts_orion',
          }),
        });

        if (!outlineRes || outlineRes.code) {
          throw new Error(outlineRes?.message || 'Erro ao gerar esboço');
        }

        const postId = outlineRes.post_id;
        const sections = outlineRes.sections || [];

        // 2) GERA CADA SEÇÃO EM SEQUÊNCIA
        for (const section of sections) {
          const sid = section.id;

          const secRes = await fetchJSON(`${REST}/orion/section`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
            body: JSON.stringify({ post_id: postId, section_id: sid }),
          });

          if (secRes && secRes.code) {
            throw new Error(secRes.message || `Erro ao gerar seção ${sid}`);
          }
        }

        const finRes = await fetchJSON(`${REST}/orion/finalize`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
          body: JSON.stringify({ post_id: postId }),
        });

        if (finRes && finRes.code) {
          throw new Error(finRes.message || 'Erro ao finalizar post');
        }

        return finRes;
      }

      await Swal.fire({
        title: 'Gerando posts…',
        html: `
            <div id="pga_loader" style="display:flex;align-items:center;gap:8px;justify-content:center;margin-bottom:6px">
              <!-- spinner svg -->
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

            try {
              // 🔹 fluxo novo, SEM /generate
              const r = await generateExtraLongPost(j);

              okCount++;
              // ===== LINK DE EDIÇÃO =====
              if (r.edit || r.post_id || r.view_link) {
                let editUrl = '';

                // 1) se o back mandar URL completa em r.edit, usa direto
                if (typeof r.edit === 'string' && r.edit.indexOf('http') === 0) {
                  editUrl = r.edit;
                } else {
                  // 2) se r.edit for ID ou vier só post_id, monta URL absoluta
                  const postId = r.post_id || r.edit;
                  if (postId) {
                    const base = window.location.origin || '';
                    editUrl = `${base}/wp-admin/post.php?post=${postId}&action=edit`;
                  }
                }

                if (editUrl) {
                  const labelId = r.post_id || r.edit;
                  editLinks.push(
                    `<li><a target="_blank" rel="noopener" href="${editUrl}">Editar #${labelId}</a></li>`
                  );
                }
              }

              // ===== ATUALIZAÇÃO DE PALAVRAS-CHAVE =====
              if (r.state) {
                // comportamento igual ao antigo, se o back mandar state
                $('#pga_keywords').val((r.state.pending || []).join('\n'));
                $('#pga_kw_done').empty().append(
                  (r.state.done || []).map(k => `<li>${k}</li>`).join('')
                );
              } else {
                // 🔁 fallback: mover keyword do job manualmente
                let kw = '';

                if (j.keyword) {
                  kw = j.keyword.trim();
                } else if (j.keywords) {
                  if (Array.isArray(j.keywords)) {
                    kw = (j.keywords[0] || '').toString().trim();
                  } else {
                    kw = String(j.keywords).split('\n')[0].trim();
                  }
                }

                if (kw) {
                  // remove essa keyword do textarea de pendentes
                  const lines = $('#pga_keywords')
                    .val()
                    .split('\n')
                    .map(l => l.trim())
                    .filter(l => l && l !== kw);

                  $('#pga_keywords').val(lines.join('\n'));

                  // acrescenta na lista de concluídas (sem limpar)
                  $('#pga_kw_done').append(`<li>${kw}</li>`);
                }
              }

            } catch (e) {
              failCount++;
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

      await Swal.fire({
        icon: (failCount ? 'warning' : 'success'),
        title: 'Finalizado',
        html: `
          Sucesso: <b>${okCount}</b><br>
          Falhas: <b>${failCount}</b><br>
          <ul style="text-align:left;margin-top:8px">
            ${editLinks.join('')}
          </ul>
        `
      });
    });


    // carrega listas
    try { await refreshKeywords(); } catch (e) { }
  }

  // boot
  $(async function () {
    if (onSettingsPage()) await bootSettings();
    await bootGenerator();
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

