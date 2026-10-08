<?php
// admin/index.php
// Administrator Main Dashboard Panel

$page_title = 'Dashboard';
$active_menu = 'dashboard';
require_once __DIR__ . '/includes/header.php';

// Fetch business KPIs
try {
    // 1. Total Sales (Delivered orders only)
    $sales_stmt = $pdo->query("SELECT SUM(grand_total) as total FROM orders WHERE order_status = 'delivered'");
    $sales_row = $sales_stmt->fetch();
    $total_sales = $sales_row['total'] ?? 0.00;

    // 2. Active Orders (Pending, Processing, Shipped)
    $orders_stmt = $pdo->query("SELECT COUNT(id) as count FROM orders WHERE order_status IN ('pending', 'processing', 'shipped')");
    $orders_row = $orders_stmt->fetch();
    $active_orders = $orders_row['count'] ?? 0;

    // 3. Registered Customers
    $cust_stmt = $pdo->query("SELECT COUNT(id) as count FROM customers WHERE status = 1");
    $cust_row = $cust_stmt->fetch();
    $total_customers = $cust_row['count'] ?? 0;

    // 4. Low stock variants (stock <= 5)
    $stock_stmt = $pdo->query("SELECT COUNT(id) as count FROM product_variants WHERE stock <= 5");
    $stock_row = $stock_stmt->fetch();
    $low_stock_count = $stock_row['count'] ?? 0;

    // 5. Recent 5 Orders
    $recent_orders_stmt = $pdo->query("
        SELECT o.*, c.name as customer_name 
        FROM orders o 
        JOIN customers c ON o.customer_id = c.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders = $recent_orders_stmt->fetchAll();

    // 6. Recent Low Stock Products details
    $low_stock_details = $pdo->query("
        SELECT v.sku, v.stock, p.name as product_name, s.size_code, c.color_name 
        FROM product_variants v 
        JOIN products p ON v.product_id = p.id 
        JOIN sizes s ON v.size_id = s.id 
        JOIN colors c ON v.color_id = c.id 
        WHERE v.stock <= 5 
        ORDER BY v.stock ASC 
        LIMIT 5
    ")->fetchAll();

    // Fetch categories for dashboard search filter
    $dash_categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll();

} catch (PDOException $e) {
    set_flash_message('error', 'Error loading dashboard metrics: ' . $e->getMessage());
}
?>

<!-- Product Quick Search Hero Section -->
<div class="card border-0 shadow-sm p-4 bg-white mb-4 position-relative" style="border-radius: 12px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-search text-brand"></i> Product Quick Search & Lookup
            </h5>
            <p class="text-muted small mb-0">Search products by name, SKU, category, fabric, or variant code in real-time.</p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
            <span class="badge bg-light text-secondary border px-2 py-1 small">
                <kbd class="bg-dark text-white rounded px-1 py-0" style="font-size: 10px;">Ctrl</kbd> + <kbd class="bg-dark text-white rounded px-1 py-0" style="font-size: 10px;">K</kbd> to focus
            </span>
            <a href="products.php?action=add" class="btn btn-sm btn-brand">
                <i class="bi bi-plus-circle me-1"></i> Add Product
            </a>
        </div>
    </div>

    <!-- Search Form & Filter Bar -->
    <form id="dashboard_product_search_form" action="products.php" method="GET" class="position-relative" autocomplete="off">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-7 col-lg-8">
                <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden border">
                    <span class="input-group-text bg-white border-0 pe-2 text-muted">
                        <i class="bi bi-search fs-5"></i>
                    </span>
                    <input type="text" id="dash_search_input" name="search" class="form-control border-0 ps-2" 
                           placeholder="Type product name, SKU (e.g. BRA-001), category, or color..." 
                           style="font-size: 15px; box-shadow: none;" autofocus>
                    <button type="button" id="dash_search_clear" class="btn bg-white border-0 text-muted px-3" style="display: none;" title="Clear search">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                    <span id="dash_search_spinner" class="input-group-text bg-white border-0 text-muted px-3" style="display: none;">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    </span>
                    <button type="submit" class="btn btn-dark px-4 fw-medium">
                        Search
                    </button>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <select id="dash_category_filter" name="category_id" class="form-select form-select-lg border shadow-sm rounded-3" style="font-size: 14px;">
                    <option value="">All Categories</option>
                    <?php if (!empty($dash_categories)): ?>
                        <?php foreach ($dash_categories as $dcat): ?>
                            <option value="<?php echo $dcat['id']; ?>"><?php echo e($dcat['name']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <select id="dash_stock_filter" name="stock_filter" class="form-select form-select-lg border shadow-sm rounded-3" style="font-size: 14px;">
                    <option value="all">All Stock Status</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock (≤5)</option>
                    <option value="out_of_stock">Out of Stock (0)</option>
                </select>
            </div>
        </div>

        <!-- Quick Filter Tags -->
        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 pt-1">
            <span class="text-muted small me-1">Quick Filters:</span>
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 dash-quick-filter" data-type="all" style="font-size: 12px;">All</button>
            <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill py-0 px-2 dash-quick-filter" data-stock="low_stock" style="font-size: 12px;"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Low Stock</button>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2 dash-quick-filter" data-stock="out_of_stock" style="font-size: 12px;"><i class="bi bi-x-circle me-1"></i>Out of Stock</button>
            <button type="button" class="btn btn-sm btn-outline-purple rounded-pill py-0 px-2 dash-quick-filter" data-type="combo" style="font-size: 12px;"><i class="bi bi-stars me-1"></i>Combo Sets</button>
            <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 dash-quick-filter" data-status="active" style="font-size: 12px;"><i class="bi bi-check-circle me-1"></i>Published</button>
        </div>

        <!-- Floating Live Results Dropdown Container -->
        <div id="dash_live_results" class="position-absolute start-0 end-0 bg-white shadow-lg border rounded-3 mt-2 overflow-hidden" 
             style="z-index: 1050; display: none; max-height: 520px; overflow-y: auto; top: 100%;">
            <!-- Dynamic Content injected via JS -->
        </div>
    </form>
</div>

<!-- Metrics KPI Cards Grid -->
<div class="row g-4 mb-4">
    <!-- Sales Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card admin-card-stat bg-white p-3 border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Total Sales</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo format_price($total_sales); ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle p-3 text-success">
                    <i class="bi bi-currency-rupee fs-3"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Orders Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card admin-card-stat bg-white p-3 border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Active Orders</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo e($active_orders); ?></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle p-3 text-primary">
                    <i class="bi bi-cart-check fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card admin-card-stat bg-white p-3 border-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Customers</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo e($total_customers); ?></h3>
                </div>
                <div class="rounded-circle bg-info-subtle p-3 text-info">
                    <i class="bi bi-people fs-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock warning Card -->
    <div class="col-12 col-sm-6 col-xl-3">
        <a href="products.php?stock_filter=low_stock" class="text-decoration-none">
            <div class="card admin-card-stat bg-white p-3 border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted small text-uppercase mb-2 font-weight-bold">Low Stock items</h6>
                        <h3 class="fw-bold text-dark mb-0"><?php echo e($low_stock_count); ?></h3>
                    </div>
                    <div class="rounded-circle bg-danger-subtle p-3 text-danger">
                        <i class="bi bi-exclamation-triangle fs-3"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders Table -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i> Recent Placed Orders</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Order No</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No recent orders available.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo e($ord['order_number']); ?></td>
                                    <td><?php echo e($ord['customer_name']); ?></td>
                                    <td><?php echo format_price($ord['grand_total']); ?></td>
                                    <td>
                                        <?php 
                                        $badge = 'bg-secondary';
                                        if($ord['order_status'] === 'pending') $badge = 'bg-warning';
                                        else if($ord['order_status'] === 'processing') $badge = 'bg-primary';
                                        else if($ord['order_status'] === 'shipped') $badge = 'bg-info';
                                        else if($ord['order_status'] === 'delivered') $badge = 'bg-success';
                                        else if($ord['order_status'] === 'cancelled') $badge = 'bg-danger';
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($ord['order_status']); ?></span>
                                    </td>
                                    <td class="small"><?php echo date('d-M-Y H:i', strtotime($ord['created_at'])); ?></td>
                                    <td class="text-end">
                                        <a href="orders.php?view=<?php echo $ord['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Ledger Warning list -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius: 12px;">
            <h5 class="fw-bold mb-3"><i class="bi bi-exclamation-octagon me-2 text-danger"></i> Low Stock Alerts</h5>
            <div class="list-group list-group-flush">
                <?php if (empty($low_stock_details)): ?>
                    <div class="text-center text-muted py-4">All products are well stocked!</div>
                <?php else: ?>
                    <?php foreach ($low_stock_details as $item): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 220px;"><?php echo e($item['product_name']); ?></div>
                                    <small class="text-muted">SKU: <?php echo e($item['sku']); ?> | Size: <?php echo e($item['size_code']); ?> | Color: <?php echo e($item['color_name']); ?></small>
                                </div>
                                <span class="badge bg-danger rounded-pill"><?php echo e($item['stock']); ?> left</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($low_stock_details)): ?>
                <div class="mt-3 text-center">
                    <a href="inventory.php" class="btn btn-sm btn-brand-outline w-100">Restock Inventory</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Dashboard Product Search Styles & Script -->
<style>
.dash-search-result-item {
    transition: all 0.15s ease-in-out;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}
.dash-search-result-item:last-child {
    border-bottom: none;
}
.dash-search-result-item:hover,
.dash-search-result-item.active-item {
    background-color: #fbf8f9;
}
.dash-search-highlight {
    background-color: #fff3cd;
    color: #856404;
    font-weight: 600;
    padding: 0 2px;
    border-radius: 2px;
}
.dash-quick-filter.active {
    background-color: var(--brand-charcoal, #1b1919) !important;
    color: #ffffff !important;
    border-color: var(--brand-charcoal, #1b1919) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('dashboard_product_search_form');
    const searchInput = document.getElementById('dash_search_input');
    const searchClear = document.getElementById('dash_search_clear');
    const searchSpinner = document.getElementById('dash_search_spinner');
    const categoryFilter = document.getElementById('dash_category_filter');
    const stockFilter = document.getElementById('dash_stock_filter');
    const resultsContainer = document.getElementById('dash_live_results');
    const quickFilterBtns = document.querySelectorAll('.dash-quick-filter');

    let activeFilterStatus = 'all';
    let activeFilterType = 'all';
    let debounceTimer = null;
    let selectedIndex = -1;
    let currentResults = [];

    // Helper: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Helper: Highlight query terms in string
    function highlightQuery(text, query) {
        if (!text || !query) return escapeHtml(text);
        const safeText = escapeHtml(text);
        const safeQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${safeQuery})`, 'gi');
        return safeText.replace(regex, '<span class="dash-search-highlight">$1</span>');
    }

    // Perform live AJAX search
    function performSearch() {
        const query = searchInput.value.trim();
        const catId = categoryFilter.value;
        const stockStatus = stockFilter.value;

        // Toggle clear button
        if (searchClear) {
            searchClear.style.display = query.length > 0 ? 'block' : 'none';
        }

        // Show spinner
        if (searchSpinner) searchSpinner.style.display = 'block';

        const params = new URLSearchParams({
            q: query,
            category_id: catId,
            stock_status: stockStatus,
            product_type: activeFilterType,
            status: activeFilterStatus,
            limit: 10
        });

        fetch(`ajax_search_products.php?${params.toString()}`)
            .then(res => res.json())
            .then(data => {
                if (searchSpinner) searchSpinner.style.display = 'none';

                if (data.status === 'success') {
                    currentResults = data.results || [];
                    selectedIndex = -1;
                    renderResults(data, query);
                } else {
                    renderError(data.message || 'An error occurred during search.');
                }
            })
            .catch(err => {
                if (searchSpinner) searchSpinner.style.display = 'none';
                renderError('Failed to fetch search results.');
            });
    }

    // Render Search Results Dropdown
    function renderResults(data, query) {
        const results = data.results || [];
        const total = data.total_count || 0;

        if (results.length === 0) {
            resultsContainer.innerHTML = `
                <div class="p-4 text-center">
                    <div class="mb-2 text-muted"><i class="bi bi-search fs-2"></i></div>
                    <h6 class="fw-bold mb-1 text-dark">No matching products found</h6>
                    <p class="text-muted small mb-3">No products match "${escapeHtml(query || 'selected filters')}".</p>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="products.php?action=add" class="btn btn-sm btn-brand"><i class="bi bi-plus-circle me-1"></i> Add New Product</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('dash_search_clear').click();">Clear Search</button>
                    </div>
                </div>
            `;
            resultsContainer.style.display = 'block';
            return;
        }

        let html = `
            <div class="p-2 px-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                <span class="small text-muted fw-semibold">
                    <i class="bi bi-lightning-charge text-warning me-1"></i> Top ${results.length} of ${total} match${total === 1 ? '' : 'es'}
                </span>
                <a href="products.php?search=${encodeURIComponent(query)}&category_id=${encodeURIComponent(categoryFilter.value)}&stock_filter=${encodeURIComponent(stockFilter.value)}" class="small text-decoration-none fw-bold text-brand">
                    View full catalog list <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="list-group list-group-flush" id="dash_results_list">
        `;

        results.forEach((item, index) => {
            const isCombo = item.product_type === 'combo';
            const imgHtml = item.image_url 
                ? `<img src="${escapeHtml(item.image_url)}" alt="thumbnail" class="rounded border" style="width: 48px; height: 48px; object-fit: cover;">`
                : `<div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px;"><i class="bi bi-image"></i></div>`;

            html += `
                <div class="list-group-item p-3 dash-search-result-item" data-index="${index}" data-edit-url="${item.edit_url}">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3" style="min-width: 0; flex: 1;">
                            <div class="flex-shrink-0">
                                ${imgHtml}
                            </div>
                            <div class="flex-grow-1 text-truncate">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-bold text-dark text-truncate" style="font-size: 14px;">
                                        ${highlightQuery(item.name, query)}
                                    </span>
                                    ${isCombo ? `<span class="badge bg-purple-subtle text-purple border" style="font-size: 10px;"><i class="bi bi-stars"></i> Combo</span>` : ''}
                                    ${item.status === 0 ? `<span class="badge bg-secondary-subtle text-secondary" style="font-size: 10px;">Draft</span>` : ''}
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 small text-muted">
                                    <span><strong>SKU:</strong> ${highlightQuery(item.sku, query)}</span>
                                    <span>•</span>
                                    <span><strong>Category:</strong> ${highlightQuery(item.category_name, query)}</span>
                                    <span>•</span>
                                    <span class="badge ${item.stock_badge_class}" style="font-size: 11px;">${escapeHtml(item.stock_label)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3 flex-shrink-0">
                            <div class="text-end">
                                <div class="fw-bold text-dark" style="font-size: 14px;">
                                    ${escapeHtml(item.formatted_offer_price || item.formatted_regular_price)}
                                </div>
                                ${item.offer_price ? `<div class="text-muted small text-decoration-line-through">${escapeHtml(item.formatted_regular_price)}</div>` : ''}
                            </div>

                            <div class="d-flex gap-1">
                                <a href="${item.edit_url}" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Product">
                                    <i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline ms-1">Edit</span>
                                </a>
                                <a href="${item.matrix_url}" class="btn btn-sm btn-outline-secondary py-1 px-2" title="${isCombo ? 'Manage Combo' : 'Manage Variants Matrix'}">
                                    <i class="bi ${isCombo ? 'bi-sliders' : 'bi-grid-3x3-gap'}"></i>
                                </a>
                                <a href="${item.view_url}" target="_blank" class="btn btn-sm btn-outline-dark py-1 px-2" title="View on Storefront">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
            <div class="p-2 bg-light border-top text-center">
                <a href="products.php?search=${encodeURIComponent(query)}&category_id=${encodeURIComponent(categoryFilter.value)}&stock_filter=${encodeURIComponent(stockFilter.value)}" class="btn btn-sm btn-brand w-100 py-1">
                    <i class="bi bi-grid me-1"></i> View All ${total} Matching Products in Full Catalog Table
                </a>
            </div>
        `;

        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';

        // Add click events to items to navigate
        const itemRows = resultsContainer.querySelectorAll('.dash-search-result-item');
        itemRows.forEach(row => {
            row.addEventListener('click', function(e) {
                // If clicked on an action button or link, let it handle natively
                if (e.target.closest('a') || e.target.closest('button')) return;
                const editUrl = this.getAttribute('data-edit-url');
                if (editUrl) window.location.href = editUrl;
            });
        });
    }

    function renderError(msg) {
        resultsContainer.innerHTML = `
            <div class="p-3 text-center text-danger small">
                <i class="bi bi-exclamation-circle me-1"></i> ${escapeHtml(msg)}
            </div>
        `;
        resultsContainer.style.display = 'block';
    }

    // Trigger debounced search
    function scheduleSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performSearch, 250);
    }

    // Input event listeners
    if (searchInput) {
        searchInput.addEventListener('input', scheduleSearch);
        searchInput.addEventListener('focus', function() {
            if (searchInput.value.trim().length > 0 || currentResults.length > 0) {
                resultsContainer.style.display = 'block';
            } else {
                performSearch();
            }
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', performSearch);
    }

    if (stockFilter) {
        stockFilter.addEventListener('change', performSearch);
    }

    if (searchClear) {
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchClear.style.display = 'none';
            searchInput.focus();
            performSearch();
        });
    }

    // Quick Filter Chips click
    quickFilterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            quickFilterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const st = this.getAttribute('data-stock');
            const tp = this.getAttribute('data-type');
            const pub = this.getAttribute('data-status');

            if (st) {
                stockFilter.value = st;
                activeFilterStatus = 'all';
                activeFilterType = 'all';
            } else if (tp) {
                stockFilter.value = 'all';
                activeFilterStatus = 'all';
                activeFilterType = tp;
            } else if (pub) {
                stockFilter.value = 'all';
                activeFilterStatus = pub;
                activeFilterType = 'all';
            } else {
                stockFilter.value = 'all';
                categoryFilter.value = '';
                activeFilterStatus = 'all';
                activeFilterType = 'all';
            }

            performSearch();
        });
    });

    // Keyboard Navigation in Results
    document.addEventListener('keydown', function(e) {
        // Global shortcut Ctrl+K or Cmd+K or "/" when not in an input
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            return;
        }

        if (e.key === '/' && document.activeElement !== searchInput && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            return;
        }

        // When dropdown is visible
        if (resultsContainer.style.display === 'block') {
            const items = resultsContainer.querySelectorAll('.dash-search-result-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateActiveSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                updateActiveSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0 && selectedIndex < items.length) {
                e.preventDefault();
                const targetUrl = items[selectedIndex].getAttribute('data-edit-url');
                if (targetUrl) window.location.href = targetUrl;
            } else if (e.key === 'Escape') {
                resultsContainer.style.display = 'none';
            }
        }
    });

    function updateActiveSelection(items) {
        items.forEach((it, idx) => {
            if (idx === selectedIndex) {
                it.classList.add('active-item');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.classList.remove('active-item');
            }
        });
    }

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!searchForm.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

