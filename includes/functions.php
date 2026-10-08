<?php
// includes/functions.php
// Common utility functions

// XSS mitigation: Safe output escaping
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Set a redirect flash message
function set_flash_message($type, $message) {
    $_SESSION['flash'][$type][] = $message;
}

// Render flash alerts
function display_flash_messages() {
    if (isset($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $type => $messages) {
            $alert_class = ($type === 'success') ? 'alert-success' : 'alert-danger';
            foreach ($messages as $msg) {
                echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
                echo e($msg);
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
        }
        unset($_SESSION['flash']);
    }
}

// Format prices with currency symbol
function format_price($amount) {
    $sym = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹';
    if ($sym === '262145' || is_numeric($sym) || empty(trim((string)$sym)) || strpos((string)$sym, '262145') !== false) {
        $sym = '₹';
    }
    return $sym . ' ' . number_format((float)$amount, 2);
}

// Generate URL slug from text
function slugify($text) {
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // trim
    $text = trim($text, '-');
    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

// Generate unique order number (e.g. PKY-YYYYMMDD-XXXX)
function generate_order_number() {
    $prefix = "PKY-";
    $date = date("Ymd");
    $rand = strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
    return $prefix . $date . "-" . $rand;
}

// Format API json responses
function json_response($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Convert color name or custom short code to a standardized compact SKU color code (e.g. "Chocolate Brown" -> "CHBR")
function get_color_short_code($color_name, $custom_short_code = null) {
    if (!empty($custom_short_code)) {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $custom_short_code));
    }
    
    $name = strtoupper(trim($color_name ?? ''));
    if (empty($name)) {
        return 'CLR';
    }

    // Standardized apparel retail color code dictionary
    $dictionary = [
        'CHOCOLATE BROWN' => 'CHBR',
        'CHOCOLATE'       => 'CHOC',
        'MIDNIGHT BLACK'  => 'MBLK',
        'BLACK'           => 'BLK',
        'BLUSH PINK'      => 'BLPK',
        'BABY PINK'       => 'BPK',
        'HOT PINK'        => 'HPK',
        'PINK'            => 'PNK',
        'RUBY RED'        => 'RRED',
        'RED'             => 'RED',
        'IVORY WHITE'     => 'IVWT',
        'OFF WHITE'       => 'OFWT',
        'WHITE'           => 'WHT',
        'WARM NUDE'       => 'WNUD',
        'NUDE'            => 'NUDE',
        'SKIN'            => 'SKIN',
        'BEIGE'           => 'BEIG',
        'MAUVE MIST'      => 'MMST',
        'MAUVE'           => 'MAUV',
        'NAVY BLUE'       => 'NVBL',
        'ROYAL BLUE'      => 'RYBL',
        'SKY BLUE'        => 'SKBL',
        'NAVY'            => 'NAVY',
        'BLUE'            => 'BLU',
        'OLIVE GREEN'     => 'OLGR',
        'MINT GREEN'      => 'MNGR',
        'OLIVE'           => 'OLV',
        'GREEN'           => 'GRN',
        'DUSTY ROSE'      => 'DROSE',
        'ROSE GOLD'       => 'RGOLD',
        'ROSE'            => 'ROSE',
        'BURGUNDY'        => 'BURG',
        'WINE'            => 'WINE',
        'MAROON'          => 'MRN',
        'LAVENDER'        => 'LAV',
        'PURPLE'          => 'PURP',
        'CHARCOAL GREY'   => 'CHGR',
        'CHARCOAL'        => 'CHAR',
        'GREY'            => 'GRY',
        'GRAY'            => 'GRY',
        'CHAMPAGNE'       => 'CHMP',
        'GOLD'            => 'GOLD',
        'SILVER'          => 'SLVR',
        'COPPER'          => 'COPR',
        'BRONZE'          => 'BRNZ',
        'CORAL'           => 'CRL',
        'PEACH'           => 'PCH',
        'TEAL'            => 'TEAL',
        'TURQUOISE'       => 'TRQ',
        'YELLOW'          => 'YLW',
        'MUSTARD'         => 'MSTD',
        'ORANGE'          => 'ORG',
        'RUST'            => 'RUST',
        'BROWN'           => 'BRN',
        'TAN'             => 'TAN',
        'CAMEL'           => 'CML',
        'CREAM'           => 'CRM',
        'VIOLET'          => 'VLT',
        'LILAC'           => 'LLC',
        'AQUA'            => 'AQUA',
        'MAGENTA'         => 'MAG',
        'MULTI'           => 'MLT',
        'PRINT'           => 'PRNT',
        'ASSORTED'        => 'ASST'
    ];

    if (isset($dictionary[$name])) {
        return $dictionary[$name];
    }

    // Clean special characters
    $clean_name = preg_replace('/[^A-Za-z0-9\s]/', '', $name);
    $words = preg_split('/\s+/', trim($clean_name));

    if (count($words) >= 2) {
        // Take first 2 chars of first word + first 2 chars of second word (e.g. "Chocolate Brown" -> "CHBR")
        $w1 = substr($words[0], 0, 2);
        $w2 = substr($words[1], 0, 2);
        return $w1 . $w2;
    }

    // Single word
    $single = $words[0];
    if (strlen($single) <= 4) {
        return $single;
    }

    // Abbreviate by removing internal vowels if longer than 4 characters
    $first_char = $single[0];
    $rest = substr($single, 1);
    $consonants = preg_replace('/[AEIOU]/', '', $rest);
    $abbr = $first_char . $consonants;

    if (strlen($abbr) >= 3) {
        return substr($abbr, 0, 4);
    }

    return substr($single, 0, 4);
}

// Log admin activities
function log_admin_action($admin_id, $action) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$admin_id, $action, $ip]);
    } catch (PDOException $e) {
        // Fail silently to keep application running
    }
}

/**
 * Compare two sizes in natural apparel order (e.g. 28A, 30B, 32B, 34B, 36B, 38B, 40B, XS, S, M, L, XL, XXL)
 */
function compare_sizes($a, $b) {
    $codeA = strtoupper(trim(is_array($a) ? ($a['size_code'] ?? $a['code'] ?? $a['size_name'] ?? $a['name'] ?? '') : (string)$a));
    $codeB = strtoupper(trim(is_array($b) ? ($b['size_code'] ?? $b['code'] ?? $b['size_name'] ?? $b['name'] ?? '') : (string)$b));
    $nameA = strtoupper(trim(is_array($a) ? ($a['size_name'] ?? $a['name'] ?? '') : ''));
    $nameB = strtoupper(trim(is_array($b) ? ($b['size_name'] ?? $b['name'] ?? '') : ''));

    if ($codeA === $codeB) {
        return 0;
    }

    // 1. Standard Alpha Sizes (XS, S, M, L, XL, XXL, etc.)
    $alpha_ranks = [
        '4XS' => 5, 'XXXXS' => 5,
        '3XS' => 10, 'XXXS' => 10,
        '2XS' => 20, 'XXS' => 20,
        'XS' => 30, 'X-SMALL' => 30, 'EXTRA SMALL' => 30,
        'S' => 40, 'SMALL' => 40,
        'S/M' => 45, 'S-M' => 45,
        'M' => 50, 'MEDIUM' => 50,
        'M/L' => 55, 'M-L' => 55,
        'L' => 60, 'LARGE' => 60,
        'L/XL' => 65, 'L-XL' => 65,
        'XL' => 70, 'X-LARGE' => 70, 'EXTRA LARGE' => 70,
        '2XL' => 80, 'XXL' => 80, 'XX-LARGE' => 80, '2X-LARGE' => 80,
        '3XL' => 90, 'XXXL' => 90, 'XXX-LARGE' => 90, '3X-LARGE' => 90,
        '4XL' => 100, 'XXXXL' => 100, 'XXXX-LARGE' => 100, '4X-LARGE' => 100,
        '5XL' => 110, 'XXXXXL' => 110, 'XXXXX-LARGE' => 110, '5X-LARGE' => 110,
        '6XL' => 120,
        'FREE' => 900, 'FREE SIZE' => 900, 'FS' => 900, 'ONE SIZE' => 900, 'OS' => 900
    ];

    $rankA = $alpha_ranks[$codeA] ?? ($alpha_ranks[$nameA] ?? null);
    $rankB = $alpha_ranks[$codeB] ?? ($alpha_ranks[$nameB] ?? null);

    if ($rankA !== null && $rankB !== null) {
        return $rankA <=> $rankB;
    }

    // 2. Bra Sizes / Numeric Band + Letter Cup (e.g., 30B, 32B, 34C, 36D, 38DD, 40B, 28A)
    $has_braA = preg_match('/^(\d+)\s*([A-Za-z]+)$/', $codeA, $mA);
    $has_braB = preg_match('/^(\d+)\s*([A-Za-z]+)$/', $codeB, $mB);

    if ($has_braA && $has_braB) {
        $bandA = (int)$mA[1];
        $bandB = (int)$mB[1];
        if ($bandA !== $bandB) {
            return $bandA <=> $bandB;
        }

        // Same band, compare cup
        $cup_ranks = [
            'AAA' => 1, 'AA' => 2, 'A' => 3, 'B' => 4, 'C' => 5, 
            'D' => 6, 'DD' => 7, 'E' => 8, 'DDD' => 8, 'F' => 9, 
            'FF' => 10, 'G' => 11, 'GG' => 12, 'H' => 13
        ];
        $cupA = strtoupper($mA[2]);
        $cupB = strtoupper($mB[2]);
        $cRankA = $cup_ranks[$cupA] ?? 50;
        $cRankB = $cup_ranks[$cupB] ?? 50;

        if ($cRankA !== $cRankB) {
            return $cRankA <=> $cRankB;
        }
        return strcmp($cupA, $cupB);
    }

    // 3. Pure Numeric (e.g., 28, 30, 32, 34, 36, 38, 40)
    $is_numA = is_numeric($codeA);
    $is_numB = is_numeric($codeB);

    if ($is_numA && $is_numB) {
        return (float)$codeA <=> (float)$codeB;
    }

    // Numeric vs Bra size (e.g. 30 vs 32B)
    if ($is_numA && $has_braB) {
        $numA = (int)$codeA;
        $bandB = (int)$mB[1];
        if ($numA !== $bandB) {
            return $numA <=> $bandB;
        }
        return -1; // 32 before 32B
    }
    if ($has_braA && $is_numB) {
        $bandA = (int)$mA[1];
        $numB = (int)$codeB;
        if ($bandA !== $numB) {
            return $bandA <=> $numB;
        }
        return 1; // 32B after 32
    }

    // 4. Size ranges (e.g., 28-30, 30-32, 32-34)
    $has_rangeA = preg_match('/^(\d+)\s*[-/]\s*(\d+)$/', $codeA, $rA);
    $has_rangeB = preg_match('/^(\d+)\s*[-/]\s*(\d+)$/', $codeB, $rB);
    if ($has_rangeA && $has_rangeB) {
        $r1A = (int)$rA[1]; $r1B = (int)$rB[1];
        if ($r1A !== $r1B) return $r1A <=> $r1B;
        return (int)$rA[2] <=> (int)$rB[2];
    }

    // 5. Fallback: Natural case-insensitive comparison
    return strnatcasecmp($codeA, $codeB);
}

/**
 * Sort size list or array of size rows in natural apparel order
 */
function sort_sizes(&$sizes, $group_by_category = false) {
    if (!is_array($sizes) || count($sizes) <= 1) {
        return;
    }

    usort($sizes, function($a, $b) use ($group_by_category) {
        if ($group_by_category) {
            $catA = is_array($a) ? ($a['category_type'] ?? '') : '';
            $catB = is_array($b) ? ($b['category_type'] ?? '') : '';
            if ($catA !== $catB) {
                return strcmp($catA, $catB);
            }
        }
        return compare_sizes($a, $b);
    });
}

/**
 * Check if a customer has purchased a specific product (and order is not cancelled)
 * Checks standard products, variant purchases, and combo product components
 */
function has_customer_purchased_product($customer_id, $product_id) {
    global $pdo;
    if (empty($customer_id) || empty($product_id)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.customer_id = ?
              AND (oi.product_id = ? OR oi.combo_product_id = ?)
              AND o.order_status != 'cancelled'
            LIMIT 1
        ");
        $stmt->execute([(int)$customer_id, (int)$product_id, (int)$product_id]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Fetch a customer's review for a specific product
 */
function get_customer_product_review($customer_id, $product_id) {
    global $pdo;
    if (empty($customer_id) || empty($product_id)) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE customer_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([(int)$customer_id, (int)$product_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Merges guest session cart items into customer account upon login/registration.
 * Seamlessly handles single variants and complex combo product configurations.
 *
 * @param int $customer_id
 * @param string $guest_session_id
 * @return int Total items in customer cart after merge
 */
function merge_guest_cart_to_customer($customer_id, $guest_session_id) {
    global $pdo;
    $customer_id = (int)$customer_id;
    if (!$customer_id || empty($guest_session_id)) {
        return 0;
    }

    try {
        $guest_stmt = $pdo->prepare("SELECT * FROM cart WHERE session_id = ? AND customer_id IS NULL");
        $guest_stmt->execute([$guest_session_id]);
        $guest_items = $guest_stmt->fetchAll();

        foreach ($guest_items as $item) {
            if (!empty($item['combo_product_id'])) {
                // Combo product merge
                $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND combo_product_id = ? AND combo_config = ? LIMIT 1");
                $check->execute([$customer_id, $item['combo_product_id'], $item['combo_config']]);
                $existing = $check->fetch();

                if ($existing) {
                    $new_qty = (int)$existing['quantity'] + (int)$item['quantity'];
                    $up = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $up->execute([$new_qty, $existing['id']]);

                    $del = $pdo->prepare("DELETE FROM cart WHERE id = ?");
                    $del->execute([$item['id']]);
                } else {
                    $up = $pdo->prepare("UPDATE cart SET customer_id = ?, session_id = NULL WHERE id = ?");
                    $up->execute([$customer_id, $item['id']]);
                }
            } else if (!empty($item['variant_id'])) {
                // Regular variant merge
                $check = $pdo->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND variant_id = ? LIMIT 1");
                $check->execute([$customer_id, $item['variant_id']]);
                $existing = $check->fetch();

                if ($existing) {
                    $new_qty = (int)$existing['quantity'] + (int)$item['quantity'];
                    $up = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $up->execute([$new_qty, $existing['id']]);

                    $del = $pdo->prepare("DELETE FROM cart WHERE id = ?");
                    $del->execute([$item['id']]);
                } else {
                    $up = $pdo->prepare("UPDATE cart SET customer_id = ?, session_id = NULL WHERE id = ?");
                    $up->execute([$customer_id, $item['id']]);
                }
            }
        }

        // Return total distinct lines in customer's cart
        $cnt_stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE customer_id = ?");
        $cnt_stmt->execute([$customer_id]);
        $cnt = $cnt_stmt->fetch();
        return (int)($cnt['count'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Returns total items/lines in cart for either customer or guest session
 */
function get_cart_item_count($customer_id = null, $session_id = null) {
    global $pdo;
    try {
        if ($customer_id) {
            $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE customer_id = ?");
            $stmt->execute([(int)$customer_id]);
        } else if ($session_id) {
            $stmt = $pdo->prepare("SELECT COUNT(id) as count FROM cart WHERE session_id = ? AND customer_id IS NULL");
            $stmt->execute([$session_id]);
        } else {
            return 0;
        }
        $row = $stmt->fetch();
        return (int)($row['count'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}
?>
