(function ($) {
    'use strict';

    const REST = PGA_CFG.rest;
    const NONCE = PGA_CFG.nonce;
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
                        title: __('Resposta não-JSON', 'alpha-suite'),
                        html: sprintf(
                            __('<p><b>HTTP</b>: %d</p><pre style="white-space:pre-wrap;max-height:320px;overflow:auto;border:1px solid #eee;padding:8px;border-radius:6px;">%s</pre>', 'alpha-suite'),
                            res.status,
                            safe
                        )
                    });
                } else {
                    alert(sprintf(__('Erro: resposta não-JSON (%d)', 'alpha-suite'), res.status));
                }
            }

            const err = new Error(sprintf(__('Non-JSON %d', 'alpha-suite'), res.status));
            err.status = res.status;
            err.rawBody = text;
            throw err;
        }

        if (!res.ok) {
            const msg = (data && (data.message || data.code)) || sprintf(__('HTTP %d', 'alpha-suite'), res.status);
            if (!silent) {
                if (window.Swal) {
                    await Swal.fire({ icon: 'error', title: __('Falha na chamada', 'alpha-suite'), text: String(msg) });
                } else {
                    alert(sprintf(__('Erro: %s', 'alpha-suite'), String(msg)));
                }
            }
            return
        }

        return data;
    }

    async function fetchJSON(url, options = {}) {
        const res = await fetch(url, options);
        const data = await res.json();

        if (!res.ok) {
            const msg = data?.message || `HTTP ${res.status}`;
            throw new Error(msg);
        }

        return data;
    }

    function openStatusModal() {
        Swal.fire({
            title: 'Gerando conteúdo...',
            html: '<div id="pga-status" style="text-align:left"></div>',
            allowOutsideClick: false,
            showConfirmButton: false
        });
    }

    function updateStatus(text) {
        const el = document.getElementById('pga-status');
        if (el) {
            el.innerHTML += '• ' + text + '<br>';
        }
    }

    $(document).on('click', '.pga_test_box', async function () {

        const box = $(this).closest('.pga-gen-box');

        const feedUrl = box.find('.pga_keywords').val()?.trim();
        const keyword = box.find('.rssKeyword').val()?.trim();

        if (!feedUrl) {
            Swal.fire('Erro', 'Informe uma URL válida.', 'error');
            return;
        }

        try {

            const data = await fetchJSON(`${REST}/rss/get`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify({
                    url: feedUrl,
                    limit: keyword ? 50 : 10,
                    keyword: keyword
                })
            });

            const items = data.items || [];

            if (!items.length) {
                Swal.fire('Nenhum item encontrado.');
                return;
            }

            // 📋 Selecionar item
            const inputOptions = {};
            items.forEach((item, index) => {
                inputOptions[index] = item.title;
            });

            const { value: selected } = await Swal.fire({
                title: 'Selecione o item',
                input: 'radio',
                inputOptions,
                confirmButtonText: 'Gerar',
                inputValidator: (value) => {
                    if (!value) return 'Selecione um item';
                }
            });

            if (selected === undefined) return;

            const item = items[selected];

            openStatusModal();

            // 🚀 START
            updateStatus('Criando post base...');
            const box = $(this).closest('.pga-gen-box');

            const payload = {
                // dados do item RSS selecionado
                title: item.title,
                hash: item.hash,
                link: item.link,
                source: item.source,
                manual: true,

                // config do gerador
                length: box.find('.pga_length').val() || 'short',
                locale: box.find('.pga_locale').val() || 'pt_BR',
                category_id: parseInt(box.find('.pga_category').val() || 0),

                tags: box.find('.pga_tags').val() || [],

                make_faq: box.find('.pga_make_faq').is(':checked'),
                faq_qty: parseInt(box.find('.pga_faq_qty').val() || 0),

                link_mode: box.find('.pga_link_mode').val() || 'none',
                link_max: parseInt(box.find('.pga_link_max').val() || 1),
                link_manual_ids: box.find('.pga_link_manual').val() || [],

                per_day: parseInt(box.find('.pga_per_day').val() || 3),
                quota_day: parseInt(box.find('.pga_quota_day').val() || 1),
            };

            const start = await fetchJSON(`${REST}/rss/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify(payload)
            });

            if (start.duplicate) {
                Swal.fire('Duplicado', 'Item já publicado.', 'info');
                return;
            }

            const postId = start.post_id;

            // 🏷 TITLE
            updateStatus('Gerando título...');
            await fetchJSON(`${REST}/rss/title`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                body: JSON.stringify({ post_id: postId })
            });

            // 📚 OUTLINE
            updateStatus('Gerando outline...');
            const outline = await fetchJSON(`${REST}/rss/outline`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                body: JSON.stringify({ post_id: postId })
            });

            const sections = outline.sections || [];

            // ✍ SECTIONS
            let i = 0;
            for (const sec of sections) {
                updateStatus(`Gerando seção ${++i}/${sections.length}...`);

                await fetchJSON(`${REST}/rss/section`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                    body: JSON.stringify({
                        post_id: postId,
                        section_id: sec.id
                    })
                });
            }

            // 🔗 SLUG
            updateStatus('Gerando slug...');
            await fetchJSON(`${REST}/rss/slug`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                body: JSON.stringify({ post_id: postId })
            });

            // 📝 META
            updateStatus('Gerando meta...');
            await fetchJSON(`${REST}/rss/meta`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                body: JSON.stringify({ post_id: postId })
            });

            if (box.find('.pga_make_faq').is(':checked')) {
                updateStatus('Gerando faq...');
                await fetchJSON(`${REST}/rss/faq`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                    body: JSON.stringify({ post_id: postId })
                });
            }

            // 🖼 IMAGE
            updateStatus('Extraindo imagem...');
            await fetchJSON(`${REST}/rss/extract-image`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                body: JSON.stringify({
                    post_id: postId,
                    url: item.link
                })
            });

            // 🧩 FINALIZE
            updateStatus('Finalizando conteúdo...');

            const result = await fetchJSON(`${REST}/rss/finalize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify({ post_id: postId })
            });


            const postTitle = result?.title || 'Ver post';
            const postUrl = result?.url || '#';

            await Swal.fire({
                icon: 'success',
                title: __('Concluído!', 'alpha-suite'),
                html: `
                    <div style="text-align:center;font-size:13px;color:#374151;margin-bottom:10px;">
                        ${__('Post criado com sucesso.', 'alpha-suite')}
                    </div>
                    <div style="text-align:center;">
                        <a href="${postUrl}" target="_blank" 
                        style="display:inline-block;padding:8px 14px;
                                background:#0f172a;color:#fff;
                                border-radius:6px;text-decoration:none;
                                font-size:13px;">
                            ${postTitle}
                        </a>
                    </div>
                `,
                confirmButtonColor: '#0f172a'
            });

        } catch (err) {
            Swal.fire('Erro', err.message, 'error');
        }

    });
})(jQuery);
