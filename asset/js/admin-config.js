(function (window, document) {
    const baseSelect = document.getElementById('globallandingpage_base_site');
    const navSelect = document.getElementById('globallandingpage_nav_pages');

    if (!baseSelect || !navSelect) {
        return;
    }

    const selectedValuesAttr = navSelect.getAttribute('data-selected-values');
    const apiEndpoint = navSelect.getAttribute('data-api-endpoint');
    const loadingLabel = navSelect.getAttribute('data-loading-label') || 'Loading…';
    const emptyLabel = navSelect.getAttribute('data-empty-label') || 'No pages found for the selected site.';

    function setOptions(options) {
        const fragment = document.createDocumentFragment();
        options.forEach(function (option) {
            fragment.appendChild(option);
        });
        navSelect.innerHTML = '';
        navSelect.appendChild(fragment);
    }

    function renderPlaceholder(text, disabled) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = text;
        option.disabled = disabled === true;
        option.selected = true;
        setOptions([option]);
    }

    function parseSelectedValues() {
        if (!selectedValuesAttr) {
            return [];
        }
        return selectedValuesAttr
            .split(',')
            .map(function (value) {
                return value.trim();
            })
            .filter(function (value) {
                return value !== '';
            });
    }

    const initialSelected = new Set(parseSelectedValues());

    function applySelection() {
        if (!initialSelected.size) {
            return;
        }
        Array.prototype.forEach.call(navSelect.options, function (option) {
            option.selected = initialSelected.has(option.value);
        });
    }

    async function fetchPages(siteId) {
        if (!apiEndpoint || !siteId) {
            renderPlaceholder(emptyLabel, true);
            navSelect.disabled = true;
            return;
        }

        try {
            navSelect.disabled = true;
            renderPlaceholder(loadingLabel, true);

            const url = new URL(apiEndpoint, window.location.origin);
            url.searchParams.set('site_id', String(siteId));
            url.searchParams.set('sort_by', 'position');
            url.searchParams.set('sort_order', 'asc');
            url.searchParams.set('limit', '0');

            const response = await window.fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load site pages.');
            }

            const payload = await response.json();
            const pages = Array.isArray(payload['hydra:member']) ? payload['hydra:member'] : payload;

            if (!Array.isArray(pages) || pages.length === 0) {
                renderPlaceholder(emptyLabel, true);
                navSelect.disabled = true;
                return;
            }

            const options = pages
                .map(function (page) {
                    const slug = page['o:slug'] || (Array.isArray(page['o:page']) ? page['o:page'][0] : '');
                    const title = page['o:title'] || slug;
                    if (!slug) {
                        return null;
                    }
                    const option = document.createElement('option');
                    option.value = slug;
                    option.textContent = title || slug;
                    return option;
                })
                .filter(Boolean);

            if (!options.length) {
                renderPlaceholder(emptyLabel, true);
                navSelect.disabled = true;
                return;
            }

            setOptions(options);
            applySelection();
            navSelect.disabled = false;
        } catch (error) {
            console.error(error);
            renderPlaceholder(emptyLabel, true);
            navSelect.disabled = true;
        }
    }

    baseSelect.addEventListener('change', function () {
        initialSelected.clear();
        const value = baseSelect.value;
        if (!value) {
            renderPlaceholder(emptyLabel, true);
            navSelect.disabled = true;
            return;
        }
        fetchPages(value);
    });

    if (!baseSelect.value) {
        navSelect.disabled = true;
    } else {
        fetchPages(baseSelect.value);
    }
})(window, document);
