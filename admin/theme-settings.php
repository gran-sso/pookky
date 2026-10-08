<?php
// admin/theme-settings.php
// Website Theme, Appearance & Typography Manager

$page_title = 'Theme & Appearance Settings';
$active_menu = 'theme';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/security.php';

// Check permissions
require_admin_permission('all');

$error = '';
$success = '';

// Handle theme configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please reload and try again.';
    } else {
        try {
            $pdo->beginTransaction();

            $theme_keys = [
                'theme_preset',
                'theme_primary_color',
                'theme_primary_hover',
                'theme_bg_canvas',
                'theme_bg_surface',
                'theme_heading_color',
                'theme_body_color',
                'theme_card_border',
                'theme_font_pairing',
                'theme_font_base_size',
                'theme_navbar_font_size',
                'theme_button_radius'
            ];

            foreach ($theme_keys as $key) {
                $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';

                // Sanitize color hex codes if applicable
                if (strpos($key, 'color') !== false || strpos($key, 'bg_') !== false || strpos($key, 'border') !== false) {
                    if (!preg_match('/^#[a-fA-F0-9]{3,8}$/', $val) && !empty($val)) {
                        $val = '#FFFFFF';
                    }
                }

                $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
                $stmt->execute([$key]);
                if ($stmt->fetch()) {
                    $up = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $up->execute([$val, $key]);
                } else {
                    $in = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $in->execute([$key, $val]);
                }
            }

            $pdo->commit();
            log_admin_action($current_admin_id, "Updated website visual theme & typography settings");
            set_flash_message('success', 'Theme & Appearance settings updated successfully! Live website has been updated.');
            header('Location: theme-settings.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to save theme settings: ' . $e->getMessage();
        }
    }
}

// Fetch current values with modern defaults
$cur_preset = get_setting('theme_preset', 'luminous_rose');
$cur_primary = get_setting('theme_primary_color', '#E12B56');
$cur_hover = get_setting('theme_primary_hover', '#C21845');
$cur_bg_canvas = get_setting('theme_bg_canvas', '#FFFFFF');
$cur_bg_surface = get_setting('theme_bg_surface', '#FFF1F5');
$cur_heading = get_setting('theme_heading_color', '#18181B');
$cur_body = get_setting('theme_body_color', '#374151');
$cur_card_border = get_setting('theme_card_border', '#E5E7EB');
$cur_font_pairing = get_setting('theme_font_pairing', 'modern_jakarta');
$cur_font_base = get_setting('theme_font_base_size', '16px');
$cur_navbar_font = get_setting('theme_navbar_font_size', '15px');
$cur_btn_radius = get_setting('theme_button_radius', 'pill');
?>

<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-palette2 text-primary me-2"></i> Website Theme & Appearance
            </h2>
            <p class="text-muted small mb-0">Control colors, typography, font sizes, and brightness across the entire customer storefront.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../index.php" target="_blank" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-box-arrow-up-right me-1"></i> Preview Storefront
            </a>
            <button type="submit" form="form-theme-settings" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-check2-circle me-1"></i> Save & Publish
            </button>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- 1-CLICK THEME PRESET SELECTOR -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-stars text-warning me-2"></i> 1-Click Theme Presets
                    </h5>
                    <p class="text-muted small mb-0">Click any preset to instantly configure colors, typography, and scaling.</p>
                </div>
                <span class="badge bg-light text-dark border px-3 py-2">
                    Current Preset: <strong id="active-preset-badge" class="text-primary"><?php echo ucfirst(str_replace('_', ' ', $cur_preset)); ?></strong>
                </span>
            </div>

            <div class="row g-3">
                <!-- Preset 1: Luminous Rose -->
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="preset-card card h-100 border p-3 rounded-4 cursor-pointer position-relative <?php echo $cur_preset === 'luminous_rose' ? 'border-primary bg-primary-subtle' : 'bg-light'; ?>"
                         onclick="applyPreset('luminous_rose')">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">🌟 Luminous Rose</span>
                            <span class="badge bg-success small">Recommended</span>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 12px;">Ultra-bright, modern & crisp. Pure white canvas with punchy coral-rose CTAs.</p>
                        <div class="d-flex gap-1 align-items-center mb-2">
                            <span class="swatch-bubble" style="background:#FFFFFF; border:1px solid #ddd;" title="Canvas: Pure White"></span>
                            <span class="swatch-bubble" style="background:#E12B56;" title="Primary: Vibrant Rose"></span>
                            <span class="swatch-bubble" style="background:#18181B;" title="Headings: Obsidian"></span>
                            <span class="swatch-bubble" style="background:#FFF1F5;" title="Surface: Soft Pink"></span>
                        </div>
                        <div class="small fw-semibold text-primary mt-auto">Plus Jakarta Sans &bull; 16px Base</div>
                    </div>
                </div>

                <!-- Preset 2: Modern Berry -->
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="preset-card card h-100 border p-3 rounded-4 cursor-pointer position-relative <?php echo $cur_preset === 'modern_berry' ? 'border-primary bg-primary-subtle' : 'bg-light'; ?>"
                         onclick="applyPreset('modern_berry')">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">💎 Modern Berry</span>
                            <span class="badge bg-info-subtle text-info-emphasis small">High Fashion</span>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 12px;">Bold, editorial luxury. Ice-white canvas with radiant ruby berry accents.</p>
                        <div class="d-flex gap-1 align-items-center mb-2">
                            <span class="swatch-bubble" style="background:#FAFBFC; border:1px solid #ddd;" title="Canvas: Ice White"></span>
                            <span class="swatch-bubble" style="background:#D81B60;" title="Primary: Ruby Berry"></span>
                            <span class="swatch-bubble" style="background:#111827;" title="Headings: Carbon"></span>
                            <span class="swatch-bubble" style="background:#FDF2F8;" title="Surface: Berry Glow"></span>
                        </div>
                        <div class="small fw-semibold text-primary mt-auto">Playfair Display &bull; 16px Base</div>
                    </div>
                </div>

                <!-- Preset 3: Champagne Glamour -->
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="preset-card card h-100 border p-3 rounded-4 cursor-pointer position-relative <?php echo $cur_preset === 'champagne_glamour' ? 'border-primary bg-primary-subtle' : 'bg-light'; ?>"
                         onclick="applyPreset('champagne_glamour')">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">✨ Champagne & Coral</span>
                            <span class="badge bg-warning-subtle text-warning-emphasis small">Warm Chic</span>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 12px;">Warm elegance. Pearl canvas, radiant rose gold accents, bold serif titles.</p>
                        <div class="d-flex gap-1 align-items-center mb-2">
                            <span class="swatch-bubble" style="background:#FCFCFA; border:1px solid #ddd;" title="Canvas: Pearl"></span>
                            <span class="swatch-bubble" style="background:#E74C6F;" title="Primary: Coral Rose"></span>
                            <span class="swatch-bubble" style="background:#231B1E;" title="Headings: Espresso"></span>
                            <span class="swatch-bubble" style="background:#FFF5F5;" title="Surface: Warm Glow"></span>
                        </div>
                        <div class="small fw-semibold text-primary mt-auto">Bodoni Moda (Bold) &bull; 16px Base</div>
                    </div>
                </div>

                <!-- Preset 4: Classic Vintage (Original) -->
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="preset-card card h-100 border p-3 rounded-4 cursor-pointer position-relative <?php echo $cur_preset === 'classic_vintage' ? 'border-primary bg-primary-subtle' : 'bg-light'; ?>"
                         onclick="applyPreset('classic_vintage')">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">🏛️ Classic Vintage</span>
                            <span class="badge bg-secondary-subtle text-secondary small">Original State</span>
                        </div>
                        <p class="text-muted small mb-3" style="font-size: 12px;">The original store look. Warm cream background with muted dusty rose.</p>
                        <div class="d-flex gap-1 align-items-center mb-2">
                            <span class="swatch-bubble" style="background:#FAF9F6; border:1px solid #ddd;" title="Canvas: Cream"></span>
                            <span class="swatch-bubble" style="background:#B76E79;" title="Primary: Dusty Rose"></span>
                            <span class="swatch-bubble" style="background:#5D3A42;" title="Headings: Mauve"></span>
                            <span class="swatch-bubble" style="background:#FFF0F2;" title="Surface: Blush"></span>
                        </div>
                        <div class="small fw-semibold text-secondary mt-auto">Bodoni Moda (400) &bull; 15px Base</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CUSTOMIZER & LIVE PREVIEW SPLIT -->
    <form action="theme-settings.php" method="POST" id="form-theme-settings">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="theme_preset" id="input_theme_preset" value="<?php echo e($cur_preset); ?>">

        <div class="row g-4">
            <!-- Left Column: Granular Form Controls -->
            <div class="col-12 col-lg-7">
                
                <!-- 1. COLOR CUSTOMIZER -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                    <div class="card-header bg-transparent border-bottom p-4">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-droplet-fill text-danger me-2"></i> 1. Color Palette Customization
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <!-- Primary CTA Brand Color -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Primary Brand Accent & Buttons <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_primary" value="<?php echo e($cur_primary); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_primary_color" id="input_primary" value="<?php echo e($cur_primary); ?>" required>
                                </div>
                                <div class="d-flex gap-1 mt-2 flex-wrap">
                                    <button type="button" class="swatch-btn" style="background:#E12B56;" onclick="setColor('primary', '#E12B56', '#C21845')"></button>
                                    <button type="button" class="swatch-btn" style="background:#D81B60;" onclick="setColor('primary', '#D81B60', '#AD1457')"></button>
                                    <button type="button" class="swatch-btn" style="background:#E63956;" onclick="setColor('primary', '#E63956', '#C72541')"></button>
                                    <button type="button" class="swatch-btn" style="background:#E74C6F;" onclick="setColor('primary', '#E74C6F', '#C43656')"></button>
                                    <button type="button" class="swatch-btn" style="background:#B76E79;" onclick="setColor('primary', '#B76E79', '#9C5862')"></button>
                                    <button type="button" class="swatch-btn" style="background:#4F46E5;" onclick="setColor('primary', '#4F46E5', '#3730A3')"></button>
                                </div>
                            </div>

                            <!-- Primary Hover Color -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Button Hover Tone</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_hover" value="<?php echo e($cur_hover); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_primary_hover" id="input_hover" value="<?php echo e($cur_hover); ?>">
                                </div>
                                <div class="form-text small text-muted">Applied when customers hover over CTA buttons.</div>
                            </div>

                            <!-- Canvas Background -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Store Canvas Background</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_bg_canvas" value="<?php echo e($cur_bg_canvas); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_bg_canvas" id="input_bg_canvas" value="<?php echo e($cur_bg_canvas); ?>" required>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('bg_canvas', '#FFFFFF')">Pure White (#FFF)</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('bg_canvas', '#FAFBFC')">Ice White</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('bg_canvas', '#FAF9F6')">Warm Cream</button>
                                </div>
                            </div>

                            <!-- Alternate Surface / Card Background -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Subtle Card / Banner Tint</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_bg_surface" value="<?php echo e($cur_bg_surface); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_bg_surface" id="input_bg_surface" value="<?php echo e($cur_bg_surface); ?>">
                                </div>
                                <div class="form-text small text-muted">Soft background for banners, badges and highlight containers.</div>
                            </div>

                            <!-- Headings & Title Tone -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Headings & Section Titles Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_heading" value="<?php echo e($cur_heading); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_heading_color" id="input_heading" value="<?php echo e($cur_heading); ?>" required>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('heading', '#18181B')">Deep Obsidian</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('heading', '#111827')">Carbon</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('heading', '#5D3A42')">Classic Mauve</button>
                                </div>
                            </div>

                            <!-- Body Text Tone -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Body Text & Descriptions</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_body" value="<?php echo e($cur_body); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_body_color" id="input_body" value="<?php echo e($cur_body); ?>" required>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('body', '#374151')">Charcoal Slate</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('body', '#4B5563')">Medium Slate</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 small" onclick="setDirect('body', '#2E2E2E')">Classic Dark</button>
                                </div>
                            </div>

                            <!-- Border Color -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Card & Divider Border Tone</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="picker_border" value="<?php echo e($cur_card_border); ?>" style="width: 48px; height: 38px;">
                                    <input type="text" class="form-control font-monospace" name="theme_card_border" id="input_border" value="<?php echo e($cur_card_border); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. TYPOGRAPHY & SCALING CUSTOMIZER -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
                    <div class="card-header bg-transparent border-bottom p-4">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-fonts text-info me-2"></i> 2. Typography Pairings & Sizing
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <!-- Font Pairing -->
                            <div class="col-12">
                                <label class="form-label fw-bold small text-dark">Google Font Pairing Strategy <span class="text-danger">*</span></label>
                                <select class="form-select" name="theme_font_pairing" id="select_font_pairing" onchange="updateLivePreview()">
                                    <option value="modern_jakarta" <?php echo $cur_font_pairing === 'modern_jakarta' ? 'selected' : ''; ?>>
                                        🌟 Plus Jakarta Sans (Modern, Ultra-Crisp, High Legibility - Recommended)
                                    </option>
                                    <option value="modern_outfit" <?php echo $cur_font_pairing === 'modern_outfit' ? 'selected' : ''; ?>>
                                        ✨ Outfit + Plus Jakarta Sans (Open, Friendly & Contemporary)
                                    </option>
                                    <option value="editorial_playfair" <?php echo $cur_font_pairing === 'editorial_playfair' ? 'selected' : ''; ?>>
                                        💎 Playfair Display + Inter (High Fashion, Editorial Magazine)
                                    </option>
                                    <option value="classic_bodoni" <?php echo $cur_font_pairing === 'classic_bodoni' ? 'selected' : ''; ?>>
                                        🏛️ Bodoni Moda + Montserrat (Classic Vintage Luxury)
                                    </option>
                                    <option value="clean_inter" <?php echo $cur_font_pairing === 'clean_inter' ? 'selected' : ''; ?>>
                                        ⚡ Inter Sans (Clean Minimalist Swiss Style)
                                    </option>
                                </select>
                            </div>

                            <!-- Base Font Size -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Base Body Font Size</label>
                                <select class="form-select" name="theme_font_base_size" id="select_font_base" onchange="updateLivePreview()">
                                    <option value="15px" <?php echo $cur_font_base === '15px' ? 'selected' : ''; ?>>15px (Original Compact)</option>
                                    <option value="16px" <?php echo $cur_font_base === '16px' ? 'selected' : ''; ?>>16px (Modern Standard - Recommended)</option>
                                    <option value="17px" <?php echo $cur_font_base === '17px' ? 'selected' : ''; ?>>17px (Enhanced Readability)</option>
                                </select>
                            </div>

                            <!-- Navbar Font Size -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Navbar Menu Font Size</label>
                                <select class="form-select" name="theme_navbar_font_size" id="select_navbar_font" onchange="updateLivePreview()">
                                    <option value="13.5px" <?php echo $cur_navbar_font === '13.5px' ? 'selected' : ''; ?>>13.5px (Original Petite)</option>
                                    <option value="15px" <?php echo $cur_navbar_font === '15px' ? 'selected' : ''; ?>>15px (Clean & Visible - Recommended)</option>
                                    <option value="16px" <?php echo $cur_navbar_font === '16px' ? 'selected' : ''; ?>>16px (Prominent & Bold)</option>
                                </select>
                            </div>

                            <!-- Button Corner Radius -->
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small text-dark">Buttons & Badges Corner Radius</label>
                                <select class="form-select" name="theme_button_radius" id="select_btn_radius" onchange="updateLivePreview()">
                                    <option value="pill" <?php echo $cur_btn_radius === 'pill' ? 'selected' : ''; ?>>Pill Curved (Full Capsule 50px - Recommended)</option>
                                    <option value="rounded" <?php echo $cur_btn_radius === 'rounded' ? 'selected' : ''; ?>>Modern Rounded (10px Smooth)</option>
                                    <option value="square" <?php echo $cur_btn_radius === 'square' ? 'selected' : ''; ?>>Sharp Minimalist (4px Clean)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-5">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-4" onclick="applyPreset('classic_vintage')">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Revert to Original State
                    </button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save & Publish Theme
                    </button>
                </div>
            </div>

            <!-- Right Column: Interactive Live Preview Card -->
            <div class="col-12 col-lg-5">
                <div class="sticky-top" style="top: 20px; z-index: 10;">
                    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
                        <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
                            <span class="fw-bold small"><i class="bi bi-eye-fill me-1"></i> Real-time Live Preview</span>
                            <span class="badge bg-light text-dark small">Instant Feedback</span>
                        </div>
                        
                        <!-- Simulated Website Container -->
                        <div id="preview-container" class="p-4" style="background-color: <?php echo e($cur_bg_canvas); ?>; transition: all 0.25s ease;">
                            
                            <!-- Navbar Preview -->
                            <div id="preview-navbar" class="bg-white border rounded-3 p-2 px-3 mb-4 shadow-sm d-flex justify-content-between align-items-center">
                                <div class="fw-bold fs-5 text-dark">Pookky</div>
                                <div class="d-flex gap-3 align-items-center">
                                    <span id="preview-nav-item" style="font-size: <?php echo e($cur_navbar_font); ?>; font-weight:600; color: <?php echo e($cur_heading); ?>;">Bras</span>
                                    <span style="font-size: <?php echo e($cur_navbar_font); ?>; font-weight:600; color: <?php echo e($cur_primary); ?>;">Panties</span>
                                    <span class="badge rounded-pill text-white" id="preview-badge" style="background-color: <?php echo e($cur_primary); ?>;">Cart (2)</span>
                                </div>
                            </div>

                            <!-- Hero Heading Preview -->
                            <div class="mb-3">
                                <span class="badge mb-2 px-3 py-1" id="preview-chip" style="background-color: <?php echo e($cur_bg_surface); ?>; color: <?php echo e($cur_primary); ?>; font-weight: 600;">NEW ARRIVALS</span>
                                <h3 id="preview-title" class="fw-bold mb-2" style="color: <?php echo e($cur_heading); ?>; font-size: 26px;">
                                    Experience Everyday Comfort & Fit
                                </h3>
                                <p id="preview-body" class="mb-3" style="color: <?php echo e($cur_body); ?>; font-size: <?php echo e($cur_font_base); ?>;">
                                    Luxurious, breathable fabrics designed to give you flawless support and unmatched elegance all day long.
                                </p>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn fw-semibold px-4 py-2" id="preview-btn-primary" style="background-color: <?php echo e($cur_primary); ?>; color: #FFFFFF; border-radius: 50px;">
                                        Shop Now
                                    </button>
                                    <button type="button" class="btn fw-semibold px-4 py-2" id="preview-btn-outline" style="border: 1px solid <?php echo e($cur_primary); ?>; color: <?php echo e($cur_primary); ?>; border-radius: 50px;">
                                        View Details
                                    </button>
                                </div>
                            </div>

                            <!-- Product Card Preview -->
                            <div class="mt-4 pt-3 border-top" id="preview-card-section">
                                <div class="card border rounded-4 shadow-sm overflow-hidden bg-white" id="preview-card" style="border-color: <?php echo e($cur_card_border); ?> !important;">
                                    <div class="p-3 text-center bg-light-subtle position-relative" style="height: 120px; display:flex; align-items:center; justify-content:center;">
                                        <i class="bi bi-bag-heart text-muted fs-1"></i>
                                        <span class="position-absolute top-0 end-0 m-2 badge" id="preview-discount-badge" style="background-color: <?php echo e($cur_primary); ?>; color: #fff;">
                                            25% OFF
                                        </span>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="small fw-semibold text-muted mb-1">Seamless Collection</div>
                                        <h6 class="fw-bold mb-2" id="preview-product-name" style="color: <?php echo e($cur_heading); ?>;">All-Day Wireless Soft Bra</h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="fw-bold fs-5" id="preview-price" style="color: <?php echo e($cur_primary); ?>;">₹ 799</span>
                                                <span class="text-muted small text-decoration-line-through ms-1">₹ 999</span>
                                            </div>
                                            <button type="button" class="btn btn-sm" id="preview-card-btn" style="background-color: <?php echo e($cur_primary); ?>; color: #fff; border-radius: 50px;">
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.preset-card {
    transition: all 0.2s ease-in-out;
}
.preset-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
}
.swatch-bubble {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: inline-block;
}
.swatch-btn {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid #FFFFFF;
    box-shadow: 0 0 0 1px #DDD;
    cursor: pointer;
    transition: transform 0.15s ease;
}
.swatch-btn:hover {
    transform: scale(1.15);
}
</style>

<script>
// Predefined Preset Data
const PRESETS = {
    'luminous_rose': {
        name: 'Luminous Rose',
        primary: '#E12B56',
        hover: '#C21845',
        bg_canvas: '#FFFFFF',
        bg_surface: '#FFF1F5',
        heading: '#18181B',
        body: '#374151',
        border: '#E5E7EB',
        font_pairing: 'modern_jakarta',
        font_base: '16px',
        navbar_font: '15px',
        btn_radius: 'pill'
    },
    'modern_berry': {
        name: 'Modern Berry',
        primary: '#D81B60',
        hover: '#AD1457',
        bg_canvas: '#FAFBFC',
        bg_surface: '#FDF2F8',
        heading: '#111827',
        body: '#374151',
        border: '#E5E7EB',
        font_pairing: 'editorial_playfair',
        font_base: '16px',
        navbar_font: '15px',
        btn_radius: 'pill'
    },
    'champagne_glamour': {
        name: 'Champagne & Coral',
        primary: '#E74C6F',
        hover: '#C43656',
        bg_canvas: '#FCFCFA',
        bg_surface: '#FFF5F5',
        heading: '#231B1E',
        body: '#3F393B',
        border: '#EAD5C3',
        font_pairing: 'classic_bodoni',
        font_base: '16px',
        navbar_font: '15px',
        btn_radius: 'pill'
    },
    'classic_vintage': {
        name: 'Classic Vintage',
        primary: '#B76E79',
        hover: '#9C5862',
        bg_canvas: '#FAF9F6',
        bg_surface: '#FFF0F2',
        heading: '#5D3A42',
        body: '#2E2E2E',
        border: '#F1F0EC',
        font_pairing: 'classic_bodoni',
        font_base: '15px',
        navbar_font: '13.5px',
        btn_radius: 'pill'
    }
};

function applyPreset(key) {
    const p = PRESETS[key];
    if (!p) return;

    document.getElementById('input_theme_preset').value = key;
    document.getElementById('active-preset-badge').textContent = p.name;

    // Set colors
    setColorValues('primary', p.primary);
    setColorValues('hover', p.hover);
    setColorValues('bg_canvas', p.bg_canvas);
    setColorValues('bg_surface', p.bg_surface);
    setColorValues('heading', p.heading);
    setColorValues('body', p.body);
    setColorValues('border', p.border);

    // Set dropdowns
    document.getElementById('select_font_pairing').value = p.font_pairing;
    document.getElementById('select_font_base').value = p.font_base;
    document.getElementById('select_navbar_font').value = p.navbar_font;
    document.getElementById('select_btn_radius').value = p.btn_radius;

    // Highlight active preset card
    document.querySelectorAll('.preset-card').forEach(card => {
        card.classList.remove('border-primary', 'bg-primary-subtle');
        card.classList.add('bg-light');
    });
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('border-primary', 'bg-primary-subtle');
        event.currentTarget.classList.remove('bg-light');
    }

    updateLivePreview();
}

function setColorValues(type, hex) {
    const input = document.getElementById('input_' + type);
    const picker = document.getElementById('picker_' + type);
    if (input) input.value = hex;
    if (picker) picker.value = hex;
}

function setColor(type, primaryHex, hoverHex) {
    setColorValues('primary', primaryHex);
    if (hoverHex) setColorValues('hover', hoverHex);
    markPresetCustom();
    updateLivePreview();
}

function setDirect(type, hex) {
    setColorValues(type, hex);
    markPresetCustom();
    updateLivePreview();
}

function markPresetCustom() {
    document.getElementById('input_theme_preset').value = 'custom';
    document.getElementById('active-preset-badge').textContent = 'Custom';
}

// Sync color pickers with text inputs
['primary', 'hover', 'bg_canvas', 'bg_surface', 'heading', 'body', 'border'].forEach(type => {
    const picker = document.getElementById('picker_' + type);
    const input = document.getElementById('input_' + type);

    if (picker && input) {
        picker.addEventListener('input', function() {
            input.value = this.value;
            markPresetCustom();
            updateLivePreview();
        });
        input.addEventListener('input', function() {
            if (/^#[a-fA-F0-9]{6}$/.test(this.value)) {
                picker.value = this.value;
            }
            markPresetCustom();
            updateLivePreview();
        });
    }
});

function updateLivePreview() {
    const primary = document.getElementById('input_primary').value || '#E12B56';
    const bgCanvas = document.getElementById('input_bg_canvas').value || '#FFFFFF';
    const bgSurface = document.getElementById('input_bg_surface').value || '#FFF1F5';
    const heading = document.getElementById('input_heading').value || '#18181B';
    const body = document.getElementById('input_body').value || '#374151';
    const border = document.getElementById('input_border').value || '#E5E7EB';
    
    const fontBase = document.getElementById('select_font_base').value || '16px';
    const navbarFont = document.getElementById('select_navbar_font').value || '15px';
    const radiusType = document.getElementById('select_btn_radius').value || 'pill';

    let radiusCss = '50px';
    if (radiusType === 'rounded') radiusCss = '10px';
    if (radiusType === 'square') radiusCss = '4px';

    // Update Live Preview elements
    const container = document.getElementById('preview-container');
    if (container) container.style.backgroundColor = bgCanvas;

    const navItem = document.getElementById('preview-nav-item');
    if (navItem) {
        navItem.style.color = heading;
        navItem.style.fontSize = navbarFont;
    }

    const badge = document.getElementById('preview-badge');
    if (badge) badge.style.backgroundColor = primary;

    const chip = document.getElementById('preview-chip');
    if (chip) {
        chip.style.backgroundColor = bgSurface;
        chip.style.color = primary;
    }

    const title = document.getElementById('preview-title');
    if (title) title.style.color = heading;

    const bodyEl = document.getElementById('preview-body');
    if (bodyEl) {
        bodyEl.style.color = body;
        bodyEl.style.fontSize = fontBase;
    }

    const btnPri = document.getElementById('preview-btn-primary');
    if (btnPri) {
        btnPri.style.backgroundColor = primary;
        btnPri.style.borderRadius = radiusCss;
    }

    const btnOut = document.getElementById('preview-btn-outline');
    if (btnOut) {
        btnOut.style.borderColor = primary;
        btnOut.style.color = primary;
        btnOut.style.borderRadius = radiusCss;
    }

    const card = document.getElementById('preview-card');
    if (card) card.style.borderColor = border;

    const prodName = document.getElementById('preview-product-name');
    if (prodName) prodName.style.color = heading;

    const price = document.getElementById('preview-price');
    if (price) price.style.color = primary;

    const cardBtn = document.getElementById('preview-card-btn');
    if (cardBtn) {
        cardBtn.style.backgroundColor = primary;
        cardBtn.style.borderRadius = radiusCss;
    }

    const discBadge = document.getElementById('preview-discount-badge');
    if (discBadge) discBadge.style.backgroundColor = primary;
}

// Initial live preview render
document.addEventListener('DOMContentLoaded', updateLivePreview);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
