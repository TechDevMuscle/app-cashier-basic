/** Format Rupiah hanya untuk tampilan; server tetap menghitung semua nominal. */
function rupiah(value) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
}

/** Mengaktifkan keranjang hanya pada halaman yang memiliki elemen data-pos. */
function setupPos() {
    const root = document.querySelector('[data-pos]');
    if (!root) return;
    const buttons = [...root.querySelectorAll('[data-product]')];
    const products = new Map();
    const cart = new Map();
    const lines = root.querySelector('[data-cart-lines]');
    const inputs = root.querySelector('[data-cart-inputs]');
    const message = root.querySelector('[data-cart-message]');
    const submit = root.querySelector('[data-checkout-button]');

    // Membaca data Blade yang sudah di-escape menjadi objek JavaScript biasa.
    for (const button of buttons) {
        products.set(Number(button.dataset.id), {
            id: Number(button.dataset.id), name: button.dataset.name,
            price: Number(button.dataset.price), stock: Number(button.dataset.stock),
            finalPrice: Number(button.dataset.finalPrice),
            discountPercent: Number(button.dataset.discountPercent),
            discountAmount: Number(button.dataset.discountAmount),
        });
        button.addEventListener('click', function addSelectedProduct() {
            // Sentuhan pertama membuat baris, sentuhan berikutnya menambah jumlah.
            changeQuantity(Number(button.dataset.id), 1);
        });
    }

    /** Tambah/kurangi jumlah dengan batas stok untuk kenyamanan; server memvalidasi ulang. */
    function changeQuantity(id, delta) {
        message.textContent = '';
        const product = products.get(id);
        if (!product) return;
        if (!cart.has(id) && cart.size >= 100) {
            message.textContent = 'Maksimal 100 jenis produk dalam satu transaksi.';
            return;
        }
        const quantity = (cart.get(id) || 0) + delta;
        if (quantity > Math.min(product.stock, 9999)) {
            message.textContent = 'Jumlah melebihi stok atau batas per produk.';
            return;
        }
        if (quantity <= 0) cart.delete(id);
        else cart.set(id, quantity);
        renderCart();
    }

    /** Membuat input tersembunyi. Hanya ID/jumlah yang dikirim, tidak ada harga/total. */
    function addHiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        inputs.append(input);
    }

    /** Menggambar ulang baris keranjang dari Map, menggunakan textContent agar nama aman. */
    function renderCart() {
        lines.replaceChildren();
        inputs.replaceChildren();
        let total = 0;
        let originalTotal = 0;
        let discountTotal = 0;
        let index = 0;
        for (const [id, quantity] of cart) {
            const product = products.get(id);
            const line = root.querySelector('[data-cart-template]').content.cloneNode(true);
            line.querySelector('[data-line-name]').textContent = product.name;
            line.querySelector('[data-line-quantity]').textContent = quantity;
            line.querySelector('[data-line-price]').textContent = product.discountPercent > 0
                ? `${rupiah(product.price)} → ${rupiah(product.finalPrice)} / unit · Diskon ${product.discountPercent}%`
                : `${rupiah(product.finalPrice)} / unit`;
            line.querySelector('[data-line-subtotal]').textContent = rupiah(product.finalPrice * quantity);
            const minus = line.querySelector('[data-line-minus]');
            const plus = line.querySelector('[data-line-plus]');
            const remove = line.querySelector('[data-line-remove]');
            minus.setAttribute('aria-label', 'Kurangi ' + product.name);
            plus.setAttribute('aria-label', 'Tambah ' + product.name);
            remove.setAttribute('aria-label', 'Hapus ' + product.name);
            plus.disabled = quantity >= Math.min(product.stock, 9999);
            // Handler tombol cukup mengubah state; renderCart memperbarui tampilannya.
            minus.addEventListener('click', function decrease() { changeQuantity(id, -1); });
            plus.addEventListener('click', function increase() { changeQuantity(id, 1); });
            remove.addEventListener('click', function removeLine() { cart.delete(id); renderCart(); });
            lines.append(line);
            addHiddenInput(`items[${index}][product_id]`, id);
            addHiddenInput(`items[${index}][quantity]`, quantity);
            // Harga net/potongan dari model PHP. Preview tidak mengirim nilai diskon ke server.
            total += product.finalPrice * quantity;
            originalTotal += product.price * quantity;
            discountTotal += product.discountAmount * quantity;
            index++;
        }
        root.querySelector('[data-cart-total]').textContent = rupiah(total);
        root.querySelector('[data-cart-original]').textContent = rupiah(originalTotal);
        root.querySelector('[data-cart-discount]').textContent = rupiah(discountTotal);
        root.querySelector('[data-cart-discount-summary]').hidden = discountTotal === 0;
        root.querySelector('[data-cart-empty]').hidden = cart.size > 0;
        submit.disabled = cart.size === 0;
    }

    /** Filter lokal tidak mengirim request dan tidak mengubah isi keranjang. */
    function filterProducts(event) {
        const search = event.target.value.trim().toLocaleLowerCase('id-ID');
        let visible = 0;
        for (const button of buttons) {
            const match = (button.dataset.name + ' ' + button.dataset.code).toLocaleLowerCase('id-ID').includes(search);
            button.hidden = !match;
            if (match) visible++;
        }
        root.querySelector('[data-no-products]').hidden = visible > 0;
    }
    root.querySelector('[data-product-search]').addEventListener('input', filterProducts);
    root.querySelector('[data-clear-cart]').addEventListener('click', function clearCart() {
        // Keranjang belum tersimpan, sehingga mengosongkannya tidak membatalkan transaksi database.
        cart.clear(); message.textContent = ''; renderCart();
    });
    root.querySelector('[data-checkout]').addEventListener('submit', function preventEmptyCheckout(event) {
        // Token unik di server tetap melindungi ketika pengguna mengirim request berulang.
        if (cart.size === 0) event.preventDefault();
    });

    // old('items') dikembalikan Laravel saat validasi gagal. Batasi sesuai katalog terbaru.
    const oldItems = JSON.parse(root.querySelector('[data-old-items]').textContent);
    for (const item of Object.values(oldItems || {})) {
        if (!item || typeof item !== 'object') continue;
        const product = products.get(Number(item.product_id));
        const quantity = Number(item.quantity);
        if (product && product.stock > 0 && Number.isInteger(quantity) && quantity > 0 && cart.size < 100) {
            cart.set(product.id, Math.min(quantity, product.stock, 9999));
        }
    }
    renderCart();
    root.querySelector('[data-no-products]').hidden = buttons.length > 0;
}

/** Menampilkan perkiraan kembalian dan tombol nominal tunai tanpa library pihak ketiga. */
function setupPayment() {
    const form = document.querySelector('[data-payment-form]');
    if (!form) return;
    const payment = form.querySelector('[data-payment]');
    const total = Number(form.dataset.total);
    /** Perkiraan UI; server adalah sumber hasil pembayaran yang sebenarnya. */
    function updateChange() {
        const amount = Number(payment.value);
        form.querySelector('[data-change]').textContent = amount >= total
            ? 'Kembalian: ' + rupiah(amount - total)
            : 'Kurang: ' + rupiah(total - amount);
    }
    payment.addEventListener('input', updateChange);
    for (const button of form.querySelectorAll('[data-cash]')) {
        button.addEventListener('click', function selectCash() {
            // Tombol nominal mengisi input; belum mengirim pembayaran.
            payment.value = button.dataset.cash;
            updateChange();
        });
    }
    updateChange();
}

/** Preview form produk: pembulatan per unit sama dengan intdiv PHP untuk nilai non-negatif. */
function setupDiscountPreview() {
    const form = document.querySelector('[data-discount-form]');
    if (!form) return;
    const price = form.querySelector('[data-original-price]');
    const percent = form.querySelector('[data-discount-percent]');
    const preview = form.querySelector('[data-discount-preview]');
    /** Menampilkan perkiraan, bukan mengganti validasi/perhitungan server. */
    function updatePreview() {
        const amount = Number(price.value);
        const discountPercent = Number(percent.value);
        if (!price.value || percent.value === '' || !Number.isInteger(amount) || amount < 1 || amount > 1000000000
            || !Number.isInteger(discountPercent) || discountPercent < 0 || discountPercent > 100) {
            preview.textContent = 'Isi harga normal yang valid dan diskon bulat 0–100%.';
            return;
        }
        const discount = Math.floor(amount * discountPercent / 100);
        preview.textContent = `Harga setelah diskon: ${rupiah(amount - discount)} · Potongan per unit: ${rupiah(discount)}`;
    }
    price.addEventListener('input', updatePreview);
    percent.addEventListener('input', updatePreview);
    updatePreview();
}

/** Menghubungkan dialog konfirmasi, cetak, dan pencegahan double tap pada form POST. */
function setupForms() {
    /** Konfirmasi dua langkah di halaman: tombol besar lebih nyaman untuk layar sentuh. */
    function showConfirmation(form) {
        if (form.querySelector('[data-confirm-panel]')) return;
        const panel = document.createElement('div');
        panel.dataset.confirmPanel = '';
        panel.className = 'mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4';
        panel.setAttribute('role', 'group');
        panel.setAttribute('aria-label', 'Konfirmasi tindakan');
        const text = document.createElement('p');
        text.className = 'mb-3 text-sm font-semibold';
        text.textContent = form.dataset.confirm;
        const yes = document.createElement('button');
        yes.type = 'button'; yes.className = 'btn-danger mr-2'; yes.textContent = 'Ya, lanjutkan';
        const no = document.createElement('button');
        no.type = 'button'; no.className = 'btn-secondary'; no.textContent = 'Kembali';
        yes.addEventListener('click', function confirmAction() {
            // requestSubmit tetap menjalankan validasi HTML dan handler submit form.
            if (!form.reportValidity()) return;
            form.dataset.confirmed = 'true';
            yes.disabled = true;
            form.requestSubmit();
        });
        no.addEventListener('click', function dismissConfirmation() {
            // Menutup konfirmasi belum mengubah data apa pun di server.
            delete form.dataset.confirmed;
            panel.remove();
        });
        panel.append(text, yes, no);
        form.append(panel);
        no.focus();
    }
    for (const form of document.querySelectorAll('form[method="POST"]')) {
        form.addEventListener('submit', function handleSubmit(event) {
            if (event.defaultPrevented) return;
            if (form.dataset.confirm && form.dataset.confirmed !== 'true') {
                event.preventDefault();
                showConfirmation(form);
                return;
            }
            // Tombol disabled tidak mengubah hidden input; CSRF dan payload tetap dikirim.
            for (const button of form.querySelectorAll('button:not([type="button"])')) {
                if (!button.disabled) { button.dataset.submitting = 'true'; button.disabled = true; }
            }
        });
    }
    window.addEventListener('pageshow', function restoreCachedForms() {
        // Browser Back dapat memulihkan HTML beserta tombol disabled dari cache.
        for (const button of document.querySelectorAll('[data-submitting]')) {
            button.disabled = false;
            delete button.dataset.submitting;
        }
        for (const form of document.querySelectorAll('[data-confirmed]')) delete form.dataset.confirmed;
        for (const panel of document.querySelectorAll('[data-confirm-panel]')) panel.remove();
    });
    for (const button of document.querySelectorAll('[data-print]')) {
        button.addEventListener('click', function printReceipt() {
            // CSS @media print menyembunyikan navigasi dan form tindakan.
            window.print();
        });
    }
}

// Vite memuat script sebagai module setelah HTML tersedia.
setupPos();
setupPayment();
setupDiscountPreview();
setupForms();
