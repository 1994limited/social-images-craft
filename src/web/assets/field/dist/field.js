/*
    The Social image field's preview. Asks the status action for the
    entry's image; while it's missing or out of date (just saved, or the
    template changed) that starts making it, and the preview asks again,
    less often as time goes on, until it lands. Regenerate remakes it now.
*/
(function () {
    const t = (message, params) => (window.Craft ? Craft.t('social-images', message, params) : message);

    function setUp(field) {
        if (field.dataset.socialImageReady) {
            return;
        }

        field.dataset.socialImageReady = '1';

        const config = JSON.parse(field.dataset.socialImage || 'null');

        if (!config) {
            return;
        }

        const frame = field.querySelector('[data-frame]');
        const status = field.querySelector('[data-status]');
        const note = field.querySelector('[data-note]');
        const button = field.querySelector('[data-regenerate]');
        let timer = null;
        let waited = 0;

        function show(url, ratio) {
            frame.innerHTML = '';
            frame.style.aspectRatio = ratio || '1200 / 630';
            const img = document.createElement('img');
            img.src = url;
            img.alt = '';
            frame.appendChild(img);
        }

        function message(text) {
            frame.querySelectorAll('img').forEach((img) => img.classList.add('si-field__img--faded'));
            status.textContent = text;
            if (!status.isConnected) {
                frame.appendChild(status);
            }
        }

        async function poll() {
            clearTimeout(timer);

            let data;

            try {
                const response = await fetch(config.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                data = await response.json();
            } catch {
                note.textContent = t('Couldn’t check the image.');
                return;
            }

            const ratio = data.width && data.height ? `${data.width} / ${data.height}` : null;

            if (data.override) {
                show(data.override, null);
                note.textContent = t('Using the image chosen below.');
                return;
            }

            if (data.state === null) {
                frame.innerHTML = '';
                frame.appendChild(status);
                status.textContent = t('No template applies to this entry.');
                button.hidden = true;
                return;
            }

            button.hidden = false;
            note.textContent = data.template ? t('Template: {template}', { template: data.template }) : '';

            if (data.url) {
                show(data.url, ratio);
                return;
            }

            if (data.previous && !frame.querySelector('img')) {
                show(data.previous, ratio);
            }

            if (data.state === 'failed') {
                message(t('The image couldn’t be made. See the log for details.'));
                return;
            }

            message(data.state === 'rendering' ? t('Rendering… {s} s', { s: data.seconds }) : t('Waiting to start…'));

            // Quicker at first, then every 10 s, for up to 10 minutes.
            const delay = waited < 20000 ? 2000 : 10000;
            waited += delay;

            if (waited < 600000) {
                timer = setTimeout(poll, delay);
            }
        }

        button?.addEventListener('click', async () => {
            button.classList.add('loading');
            button.disabled = true;
            message(t('Rendering…'));

            try {
                const response = await Craft.sendActionRequest('POST', 'social-images/images/regenerate', {
                    data: { entryId: config.entryId, siteId: config.siteId },
                });
                show(response.data.url);
                Craft.cp.displaySuccess(t('Image generated'));
            } catch (error) {
                const data = error?.response?.data ?? {};
                message(data.message || t('The image couldn’t be generated.'));
                Craft.cp.displayError(data.message || t('The image couldn’t be generated.'));
            } finally {
                button.classList.remove('loading');
                button.disabled = false;
            }
        });

        poll();
    }

    function scan() {
        document.querySelectorAll('[data-social-image]').forEach(setUp);
    }

    scan();
    new MutationObserver(scan).observe(document.body, { childList: true, subtree: true });
})();
