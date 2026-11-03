<?php
// includes/size-guide-content.php
// Reusable, modern e-commerce size guide component with interactive unit switcher (Inches / CM)
?>
<div class="size-guide-wrapper">
    <!-- Top Bar: Navigation Pills & Unit Switcher -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
        <!-- Category Tabs -->
        <ul class="nav nav-pills gap-1" id="pookkySizeGuideTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active btn-sm px-3 py-1 fw-semibold" id="tab-btn-bras" data-bs-toggle="pill" data-bs-target="#tab-pane-bras" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-tag me-1"></i> Bras
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm px-3 py-1 fw-semibold" id="tab-btn-panties" data-bs-toggle="pill" data-bs-target="#tab-pane-panties" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-layers me-1"></i> Panties
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm px-3 py-1 fw-semibold" id="tab-btn-nightwear" data-bs-toggle="pill" data-bs-target="#tab-pane-nightwear" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-moon-stars me-1"></i> Nightwear
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm px-3 py-1 fw-semibold" id="tab-btn-howto" data-bs-toggle="pill" data-bs-target="#tab-pane-howto" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-rulers me-1"></i> How to Measure
                </button>
            </li>
        </ul>

        <!-- Interactive Unit Switcher Pill -->
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="text-muted small fw-semibold" style="font-size: 11px;">Unit:</span>
            <div class="btn-group btn-group-sm p-1 bg-light rounded-pill border" role="group" aria-label="Measurement Units">
                <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold size-unit-btn active btn-dark" data-unit="in" onclick="switchPookkySizeUnit('in')">
                    Inches
                </button>
                <button type="button" class="btn btn-xs rounded-pill px-2 py-0 fw-bold size-unit-btn text-muted" data-unit="cm" onclick="switchPookkySizeUnit('cm')">
                    CM
                </button>
            </div>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="pookkySizeGuideContent">
        <!-- ==========================================
             TAB 1: BRAS SIZING CHART
             ========================================== -->
        <div class="tab-pane fade show active" id="tab-pane-bras" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-dark text-uppercase">B-Cup Sizing (Everyday Cotton Fit)</span>
                <span class="badge bg-rose-subtle text-rose border small" style="font-size: 10px;">Zero Underwire</span>
            </div>

            <div class="table-responsive rounded-3 border mb-3">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-2 text-dark">Size Code</th>
                            <th class="py-2 text-dark">Underbust (Band)</th>
                            <th class="py-2 text-dark">Overbust (Bust)</th>
                            <th class="pe-3 py-2 text-dark">Fit Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">30B</td>
                            <td>
                                <span class="unit-in fw-semibold">26" – 27"</span>
                                <span class="unit-cm fw-semibold d-none">66 – 70 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">66–70 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">26–27 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">31" – 32"</span>
                                <span class="unit-cm fw-semibold d-none">79 – 82 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">79–82 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">31–32 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Petite / Slim</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">32B</td>
                            <td>
                                <span class="unit-in fw-semibold">28" – 29"</span>
                                <span class="unit-cm fw-semibold d-none">71 – 75 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">71–75 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">28–29 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">33" – 34"</span>
                                <span class="unit-cm fw-semibold d-none">84 – 87 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">84–87 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">33–34 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Regular Fit</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">34B</td>
                            <td>
                                <span class="unit-in fw-semibold">30" – 31"</span>
                                <span class="unit-cm fw-semibold d-none">76 – 80 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">76–80 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">30–31 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">35" – 36"</span>
                                <span class="unit-cm fw-semibold d-none">89 – 92 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">89–92 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">35–36 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Regular Fit</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">36B</td>
                            <td>
                                <span class="unit-in fw-semibold">32" – 33"</span>
                                <span class="unit-cm fw-semibold d-none">81 – 85 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">81–85 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">32–33 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">37" – 38"</span>
                                <span class="unit-cm fw-semibold d-none">94 – 97 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">94–97 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">37–38 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Regular Fit</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">38B</td>
                            <td>
                                <span class="unit-in fw-semibold">34" – 35"</span>
                                <span class="unit-cm fw-semibold d-none">86 – 90 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">86–90 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">34–35 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">39" – 40"</span>
                                <span class="unit-cm fw-semibold d-none">99 – 102 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">99–102 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">39–40 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Regular Fit</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">40B</td>
                            <td>
                                <span class="unit-in fw-semibold">36" – 37"</span>
                                <span class="unit-cm fw-semibold d-none">91 – 95 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">91–95 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">36–37 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">41" – 42"</span>
                                <span class="unit-cm fw-semibold d-none">104 – 107 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">104–107 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">41–42 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Plus / Comfort</span></td>
                        </tr>
                        <tr class="table-rose-subtle">
                            <td class="ps-3 fw-bold text-dark">42B</td>
                            <td>
                                <span class="unit-in fw-semibold">38" – 39"</span>
                                <span class="unit-cm fw-semibold d-none">96 – 100 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">96–100 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">38–39 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">43" – 44"</span>
                                <span class="unit-cm fw-semibold d-none">109 – 112 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">109–112 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">43–44 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-rose-subtle text-rose border">Extended Curvy</span></td>
                        </tr>
                        <tr class="table-rose-subtle">
                            <td class="ps-3 fw-bold text-dark">44B</td>
                            <td>
                                <span class="unit-in fw-semibold">40" – 41"</span>
                                <span class="unit-cm fw-semibold d-none">101 – 105 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">101–105 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">40–41 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">45" – 46"</span>
                                <span class="unit-cm fw-semibold d-none">114 – 117 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">114–117 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">45–46 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-rose-subtle text-rose border">Extended Curvy</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">46B</td>
                            <td>
                                <span class="unit-in fw-semibold">42" – 43"</span>
                                <span class="unit-cm fw-semibold d-none">106 – 110 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">106–110 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">42–43 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">47" – 48"</span>
                                <span class="unit-cm fw-semibold d-none">119 – 122 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">119–122 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">47–48 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Extended Curvy</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- C-Cup Section -->
            <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
                <span class="small fw-bold text-dark text-uppercase">C-Cup Sizing (Fuller Bust Coverage)</span>
            </div>
            <div class="table-responsive rounded-3 border mb-3">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-2 text-dark">Size Code</th>
                            <th class="py-2 text-dark">Underbust (Band)</th>
                            <th class="py-2 text-dark">Overbust (Bust)</th>
                            <th class="pe-3 py-2 text-dark">Fit Classification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">32C</td>
                            <td>
                                <span class="unit-in fw-semibold">28" – 29"</span>
                                <span class="unit-cm fw-semibold d-none">71 – 75 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">71–75 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">28–29 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">34" – 35"</span>
                                <span class="unit-cm fw-semibold d-none">86 – 89 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">86–89 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">34–35 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Fuller Cup</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">34C</td>
                            <td>
                                <span class="unit-in fw-semibold">30" – 31"</span>
                                <span class="unit-cm fw-semibold d-none">76 – 80 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">76–80 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">30–31 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">36" – 37"</span>
                                <span class="unit-cm fw-semibold d-none">91 – 94 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">91–94 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">36–37 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Fuller Cup</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">36C</td>
                            <td>
                                <span class="unit-in fw-semibold">32" – 33"</span>
                                <span class="unit-cm fw-semibold d-none">81 – 85 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">81–85 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">32–33 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">38" – 39"</span>
                                <span class="unit-cm fw-semibold d-none">96 – 99 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">96–99 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">38–39 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Fuller Cup</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">38C</td>
                            <td>
                                <span class="unit-in fw-semibold">34" – 35"</span>
                                <span class="unit-cm fw-semibold d-none">86 – 90 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">86–90 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">34–35 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">40" – 41"</span>
                                <span class="unit-cm fw-semibold d-none">101 – 104 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">101–104 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">40–41 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Fuller Cup</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bra Fit Tip -->
            <div class="alert alert-light border small text-muted d-flex align-items-start gap-2 mb-0">
                <i class="bi bi-lightbulb-fill text-warning fs-5 mt-n1"></i>
                <div>
                    <strong class="text-dark">Pookky Comfort Fit Tip:</strong> All our bras are tailored from 100% breathable combed cotton with zero underwire. If your measurement falls between two sizes (e.g. 31.5" underbust), we recommend selecting the larger band size (34B) for effortless all-day ease.
                </div>
            </div>
        </div>

        <!-- ==========================================
             TAB 2: PANTIES SIZING CHART
             ========================================== -->
        <div class="tab-pane fade" id="tab-pane-panties" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-dark text-uppercase">Ladies' Panties Sizing Chart</span>
                <span class="badge bg-success-subtle text-success border small" style="font-size: 10px;">Pure Cotton Breathability</span>
            </div>

            <div class="table-responsive rounded-3 border mb-3">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-2 text-dark">Size Code</th>
                            <th class="py-2 text-dark">Waist</th>
                            <th class="py-2 text-dark">Hips</th>
                            <th class="pe-3 py-2 text-dark">Coverage & Rise</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                S <span class="text-muted fw-normal">(Small)</span>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">26" – 27"</span>
                                <span class="unit-cm fw-semibold d-none">66 – 70 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">66–70 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">26–27 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">35" – 37"</span>
                                <span class="unit-cm fw-semibold d-none">89 – 94 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">89–94 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">35–37 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Mid-Rise / Regular</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                M <span class="text-muted fw-normal">(Medium)</span>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">28" – 29"</span>
                                <span class="unit-cm fw-semibold d-none">71 – 75 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">71–75 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">28–29 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">38" – 40"</span>
                                <span class="unit-cm fw-semibold d-none">96 – 101 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">96–101 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">38–40 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Mid-Rise / Regular</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                L <span class="text-muted fw-normal">(Large)</span>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">30" – 32"</span>
                                <span class="unit-cm fw-semibold d-none">76 – 82 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">76–82 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">30–32 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">41" – 43"</span>
                                <span class="unit-cm fw-semibold d-none">104 – 109 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">104–109 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">41–43 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Full Back Coverage</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                XL <span class="text-muted fw-normal">(X-Large)</span>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">33" – 35"</span>
                                <span class="unit-cm fw-semibold d-none">84 – 89 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">84–89 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">33–35 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">44" – 46"</span>
                                <span class="unit-cm fw-semibold d-none">111 – 117 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">111–117 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">44–46 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Full Back Coverage</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">
                                XXL <span class="text-muted fw-normal">(2X-Large)</span>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">36" – 38"</span>
                                <span class="unit-cm fw-semibold d-none">91 – 97 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">91–97 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">36–38 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">47" – 49"</span>
                                <span class="unit-cm fw-semibold d-none">119 – 124 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">119–124 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">47–49 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Comfort Stretch</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Panties Tip -->
            <div class="alert alert-light border small text-muted d-flex align-items-start gap-2 mb-0">
                <i class="bi bi-lightbulb-fill text-warning fs-5 mt-n1"></i>
                <div>
                    <strong class="text-dark">Waistband Comfort:</strong> Pookky panties are crafted with ultra-soft elastane waistbands that conform gently to your hips without leaving pinch marks. If you prefer a loose night-time fit, consider sizing up one step.
                </div>
            </div>
        </div>

        <!-- ==========================================
             TAB 3: NIGHTWEAR SIZING CHART
             ========================================== -->
        <div class="tab-pane fade" id="tab-pane-nightwear" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-dark text-uppercase">Nighties & Loungewear Sizing Chart</span>
                <span class="badge bg-primary-subtle text-primary border small" style="font-size: 10px;">Relaxed Fit</span>
            </div>

            <div class="table-responsive rounded-3 border mb-3">
                <table class="table table-hover align-middle small mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-2 text-dark">Size Code</th>
                            <th class="py-2 text-dark">Bust Measurement</th>
                            <th class="py-2 text-dark">Full Garment Length</th>
                            <th class="pe-3 py-2 text-dark">Comfort Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">S</td>
                            <td>
                                <span class="unit-in fw-semibold">32" – 34"</span>
                                <span class="unit-cm fw-semibold d-none">81 – 86 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">81–86 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">32–34 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">52"</span>
                                <span class="unit-cm fw-semibold d-none">132 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">132 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">52 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Petite</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">M</td>
                            <td>
                                <span class="unit-in fw-semibold">34" – 36"</span>
                                <span class="unit-cm fw-semibold d-none">86 – 91 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">86–91 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">34–36 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">53"</span>
                                <span class="unit-cm fw-semibold d-none">135 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">135 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">53 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Standard</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">L</td>
                            <td>
                                <span class="unit-in fw-semibold">36" – 38"</span>
                                <span class="unit-cm fw-semibold d-none">91 – 97 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">91–97 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">36–38 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">54"</span>
                                <span class="unit-cm fw-semibold d-none">137 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">137 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">54 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Standard</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">XL</td>
                            <td>
                                <span class="unit-in fw-semibold">38" – 40"</span>
                                <span class="unit-cm fw-semibold d-none">97 – 102 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">97–102 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">38–40 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">55"</span>
                                <span class="unit-cm fw-semibold d-none">140 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">140 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">55 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Comfort Relaxed</span></td>
                        </tr>
                        <tr>
                            <td class="ps-3 fw-bold text-dark">XXL</td>
                            <td>
                                <span class="unit-in fw-semibold">40" – 44"</span>
                                <span class="unit-cm fw-semibold d-none">102 – 112 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">102–112 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">40–44 in</small>
                            </td>
                            <td>
                                <span class="unit-in fw-semibold">55"</span>
                                <span class="unit-cm fw-semibold d-none">140 cm</span>
                                <small class="text-muted d-block unit-sub-in" style="font-size: 10px;">140 cm</small>
                                <small class="text-muted d-block unit-sub-cm d-none" style="font-size: 10px;">55 in</small>
                            </td>
                            <td class="pe-3"><span class="badge bg-light text-secondary border">Comfort Relaxed</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ==========================================
             TAB 4: HOW TO MEASURE GUIDE
             ========================================== -->
        <div class="tab-pane fade" id="tab-pane-howto" role="tabpanel">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-dark rounded-circle px-2 py-1">1</span>
                            <h6 class="fw-bold text-dark mb-0">Underbust (Band)</h6>
                        </div>
                        <p class="small text-muted mb-0">
                            Wrap a soft measuring tape snugly around your ribcage directly beneath your breasts, right where your bra band rests. Keep the tape parallel to the floor.
                        </p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-dark rounded-circle px-2 py-1">2</span>
                            <h6 class="fw-bold text-dark mb-0">Overbust (Cup)</h6>
                        </div>
                        <p class="small text-muted mb-0">
                            Wrap the measuring tape around the fullest part of your bust while breathing normally. The tape should be level across your shoulder blades without pulling too tight.
                        </p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-3 border rounded-3 bg-light h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-rose rounded-circle px-2 py-1 text-white">3</span>
                            <h6 class="fw-bold text-dark mb-0">Calculate Cup Size</h6>
                        </div>
                        <p class="small text-muted mb-0">
                            Subtract your Underbust measurement from your Overbust measurement:
                            <br>&bull; 1" diff = <strong>A Cup</strong>
                            <br>&bull; 2" diff = <strong>B Cup (Standard)</strong>
                            <br>&bull; 3" diff = <strong>C Cup</strong>
                            <br>&bull; 4" diff = <strong>D Cup</strong>
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-3 border rounded-3 bg-white">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-question-circle me-1 text-rose"></i> Need Help with Sizing?</h6>
                <p class="small text-muted mb-0">
                    If you are still unsure of your size, our dedicated customer care team is available on WhatsApp or email to help guide your fitting. <a href="contact.php" class="text-rose fw-semibold text-decoration-none">Contact Support</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function switchPookkySizeUnit(unit) {
    document.querySelectorAll('.size-unit-btn').forEach(btn => {
        if (btn.getAttribute('data-unit') === unit) {
            btn.classList.add('active', 'btn-dark', 'text-white');
            btn.classList.remove('text-muted');
        } else {
            btn.classList.remove('active', 'btn-dark', 'text-white');
            btn.classList.add('text-muted');
        }
    });

    if (unit === 'cm') {
        document.querySelectorAll('.unit-in').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.unit-cm').forEach(el => el.classList.remove('d-none'));
        document.querySelectorAll('.unit-sub-in').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.unit-sub-cm').forEach(el => el.classList.remove('d-none'));
    } else {
        document.querySelectorAll('.unit-cm').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.unit-in').forEach(el => el.classList.remove('d-none'));
        document.querySelectorAll('.unit-sub-cm').forEach(el => el.classList.add('d-none'));
        document.querySelectorAll('.unit-sub-in').forEach(el => el.classList.remove('d-none'));
    }
}
</script>
