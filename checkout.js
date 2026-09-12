(() => {
    const CART_KEY = 'farmacia_cart_v2';
    const form = document.getElementById('checkout-form');
    if (!form) return;

    const csrf = form.querySelector('[name="csrf_token"]').value;
    const cpInput = document.getElementById('codigo_postal');
    const cartInput = document.getElementById('cart_json');
    const methodInput = document.getElementById('envio_metodo');
    const options = document.getElementById('shipping-options');
    const itemsNode = document.getElementById('checkout-items');
    const subNode = document.getElementById('checkout-subtotal');
    const shipNode = document.getElementById('checkout-shipping');
    const totalNode = document.getElementById('checkout-total');
    const button = document.getElementById('create-order');
    const error = document.getElementById('checkout-error');

    let quotes = [];
    let subtotal = 0;
    let cart = [];

    const money = n => '$' + Number(n || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    try {
        cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
    } catch {
        cart = [];
    }

    if (!cart.length) {
        location.href = 'index.php#carrito';
        return;
    }

    cartInput.value = JSON.stringify(cart.map(({ id, cantidad }) => ({ id, cantidad })));
    itemsNode.innerHTML = cart.map(item => `
        <div class="summary-line checkout-item-line">
            <span><strong>${escapeHtml(item.nombre)}</strong><small>${item.cantidad} × ${money(item.precio)}</small></span>
            <strong>${money(item.precio * item.cantidad)}</strong>
        </div>
    `).join('');

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setError(message = '') {
        error.textContent = message;
        error.classList.toggle('hidden', !message);
        if (message) error.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function selectQuote(method) {
        const quote = quotes.find(item => item.metodo === method) || quotes[0];
        if (!quote) return;

        methodInput.value = quote.metodo;
        shipNode.textContent = money(quote.precio);
        totalNode.textContent = money(subtotal + Number(quote.precio));
        button.disabled = false;

        document.querySelectorAll('.shipping-option').forEach(el => {
            el.classList.toggle('selected', el.dataset.method === quote.metodo);
        });
    }

    async function quote() {
        const cp = cpInput.value.trim();

        if (!/^\d{5}$/.test(cp)) {
            button.disabled = true;
            options.innerHTML = '<p class="muted">Escribe un código postal de 5 dígitos para cotizar.</p>';
            return;
        }

        setError();
        button.disabled = true;
        options.innerHTML = `
            <div class="shipping-loading">
                <span></span><div><strong>Calculando envío</strong><small>Consultando peso y opciones disponibles…</small></div>
            </div>`;

        try {
            const response = await fetch('cotizar_envio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: csrf,
                    codigo_postal: cp,
                    carrito: cart.map(({ id, cantidad }) => ({ id, cantidad }))
                })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'No fue posible cotizar.');
            }

            subtotal = Number(data.subtotal);
            quotes = data.opciones || [];
            subNode.textContent = money(subtotal);

            if (Array.isArray(data.detalles) && data.detalles.length) {
                itemsNode.innerHTML = data.detalles.map(item => `
                    <div class="summary-line checkout-item-line">
                        <span>
                            <strong>${escapeHtml(item.nombre)}</strong>
                            <small>${Number(item.cantidad)} × ${money(item.precio)}${Number(item.descuento_unitario || 0) > 0 ? ` · ahorro ${money(Number(item.descuento_unitario) * Number(item.cantidad))}` : ''}</small>
                        </span>
                        <strong>${money(item.total)}</strong>
                    </div>
                `).join('');
            }

            options.innerHTML = quotes.map(q => `
                <button type="button" class="shipping-option" data-method="${q.metodo}">
                    <span class="shipping-copy">
                        <span class="shipping-radio"></span>
                        <span>
                            <strong>${q.nombre}</strong>
                            <small>${q.dias} · ${Number(data.peso_kg).toFixed(3)} kg</small>
                        </span>
                    </span>
                    <strong>${money(q.precio)}</strong>
                </button>
            `).join('');

            selectQuote('estandar');
        } catch (e) {
            options.innerHTML = '';
            setError(e.message);
            shipNode.textContent = 'Por calcular';
            totalNode.textContent = money(subtotal);
        }
    }

    let timer;
    cpInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(quote, 350);
    });

    options.addEventListener('click', event => {
        const option = event.target.closest('.shipping-option');
        if (option) selectQuote(option.dataset.method);
    });

    form.addEventListener('submit', event => {
        if (button.disabled) {
            event.preventDefault();
            setError('Cotiza el envío antes de continuar.');
            return;
        }

        cartInput.value = JSON.stringify(cart.map(({ id, cantidad }) => ({ id, cantidad })));
        button.disabled = true;
        button.classList.add('is-loading');
        button.textContent = 'Creando pedido…';
    });

    if (/^\d{5}$/.test(cpInput.value.trim())) {
        setTimeout(quote, 150);
    }
})();
