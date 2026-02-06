(function ($) {
    'use strict';

    let BUSY = false;
    const REST = PGA_CFG.rest;
    const NONCE = PGA_CFG.nonce;

    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (e) {
            return false;
        }
    }

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
            return
        }

        return data;
    }

    function getRSSBoxData($box) {
        return {
            source_url: $box.find('.pga_keywords').val().trim(),
            category: $box.find('.pga_category').val(),
            length: $box.find('.pga_length').val(),
            per_day: parseInt($box.find('.pga_per_day').val(), 10) || 1,
            update_interval: parseInt($box.find('.pga_quota_day').val(), 10) || 1,
            make_faq: $box.find('.pga_make_faq').is(':checked'),
            faq_qty: parseInt($box.find('.pga_faq_qty').val(), 10) || 0,
            locale: $box.find('.pga_locale').val(),
            tags: $box.find('.pga_tags').val() || []
        };
    }

    function alertMsg(type, text) {
        if (window.Swal) {
            Swal.fire({
                icon: type,
                text,
                confirmButtonText: 'OK'
            });
        } else {
            alert(text);
        }
    }

    async function runRSS($box) {
        const data = getRSSBoxData($box);

        if (!data.source_url) {
            alertMsg('warning', 'Informe a URL do RSS.');
            return;
        }

        if (!isValidUrl(data.source_url)) {
            alertMsg('error', 'URL inválida.');
            return;
        }

        try {
            BUSY = true;

            Swal.fire({
                title: 'Processando RSS…',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            const res = await fetch(PGA_CFG.rest + '/rss/run', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': PGA_CFG.nonce
                },
                body: JSON.stringify(data)
            });

            const json = await res.json();
            Swal.close();

            if (!res.ok || !json.ok) {
                throw new Error(json.message || 'Erro ao processar RSS');
            }

            alertMsg(
                'success',
                `RSS processado. ${json.created || 0} posts agendados.`
            );

        } catch (err) {
            Swal.close();
            alertMsg('error', err.message || 'Erro inesperado');
        } finally {
            BUSY = false;
        }
    }

    // =========================
    // Bind
    // =========================

    // $(document).on('click', '#pga_gen_container .pga_generate_box', function () {
    //     if (BUSY) return;

    //     const $box = $(this).closest('.pga-gen-box');
    //     runRSS($box);
    // });

    $(document).on('click', '#pga_test_box', async function () {
        const payload = {
            url: $('.pga_keywords').val().trim(),
            limit: 10
        };

        if (!payload.url) {
            console.warn('URL vazia');
            return;
        }

        try {
            const data = await fetchJSON(`${REST}/rss/get`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': NONCE
                },
                body: JSON.stringify(payload)
            });

            console.log('RSS OK');
            console.table(data.items || []);

        } catch (err) {
            console.error('Erro RSS:', err);
        }
    });


})(jQuery);
