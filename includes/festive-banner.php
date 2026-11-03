<?php
// includes/festive-banner.php
// Dynamic E-Commerce Festive Theme & Ambient Celebration Engine for Pookky

$festive_enabled = get_setting('festive_mode_enabled', '0') === '1';
if (!$festive_enabled) {
    return; // Zero performance overhead when turned off
}

$festive_theme = get_setting('festive_theme', 'diwali');
$festive_anim_enabled = get_setting('festive_animation_enabled', '1') === '1';
$festive_badge = get_setting('festive_badge_text', 'DIWALI SPECIAL');
$festive_msg = get_setting('festive_message', 'Wishing you a Sparkling & Blessed Diwali! Enjoy complimentary gifts & extra savings on all luxury collections.');
$festive_code = trim(get_setting('festive_coupon_code', 'DIWALI15'));
$festive_discount = trim(get_setting('festive_coupon_discount', 'Flat 15% OFF'));
$festive_btn_text = get_setting('festive_btn_text', 'Shop Festive Sets');
$festive_btn_url = get_setting('festive_btn_url', 'products.php?type=combo');

// Theme definitions
$theme_cfg = [
    'diwali' => [
        'bg' => 'linear-gradient(90deg, #30060C 0%, #630C1C 25%, #841026 50%, #630C1C 75%, #30060C 100%)',
        'border' => '#E5B25D',
        'text_color' => '#FFF8F0',
        'badge_bg' => '#D4AF37',
        'badge_color' => '#2A060C',
        'icon' => '🪔',
        'coupon_bg' => 'rgba(212, 175, 55, 0.18)',
        'coupon_border' => '#D4AF37',
        'btn_bg' => '#D4AF37',
        'btn_color' => '#2A060C',
        'canvas_type' => 'sparkles'
    ],
    'christmas' => [
        'bg' => 'linear-gradient(90deg, #091A2F 0%, #142E4E 50%, #091A2F 100%)',
        'border' => '#94A3B8',
        'text_color' => '#F8FAFC',
        'badge_bg' => '#E2E8F0',
        'badge_color' => '#0F172A',
        'icon' => '❄️',
        'coupon_bg' => 'rgba(226, 232, 240, 0.15)',
        'coupon_border' => '#94A3B8',
        'btn_bg' => '#E2E8F0',
        'btn_color' => '#0F172A',
        'canvas_type' => 'snow'
    ],
    'valentines' => [
        'bg' => 'linear-gradient(90deg, #4C0519 0%, #9F1239 50%, #4C0519 100%)',
        'border' => '#FB7185',
        'text_color' => '#FFF1F2',
        'badge_bg' => '#FECDD3',
        'badge_color' => '#881337',
        'icon' => '💖',
        'coupon_bg' => 'rgba(254, 205, 211, 0.18)',
        'coupon_border' => '#FB7185',
        'btn_bg' => '#FECDD3',
        'btn_color' => '#881337',
        'canvas_type' => 'hearts'
    ],
    'celebration' => [
        'bg' => 'linear-gradient(90deg, #1E1B4B 0%, #4338CA 50%, #1E1B4B 100%)',
        'border' => '#A5B4FC',
        'text_color' => '#EEF2FF',
        'badge_bg' => '#FBBF24',
        'badge_color' => '#1E1B4B',
        'icon' => '🎉',
        'coupon_bg' => 'rgba(251, 191, 36, 0.18)',
        'coupon_border' => '#FBBF24',
        'btn_bg' => '#FBBF24',
        'btn_color' => '#1E1B4B',
        'canvas_type' => 'confetti'
    ]
];

$active_cfg = $theme_cfg[$festive_theme] ?? $theme_cfg['diwali'];
?>

<style>
/* ==========================================================================
   Festive Header Ribbon & Ambient Animations System
   ========================================================================== */
#festiveRibbonBanner {
    background: <?php echo $active_cfg['bg']; ?>;
    border-bottom: 2px solid <?php echo $active_cfg['border']; ?>;
    color: <?php echo $active_cfg['text_color']; ?>;
    font-size: 13px;
    z-index: 1042;
    box-shadow: 0 4px 15px rgba(0,0,0,0.18);
    position: relative;
    transition: opacity 0.3s ease;
}
.festive-ribbon-badge {
    background: <?php echo $active_cfg['badge_bg']; ?>;
    color: <?php echo $active_cfg['badge_color']; ?>;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
    border-radius: 50px;
    padding: 3px 10px;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
.festive-coupon-pill {
    background: <?php echo $active_cfg['coupon_bg']; ?>;
    border: 1px dashed <?php echo $active_cfg['coupon_border']; ?>;
    color: #ffffff;
    font-family: 'Courier New', Courier, monospace;
    font-weight: 700;
    font-size: 11.5px;
    padding: 2px 8px;
    border-radius: 6px;
    cursor: pointer;
    user-select: all;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
}
.festive-coupon-pill:hover {
    background: <?php echo $active_cfg['btn_bg']; ?>;
    color: <?php echo $active_cfg['btn_color']; ?>;
    border-style: solid;
}
.btn-festive-action {
    background: <?php echo $active_cfg['btn_bg']; ?>;
    color: <?php echo $active_cfg['btn_color']; ?> !important;
    font-weight: 700;
    font-size: 11.5px;
    padding: 3px 12px;
    border-radius: 50px;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    transition: transform 0.2s ease;
}
.btn-festive-action:hover {
    transform: scale(1.05);
}

/* Ambient Canvas Overlay */
#festiveAmbientCanvas {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 180px;
    pointer-events: none;
    z-index: 1045;
    opacity: 0.85;
}

/* Realistic Diwali Diya Motifs */
.festive-diya-unit {
    position: fixed;
    top: 6px;
    z-index: 1046;
    pointer-events: none;
    display: none;
}
@media (min-width: 992px) {
    .festive-diya-unit {
        display: block;
    }
}
.festive-diya-left {
    left: 14px;
}
.festive-diya-right {
    right: 14px;
    transform: scaleX(-1);
}
.diya-flame-glow {
    width: 12px;
    height: 20px;
    margin: 0 auto -4px;
    background: radial-gradient(ellipse at bottom, #FFFFFF 0%, #FFD700 45%, #FF5722 85%);
    border-radius: 50% 50% 20% 20%;
    animation: diyaFlicker 1.6s infinite alternate ease-in-out;
    filter: drop-shadow(0 0 6px #FFC107);
}
.diya-lamp-base {
    width: 28px;
    height: 13px;
    background: linear-gradient(180deg, #D4AF37 0%, #AA7C11 60%, #5E4304 100%);
    border-radius: 0 0 28px 28px;
    border-top: 1.5px solid #FFECB3;
    box-shadow: 0 3px 8px rgba(0,0,0,0.3);
}

@keyframes diyaFlicker {
    0% { transform: scale(0.95) rotate(-2deg); opacity: 0.9; }
    50% { transform: scale(1.08) rotate(1.5deg); opacity: 1; }
    100% { transform: scale(1) rotate(-0.5deg); opacity: 0.95; }
}
</style>

<!-- Top Festive Ribbon Banner -->
<div id="festiveRibbonBanner" class="py-2 px-3 position-relative text-center fw-medium">
    <div class="container d-flex align-items-center justify-content-center flex-wrap gap-2 pe-md-4">
        <!-- Festive Badge with Icon -->
        <span class="festive-ribbon-badge">
            <span class="me-1"><?php echo $active_cfg['icon']; ?></span> <?php echo e($festive_badge); ?>
        </span>

        <!-- Festive Message -->
        <span class="festive-msg-text">
            <?php echo e($festive_msg); ?>
        </span>

        <!-- Coupon Code Pill (if configured) -->
        <?php if (!empty($festive_code)): ?>
            <span class="festive-coupon-pill" onclick="copyFestiveCoupon('<?php echo e($festive_code); ?>', this)" title="Click to copy coupon code">
                <i class="bi bi-tag-fill me-1" style="font-size: 10px;"></i>
                <span id="festiveCouponCodeSpan"><?php echo e($festive_code); ?></span>
                <?php if (!empty($festive_discount)): ?>
                    <span class="text-white-50 ms-1 small">(<?php echo e($festive_discount); ?>)</span>
                <?php endif; ?>
                <i class="bi bi-clipboard ms-1" style="font-size: 10px;"></i>
            </span>
        <?php endif; ?>

        <!-- Shop Now CTA Button -->
        <?php if (!empty($festive_btn_url)): ?>
            <a href="<?php echo e($festive_btn_url); ?>" class="btn-festive-action ms-1">
                <?php echo e($festive_btn_text); ?> &rsaquo;
            </a>
        <?php endif; ?>

        <!-- Dismiss Button -->
        <button type="button" class="btn-close btn-close-white position-absolute top-50 end-0 translate-middle-y me-3 d-none d-md-inline-block" aria-label="Dismiss festive banner" onclick="dismissFestiveBanner()" style="font-size: 0.65rem; opacity: 0.75;"></button>
    </div>
</div>

<?php if ($festive_anim_enabled): ?>
    <!-- Ambient Canvas for Festive Particle Shimmer -->
    <canvas id="festiveAmbientCanvas"></canvas>

    <?php if ($festive_theme === 'diwali'): ?>
        <!-- Traditional Glowing Diyas in Desktop Corners -->
        <div class="festive-diya-unit festive-diya-left">
            <div class="diya-flame-glow"></div>
            <div class="diya-lamp-base"></div>
        </div>
        <div class="festive-diya-unit festive-diya-right">
            <div class="diya-flame-glow"></div>
            <div class="diya-lamp-base"></div>
        </div>
    <?php endif; ?>

    <script>
    // Lightweight Festive Ambient Particles (Canvas)
    (function() {
        var canvas = document.getElementById('festiveAmbientCanvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var theme = '<?php echo $active_cfg['canvas_type']; ?>';
        var particles = [];
        var maxParticles = 30;
        var width, height;

        function resize() {
            width = canvas.width = window.innerWidth;
            height = canvas.height = 180;
        }
        window.addEventListener('resize', resize);
        resize();

        for (var i = 0; i < maxParticles; i++) {
            particles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                radius: Math.random() * 2 + 1,
                speedY: Math.random() * 0.7 + 0.3,
                speedX: (Math.random() - 0.5) * 0.6,
                alpha: Math.random() * 0.7 + 0.3,
                fade: Math.random() * 0.02 + 0.005
            });
        }

        var isRunning = true;
        function animate() {
            if (!isRunning) return;
            ctx.clearRect(0, 0, width, height);

            for (var i = 0; i < particles.length; i++) {
                var p = particles[i];
                p.y += p.speedY;
                p.x += p.speedX;
                p.alpha -= p.fade;

                if (p.alpha <= 0 || p.y > height) {
                    p.x = Math.random() * width;
                    p.y = 0;
                    p.alpha = Math.random() * 0.7 + 0.3;
                }

                ctx.save();
                ctx.globalAlpha = Math.max(0, p.alpha);

                if (theme === 'sparkles' || theme === 'diwali') {
                    // Golden stardust spark
                    ctx.fillStyle = '#FFD700';
                    ctx.shadowBlur = 6;
                    ctx.shadowColor = '#FFA500';
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fill();
                } else if (theme === 'snow') {
                    // Soft white snowflake
                    ctx.fillStyle = '#FFFFFF';
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius * 1.2, 0, Math.PI * 2);
                    ctx.fill();
                } else if (theme === 'hearts') {
                    // Rose petal / heart glow
                    ctx.fillStyle = '#FB7185';
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    // Confetti
                    ctx.fillStyle = ['#F59E0B', '#EC4899', '#6366F1', '#10B981'][i % 4];
                    ctx.fillRect(p.x, p.y, p.radius * 2, p.radius * 2);
                }
                ctx.restore();
            }

            requestAnimationFrame(animate);
        }

        // Pause animation when tab is inactive to save battery
        document.addEventListener('visibilitychange', function() {
            isRunning = !document.hidden;
            if (isRunning) requestAnimationFrame(animate);
        });

        // Run ambient particles smoothly
        requestAnimationFrame(animate);
    })();
    </script>
<?php endif; ?>

<script>
// Check dismissed state in session
(function() {
    try {
        if (sessionStorage.getItem('pookky_festive_dismissed') === 'true') {
            var banner = document.getElementById('festiveRibbonBanner');
            if (banner) banner.style.display = 'none';
        }
    } catch(e){}
})();

// Dismiss action
function dismissFestiveBanner() {
    var banner = document.getElementById('festiveRibbonBanner');
    if (banner) {
        banner.style.opacity = '0';
        setTimeout(function() { banner.style.display = 'none'; }, 250);
    }
    try { sessionStorage.setItem('pookky_festive_dismissed', 'true'); } catch(e){}
}

// 1-Click Copy Coupon Code with feedback tooltip
function copyFestiveCoupon(code, element) {
    if (!navigator.clipboard) return;
    navigator.clipboard.writeText(code).then(function() {
        var originalText = element.innerHTML;
        element.innerHTML = '<i class="bi bi-check2 me-1"></i> COPIED!';
        element.style.background = '#22C55E';
        element.style.color = '#FFFFFF';
        setTimeout(function() {
            element.innerHTML = originalText;
            element.style.background = '';
            element.style.color = '';
        }, 2200);
    }).catch(function(){});
}
</script>

