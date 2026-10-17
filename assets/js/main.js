/* assets/js/main.js */
/* Pookky Customer-facing AJAX operations and interactive handlers */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize standard event handlers
    initWishlistHandlers();
    initCartQuantityHandlers();
    initProductZoom();
    initVariantSelection();
    initComboBuilder();
    initNewsletterForm();
    initCheckoutAddressHandlers();
    initPincodeAutofill();
    initGuestAddressAutofill();
    initAlertDismissHandlers();
    initOrderSuccessHandlers();
    initMobileStickyBar();
});

// Utility to display notifications using Bootstrap Toasts or fallback Alerts
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
    
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    
    if (window.bootstrap && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toastElement, { delay: 4000 });
        bsToast.show();
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    } else {
        // Fallback if bootstrap is not loaded
        alert(message);
    }
}

// Global Badge UI Synchronizers (Updates Desktop Header, Mobile Top Header, and Mobile Bottom Nav)
function updateAllCartBadges(count) {
    const total = parseInt(count) || 0;
    const badgeIds = ['cart-badge', 'mobile-header-cart-badge', 'mobile-nav-cart-badge'];
    badgeIds.forEach(id => {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = total;
            if (total > 0) {
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    });
}

function updateAllWishlistBadges(count) {
    const total = parseInt(count) || 0;
    const badgeIds = ['wishlist-badge', 'mobile-header-wishlist-badge'];
    badgeIds.forEach(id => {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = total;
            if (total > 0) {
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    });
}

// Add/Remove from Wishlist via AJAX
function initWishlistHandlers() {
    document.querySelectorAll('.wishlist-toggle, .wishlist-overlay').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const productId = this.getAttribute('data-product-id');
            if (!productId) return;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const formData = new FormData();
            formData.append('action', 'toggle_wishlist');
            formData.append('product_id', productId);
            formData.append('csrf_token', csrfToken);
            
            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    // Update header wishlist badge count across all viewports
                    if (typeof data.data.wishlist_count !== 'undefined') {
                        updateAllWishlistBadges(data.data.wishlist_count);
                    }

                    // Toggle active classes on icons
                    const icon = this.querySelector('i');
                    if (icon) {
                        if (data.data.is_added) {
                            icon.className = 'bi bi-heart-fill';
                        } else {
                            icon.className = 'bi bi-heart';
                            // If we are on wishlist.php dashboard, reload or remove card
                            if (window.location.pathname.includes('wishlist.php') || document.getElementById('wishlist-container')) {
                                location.reload();
                            }
                        }
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Unable to complete request. Please log in first.', 'error');
            });
        });
    });
}

// Cart Quantity Updaters
function initCartQuantityHandlers() {
    // Quantity Input Incrementor
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentNode.querySelector('.qty-input');
            let val = parseInt(input.value) || 1;
            const min = parseInt(input.getAttribute('min')) || 1;
            const max = parseInt(input.getAttribute('max')) || 10;
            
            if (this.classList.contains('qty-plus')) {
                if (val < max) input.value = val + 1;
            } else {
                if (val > min) input.value = val - 1;
            }
            
            // Trigger change event to fire update API if inside Cart page
            input.dispatchEvent(new Event('change'));
        });
    });

    // Update quantities on manual change
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const cartId = this.getAttribute('data-cart-id');
            const qty = parseInt(this.value);
            
            if (!cartId || isNaN(qty) || qty < 1) return;
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'update_cart');
            formData.append('cart_id', cartId);
            formData.append('quantity', qty);
            formData.append('csrf_token', csrfToken);
            
            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    if (data.data && typeof data.data.total_items !== 'undefined') {
                        updateAllCartBadges(data.data.total_items);
                    }
                    // Reload cart totals if on cart.php page
                    if (window.location.pathname.includes('cart.php')) {
                        location.reload();
                    }
                } else {
                    showToast(data.message, 'error');
                    if (data.data && data.data.available_stock !== undefined) {
                        this.value = data.data.available_stock;
                    }
                }
            })
            .catch(err => console.error(err));
        });
    });

    // Remove item from cart via AJAX
    document.querySelectorAll('.btn-remove-cart-item').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cartId = this.getAttribute('data-cart-id');
            if (!cartId) return;

            if (!confirm('Remove item from cart?')) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'remove_from_cart');
            formData.append('cart_id', cartId);
            formData.append('csrf_token', csrfToken);

            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    if (data.data && typeof data.data.total_items !== 'undefined') {
                        updateAllCartBadges(data.data.total_items);
                    }
                    // Reload cart page to refresh summary totals or show empty cart UI
                    if (window.location.pathname.includes('cart.php')) {
                        location.reload();
                    }
                } else {
                    showToast(data.message || 'Failed to remove item', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                const form = btn.closest('form');
                if (form) form.submit();
            });
        });
    });
}

// Product detail variant selection (Size & Color Swatches)
function initVariantSelection() {
    const colorSwatches = document.querySelectorAll('.swatch-color:not(.combo-swatch-color)');
    const sizeSwatches = document.querySelectorAll('.swatch-size:not(.combo-swatch-size)');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const buyNowBtn = document.getElementById('buy-now-btn');
    const priceDisplay = document.getElementById('product-price-span') || document.getElementById('variant-price-display');
    const stockDisplay = document.getElementById('variant-stock-display');
    const variantInput = document.getElementById('selected-variant-id');
    const qtyInput = document.getElementById('product-qty');

    if (!addToCartBtn) return;

    // Handle case where product has only 1 variant (or standard direct variant with no swatch buttons)
    if ((!colorSwatches.length || !sizeSwatches.length) && typeof productVariants !== 'undefined' && productVariants.length > 0) {
        const singleVar = productVariants[0];
        if (singleVar && variantInput) {
            variantInput.value = singleVar.id;
            const stock = parseInt(singleVar.stock) || 0;
            if (stock > 0) {
                if (stockDisplay) {
                    stockDisplay.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> In Stock (${stock} available)</span>`;
                }
                if (qtyInput) {
                    qtyInput.setAttribute('max', Math.min(10, stock));
                }
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = '<i class="bi bi-bag-plus me-1"></i> Add to Bag';
                if (buyNowBtn) {
                    buyNowBtn.disabled = false;
                }
            } else {
                if (stockDisplay) {
                    stockDisplay.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> Out of Stock</span>`;
                }
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Out of Stock';
                if (buyNowBtn) {
                    buyNowBtn.disabled = true;
                }
            }
        }
    }

    if (!colorSwatches.length && !sizeSwatches.length) {
        // Still bind add to cart and buy now handlers if single direct variant is present
        bindAddToCartHandler();
        bindBuyNowHandler();
        return;
    }

    let selectedColor = null;
    let selectedSize = null;

    // Helper to find variant matching color and size
    function updateVariantSelection() {
        if (!selectedColor || !selectedSize) return;

        if (typeof productVariants === 'undefined' || !productVariants.length) return;

        const matched = productVariants.find(v => v.color_id == selectedColor && v.size_id == selectedSize);

        if (matched) {
            if (variantInput) variantInput.value = matched.id;
            
            // Render dual-tier price (Offer & Regular / Strikethrough & Savings)
            const regPriceSpan = document.getElementById('product-regular-price-span');
            const saveBadge = document.getElementById('product-save-badge');
            
            let masterReg = (typeof window.productMasterPricing !== 'undefined' && window.productMasterPricing.regular_price) ? parseFloat(window.productMasterPricing.regular_price) : 0;
            let masterOff = (typeof window.productMasterPricing !== 'undefined' && window.productMasterPricing.offer_price) ? parseFloat(window.productMasterPricing.offer_price) : null;

            let varReg = (matched.regular_price && parseFloat(matched.regular_price) > 0) ? parseFloat(matched.regular_price) : masterReg;
            let varOff = (matched.offer_price && parseFloat(matched.offer_price) > 0) ? parseFloat(matched.offer_price) : ((matched.price && parseFloat(matched.price) > 0 && !matched.regular_price) ? parseFloat(matched.price) : masterOff);

            let effectiveSelling = varOff !== null ? varOff : varReg;
            let effectiveRegular = varReg;

            if (priceDisplay) {
                priceDisplay.textContent = '₹ ' + effectiveSelling.toFixed(2);
            }

            if (regPriceSpan) {
                if (varOff !== null && varOff < effectiveRegular && effectiveRegular > 0) {
                    regPriceSpan.textContent = '₹ ' + effectiveRegular.toFixed(2);
                    regPriceSpan.classList.remove('d-none');
                } else {
                    regPriceSpan.classList.add('d-none');
                }
            }

            if (saveBadge) {
                if (varOff !== null && varOff < effectiveRegular && effectiveRegular > 0) {
                    const diff = effectiveRegular - varOff;
                    const pct = Math.round((diff / effectiveRegular) * 100);
                    saveBadge.textContent = `Save ₹ ${diff.toFixed(2)} (${pct}% OFF)`;
                    saveBadge.classList.remove('d-none');
                } else {
                    saveBadge.classList.add('d-none');
                }
            }
            
            const mobileBarPrice = document.getElementById('mobile-bar-price');
            if (mobileBarPrice) {
                mobileBarPrice.textContent = '₹ ' + effectiveSelling.toFixed(2);
            }
            
            // Stock checks
            const stock = parseInt(matched.stock) || 0;
            if (stock > 0) {
                if (stockDisplay) {
                    stockDisplay.innerHTML = `<span class="text-success"><i class="bi bi-check-circle"></i> In Stock (${stock} available)</span>`;
                }
                if (qtyInput) {
                    qtyInput.setAttribute('max', Math.min(10, stock));
                }
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = '<i class="bi bi-bag-plus me-1"></i> Add to Bag';
                if (buyNowBtn) {
                    buyNowBtn.disabled = false;
                }
            } else {
                if (stockDisplay) {
                    stockDisplay.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> Out of Stock</span>`;
                }
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Out of Stock';
                if (buyNowBtn) {
                    buyNowBtn.disabled = true;
                }
            }
        } else {
            if (variantInput) variantInput.value = '';
            if (stockDisplay) {
                stockDisplay.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle"></i> Size/Color combination unavailable</span>`;
            }
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Unavailable';
            if (buyNowBtn) {
                buyNowBtn.disabled = true;
            }
        }
    }

    // Color clicked
    colorSwatches.forEach(swatch => {
        swatch.addEventListener('click', function() {
            colorSwatches.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            selectedColor = this.getAttribute('data-color-id');
            const colorName = this.getAttribute('data-color-name') || this.getAttribute('title') || '';

            // Update color name label next to heading
            const colorNameSpan = document.getElementById('selected-color-name');
            if (colorNameSpan && colorName) {
                colorNameSpan.textContent = colorName;
            }

            // Switch gallery photos to color-specific images
            if (window.ProductGalleryController) {
                window.ProductGalleryController.switchColor(selectedColor);
            }

            // Dynamic sizes filtering based on variant mapping
            if (typeof productVariants !== 'undefined') {
                const availableSizesForColor = productVariants
                    .filter(v => v.color_id == selectedColor && parseInt(v.stock) > 0)
                    .map(v => parseInt(v.size_id));

                sizeSwatches.forEach(sizeSwatch => {
                    const sId = parseInt(sizeSwatch.getAttribute('data-size-id'));
                    if (availableSizesForColor.includes(sId)) {
                        sizeSwatch.classList.remove('disabled');
                    } else {
                        sizeSwatch.classList.add('disabled');
                        if (selectedSize == sId) {
                            sizeSwatch.classList.remove('active');
                            selectedSize = null;
                        }
                    }
                });
            }

            // If selectedSize is null or disabled, auto-select first available size for this color
            if (!selectedSize || document.querySelector(`.swatch-size[data-size-id="${selectedSize}"].disabled`)) {
                const firstValidSize = document.querySelector('.swatch-size:not(.disabled)');
                if (firstValidSize) {
                    firstValidSize.click();
                }
            } else {
                updateVariantSelection();
            }
        });
    });

    // Size clicked
    sizeSwatches.forEach(swatch => {
        swatch.addEventListener('click', function() {
            if (this.classList.contains('disabled')) return;
            
            sizeSwatches.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            selectedSize = this.getAttribute('data-size-id');
            updateVariantSelection();
        });
    });

    // Auto-select first in-stock variant on load
    if (typeof productVariants !== 'undefined' && productVariants.length > 0) {
        const firstInStock = productVariants.find(v => parseInt(v.stock) > 0) || productVariants[0];
        if (firstInStock) {
            const cSwatch = document.querySelector(`.swatch-color[data-color-id="${firstInStock.color_id}"]`);
            if (cSwatch) {
                cSwatch.click();
            }
            const sSwatch = document.querySelector(`.swatch-size[data-size-id="${firstInStock.size_id}"]`);
            if (sSwatch) {
                sSwatch.click();
            }
        }
    }

    bindAddToCartHandler();
    bindBuyNowHandler();

    function bindAddToCartHandler() {
        // Handle add to cart submission via AJAX (prevent duplicate bindings)
        if (addToCartBtn._boundAddToCart) return;
        addToCartBtn._boundAddToCart = true;

        addToCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const vId = variantInput ? variantInput.value : '';
            const qty = qtyInput ? qtyInput.value : 1;

            if (!vId) {
                showToast('Please select Color and Size first.', 'error');
                return;
            }

            const originalBtnHtml = addToCartBtn.innerHTML;
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Adding...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('variant_id', vId);
            formData.append('quantity', qty);
            formData.append('csrf_token', csrfToken);

            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = originalBtnHtml;

                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    // Visual confirmation on button
                    addToCartBtn.innerHTML = '<i class="bi bi-check2 me-1"></i> Added to Bag!';
                    setTimeout(() => {
                        addToCartBtn.innerHTML = originalBtnHtml;
                    }, 2000);

                    // Update navigation cart counts across all viewports (desktop, mobile top, and mobile bottom nav)
                    if (data.data && typeof data.data.total_items !== 'undefined') {
                        updateAllCartBadges(data.data.total_items);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                addToCartBtn.disabled = false;
                addToCartBtn.innerHTML = originalBtnHtml;
                showToast('Failed to add product. Try again.', 'error');
            });
        });
    }

    function bindBuyNowHandler() {
        if (!buyNowBtn || buyNowBtn._boundBuyNow) return;
        buyNowBtn._boundBuyNow = true;

        buyNowBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const vId = variantInput ? variantInput.value : '';
            const qty = qtyInput ? qtyInput.value : 1;

            if (!vId) {
                showToast('Please select Color and Size first.', 'error');
                const form = document.getElementById('purchase-form');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            const originalBtnHtml = buyNowBtn.innerHTML;
            buyNowBtn.disabled = true;
            buyNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Fast Tracking...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('variant_id', vId);
            formData.append('quantity', qty);
            formData.append('csrf_token', csrfToken);

            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    buyNowBtn.innerHTML = '<i class="bi bi-check2 me-1 text-success"></i> Redirecting...';
                    window.location.href = 'checkout.php';
                } else {
                    buyNowBtn.disabled = false;
                    buyNowBtn.innerHTML = originalBtnHtml;
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                buyNowBtn.disabled = false;
                buyNowBtn.innerHTML = originalBtnHtml;
                showToast('Failed to process. Please try again.', 'error');
            });
        });
    }
}

// Newsletter subscriber submissions
function initNewsletterForm() {
    const form = document.getElementById('newsletter-form');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const emailInput = this.querySelector('input[type="email"]');
        const email = emailInput.value.trim();
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (!email) return;

        const formData = new FormData();
        formData.append('action', 'subscribe');
        formData.append('email', email);
        formData.append('csrf_token', csrfToken);

        fetch('process-cart.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                emailInput.value = '';
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => console.error(err));
    });
}

// Dynamic Combo / Bundle Set Builder
function initComboBuilder() {
    const comboContainer = document.getElementById('combo-builder-container');
    if (!comboContainer || typeof isComboProduct === 'undefined' || !isComboProduct) return;

    if (typeof comboComponentsData === 'undefined' || !comboComponentsData.length) return;

    const addComboBtn = document.getElementById('add-combo-to-cart-btn');
    const buyComboNowBtn = document.getElementById('buy-combo-now-btn');
    const comboQtyInput = document.getElementById('combo-qty');
    const comboProductIdInput = document.getElementById('combo-product-id');

    // Selection tracking per component ID: { [compId]: { colorId, sizeId, variantId, sku, sizeCode, colorName, stock } }
    const selections = {};

    comboComponentsData.forEach(comp => {
        selections[comp.id] = {
            colorId: null,
            sizeId: null,
            variantId: null,
            sku: null,
            sizeCode: null,
            colorName: null,
            stock: 0
        };
    });

    function checkAllComponentsSelected() {
        let allSelected = true;
        let minStock = 999999;

        for (const comp of comboComponentsData) {
            const sel = selections[comp.id];
            if (!sel || !sel.variantId || sel.stock <= 0) {
                allSelected = false;
                break;
            }
            if (sel.stock < minStock) {
                minStock = sel.stock;
            }
        }

        if (allSelected) {
            addComboBtn.disabled = false;
            addComboBtn.innerHTML = '<i class="bi bi-bag-check-fill me-1"></i> Add Set to Bag';
            if (buyComboNowBtn) {
                buyComboNowBtn.disabled = false;
            }
            if (comboQtyInput) {
                comboQtyInput.setAttribute('max', Math.min(10, minStock));
            }
        } else {
            addComboBtn.disabled = true;
            addComboBtn.innerHTML = '<i class="bi bi-bag-plus me-1"></i> Complete Selections to Add';
            if (buyComboNowBtn) {
                buyComboNowBtn.disabled = true;
            }
        }
    }

    function updateComponentVariant(compId, prodId) {
        const comp = comboComponentsData.find(c => c.id == compId);
        if (!comp) return;

        const prod = comp.products.find(p => p.id == prodId) || (comp.products[0] || null);
        if (!prod) return;

        const sel = selections[compId];
        const statusEl = document.getElementById('status-comp-' + compId);
        const summaryTextEl = document.getElementById('summary-text-' + compId);
        const hiddenInput = document.querySelector(`.combo-component-variant-input[data-component-id="${compId}"]`);

        if (!sel.colorId || !sel.sizeId) {
            if (statusEl) {
                statusEl.innerHTML = `<span class="text-muted"><i class="bi bi-info-circle me-1"></i> Choose ${!sel.colorId ? 'Color' : 'Size'} above</span>`;
            }
            if (summaryTextEl) {
                summaryTextEl.textContent = 'Pending Selection';
                summaryTextEl.className = 'item-choice text-muted';
            }
            if (hiddenInput) hiddenInput.value = '';
            sel.variantId = null;
            checkAllComponentsSelected();
            return;
        }

        // Find variant matching color and size
        const matched = prod.variants.find(v => v.color_id == sel.colorId && v.size_id == sel.sizeId);

        if (matched && parseInt(matched.stock) > 0) {
            sel.variantId = matched.variant_id;
            sel.sku = matched.sku;
            sel.sizeCode = matched.size_code;
            sel.colorName = matched.color_name;
            sel.stock = parseInt(matched.stock);

            if (hiddenInput) hiddenInput.value = matched.variant_id;

            if (statusEl) {
                statusEl.innerHTML = `<span class="text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> In Stock (${matched.stock} available)</span>`;
            }

            if (summaryTextEl) {
                summaryTextEl.innerHTML = `<span class="badge bg-success-subtle text-success border me-1">${matched.size_code}</span> <span class="badge bg-light text-dark border">${matched.color_name}</span>`;
                summaryTextEl.className = 'item-choice';
            }
        } else {
            sel.variantId = null;
            sel.stock = 0;
            if (hiddenInput) hiddenInput.value = '';

            if (statusEl) {
                statusEl.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Size/Color out of stock</span>`;
            }

            if (summaryTextEl) {
                summaryTextEl.textContent = 'Out of stock';
                summaryTextEl.className = 'item-choice text-danger';
            }
        }

        checkAllComponentsSelected();
    }

    // Color Swatch Clicks for Combo
    document.querySelectorAll('.combo-swatch-color').forEach(swatch => {
        swatch.addEventListener('click', function() {
            const compId = this.getAttribute('data-component-id');
            const prodId = this.getAttribute('data-product-id');
            const colorId = this.getAttribute('data-color-id');
            const colorName = this.getAttribute('data-color-name');

            // Set active color swatch for this component
            const card = document.getElementById('step-card-' + compId);
            if (card) {
                card.querySelectorAll('.combo-swatch-color').forEach(s => s.classList.remove('active'));
                this.classList.add('active');

                const label = card.querySelector('.selected-color-label');
                if (label) label.textContent = colorName;

                // Find eligible product variants
                const comp = comboComponentsData.find(c => c.id == compId);
                const prod = comp ? comp.products.find(p => p.id == prodId) : null;

                if (prod) {
                    const availableSizesForColor = prod.variants
                        .filter(v => v.color_id == colorId && parseInt(v.stock) > 0)
                        .map(v => parseInt(v.size_id));

                    card.querySelectorAll('.combo-swatch-size').forEach(sizeSwatch => {
                        const sId = parseInt(sizeSwatch.getAttribute('data-size-id'));
                        if (availableSizesForColor.includes(sId)) {
                            sizeSwatch.classList.remove('disabled');
                        } else {
                            sizeSwatch.classList.add('disabled');
                            if (selections[compId].sizeId == sId) {
                                sizeSwatch.classList.remove('active');
                                selections[compId].sizeId = null;
                            }
                        }
                    });
                }
            }

            selections[compId].colorId = colorId;
            selections[compId].colorName = colorName;
            updateComponentVariant(compId, prodId);
        });
    });

    // Size Swatch Clicks for Combo
    document.querySelectorAll('.combo-swatch-size').forEach(swatch => {
        swatch.addEventListener('click', function() {
            if (this.classList.contains('disabled')) return;

            const compId = this.getAttribute('data-component-id');
            const prodId = this.getAttribute('data-product-id');
            const sizeId = this.getAttribute('data-size-id');
            const sizeCode = this.getAttribute('data-size-code');

            const card = document.getElementById('step-card-' + compId);
            if (card) {
                card.querySelectorAll('.combo-swatch-size').forEach(s => s.classList.remove('active'));
                this.classList.add('active');

                const label = card.querySelector('.selected-size-label');
                if (label) label.textContent = sizeCode;
            }

            selections[compId].sizeId = sizeId;
            selections[compId].sizeCode = sizeCode;
            updateComponentVariant(compId, prodId);
        });
    });

    // Handle Add Combo To Bag
    if (addComboBtn) {
        addComboBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Validate all components
            const payloadComponents = [];
            for (const comp of comboComponentsData) {
                const sel = selections[comp.id];
                if (!sel || !sel.variantId) {
                    showToast(`Please complete selections for ${comp.component_title}.`, 'error');
                    return;
                }
                payloadComponents.push({
                    component_id: comp.id,
                    variant_id: sel.variantId
                });
            }

            const qty = comboQtyInput ? parseInt(comboQtyInput.value) || 1 : 1;
            const comboProductId = comboProductIdInput ? parseInt(comboProductIdInput.value) : 0;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const formData = new FormData();
            formData.append('action', 'add_combo_to_cart');
            formData.append('combo_product_id', comboProductId);
            formData.append('components', JSON.stringify(payloadComponents));
            formData.append('quantity', qty);
            formData.append('csrf_token', csrfToken);

            addComboBtn.disabled = true;
            addComboBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Adding Set...';

            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                addComboBtn.disabled = false;
                addComboBtn.innerHTML = '<i class="bi bi-bag-check-fill me-1"></i> Add Set to Bag';

                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    if (data.data && typeof data.data.total_items !== 'undefined') {
                        updateAllCartBadges(data.data.total_items);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                addComboBtn.disabled = false;
                addComboBtn.innerHTML = '<i class="bi bi-bag-check-fill me-1"></i> Add Set to Bag';
                showToast('Failed to add combo set. Please try again.', 'error');
            });
        });
    }

    // Handle Buy Combo Now (Fast-Track into Checkout)
    if (buyComboNowBtn) {
        buyComboNowBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Validate all components
            const payloadComponents = [];
            for (const comp of comboComponentsData) {
                const sel = selections[comp.id];
                if (!sel || !sel.variantId) {
                    showToast(`Please complete selections for ${comp.component_title}.`, 'error');
                    const compCard = document.getElementById('step-card-' + comp.id);
                    if (compCard) {
                        compCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                payloadComponents.push({
                    component_id: comp.id,
                    variant_id: sel.variantId
                });
            }

            const qty = comboQtyInput ? parseInt(comboQtyInput.value) || 1 : 1;
            const comboProductId = comboProductIdInput ? parseInt(comboProductIdInput.value) : 0;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const formData = new FormData();
            formData.append('action', 'add_combo_to_cart');
            formData.append('combo_product_id', comboProductId);
            formData.append('components', JSON.stringify(payloadComponents));
            formData.append('quantity', qty);
            formData.append('csrf_token', csrfToken);

            const origHtml = buyComboNowBtn.innerHTML;
            buyComboNowBtn.disabled = true;
            buyComboNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Fast Tracking...';

            fetch('process-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    buyComboNowBtn.innerHTML = '<i class="bi bi-check2 me-1 text-success"></i> Redirecting...';
                    window.location.href = 'checkout.php';
                } else {
                    buyComboNowBtn.disabled = false;
                    buyComboNowBtn.innerHTML = origHtml;
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                buyComboNowBtn.disabled = false;
                buyComboNowBtn.innerHTML = origHtml;
                showToast('Failed to process combo set. Please try again.', 'error');
            });
        });
    }
}

// Interactive Product Detail Image Zoom & Fullscreen Lightbox
function initProductZoom() {
    const zoomContainer = document.getElementById('product-zoom-container');
    const mainImg = document.getElementById('main-product-gallery-img');
    const zoomHint = document.getElementById('zoom-hint-badge');
    const openLightboxBtn = document.getElementById('btn-open-lightbox');
    const lightboxModalEl = document.getElementById('lightboxModal');
    
    if (!zoomContainer || !mainImg) return;

    // Build initial images array
    let images = [];
    if (typeof productGalleryImages !== 'undefined' && Array.isArray(productGalleryImages) && productGalleryImages.length > 0) {
        images = [...productGalleryImages];
    } else {
        images = [mainImg.src];
    }

    let currentIndex = 0;
    let isHoverZooming = false;

    // 1. Desktop Hover Pan Zoom on Main Image
    zoomContainer.addEventListener('mouseenter', function() {
        if (window.innerWidth >= 768) {
            isHoverZooming = true;
            mainImg.style.transform = 'scale(2.2)';
            if (zoomHint) zoomHint.style.opacity = '0';
        }
    });

    zoomContainer.addEventListener('mousemove', function(e) {
        if (!isHoverZooming || window.innerWidth < 768) return;
        const rect = zoomContainer.getBoundingClientRect();
        const x = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
        const y = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
        mainImg.style.transformOrigin = `${x}% ${y}%`;
    });

    zoomContainer.addEventListener('mouseleave', function() {
        isHoverZooming = false;
        mainImg.style.transform = 'scale(1)';
        mainImg.style.transformOrigin = 'center center';
        if (zoomHint) zoomHint.style.opacity = '1';
    });

    // Helper: Bind Thumbnail Clicks
    function bindThumbnailClicks() {
        const thumbItems = document.querySelectorAll('#gallery-thumbnails-strip .gallery-thumb-item');
        thumbItems.forEach((thumb, idx) => {
            thumb.addEventListener('click', function(e) {
                e.preventDefault();
                const newSrc = this.getAttribute('data-src');
                if (!newSrc) return;

                // Highlight active thumbnail
                thumbItems.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Fade transition on main image
                mainImg.style.opacity = '0.3';
                setTimeout(() => {
                    mainImg.src = newSrc;
                    mainImg.setAttribute('data-zoom-src', newSrc);
                    mainImg.style.opacity = '1';
                }, 120);

                currentIndex = idx;
            });
        });
    }

    bindThumbnailClicks();

    // 2. Fullscreen Lightbox Modal Setup
    let updateLightboxView = null;
    let toggleLightboxZoom = null;

    if (lightboxModalEl && window.bootstrap && bootstrap.Modal) {
        const bsLightbox = new bootstrap.Modal(lightboxModalEl);
        const lightboxImg = document.getElementById('lightbox-img');
        const lightboxCounter = document.getElementById('lightbox-counter');
        const lightboxPrev = document.getElementById('lightbox-prev-btn');
        const lightboxNext = document.getElementById('lightbox-next-btn');
        const lightboxZoomToggle = document.getElementById('lightbox-zoom-toggle');
        const lightboxZoomIcon = document.getElementById('lightbox-zoom-icon');
        const lightboxViewport = document.getElementById('lightbox-viewport');

        let isLightboxZoomed = false;

        updateLightboxView = function(idx) {
            if (!images.length) return;
            if (idx < 0) idx = images.length - 1;
            if (idx >= images.length) idx = 0;
            currentIndex = idx;

            if (lightboxImg) {
                lightboxImg.style.opacity = '0.4';
                lightboxImg.src = images[currentIndex];
                lightboxImg.onload = function() {
                    lightboxImg.style.opacity = '1';
                };
                // Reset zoom
                isLightboxZoomed = false;
                lightboxImg.style.transform = 'scale(1)';
                lightboxImg.style.transformOrigin = 'center center';
                lightboxImg.style.cursor = 'zoom-in';
                if (lightboxZoomIcon) {
                    lightboxZoomIcon.className = 'bi bi-zoom-in fs-5';
                }
            }

            if (lightboxCounter) {
                lightboxCounter.textContent = `${currentIndex + 1} / ${images.length}`;
            }

            // Sync lightbox thumbnails
            const currentLightboxThumbs = document.querySelectorAll('#lightbox-thumbs-strip .lightbox-thumb');
            currentLightboxThumbs.forEach((lt, i) => {
                if (i === currentIndex) {
                    lt.classList.add('active');
                    lt.classList.remove('opacity-75');
                    lt.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                } else {
                    lt.classList.remove('active');
                    lt.classList.add('opacity-75');
                }
            });

            // Sync page thumbnails
            const pageThumbs = document.querySelectorAll('#gallery-thumbnails-strip .gallery-thumb-item');
            pageThumbs.forEach((pt, i) => {
                if (i === currentIndex) pt.classList.add('active');
                else pt.classList.remove('active');
            });
            mainImg.src = images[currentIndex];
        };

        toggleLightboxZoom = function() {
            if (!lightboxImg) return;
            isLightboxZoomed = !isLightboxZoomed;
            if (isLightboxZoomed) {
                lightboxImg.style.transform = 'scale(2.2)';
                lightboxImg.style.cursor = 'zoom-out';
                if (lightboxZoomIcon) lightboxZoomIcon.className = 'bi bi-zoom-out fs-5';
            } else {
                lightboxImg.style.transform = 'scale(1)';
                lightboxImg.style.transformOrigin = 'center center';
                lightboxImg.style.cursor = 'zoom-in';
                if (lightboxZoomIcon) lightboxZoomIcon.className = 'bi bi-zoom-in fs-5';
            }
        };

        // Open Lightbox on Expand Button or Main Image Click
        if (openLightboxBtn) {
            openLightboxBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (updateLightboxView) updateLightboxView(currentIndex);
                bsLightbox.show();
            });
        }

        zoomContainer.addEventListener('click', function(e) {
            if (e.target.closest('#btn-open-lightbox')) return;
            if (updateLightboxView) updateLightboxView(currentIndex);
            bsLightbox.show();
        });

        // Prev / Next Buttons
        if (lightboxPrev) {
            lightboxPrev.addEventListener('click', function(e) {
                e.stopPropagation();
                if (updateLightboxView) updateLightboxView(currentIndex - 1);
            });
        }
        if (lightboxNext) {
            lightboxNext.addEventListener('click', function(e) {
                e.stopPropagation();
                if (updateLightboxView) updateLightboxView(currentIndex + 1);
            });
        }

        // Lightbox Zoom toggle
        if (lightboxZoomToggle) {
            lightboxZoomToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (toggleLightboxZoom) toggleLightboxZoom();
            });
        }

        // Double-click or click on viewport image to toggle zoom
        if (lightboxImg) {
            lightboxImg.addEventListener('click', function(e) {
                e.stopPropagation();
                if (toggleLightboxZoom) toggleLightboxZoom();
            });
        }

        // Pan when zoomed in lightbox
        if (lightboxViewport) {
            lightboxViewport.addEventListener('mousemove', function(e) {
                if (!isLightboxZoomed || !lightboxImg) return;
                const rect = lightboxViewport.getBoundingClientRect();
                const x = Math.max(0, Math.min(100, ((e.clientX - rect.left) / rect.width) * 100));
                const y = Math.max(0, Math.min(100, ((e.clientY - rect.top) / rect.height) * 100));
                lightboxImg.style.transformOrigin = `${x}% ${y}%`;
            });
        }

        // Keyboard navigation when Lightbox is open
        document.addEventListener('keydown', function(e) {
            if (!lightboxModalEl.classList.contains('show')) return;
            if (e.key === 'ArrowLeft') {
                if (updateLightboxView) updateLightboxView(currentIndex - 1);
            } else if (e.key === 'ArrowRight') {
                if (updateLightboxView) updateLightboxView(currentIndex + 1);
            }
        });
    }

    // Helper: Bind Lightbox Thumbnail Clicks
    function bindLightboxThumbnailClicks() {
        const lbThumbs = document.querySelectorAll('#lightbox-thumbs-strip .lightbox-thumb');
        lbThumbs.forEach(lt => {
            lt.addEventListener('click', function(e) {
                e.stopPropagation();
                const idx = parseInt(this.getAttribute('data-index'));
                if (!isNaN(idx) && updateLightboxView) updateLightboxView(idx);
            });
        });
    }

    bindLightboxThumbnailClicks();

    // 3. Expose ProductGalleryController for Dynamic Color Switching
    window.ProductGalleryController = {
        setImages: function(newImages) {
            if (!newImages || !newImages.length) return;
            images = [...newImages];
            currentIndex = 0;

            // Update main image immediately
            if (mainImg) {
                mainImg.src = images[0];
                mainImg.setAttribute('data-zoom-src', images[0]);
                mainImg.style.opacity = '1';
            }

            // Update lightbox main image
            const lbImg = document.getElementById('lightbox-img');
            if (lbImg) {
                lbImg.src = images[0];
            }

            // Rebuild thumbnails strip
            const strip = document.getElementById('gallery-thumbnails-strip');
            const stripContainer = document.getElementById('gallery-thumbnails-container') || (strip ? strip.parentElement : null);
            if (strip) {
                if (images.length > 1) {
                    if (stripContainer) stripContainer.classList.remove('d-none');
                    let thumbsHtml = '';
                    images.forEach((src, idx) => {
                        thumbsHtml += `
                            <div class="gallery-thumb-item ${idx === 0 ? 'active' : ''}" data-index="${idx}" data-src="${src}">
                                <img src="${src}" alt="thumbnail ${idx + 1}">
                            </div>
                        `;
                    });
                    strip.innerHTML = thumbsHtml;
                    bindThumbnailClicks();
                } else {
                    strip.innerHTML = '';
                    if (stripContainer) stripContainer.classList.add('d-none');
                }
            }

            // Rebuild lightbox ribbon & navigation
            const lbStrip = document.getElementById('lightbox-thumbs-strip');
            const lbFooter = document.getElementById('lightbox-footer-ribbon') || (lbStrip ? lbStrip.closest('.modal-footer') : null);
            const lbPrev = document.getElementById('lightbox-prev-btn');
            const lbNext = document.getElementById('lightbox-next-btn');

            if (lbPrev) lbPrev.classList.toggle('d-none', images.length <= 1);
            if (lbNext) lbNext.classList.toggle('d-none', images.length <= 1);

            if (lbStrip) {
                if (images.length > 1) {
                    if (lbFooter) lbFooter.classList.remove('d-none');
                    let lbThumbsHtml = '';
                    images.forEach((src, idx) => {
                        lbThumbsHtml += `
                            <img src="${src}" data-index="${idx}" alt="thumb" class="lightbox-thumb rounded ${idx === 0 ? 'active' : 'opacity-75'}" style="width: 48px; height: 62px; object-fit: cover;">
                        `;
                    });
                    lbStrip.innerHTML = lbThumbsHtml;
                    bindLightboxThumbnailClicks();
                } else {
                    lbStrip.innerHTML = '';
                    if (lbFooter) lbFooter.classList.add('d-none');
                }
            }

            const lbCounter = document.getElementById('lightbox-counter');
            if (lbCounter) {
                lbCounter.textContent = `1 / ${images.length}`;
            }
        },
        switchColor: function(colorId) {
            const map = window.productGalleryMap || (typeof productGalleryMap !== 'undefined' ? productGalleryMap : null);
            if (!map) return;
            const byColor = map.byColor || {};
            const general = map.general || [];
            const all = map.all || [];

            const cKeyStr = String(colorId);
            const cKeyNum = Number(colorId);
            const colorImagesObj = byColor[cKeyStr] || byColor[cKeyNum];

            if (colorImagesObj && Array.isArray(colorImagesObj) && colorImagesObj.length > 0) {
                // Strictly show ONLY the images assigned to the chosen color
                const colorImgs = colorImagesObj.map(item => typeof item === 'string' ? item : item.src);
                this.setImages(colorImgs);
            } else if (general.length > 0) {
                // Fallback to general images only if this color has no dedicated photos
                this.setImages(general.map(item => typeof item === 'string' ? item : item.src));
            } else if (all.length > 0) {
                this.setImages(all);
            }
        }
    };
}

// 8. CHECKOUT INLINE & MODAL ADDRESS MANAGER
function initCheckoutAddressHandlers() {
    const addressesListContainer = document.getElementById('checkout-addresses-list');
    const modalEl = document.getElementById('modalCheckoutAddress');
    const formEl = document.getElementById('formCheckoutAddress') || document.getElementById('formCheckoutAddressModal');
    const modalTitle = document.getElementById('modalCheckoutAddressLabel') || document.getElementById('modalAddressTitleText');
    const modalError = document.getElementById('modalAddressError');
    const spinner = document.getElementById('saveAddressSpinner') || document.getElementById('spinnerAddressModal');
    const saveBtn = document.getElementById('btnSaveCheckoutAddress') || document.getElementById('btnSaveCheckoutAddressModal');

    if (!addressesListContainer || !modalEl || !formEl) return;

    let bsModal = null;
    if (window.bootstrap && bootstrap.Modal) {
        bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    // Helper: Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Reset modal form for "Add Address"
    function resetAddressForm() {
        formEl.reset();
        document.getElementById('modal_address_id').value = '0';
        if (modalTitle) modalTitle.textContent = 'Add Delivery Address';
        if (modalError) {
            modalError.textContent = '';
            modalError.classList.add('d-none');
        }
    }

    // Handle "Add New Address" button clicks
    document.querySelectorAll('.btn-add-checkout-address').forEach(btn => {
        btn.addEventListener('click', function() {
            resetAddressForm();
        });
    });

    // Reset when modal is hidden
    modalEl.addEventListener('hidden.bs.modal', function() {
        resetAddressForm();
    });

    // Populate modal form for "Edit Address"
    function populateEditForm(data) {
        if (modalError) {
            modalError.textContent = '';
            modalError.classList.add('d-none');
        }
        if (modalTitle) modalTitle.textContent = 'Edit Delivery Address';

        document.getElementById('modal_address_id').value = data.id || '0';
        document.getElementById('modal_address_type').value = data.type || 'Home';
        document.getElementById('modal_receiver_name').value = data.name || '';
        document.getElementById('modal_receiver_phone').value = data.phone || '';
        document.getElementById('modal_address_line1').value = data.line1 || '';
        document.getElementById('modal_address_line2').value = data.line2 || '';
        document.getElementById('modal_city').value = data.city || '';
        document.getElementById('modal_state').value = data.state || '';
        document.getElementById('modal_pincode').value = data.pincode || '';
        document.getElementById('modal_is_default').checked = (data.is_default == '1' || data.is_default == 1);

        if (bsModal) {
            bsModal.show();
        }
    }

    // Event Delegation: Edit address button clicks
    addressesListContainer.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.btn-edit-checkout-address');
        if (editBtn) {
            e.preventDefault();
            e.stopPropagation();
            const addrData = {
                id: editBtn.getAttribute('data-id'),
                type: editBtn.getAttribute('data-type'),
                name: editBtn.getAttribute('data-name'),
                phone: editBtn.getAttribute('data-phone'),
                line1: editBtn.getAttribute('data-line1'),
                line2: editBtn.getAttribute('data-line2'),
                city: editBtn.getAttribute('data-city'),
                state: editBtn.getAttribute('data-state'),
                pincode: editBtn.getAttribute('data-pincode'),
                is_default: editBtn.getAttribute('data-default')
            };
            populateEditForm(addrData);
            return;
        }

        // Event Delegation: Click on address card selects radio and updates visual styling
        const card = e.target.closest('.address-selection-card');
        if (card && !e.target.closest('button') && !e.target.closest('a')) {
            const radio = card.querySelector('.address-radio');
            if (radio && !radio.checked) {
                radio.checked = true;
                updateSelectedCardHighlight(radio.value);
            }
        }
    });

    // Listen to direct radio change events
    addressesListContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('address-radio')) {
            updateSelectedCardHighlight(e.target.value);
        }
    });

    // Update active highlight classes on cards
    function updateSelectedCardHighlight(selectedId) {
        document.querySelectorAll('#checkout-addresses-list .address-selection-card').forEach(card => {
            const radio = card.querySelector('.address-radio');
            if (radio && radio.value === String(selectedId)) {
                card.classList.add('border-rose', 'bg-brand-blush');
                card.classList.remove('bg-light-subtle');
            } else {
                card.classList.remove('border-rose', 'bg-brand-blush');
                card.classList.add('bg-light-subtle');
            }
        });
    }

    // Re-render address cards dynamically in the checkout page
    function renderAddressCards(addresses, selectedAddressId) {
        if (!addresses || addresses.length === 0) {
            addressesListContainer.innerHTML = `
                <div class="alert alert-light border p-3 mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-info-circle text-rose fs-5 me-2"></i>
                        <strong>No saved address found.</strong>
                    </div>
                    <p class="small text-muted mb-0">Click <strong>Add New Address</strong> above to add your delivery location.</p>
                </div>
            `;
            return;
        }

        let html = '<div class="row g-3" id="address-cards-row">';
        addresses.forEach((addr, idx) => {
            const isChecked = selectedAddressId ? (String(addr.id) === String(selectedAddressId)) : (idx === 0);
            const cardClass = isChecked ? 'border-rose bg-brand-blush' : 'bg-light-subtle';
            const defaultBadge = (addr.is_default == 1) ? '<span class="badge bg-success small ms-1">Default</span>' : '';
            const line2Str = addr.address_line2 ? `, ${escapeHtml(addr.address_line2)}` : '';

            html += `
                <div class="col-12 address-card-wrapper" id="addr-card-item-${addr.id}">
                    <div class="border rounded p-3 address-selection-card ${cardClass}" style="cursor: pointer; transition: all 0.2s ease;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="form-check flex-grow-1">
                                <input class="form-check-input address-radio" type="radio" name="address_id" id="addr_radio_${addr.id}" value="${addr.id}" ${isChecked ? 'checked' : ''}>
                                <label class="form-check-label d-block text-dark fw-bold mb-1" for="addr_radio_${addr.id}">
                                    ${escapeHtml(addr.receiver_name)} 
                                    <span class="badge bg-secondary small ms-1">${escapeHtml(addr.address_type)}</span>
                                    ${defaultBadge}
                                </label>
                                <div class="small text-muted mb-1"><i class="bi bi-phone me-1"></i> ${escapeHtml(addr.receiver_phone)}</div>
                                <div class="small text-secondary">
                                    ${escapeHtml(addr.address_line1)}${line2Str}, ${escapeHtml(addr.city)}, ${escapeHtml(addr.state)} - <strong>${escapeHtml(addr.pincode)}</strong>
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 btn-edit-checkout-address" 
                                        data-id="${addr.id}"
                                        data-type="${escapeHtml(addr.address_type)}"
                                        data-name="${escapeHtml(addr.receiver_name)}"
                                        data-phone="${escapeHtml(addr.receiver_phone)}"
                                        data-line1="${escapeHtml(addr.address_line1)}"
                                        data-line2="${escapeHtml(addr.address_line2 || '')}"
                                        data-city="${escapeHtml(addr.city)}"
                                        data-state="${escapeHtml(addr.state)}"
                                        data-pincode="${escapeHtml(addr.pincode)}"
                                        data-default="${addr.is_default}"
                                        style="font-size:11px;" title="Edit Address">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        addressesListContainer.innerHTML = html;
    }

    // Handle AJAX Form Submit
    formEl.addEventListener('submit', function(e) {
        e.preventDefault();

        if (modalError) {
            modalError.textContent = '';
            modalError.classList.add('d-none');
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData(formEl);
        formData.append('action', 'save_checkout_address');
        formData.append('csrf_token', csrfToken);

        if (spinner) spinner.classList.remove('d-none');
        if (saveBtn) saveBtn.disabled = true;

        fetch('process-cart.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (spinner) spinner.classList.add('d-none');
            if (saveBtn) saveBtn.disabled = false;

            if (data.status === 'success') {
                showToast(data.message || 'Address saved successfully!', 'success');
                if (bsModal) bsModal.hide();

                const savedId = data.data.saved_address_id;
                const addresses = data.data.addresses || [];
                renderAddressCards(addresses, savedId);
            } else {
                if (modalError) {
                    modalError.textContent = data.message || 'Failed to save address.';
                    modalError.classList.remove('d-none');
                } else {
                    showToast(data.message || 'Failed to save address.', 'error');
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (spinner) spinner.classList.add('d-none');
            if (saveBtn) saveBtn.disabled = false;
            if (modalError) {
                modalError.textContent = 'A network error occurred. Please try again.';
                modalError.classList.remove('d-none');
            }
        });
    });
}

// Global resilient dismiss handler for alerts and toasts
function initAlertDismissHandlers() {
    document.addEventListener('click', function(e) {
        const closeBtn = e.target.closest('.alert .btn-close, [data-bs-dismiss="alert"]');
        if (closeBtn) {
            e.preventDefault();
            const alertBox = closeBtn.closest('.alert');
            if (alertBox) {
                alertBox.classList.remove('show');
                alertBox.classList.add('fade');
                setTimeout(() => {
                    alertBox.remove();
                }, 150);
            }
        }
    });
}

// Order Confirmation copy and action handlers
function initOrderSuccessHandlers() {
    document.querySelectorAll('.copy-order-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const textToCopy = this.getAttribute('data-clipboard-text') || '';
            if (!textToCopy) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = 'bi bi-check-lg text-success';
                        setTimeout(() => {
                            icon.className = 'bi bi-clipboard';
                        }, 2000);
                    }
                    showToast('Order reference copied to clipboard!', 'success');
                }).catch(() => {
                    fallbackCopyText(textToCopy, btn);
                });
            } else {
                fallbackCopyText(textToCopy, btn);
            }
        });
    });
}

function fallbackCopyText(text, btn) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    try {
        document.execCommand('copy');
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'bi bi-check-lg text-success';
            setTimeout(() => {
                icon.className = 'bi bi-clipboard';
            }, 2000);
        }
        showToast('Order reference copied!', 'success');
    } catch (e) {
        showToast('Failed to copy order reference.', 'error');
    }
    document.body.removeChild(input);
}

// 10. REAL-TIME PINCODE AUTO-LOOKUP (City & State Detection)
function initPincodeAutofill() {
    const pincodeCache = {};

    function setupLookup(pincodeId, cityId, stateId, statusId) {
        const pincodeInput = document.getElementById(pincodeId);
        const cityInput = document.getElementById(cityId);
        const stateInput = document.getElementById(stateId);
        const statusEl = document.getElementById(statusId);

        if (!pincodeInput || !cityInput || !stateInput) return;

        let debounceTimer = null;

        const checkPincode = function() {
            const rawVal = pincodeInput.value.trim();
            const pin = rawVal.replace(/\D/g, '');

            if (pin.length !== 6) {
                if (statusEl) statusEl.innerHTML = '';
                return;
            }

            // Check in-memory cache first
            if (pincodeCache[pin]) {
                populate(pincodeCache[pin]);
                return;
            }

            if (statusEl) {
                statusEl.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1" role="status" style="width: 12px; height: 12px;"></span> Detecting location...</span>';
            }

            fetch(`https://api.postalpincode.in/pincode/${pin}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data[0] && data[0].Status === 'Success' && Array.isArray(data[0].PostOffice) && data[0].PostOffice.length > 0) {
                        const po = data[0].PostOffice[0];
                        const result = {
                            city: po.District || po.Division || po.Block || '',
                            state: po.State || ''
                        };
                        pincodeCache[pin] = result;
                        populate(result);
                    } else {
                        if (statusEl) {
                            statusEl.innerHTML = '<span class="text-muted small">Enter city and state manually</span>';
                            setTimeout(() => { if (statusEl) statusEl.innerHTML = ''; }, 3500);
                        }
                    }
                })
                .catch(() => {
                    if (statusEl) statusEl.innerHTML = '';
                });
        };

        function populate(result) {
            let changed = false;
            if (result.city && (!cityInput.value || cityInput.value !== result.city)) {
                cityInput.value = result.city;
                changed = true;
            }
            if (result.state && (!stateInput.value || stateInput.value !== result.state)) {
                stateInput.value = result.state;
                changed = true;
            }

            if (changed) {
                cityInput.classList.add('is-valid');
                stateInput.classList.add('is-valid');
                setTimeout(() => {
                    cityInput.classList.remove('is-valid');
                    stateInput.classList.remove('is-valid');
                }, 2000);
            }

            if (statusEl) {
                const locName = [result.city, result.state].filter(Boolean).join(', ');
                statusEl.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${locName} detected</span>`;
            }
        }

        pincodeInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(checkPincode, 250);
        });

        pincodeInput.addEventListener('change', checkPincode);
    }

    // Initialize for guest checkout fields
    setupLookup('guest_pincode', 'guest_city', 'guest_state', 'guest-pincode-status');

    // Initialize for modal address fields
    setupLookup('modal_pincode', 'modal_city', 'modal_state', 'modal-pincode-status');
}

// 11. GUEST CHECKOUT AUTO-FILL & LOCAL PERSISTENCE
function initGuestAddressAutofill() {
    const STORAGE_KEY = 'pookky_guest_shipping_address';
    const form = document.getElementById('checkout-form');
    const banner = document.getElementById('guest-autofill-banner');
    const applyBtn = document.getElementById('btn-apply-guest-autofill');
    const dismissBtn = document.getElementById('btn-dismiss-guest-autofill');
    const previewEl = document.getElementById('guest-autofill-preview');

    const nameInput = document.getElementById('guest_name');
    const phoneInput = document.getElementById('guest_phone');
    const emailInput = document.getElementById('guest_email');
    const line1Input = document.getElementById('guest_address_line1');
    const line2Input = document.getElementById('guest_address_line2');
    const cityInput = document.getElementById('guest_city');
    const stateInput = document.getElementById('guest_state');
    const pincodeInput = document.getElementById('guest_pincode');

    // If not on guest checkout page, return
    if (!form || !nameInput) return;

    // Load saved address from localStorage
    let savedData = null;
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            savedData = JSON.parse(stored);
        }
    } catch (e) {
        savedData = null;
    }

    // Function to apply data to inputs
    function applySavedDetails(data, animate = true) {
        if (!data) return;
        if (data.name && nameInput) nameInput.value = data.name;
        if (data.phone && phoneInput) phoneInput.value = data.phone;
        if (data.email && emailInput) emailInput.value = data.email;
        if (data.line1 && line1Input) line1Input.value = data.line1;
        if (data.line2 && line2Input) line2Input.value = data.line2;
        if (data.city && cityInput) cityInput.value = data.city;
        if (data.state && stateInput) stateInput.value = data.state;
        if (data.pincode && pincodeInput) pincodeInput.value = data.pincode;

        if (animate) {
            const inputs = [nameInput, phoneInput, emailInput, line1Input, cityInput, stateInput, pincodeInput];
            inputs.forEach(input => {
                if (input && input.value) {
                    input.classList.add('is-valid');
                    setTimeout(() => input.classList.remove('is-valid'), 2000);
                }
            });
            showToast('Delivery details populated!', 'success');
        }
    }

    // Function to save current guest form data to localStorage
    function saveGuestDetails() {
        const data = {
            name: nameInput ? nameInput.value.trim() : '',
            phone: phoneInput ? phoneInput.value.trim() : '',
            email: emailInput ? emailInput.value.trim() : '',
            line1: line1Input ? line1Input.value.trim() : '',
            line2: line2Input ? line2Input.value.trim() : '',
            city: cityInput ? cityInput.value.trim() : '',
            state: stateInput ? stateInput.value.trim() : '',
            pincode: pincodeInput ? pincodeInput.value.trim() : '',
            updatedAt: Date.now()
        };

        // Only save if at least name and phone or line1 are filled
        if (data.name || data.phone || data.line1) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            } catch (e) {}
        }
    }

    // If saved data exists, check if form is currently empty
    if (savedData && banner) {
        const isFormEmpty = (!nameInput.value && !phoneInput.value && !line1Input.value);
        if (isFormEmpty) {
            if (previewEl) {
                const summary = [savedData.name, savedData.city, savedData.pincode].filter(Boolean).join(', ');
                previewEl.textContent = summary ? `Previous address found: ${summary}` : 'We found your previously used delivery details.';
            }
            banner.classList.remove('d-none');
        }
    }

    if (applyBtn && savedData) {
        applyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            applySavedDetails(savedData, true);
            if (banner) {
                banner.classList.add('d-none');
            }
        });
    }

    if (dismissBtn && banner) {
        dismissBtn.addEventListener('click', function(e) {
            e.preventDefault();
            banner.classList.add('d-none');
        });
    }

    // Auto-save guest details on form submit
    form.addEventListener('submit', function() {
        saveGuestDetails();
    });

    // Auto-save on input change (debounced)
    let saveTimeout = null;
    const inputs = [nameInput, phoneInput, emailInput, line1Input, line2Input, cityInput, stateInput, pincodeInput];
    inputs.forEach(input => {
        if (input) {
            input.addEventListener('blur', saveGuestDetails);
            input.addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(saveGuestDetails, 800);
            });
        }
    });
}

// Floating Mobile Sticky Action Bar for Product Detail Page
function initMobileStickyBar() {
    const stickyBar = document.getElementById('pdp-sticky-mobile-bar');
    if (!stickyBar) return;

    const stickyAddBtn = document.getElementById('mobile-sticky-add-cart-btn');
    const stickyBuyBtn = document.getElementById('mobile-sticky-buy-now-btn');
    const mainForm = document.getElementById('purchase-form') || document.getElementById('combo-builder-container');

    let isVisible = false;

    function evaluateStickyVisibility() {
        if (window.innerWidth >= 992) {
            if (isVisible) {
                stickyBar.style.transform = 'translateY(115%)';
                isVisible = false;
            }
            return;
        }

        if (!mainForm) return;

        const rect = mainForm.getBoundingClientRect();
        // Show sticky bar once user scrolls past the main buy box
        const shouldShow = rect.bottom < 80;

        if (shouldShow && !isVisible) {
            stickyBar.style.transform = 'translateY(0)';
            isVisible = true;
        } else if (!shouldShow && isVisible) {
            stickyBar.style.transform = 'translateY(115%)';
            isVisible = false;
        }
    }

    window.addEventListener('scroll', evaluateStickyVisibility, { passive: true });
    window.addEventListener('resize', evaluateStickyVisibility);
    evaluateStickyVisibility();

    // Delegate mobile Add to Cart click
    if (stickyAddBtn) {
        stickyAddBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const variantInput = document.getElementById('selected-variant-id');
            const mainAddBtn = document.getElementById('add-to-cart-btn') || document.getElementById('add-combo-to-cart-btn');

            // If variant is required but not selected yet, scroll user to options
            if (variantInput && !variantInput.value && mainForm) {
                mainForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showToast('Please select your Color and Size first', 'error');
                return;
            }

            if (mainAddBtn) {
                mainAddBtn.click();
            }
        });
    }

    // Delegate mobile Buy Now click
    if (stickyBuyBtn) {
        stickyBuyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const variantInput = document.getElementById('selected-variant-id');
            const mainBuyBtn = document.getElementById('buy-now-btn') || document.getElementById('buy-combo-now-btn');

            // If variant is required but not selected yet, scroll user to options
            if (variantInput && !variantInput.value && mainForm) {
                mainForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
                showToast('Please select your Color and Size first', 'error');
                return;
            }

            if (mainBuyBtn) {
                mainBuyBtn.click();
            }
        });
    }
}




