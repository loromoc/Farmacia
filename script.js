(() => {
    const CART_KEY = 'farmacia_cart_v2';

    const parseCart = () => {
        try {
            return JSON.parse(localStorage.getItem(CART_KEY)) || [];
        } catch {
            return [];
        }
    };

    const money = value => Number(value || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function toast(message) {
        const node = document.getElementById('ui-toast');
        if (!node) return;
        node.textContent = message;
        node.classList.add('show');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => node.classList.remove('show'), 2300);
    }

    function saveCart(cart, message = '') {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        renderCart();
        if (message) toast(message);
    }

    function addToCart(button) {
        const id = Number(button.dataset.id);
        const nombre = button.dataset.nombre;
        const precio = Number(button.dataset.precio);
        const stock = Number(button.dataset.stock);
        const cart = parseCart();
        const existing = cart.find(item => item.id === id);

        if (existing) {
            if (existing.cantidad >= stock) {
                toast('Ya tienes el máximo disponible de este producto.');
                return;
            }
            existing.cantidad += 1;
            existing.stock = stock;
        } else {
            cart.push({ id, nombre, precio, cantidad: 1, stock });
        }

        const original = button.textContent;
        button.textContent = '✓ Agregado';
        button.classList.add('is-added');
        setTimeout(() => {
            button.textContent = original;
            button.classList.remove('is-added');
        }, 1100);

        saveCart(cart, `${nombre} se agregó al carrito.`);
        document.querySelector('.nav-cart')?.classList.add('cart-bump');
        setTimeout(() => document.querySelector('.nav-cart')?.classList.remove('cart-bump'), 450);
    }

    function changeQty(id, delta) {
        const cart = parseCart();
        const item = cart.find(p => p.id === id);
        if (!item) return;

        const next = item.cantidad + delta;
        if (next < 1) {
            removeItem(id);
            return;
        }
        if (next > item.stock) {
            toast('La cantidad supera el stock disponible.');
            return;
        }

        item.cantidad = next;
        saveCart(cart);
    }

    function removeItem(id) {
        const cart = parseCart();
        const item = cart.find(p => p.id === id);
        saveCart(cart.filter(product => product.id !== id), item ? `${item.nombre} se quitó del carrito.` : '');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderCart() {
        const cart = parseCart();
        const container = document.getElementById('lista-carrito');
        const subtotalNode = document.getElementById('subtotal-carrito');
        const countNode = document.getElementById('cart-count');
        const checkout = document.getElementById('checkout-link');

        const count = cart.reduce((sum, item) => sum + item.cantidad, 0);
        if (countNode) countNode.textContent = String(count);

        if (!container || !subtotalNode) return;

        if (!cart.length) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon">♡</span>
                    <strong>Tu carrito está vacío</strong>
                    <small>Agrega productos para empezar.</small>
                </div>`;
            subtotalNode.textContent = '0.00';
            checkout?.classList.add('disabled');
            return;
        }

        const subtotal = cart.reduce((sum, item) => sum + item.precio * item.cantidad, 0);
        subtotalNode.textContent = money(subtotal);
        checkout?.classList.remove('disabled');

        container.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-copy">
                    <strong>${escapeHtml(item.nombre)}</strong>
                    <small>$${money(item.precio)} por unidad</small>
                </div>
                <div class="qty-control" aria-label="Cantidad de ${escapeHtml(item.nombre)}">
                    <button type="button" data-action="minus" data-id="${item.id}" aria-label="Reducir cantidad">−</button>
                    <span>${item.cantidad}</span>
                    <button type="button" data-action="plus" data-id="${item.id}" aria-label="Aumentar cantidad">+</button>
                </div>
                <strong class="cart-line-total">$${money(item.precio * item.cantidad)}</strong>
                <button type="button" class="icon-danger" data-action="remove" data-id="${item.id}">Quitar</button>
            </div>
        `).join('');
    }

    document.addEventListener('click', event => {
        const add = event.target.closest('.btn-add-cart');
        if (add) {
            addToCart(add);
            return;
        }

        const action = event.target.closest('[data-action]');
        if (!action) return;

        const id = Number(action.dataset.id);
        if (action.dataset.action === 'minus') changeQty(id, -1);
        if (action.dataset.action === 'plus') changeQty(id, 1);
        if (action.dataset.action === 'remove') removeItem(id);
    });

    renderCart();
    window.FarmaciaCart = {
        key: CART_KEY,
        get: parseCart,
        clear: () => saveCart([]),
        render: renderCart
    };
})();
