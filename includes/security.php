<?php
// includes/security.php
// CSRF Protection and Upload Validation helper functions

// Generate a secure CSRF token if one doesn't exist
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify a CSRF token
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Generate the CSRF hidden input field for forms
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// Image upload validator and processor
// Returns array [ 'success' => bool, 'filename' => string|null, 'error' => string|null ]
function validate_image_upload($file, $target_dir = '../uploads/') {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 50 * 1024 * 1024; // 50MB
 
     if ($file['error'] !== UPLOAD_ERR_OK) {
         return ['success' => false, 'filename' => null, 'error' => 'File upload error occurred.'];
     }
 
     if ($file['size'] > $max_size) {
         return ['success' => false, 'filename' => null, 'error' => 'File size exceeds maximum limit of 50MB.'];
     }

    // Double check extensions and mime-types
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_extensions)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid file extension. Only JPG, JPEG, PNG, and WEBP allowed.'];
    }

    if (!function_exists('mime_content_type')) {
        // Fallback if mime_content_type doesn't exist (rare on standard PHP hostings)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime_type = mime_content_type($file['tmp_name']);
    }

    if (!in_array($mime_type, $allowed_mime_types)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid file mime type. The file must be a real image.'];
    }

    // Auto create upload directory if missing
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // Generate unique name to prevent collisions and execution
    $new_filename = uniqid('img_', true) . '.' . $file_ext;
    $dest_path = rtrim($target_dir, '/') . '/' . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return ['success' => true, 'filename' => $new_filename, 'error' => null];
    } else {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to move uploaded file. Check directory write permissions.'];
    }
}
?>
