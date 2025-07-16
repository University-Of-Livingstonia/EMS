<?php

/**
 * 🛠️ Essential Helper Functions - EMS
 * Ekwendeni Mighty Campus Event Management System
 */

/**
 * 🧹 Sanitize input data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * 📧 Validate email
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 📱 Validate phone number
 */
function isValidPhone($phone)
{
    return preg_match('/^[+]?[0-9\s\-\(\)]{10,}$/', $phone);
}

/**
 * 🔐 Generate secure token
 */
function generateToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * 📅 Format date for display
 */
function formatDate($date, $format = 'M j, Y')
{
    return date($format, strtotime($date));
}

/**
 * 📅 Format datetime for display
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A')
{
    return date($format, strtotime($datetime));
}

/**
 * ⏰ Time ago function
 */
function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . ' minutes ago';
    if ($time < 86400) return floor($time / 3600) . ' hours ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';
    if ($time < 31536000) return floor($time / 2592000) . ' months ago';
    return floor($time / 31536000) . ' years ago';
}

/**
 * 💰 Format currency
 */
function formatCurrency($amount, $currency = 'MWK')
{
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * 📊 Generate random color
 */
function generateRandomColor()
{
    $colors = [
        '#FF6B6B',
        '#4ECDC4',
        '#45B7D1',
        '#96CEB4',
        '#FFEAA7',
        '#DDA0DD',
        '#98D8C8',
        '#F7DC6F',
        '#BB8FCE',
        '#85C1E9'
    ];
    return $colors[array_rand($colors)];
}

/**
 * 🖼️ Handle file upload
 */
function handleFileUpload($file, $uploadDir = 'uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'])
{
    try {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No file uploaded'];
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
        }

        // Get file info
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmp = $file['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($fileExt, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
        }

        // Validate file size (5MB max)
        if ($fileSize > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File too large. Maximum size: 5MB'];
        }

        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
        $uploadPath = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmp, $uploadPath)) {
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => $newFileName,
                'path' => $uploadPath
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during file upload'];
    }
}

/**
 * 📧 Send email notification
 */
function sendEmail($to, $subject, $message, $isHTML = false)
{
    try {
        // For now, we'll just log the email
        error_log("EMAIL TO: $to, SUBJECT: $subject, MESSAGE: $message");

        // TODO: Implement actual email sending
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}

/**
 * 🔔 Create notification
 */
function createNotification($conn, $userId, $title, $message, $type = 'system', $relatedId = null)
{
    try {
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssi", $userId, $title, $message, $type, $relatedId);

        if ($stmt->execute()) {
            return ['success' => true, 'notification_id' => $conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Failed to create notification'];
        }
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while creating notification'];
    }
}

/**
 * 📱 Generate QR Code (placeholder)
 */
function generateQRCode($data, $size = 200)
{
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
    return $qrUrl;
}

/**
 * 🎫 Generate ticket number
 */
function generateTicketNumber($eventId, $userId)
{
    return 'EMT-' . str_pad($eventId, 4, '0', STR_PAD_LEFT) . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -4));
}

/**
 * 🔍 Search and highlight text
 */
function highlightSearchTerm($text, $searchTerm)
{
    if (empty($searchTerm)) return $text;

    return preg_replace('/(' . preg_quote($searchTerm, '/') . ')/i', '<mark>$1</mark>', $text);
}

/**
 * 📊 Calculate event statistics
 */
function getEventStats($conn, $eventId)
{
    try {
        $stats = [];

        // Total registrations
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tickets WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_registrations'] = $result->fetch_assoc()['total'];

        // Confirmed payments
        $stmt = $conn->prepare("SELECT COUNT(*) as confirmed FROM tickets WHERE event_id = ? AND payment_status = 'completed'");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['confirmed_payments'] = $result->fetch_assoc()['confirmed'];

        // Total revenue
        $stmt = $conn->prepare("SELECT SUM(price) as revenue FROM tickets WHERE event_id = ? AND payment_status = 'completed'");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_revenue'] = $result->fetch_assoc()['revenue'] ?? 0;

        // Attendance rate (if event is completed)
        $stmt = $conn->prepare("SELECT COUNT(*) as attended FROM tickets WHERE event_id = ? AND is_used = 1");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['attended'] = $result->fetch_assoc()['attended'];

        $stats['attendance_rate'] = $stats['total_registrations'] > 0 ?
            round(($stats['attended'] / $stats['total_registrations']) * 100, 2) : 0;

        return $stats;
    } catch (Exception $e) {
        error_log("Event stats error: " . $e->getMessage());
        return [
            'total_registrations' => 0,
            'confirmed_payments' => 0,
            'total_revenue' => 0,
            'attended' => 0,
            'attendance_rate' => 0
        ];
    }
}

/**
 * 🎨 Get status badge HTML
 */
function getStatusBadge($status, $type = 'event')
{
    $badges = [
        'event' => [
            'draft' => '<span class="badge badge-secondary"><i class="fas fa-edit"></i> Draft</span>',
            'pending' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>',
            'approved' => '<span class="badge badge-success"><i class="fas fa-check"></i> Approved</span>',
            'rejected' => '<span class="badge badge-danger"><i class="fas fa-times"></i> Rejected</span>',
            'cancelled' => '<span class="badge badge-dark"><i class="fas fa-ban"></i> Cancelled</span>',
            'completed' => '<span class="badge badge-info"><i class="fas fa-flag-checkered"></i> Completed</span>'
        ],
        'payment' => [
            'pending' => '<span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>',
            'completed' => '<span class="badge badge-success"><i class="fas fa-check"></i> Paid</span>',
            'failed' => '<span class="badge badge-danger"><i class="fas fa-times"></i> Failed</span>',
            'refunded' => '<span class="badge badge-info"><i class="fas fa-undo"></i> Refunded</span>'
        ],
        'ticket' => [
            'active' => '<span class="badge badge-success"><i class="fas fa-ticket-alt"></i> Active</span>',
            'used' => '<span class="badge badge-info"><i class="fas fa-check-circle"></i> Used</span>',
            'cancelled' => '<span class="badge badge-danger"><i class="fas fa-ban"></i> Cancelled</span>'
        ]
    ];

    return $badges[$type][$status] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

/**
 * 🔐 Generate CSRF token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 🛡️ Verify CSRF token
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 📝 Log activity
 */
function logActivity($conn, $userId, $action, $details = null, $ipAddress = null)
{
    try {
        if (!$ipAddress) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        // You might want to create an activity_logs table for this
        error_log("ACTIVITY LOG - User: $userId, Action: $action, Details: $details, IP: $ipAddress");

        return true;
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

/**
 * 🌟 Get user avatar
 */
function getUserAvatar($user, $size = 50)
{
    if (!empty($user['profile_image']) && file_exists('uploads/' . $user['profile_image'])) {
        return '<img src="uploads/' . htmlspecialchars($user['profile_image']) . '" alt="Avatar" class="avatar" style="width: ' . $size . 'px; height: ' . $size . 'px; border-radius: 50%; object-fit: cover;">';
    } else {
        // Generate initials avatar
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
        $bgColor = generateRandomColor();

        return '<div class="avatar-initials" style="width: ' . $size . 'px; height: ' . $size . 'px; background: ' . $bgColor . '; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: ' . ($size * 0.4) . 'px;">' . $initials . '</div>';
    }
}

/**
 * 📱 Check if mobile device
 */
function isMobile()
{
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

/**
 * 🎯 Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length) . $suffix;
}

/**
 * 🔄 Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * 💬 Get flash message
 */
function getFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';

        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);

        return ['message' => $message, 'type' => $type];
    }

    return null;
}

/**
 * 🎨 Display flash message HTML
 */
function displayFlashMessage()
{
    $flash = getFlashMessage();
    if ($flash) {
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ][$flash['type']] ?? 'alert-info';

        $icon = [
            'success' => 'fas fa-check-circle',
            'error' => 'fas fa-exclamation-triangle',
            'warning' => 'fas fa-exclamation-circle',
            'info' => 'fas fa-info-circle'
        ][$flash['type']] ?? 'fas fa-info-circle';

        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo '<i class="' . $icon . ' me-2"></i>';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * 🔍 Paginate results
 */
function paginate($conn, $sql, $params = [], $types = '', $page = 1, $perPage = 10)
{
    try {
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_table";
        $stmt = $conn->prepare($countSql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $totalResult = $stmt->get_result();
        $total = $totalResult->fetch_assoc()['total'];

        // Calculate pagination
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Get paginated results
        $paginatedSql = $sql . " LIMIT ? OFFSET ?";
        $paginatedParams = array_merge($params, [$perPage, $offset]);
        $paginatedTypes = $types . 'ii';

        $stmt = $conn->prepare($paginatedSql);
        $stmt->bind_param($paginatedTypes, ...$paginatedParams);
        $stmt->execute();
        $result = $stmt->get_result();

        return [
            'data' => $result->fetch_all(MYSQLI_ASSOC),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'total_records' => $total,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null
            ]
        ];
    } catch (Exception $e) {
        error_log("Pagination error: " . $e->getMessage());
        return [
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total_pages' => 0,
                'total_records' => 0,
                'has_prev' => false,
                'has_next' => false,
                'prev_page' => null,
                'next_page' => null
            ]
        ];
    }
}

/**
 * 🎨 Generate pagination HTML
 */
function generatePaginationHTML($pagination, $baseUrl = '')
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-center">';

    // Previous button
    if ($pagination['has_prev']) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $pagination['prev_page'] . '">';
        $html .= '<i class="fas fa-chevron-left"></i> Previous';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span>';
        $html .= '</li>';
    }

    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $pagination['total_pages'] . '">' . $pagination['total_pages'] . '</a></li>';
    }

    // Next button
    if ($pagination['has_next']) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $pagination['next_page'] . '">';
        $html .= 'Next <i class="fas fa-chevron-right"></i>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link">Next <i class="fas fa-chevron-right"></i></span>';
        $html .= '</li>';
    }

    $html .= '</ul>';
    $html .= '</nav>';

    return $html;
}

/**
 * 🔒 Encrypt sensitive data
 */
function encryptData($data, $key = null)
{
    if (!$key) {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default_key_change_this';
    }

    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);

    return base64_encode($encrypted . '::' . $iv);
}

/**
 * 🔓 Decrypt sensitive data
 */
function decryptData($data, $key = null)
{
    if (!$key) {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default_key_change_this';
    }

    $cipher = "AES-256-CBC";
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);

    return openssl_decrypt($encrypted_data, $cipher, $key, 0, $iv);
}

/**
 * 🎯 Get client IP address
 */
function getClientIP()
{
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * 📊 Generate chart data for events
 */
function getEventChartData($conn, $userId = null, $days = 30)
{
    try {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";

        $params = [$days];
        $types = 'i';

        if ($userId) {
            $sql .= " AND organizer_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }

        $sql .= " GROUP BY DATE(created_at) ORDER BY date";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'date' => $row['date'],
                'count' => (int)$row['count']
            ];
        }

        return $data;
    } catch (Exception $e) {
        error_log("Chart data error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event category colors
 */
function getCategoryColor($category)
{
    $colors = [
        'academic' => '#4CAF50',
        'social' => '#FF9800',
        'sports' => '#2196F3',
        'cultural' => '#9C27B0',
        'other' => '#607D8B'
    ];

    return $colors[$category] ?? $colors['other'];
}

/**
 * 🎯 Get event type icon
 */
function getEventTypeIcon($type)
{
    $icons = [
        'workshop' => 'fas fa-tools',
        'seminar' => 'fas fa-chalkboard-teacher',
        'concert' => 'fas fa-music',
        'meeting' => 'fas fa-users',
        'conference' => 'fas fa-microphone',
        'other' => 'fas fa-calendar-alt'
    ];

    return $icons[$type] ?? $icons['other'];
}

/**
 * 📱 Send SMS notification (placeholder)
 */
function sendSMS($phone, $message)
{
    try {
        // This is a placeholder for SMS functionality
        // You can integrate with services like Twilio, Africa's Talking, etc.

        error_log("SMS TO: $phone, MESSAGE: $message");

        // TODO: Implement actual SMS sending
        return ['success' => true, 'message' => 'SMS sent successfully'];
    } catch (Exception $e) {
        error_log("SMS error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send SMS'];
    }
}

/**
 * 🔍 Advanced search function
 */
function searchEvents($conn, $query, $filters = [])
{
    try {
        $sql = "SELECT e.*, u.first_name, u.last_name 
                FROM events e 
                LEFT JOIN users u ON e.organizer_id = u.user_id 
                WHERE e.status = 'approved'";

        $params = [];
        $types = '';

        // Search in title and description
        if (!empty($query)) {
            $sql .= " AND (e.title LIKE ? OR e.description LIKE ?)";
            $searchTerm = '%' . $query . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $sql .= " AND e.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }

        // Filter by event type
        if (!empty($filters['event_type'])) {
            $sql .= " AND e.event_type = ?";
            $params[] = $filters['event_type'];
            $types .= 's';
        }

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(e.start_datetime) >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(e.start_datetime) <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }

        // Filter by venue
        if (!empty($filters['venue'])) {
            $sql .= " AND e.venue LIKE ?";
            $params[] = '%' . $filters['venue'] . '%';
            $types .= 's';
        }

        // Filter by paid/free
        if (isset($filters['is_paid'])) {
            $sql .= " AND e.is_paid = ?";
            $params[] = $filters['is_paid'] ? 1 : 0;
            $types .= 'i';
        }

        $sql .= " ORDER BY e.start_datetime ASC";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Advanced search error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎯 Get user's event history
 */
function getUserEventHistory($conn, $userId, $limit = 10)
{
    try {
        $sql = "SELECT e.*, t.created_at as registration_date, t.payment_status, t.is_used, t.price as paid_amount,
                       t.ticket_number, t.used_at, u.first_name as organizer_first_name, u.last_name as organizer_last_name
                FROM tickets t
                JOIN events e ON t.event_id = e.event_id
                LEFT JOIN users u ON e.organizer_id = u.user_id
                WHERE t.user_id = ?
                ORDER BY t.created_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Get user event history error: " . $e->getMessage());
        return [];
    }
}


/**
 * 🔍 Validate API token
 */
function validateAPIToken($conn, $token)
{
    try {
        $hashedToken = hash('sha256', $token);

        $stmt = $conn->prepare("SELECT at.*, u.user_id, u.role FROM api_tokens at JOIN users u ON at.user_id = u.user_id WHERE at.token_hash = ? AND at.expires_at > NOW() AND at.is_active = 1");
        $stmt->bind_param("s", $hashedToken);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($tokenData = $result->fetch_assoc()) {
            // Update last used timestamp
            $updateStmt = $conn->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token_id = ?");
            $updateStmt->bind_param("i", $tokenData['token_id']);
            $updateStmt->execute();

            return [
                'valid' => true,
                'user_id' => $tokenData['user_id'],
                'role' => $tokenData['role'],
                'token_name' => $tokenData['token_name']
            ];
        }

        return ['valid' => false];
    } catch (Exception $e) {
        error_log("Validate API token error: " . $e->getMessage());
        return ['valid' => false];
    }
}

/**
 * 📅 Format event duration
 */
function formatEventDuration($startDateTime, $endDateTime)
{
    $start = new DateTime($startDateTime);
    $end = new DateTime($endDateTime);
    $interval = $start->diff($end);

    $duration = '';

    if ($interval->d > 0) {
        $duration .= $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ';
    }

    if ($interval->h > 0) {
        $duration .= $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ';
    }

    if ($interval->i > 0) {
        $duration .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    }

    return trim($duration) ?: '0 minutes';
}

/**
 * 🎯 Get event waitlist
 */
function getEventWaitlist($conn, $eventId)
{
    try {
        // Note: This assumes you have a waitlist table or field
        $sql = "SELECT w.*, u.first_name, u.last_name, u.email 
                FROM event_waitlist w 
                JOIN users u ON w.user_id = u.user_id 
                WHERE w.event_id = ? 
                ORDER BY w.created_at ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Get event waitlist error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event social media content
 */
function generateSocialMediaContent($event)
{
    $content = [];

    // Twitter content
    $content['twitter'] = [
        'text' => "🎉 Join us for " . $event['title'] . "!\n" .
            "📅 " . formatDate($event['start_datetime']) . "\n" .
            "📍 " . $event['venue'] . "\n" .
            ($event['is_paid'] ? "💰 " . formatCurrency($event['price']) : "🆓 FREE") . "\n" .
            "#EMS #Event #" . ucfirst($event['category']),
        'hashtags' => ['EMS', 'Event', ucfirst($event['category']), 'University']
    ];

    // Facebook content
    $content['facebook'] = [
        'text' => "🎉 Exciting Event Alert! 🎉\n\n" .
            "We're thrilled to announce: " . $event['title'] . "\n\n" .
            "📅 Date: " . formatDateTime($event['start_datetime']) . "\n" .
            "📍 Venue: " . $event['venue'] . "\n" .
            "💰 Price: " . ($event['is_paid'] ? formatCurrency($event['price']) : "FREE") . "\n\n" .
            $event['description'] . "\n\n" .
            "Don't miss out! Register now! 🎫"
    ];

    // Instagram content
    $content['instagram'] = [
        'caption' => "✨ " . $event['title'] . " ✨\n\n" .
            "Mark your calendars! 📅\n" .
            formatDate($event['start_datetime']) . " at " . $event['venue'] . "\n\n" .
            ($event['is_paid'] ? "Tickets: " . formatCurrency($event['price']) : "FREE Entry! 🎉") . "\n\n" .
            "#EMS #Event #" . ucfirst($event['category']) . " #University #DontMiss",
        'hashtags' => ['EMS', 'Event', ucfirst($event['category']), 'University', 'DontMiss', 'RegisterNow']
    ];

    return $content;
}

/**
 * 🔔 Send automated reminders
 */
function sendAutomatedReminders($conn)
{
    try {
        $remindersSent = 0;

        // 24-hour reminders
        $stmt = $conn->prepare("SELECT DISTINCT e.*, t.user_id, u.email, u.first_name
                               FROM events e
                               JOIN tickets t ON e.event_id = t.event_id
                               JOIN users u ON t.user_id = u.user_id
                               WHERE e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                               AND t.payment_status = 'completed'
                               AND e.status = 'approved'
                               AND NOT EXISTS (
                                   SELECT 1 FROM notifications n 
                                   WHERE n.user_id = t.user_id 
                                   AND n.related_id = e.event_id 
                                   AND n.type = 'event_reminder_24h'
                                   AND DATE(n.created_at) = CURDATE()
                               )");

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $title = "Event Reminder: " . $row['title'];
            $message = "Your event '" . $row['title'] . "' starts tomorrow at " . formatDateTime($row['start_datetime']);

            createNotification($conn, $row['user_id'], $title, $message, 'event_reminder_24h', $row['event_id']);
            $remindersSent++;
        }

        return $remindersSent;
    } catch (Exception $e) {
        error_log("Send automated reminders error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 🔧 Helper function to get single stat
 */
function getSingleStat($conn, $sql)
{
    try {
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            return $row['count'] ?? 0;
        }
        return 0;
    } catch (Exception $e) {
        error_log("Get single stat error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 🧹 Clean old data
 */
function cleanOldData($conn)
{
    try {
        $cleanedCount = 0;

        // Clean old notifications (older than 90 days)
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $stmt->execute();
        $cleanedCount += $stmt->affected_rows;

        // Clean old event views (older than 1 year)
        $stmt = $conn->prepare("DELETE FROM event_views WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
        $stmt->execute();
        $cleanedCount += $stmt->affected_rows;

        // Clean expired password reset tokens
        $stmt = $conn->prepare("UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE reset_token_expires < NOW()");
        $stmt->execute();
        $cleanedCount += $stmt->affected_rows;

        return $cleanedCount;
    } catch (Exception $e) {
        error_log("Clean old data error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 📊 Get event analytics
 */
function getEventAnalytics($conn, $eventId)
{
    try {
        $analytics = [];

        // Registration sources
        $stmt = $conn->prepare("SELECT registration_source, COUNT(*) as count 
                               FROM tickets 
                               WHERE event_id = ? AND registration_source IS NOT NULL 
                               GROUP BY registration_source");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $analytics['registration_sources'] = [];
        while ($row = $result->fetch_assoc()) {
            $analytics['registration_sources'][] = $row;
        }

        // Peak registration times
        $stmt = $conn->prepare("SELECT HOUR(created_at) as hour, COUNT(*) as count 
                               FROM tickets 
                               WHERE event_id = ? 
                               GROUP BY HOUR(created_at) 
                               ORDER BY count DESC 
                               LIMIT 5");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $analytics['peak_hours'] = [];
        while ($row = $result->fetch_assoc()) {
            $analytics['peak_hours'][] = [
                'hour' => $row['hour'],
                'count' => $row['count'],
                'time_range' => sprintf('%02d:00 - %02d:59', $row['hour'], $row['hour'])
            ];
        }

        // User engagement
        $stmt = $conn->prepare("SELECT 
                                   AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.updated_at)) as avg_completion_time,
                                   COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as completed_registrations,
                                   COUNT(CASE WHEN t.payment_status = 'pending' THEN 1 END) as pending_registrations,
                                   COUNT(CASE WHEN t.payment_status = 'failed' THEN 1 END) as failed_registrations
                               FROM tickets t 
                               WHERE t.event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $analytics['engagement'] = $row;
        }

        return $analytics;
    } catch (Exception $e) {
        error_log("Get event analytics error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event badge/certificate template
 */
function generateEventBadge($eventData, $userData)
{
    try {
        $badgeData = [
            'event_title' => $eventData['title'],
            'participant_name' => $userData['first_name'] . ' ' . $userData['last_name'],
            'event_date' => formatDate($eventData['start_datetime']),
            'event_venue' => $eventData['venue'],
            'badge_id' => 'BADGE-' . $eventData['event_id'] . '-' . $userData['user_id'],
            'generated_at' => date('Y-m-d H:i:s'),
            'qr_code' => generateQRCode(json_encode([
                'type' => 'event_badge',
                'event_id' => $eventData['event_id'],
                'user_id' => $userData['user_id'],
                'verification' => hash('sha256', $eventData['event_id'] . $userData['user_id'])
            ]))
        ];

        return [
            'success' => true,
            'badge_data' => $badgeData,
            'message' => 'Badge generated successfully'
        ];
    } catch (Exception $e) {
        error_log("Generate event badge error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to generate badge'];
    }
}

/**
 * 🔍 Get event recommendations
 */
function getEventRecommendations($conn, $userId, $limit = 5)
{
    try {
        // Get user's registration history to understand preferences
        $sql = "SELECT e.category, e.event_type, COUNT(*) as count
                FROM tickets t
                JOIN events e ON t.event_id = e.event_id
                WHERE t.user_id = ?
                GROUP BY e.category, e.event_type
                ORDER BY count DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $preferences = [];
        while ($row = $result->fetch_assoc()) {
            $preferences[] = $row;
        }

        // Build recommendation query
        $recommendationSql = "SELECT DISTINCT e.*, u.first_name, u.last_name,
                             COUNT(t.ticket_id) as popularity_score
                             FROM events e
                             LEFT JOIN users u ON e.organizer_id = u.user_id
                             LEFT JOIN tickets t ON e.event_id = t.event_id
                             WHERE e.status = 'approved' 
                             AND e.start_datetime > NOW()
                             AND e.event_id NOT IN (
                                 SELECT event_id FROM tickets WHERE user_id = ?
                             )";

        $params = [$userId];
        $types = 'i';

        // Add preference-based filtering
        if (!empty($preferences)) {
            $categoryConditions = [];
            foreach ($preferences as $pref) {
                $categoryConditions[] = "(e.category = ? AND e.event_type = ?)";
                $params[] = $pref['category'];
                $params[] = $pref['event_type'];
                $types .= 'ss';
            }

            if (!empty($categoryConditions)) {
                $recommendationSql .= " AND (" . implode(" OR ", $categoryConditions) . ")";
            }
        }

        $recommendationSql .= " GROUP BY e.event_id
                               ORDER BY popularity_score DESC, e.start_datetime ASC
                               LIMIT ?";

        $params[] = $limit;
        $types .= 'i';

        $stmt = $conn->prepare($recommendationSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $recommendations = $result->fetch_all(MYSQLI_ASSOC);

        // If we don't have enough recommendations based on preferences, get popular events
        if (count($recommendations) < $limit) {
            $remaining = $limit - count($recommendations);
            $popularSql = "SELECT DISTINCT e.*, u.first_name, u.last_name,
                          COUNT(t.ticket_id) as popularity_score
                          FROM events e
                          LEFT JOIN users u ON e.organizer_id = u.user_id
                          LEFT JOIN tickets t ON e.event_id = t.event_id
                          WHERE e.status = 'approved' 
                          AND e.start_datetime > NOW()
                          AND e.event_id NOT IN (
                              SELECT event_id FROM tickets WHERE user_id = ?
                          )";

            // Exclude already recommended events
            if (!empty($recommendations)) {
                $excludeIds = array_column($recommendations, 'event_id');
                $placeholders = str_repeat('?,', count($excludeIds) - 1) . '?';
                $popularSql .= " AND e.event_id NOT IN ($placeholders)";
                $params = array_merge([$userId], $excludeIds, [$remaining]);
                $types = 'i' . str_repeat('i', count($excludeIds)) . 'i';
            } else {
                $params = [$userId, $remaining];
                $types = 'ii';
            }

            $popularSql .= " GROUP BY e.event_id
                            ORDER BY popularity_score DESC, e.start_datetime ASC
                            LIMIT ?";

            $stmt = $conn->prepare($popularSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $popularEvents = $result->fetch_all(MYSQLI_ASSOC);
            $recommendations = array_merge($recommendations, $popularEvents);
        }

        return array_slice($recommendations, 0, $limit);
    } catch (Exception $e) {
        error_log("Event recommendations error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event summary report
 */
function generateEventSummaryReport($conn, $eventId)
{
    try {
        // Get event details
        $stmt = $conn->prepare("SELECT e.*, u.first_name, u.last_name FROM events e LEFT JOIN users u ON e.organizer_id = u.user_id WHERE e.event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if (!$event) {
            return null;
        }

        $report = [
            'event' => $event,
            'statistics' => getEventStats($conn, $eventId),
            'analytics' => getEventAnalytics($conn, $eventId),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['user_id'] ?? 'System'
        ];

        // Additional insights
        $report['insights'] = [];

        // Registration rate insight
        $totalDays = max(1, ceil((strtotime($event['start_datetime']) - strtotime($event['created_at'])) / (24 * 60 * 60)));
        $dailyAvgRegistrations = $report['statistics']['total_registrations'] / $totalDays;

        $report['insights'][] = [
            'type' => 'registration_rate',
            'title' => 'Registration Rate',
            'value' => round($dailyAvgRegistrations, 2),
            'description' => "Average registrations per day since event creation"
        ];

        // Revenue per attendee
        if ($report['statistics']['total_registrations'] > 0) {
            $revenuePerAttendee = $report['statistics']['total_revenue'] / $report['statistics']['total_registrations'];
            $report['insights'][] = [
                'type' => 'revenue_per_attendee',
                'title' => 'Revenue per Attendee',
                'value' => formatCurrency($revenuePerAttendee),
                'description' => "Average revenue generated per registered attendee"
            ];
        }

        // Capacity utilization
        if (!empty($event['max_attendees']) && $event['max_attendees'] > 0) {
            $capacityUtilization = ($report['statistics']['total_registrations'] / $event['max_attendees']) * 100;
            $report['insights'][] = [
                'type' => 'capacity_utilization',
                'title' => 'Capacity Utilization',
                'value' => round($capacityUtilization, 1) . '%',
                'description' => "Percentage of maximum capacity filled"
            ];
        }

        return $report;
    } catch (Exception $e) {
        error_log("Event summary report error: " . $e->getMessage());
        return null;
    }
}

/**
 * 🔔 Send bulk notifications
 */
function sendBulkNotifications($conn, $userIds, $title, $message, $type = 'system', $relatedId = null)
{
    try {
        $successCount = 0;
        $errorCount = 0;

        foreach ($userIds as $userId) {
            $result = createNotification($conn, $userId, $title, $message, $type, $relatedId);
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'success' => true,
            'sent' => $successCount,
            'failed' => $errorCount,
            'total' => count($userIds)
        ];
    } catch (Exception $e) {
        error_log("Bulk notifications error: " . $e->getMessage());
        return [
            'success' => false,
            'sent' => 0,
            'failed' => count($userIds),
            'total' => count($userIds)
        ];
    }
}

/**
 * 🎯 Get event attendee list
 */
function getEventAttendees($conn, $eventId, $filters = [])
{
    try {
        $sql = "SELECT t.*, u.first_name, u.last_name, u.email, u.phone, u.department, u.student_id
                FROM tickets t
                JOIN users u ON t.user_id = u.user_id
                WHERE t.event_id = ?";

        $params = [$eventId];
        $types = 'i';

        // Filter by payment status
        if (!empty($filters['payment_status'])) {
            $sql .= " AND t.payment_status = ?";
            $params[] = $filters['payment_status'];
            $types .= 's';
        }

        // Filter by attendance status
        if (isset($filters['attended'])) {
            $sql .= " AND t.is_used = ?";
            $params[] = $filters['attended'] ? 1 : 0;
            $types .= 'i';
        }

        // Filter by department
        if (!empty($filters['department'])) {
            $sql .= " AND u.department = ?";
            $params[] = $filters['department'];
            $types .= 's';
        }

        // Search by name or email
        if (!empty($filters['search'])) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= 'ssss';
        }

        $sql .= " ORDER BY t.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Get event attendees error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🔧 Database maintenance functions
 */
function performDatabaseMaintenance($conn)
{
    try {
        $maintenanceLog = [];

        // Clean old notifications
        $cleaned = cleanOldData($conn);
        $maintenanceLog[] = "Cleaned $cleaned old records";

        // Update event statuses
        $stmt = $conn->prepare("UPDATE events SET status = 'completed' WHERE status = 'approved' AND end_datetime < NOW()");
        $stmt->execute();
        $completedEvents = $stmt->affected_rows;
        $maintenanceLog[] = "Marked $completedEvents events as completed";

        // Clean expired verification tokens
        $stmt = $conn->prepare("UPDATE users SET verification_token = NULL WHERE verification_token IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $expiredTokens = $stmt->affected_rows;
        $maintenanceLog[] = "Cleaned $expiredTokens expired verification tokens";

        // Optimize tables
        $tables = ['users', 'events', 'tickets', 'notifications'];
        foreach ($tables as $table) {
            $conn->query("OPTIMIZE TABLE $table");
        }
        $maintenanceLog[] = "Optimized database tables";

        return [
            'success' => true,
            'log' => $maintenanceLog,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        error_log("Database maintenance error: " . $e->getMessage());
        return [
            'success' => false,
            'log' => ["Maintenance failed: " . $e->getMessage()],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * 🎫 Verify ticket QR code
 */
function verifyTicketQR($conn, $qrData)
{
    try {
        $ticketData = json_decode($qrData, true);

        if (!$ticketData || !isset($ticketData['ticket_id'], $ticketData['verification_code'])) {
            return ['success' => false, 'message' => 'Invalid QR code format'];
        }

        // Get ticket details
        $stmt = $conn->prepare("SELECT t.*, e.title, e.start_datetime, u.first_name, u.last_name 
                               FROM tickets t 
                               JOIN events e ON t.event_id = e.event_id 
                               JOIN users u ON t.user_id = u.user_id 
                               WHERE t.ticket_id = ?");
        $stmt->bind_param("i", $ticketData['ticket_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $ticket = $result->fetch_assoc();

        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        // Verify the verification code
        $expectedCode = hash('sha256', $ticket['ticket_id'] . $ticket['created_at']);
        if ($ticketData['verification_code'] !== $expectedCode) {
            return ['success' => false, 'message' => 'Invalid verification code'];
        }

        // Check if ticket is already used
        if ($ticket['is_used']) {
            return [
                'success' => false,
                'message' => 'Ticket already used',
                'used_at' => $ticket['used_at'],
                'ticket' => $ticket
            ];
        }

        // Check payment status
        if ($ticket['payment_status'] !== 'completed') {
            return [
                'success' => false,
                'message' => 'Payment not completed',
                'payment_status' => $ticket['payment_status'],
                'ticket' => $ticket
            ];
        }

        return [
            'success' => true,
            'message' => 'Valid ticket',
            'ticket' => $ticket
        ];
    } catch (Exception $e) {
        error_log("Verify ticket QR error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Verification failed'];
    }
}

/**
 * ✅ Mark ticket as used
 */
function markTicketAsUsed($conn, $ticketId, $verifiedBy = null)
{
    try {
        $stmt = $conn->prepare("UPDATE tickets SET is_used = 1, used_at = NOW(), verified_by = ? WHERE ticket_id = ?");
        $stmt->bind_param("ii", $verifiedBy, $ticketId);

        if ($stmt->execute()) {
            // Log the activity
            logActivity($conn, $verifiedBy, 'ticket_verified', "Verified ticket ID: $ticketId");

            return ['success' => true, 'message' => 'Ticket marked as used'];
        } else {
            return ['success' => false, 'message' => 'Failed to update ticket'];
        }
    } catch (Exception $e) {
        error_log("Mark ticket as used error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * 📊 Get system-wide statistics
 */
function getSystemStats($conn)
{
    try {
        $stats = [];

        // Total counts
        $stats['total_users'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM users");
        $stats['total_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events");
        $stats['total_tickets'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets");
        $stats['total_revenue'] = getSingleStat($conn, "SELECT SUM(price) as count FROM tickets WHERE payment_status = 'completed'") ?? 0;

        // This month stats
        $stats['users_this_month'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
        $stats['events_this_month'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
        $stats['revenue_this_month'] = getSingleStat($conn, "SELECT SUM(price) as count FROM tickets WHERE payment_status = 'completed' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())") ?? 0;

        // Event status breakdown
        $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM events GROUP BY status");
        $stmt->execute();
        $result = $stmt->get_result();

        $stats['events_by_status'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['events_by_status'][$row['status']] = $row['count'];
        }

        // Popular categories
        $stmt = $conn->prepare("SELECT category, COUNT(*) as count FROM events WHERE status = 'approved' GROUP BY category ORDER BY count DESC LIMIT 5");
        $stmt->execute();
        $result = $stmt->get_result();

        $stats['popular_categories'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['popular_categories'][] = [
                'category' => $row['category'],
                'count' => $row['count']
            ];
        }

        // Recent activity
        $stats['recent_registrations'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['recent_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");

        return $stats;
    } catch (Exception $e) {
        error_log("System stats error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate activity feed
 */
function getActivityFeed($conn, $userId = null, $limit = 20)
{
    try {
        $activities = [];

        // Recent event creations
        $sql = "SELECT 'event_created' as type, e.event_id as related_id, e.title, e.created_at, 
                       u.first_name, u.last_name, u.user_id
                FROM events e 
                JOIN users u ON e.organizer_id = u.user_id 
                WHERE e.status = 'approved'";

        $params = [];
        $types = '';

        if ($userId) {
            $sql .= " AND e.organizer_id = ?";
            $params[] = $userId;
            $types = 'i';
        }

        $sql .= " ORDER BY e.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => $row['type'],
                'title' => $row['first_name'] . ' ' . $row['last_name'] . ' created event: ' . $row['title'],
                'timestamp' => $row['created_at'],
                'user_id' => $row['user_id'],
                'user_name' => $row['first_name'] . ' ' . $row['last_name'],
                'related_id' => $row['related_id'],
                'icon' => 'fas fa-calendar-plus',
                'color' => 'success'
            ];
        }

        // Recent registrations (if not user-specific)
        if (!$userId) {
            $regSql = "SELECT 'registration' as type, t.event_id as related_id, e.title, t.created_at,
                              u.first_name, u.last_name, u.user_id
                       FROM tickets t
                       JOIN events e ON t.event_id = e.event_id
                       JOIN users u ON t.user_id = u.user_id
                       WHERE t.payment_status = 'completed'
                       ORDER BY t.created_at DESC 
                       LIMIT ?";

            $stmt = $conn->prepare($regSql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $activities[] = [
                    'type' => $row['type'],
                    'title' => $row['first_name'] . ' ' . $row['last_name'] . ' registered for: ' . $row['title'],
                    'timestamp' => $row['created_at'],
                    'user_id' => $row['user_id'],
                    'user_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'related_id' => $row['related_id'],
                    'icon' => 'fas fa-ticket-alt',
                    'color' => 'primary'
                ];
            }
        }

        // Sort all activities by timestamp
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, $limit);
    } catch (Exception $e) {
        error_log("Activity feed error: " . $e->getMessage());
        return [];
    }
}


/**
 * 🔍 Validate and sanitize form data
 */
function validateFormData($data, $rules)
{
    $errors = [];
    $sanitized = [];

    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';

        // Sanitize
        $sanitized[$field] = sanitizeInput($value);

        // Required validation
        if (isset($rule['required']) && $rule['required'] && empty($sanitized[$field])) {
            $errors[$field] = ucfirst($field) . ' is required';
            continue;
        }

        // Skip other validations if field is empty and not required
        if (empty($sanitized[$field])) {
            continue;
        }

        // Email validation
        if (isset($rule['email']) && $rule['email'] && !isValidEmail($sanitized[$field])) {
            $errors[$field] = 'Please enter a valid email address';
        }

        // Phone validation
        if (isset($rule['phone']) && $rule['phone'] && !isValidPhone($sanitized[$field])) {
            $errors[$field] = 'Please enter a valid phone number';
        }

        // Min length validation
        if (isset($rule['min_length']) && strlen($sanitized[$field]) < $rule['min_length']) {
            $errors[$field] = ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters';
        }

        // Max length validation
        if (isset($rule['max_length']) && strlen($sanitized[$field]) > $rule['max_length']) {
            $errors[$field] = ucfirst($field) . ' must not exceed ' . $rule['max_length'] . ' characters';
        }

        // Numeric validation
        if (isset($rule['numeric']) && $rule['numeric'] && !is_numeric($sanitized[$field])) {
            $errors[$field] = ucfirst($field) . ' must be a number';
        }

        // Date validation
        if (isset($rule['date']) && $rule['date'] && !strtotime($sanitized[$field])) {
            $errors[$field] = 'Please enter a valid date';
        }

        // Custom validation
        if (isset($rule['custom']) && is_callable($rule['custom'])) {
            $customResult = $rule['custom']($sanitized[$field]);
            if ($customResult !== true) {
                $errors[$field] = $customResult;
            }
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $sanitized
    ];
}

/**
 * 🎯 Get user permissions
 */
function getUserPermissions($role)
{
    $permissions = [
        'admin' => [
            'manage_users',
            'manage_events',
            'manage_tickets',
            'view_reports',
            'system_settings',
            'approve_events',
            'delete_events',
            'manage_categories',
            'view_analytics'
        ],
        'organizer' => [
            'create_events',
            'edit_own_events',
            'view_own_reports',
            'manage_own_tickets',
            'view_attendees'
        ],
        'user' => [
            'register_events',
            'view_events',
            'manage_profile',
            'view_tickets'
        ]
    ];

    return $permissions[$role] ?? $permissions['user'];
}

/**
 * 🔐 Check user permission
 */
function hasPermission($userRole, $permission)
{
    $userPermissions = getUserPermissions($userRole);
    return in_array($permission, $userPermissions);
}

/**
 * 🎨 Format notification message
 */
function formatNotificationMessage($type, $data)
{
    $messages = [
        'event_approved' => "Your event '{$data['title']}' has been approved!",
        'event_rejected' => "Your event '{$data['title']}' has been rejected. Reason: {$data['reason']}",
        'payment_completed' => "Payment completed for '{$data['title']}'. Your ticket is ready!",
        'event_reminder' => "Reminder: '{$data['title']}' starts {$data['time']}",
        'event_cancelled' => "Event '{$data['title']}' has been cancelled. You will be refunded.",
        'event_updated' => "Event '{$data['title']}' has been updated. Check the details.",
        'new_registration' => "New registration for your event '{$data['title']}'",
        'ticket_verified' => "Your ticket for '{$data['title']}' has been verified"
    ];

    return $messages[$type] ?? 'You have a new notification';
}

/**
 * 🔄 Process scheduled tasks
 */
function processScheduledTasks($conn)
{
    try {
        $tasksProcessed = 0;

        // Send event reminders
        $reminders = sendAutomatedReminders($conn);
        $tasksProcessed += $reminders;

        // Clean old data
        $cleaned = cleanOldData($conn);
        $tasksProcessed += ($cleaned > 0 ? 1 : 0);

        // Update event statuses
        $stmt = $conn->prepare("UPDATE events SET status = 'completed' WHERE status = 'approved' AND end_datetime < NOW()");
        $stmt->execute();
        $completedEvents = $stmt->affected_rows;
        $tasksProcessed += ($completedEvents > 0 ? 1 : 0);

        return [
            'success' => true,
            'tasks_processed' => $tasksProcessed,
            'details' => [
                'reminders_sent' => $reminders,
                'records_cleaned' => $cleaned,
                'events_completed' => $completedEvents
            ]
        ];
    } catch (Exception $e) {
        error_log("Process scheduled tasks error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 🎯 Get trending events
 */
function getTrendingEvents($conn, $limit = 10, $days = 7)
{
    try {
        $sql = "SELECT e.*, u.first_name, u.last_name,
                       COUNT(t.ticket_id) as recent_registrations,
                       (COUNT(t.ticket_id) / DATEDIFF(e.start_datetime, NOW())) as trend_score
                FROM events e
                LEFT JOIN users u ON e.organizer_id = u.user_id
                LEFT JOIN tickets t ON e.event_id = t.event_id 
                    AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND t.payment_status IN ('completed', 'pending')
                WHERE e.status = 'approved' 
                AND e.start_datetime > NOW()
                AND e.start_datetime <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                GROUP BY e.event_id
                HAVING recent_registrations > 0
                ORDER BY trend_score DESC, recent_registrations DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $days, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Get trending events error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event sharing links
 */
function generateSharingLinks($event)
{
    $eventUrl = urlencode("http://" . $_SERVER['HTTP_HOST'] . "/events/view.php?id=" . $event['event_id']);
    $eventTitle = urlencode($event['title']);
    $eventDescription = urlencode(truncateText($event['description'], 100));

    return [
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u=$eventUrl",
        'twitter' => "https://twitter.com/intent/tweet?url=$eventUrl&text=$eventTitle",
        'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url=$eventUrl",
        'whatsapp' => "https://wa.me/?text=$eventTitle%20$eventUrl",
        'email' => "mailto:?subject=$eventTitle&body=$eventDescription%20$eventUrl",
        'copy_link' => "http://" . $_SERVER['HTTP_HOST'] . "/events/view.php?id=" . $event['event_id']
    ];
}

/**
 * 🔍 Search users for admin
 */
function searchUsersForAdmin($conn, $query, $filters = [], $limit = 50)
{
    try {
        $sql = "SELECT user_id, username, email, first_name, last_name, role, department, 
                       phone, created_at, last_login, is_verified, status
                FROM users 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)";

        $searchTerm = '%' . $query . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        $types = 'ssss';

        // Role filter
        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
            $types .= 's';
        }

        // Department filter
        if (!empty($filters['department'])) {
            $sql .= " AND department = ?";
            $params[] = $filters['department'];
            $types .= 's';
        }

        // Status filter
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        // Verification filter
        if (isset($filters['is_verified'])) {
            $sql .= " AND is_verified = ?";
            $params[] = $filters['is_verified'] ? 1 : 0;
            $types .= 'i';
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Search users for admin error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎯 Get event feedback and ratings
 */
function getEventFeedback($conn, $eventId)
{
    try {
        $sql = "SELECT f.*, u.first_name, u.last_name, u.profile_image
                FROM event_feedback f
                JOIN users u ON f.user_id = u.user_id
                WHERE f.event_id = ?
                ORDER BY f.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $feedback = $result->fetch_all(MYSQLI_ASSOC);

        // Calculate average rating
        $avgRating = 0;
        if (!empty($feedback)) {
            $totalRating = array_sum(array_column($feedback, 'rating'));
            $avgRating = round($totalRating / count($feedback), 1);
        }

        return [
            'feedback' => $feedback,
            'average_rating' => $avgRating,
            'total_reviews' => count($feedback)
        ];
    } catch (Exception $e) {
        error_log("Get event feedback error: " . $e->getMessage());
        return [
            'feedback' => [],
            'average_rating' => 0,
            'total_reviews' => 0
        ];
    }
}

/**
 * 🎨 Generate event certificate
 */
function generateEventCertificate($eventData, $userData)
{
    try {
        $certificateData = [
            'certificate_id' => 'CERT-' . $eventData['event_id'] . '-' . $userData['user_id'] . '-' . time(),
            'participant_name' => $userData['first_name'] . ' ' . $userData['last_name'],
            'event_title' => $eventData['title'],
            'event_date' => formatDate($eventData['start_datetime']),
            'event_venue' => $eventData['venue'],
            'organizer_name' => $eventData['organizer_name'] ?? 'Event Organizer',
            'generated_date' => date('F j, Y'),
            'verification_code' => hash('sha256', $eventData['event_id'] . $userData['user_id'] . date('Y-m-d'))
        ];

        return [
            'success' => true,
            'certificate_data' => $certificateData,
            'message' => 'Certificate generated successfully'
        ];
    } catch (Exception $e) {
        error_log("Generate certificate error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to generate certificate'];
    }
}

/**
 * 🔔 Get notification preferences
 */
function getNotificationPreferences($conn, $userId)
{
    try {
        $stmt = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($preferences = $result->fetch_assoc()) {
            return $preferences;
        }

        // Return default preferences if none exist
        return [
            'email_notifications' => 1,
            'sms_notifications' => 0,
            'push_notifications' => 1,
            'event_reminders' => 1,
            'payment_notifications' => 1,
            'marketing_emails' => 0
        ];
    } catch (Exception $e) {
        error_log("Get notification preferences error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎯 Update notification preferences
 */
function updateNotificationPreferences($conn, $userId, $preferences)
{
    try {
        $sql = "INSERT INTO notification_preferences (user_id, email_notifications, sms_notifications, 
                push_notifications, event_reminders, payment_notifications, marketing_emails, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                email_notifications = VALUES(email_notifications),
                sms_notifications = VALUES(sms_notifications),
                push_notifications = VALUES(push_notifications),
                event_reminders = VALUES(event_reminders),
                payment_notifications = VALUES(payment_notifications),
                marketing_emails = VALUES(marketing_emails),
                updated_at = NOW()";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iiiiiii",
            $userId,
            $preferences['email_notifications'],
            $preferences['sms_notifications'],
            $preferences['push_notifications'],
            $preferences['event_reminders'],
            $preferences['payment_notifications'],
            $preferences['marketing_emails']
        );

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Preferences updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update preferences'];
        }
    } catch (Exception $e) {
        error_log("Update notification preferences error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * 🎨 Generate event statistics chart data
 */
function getEventStatsChartData($conn, $eventId)
{
    try {
        $chartData = [];

        // Registration timeline
        $stmt = $conn->prepare("SELECT DATE(created_at) as date, COUNT(*) as registrations
                               FROM tickets 
                               WHERE event_id = ? 
                               GROUP BY DATE(created_at) 
                               ORDER BY date");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $chartData['registration_timeline'] = [];
        while ($row = $result->fetch_assoc()) {
            $chartData['registration_timeline'][] = [
                'date' => $row['date'],
                'registrations' => (int)$row['registrations']
            ];
        }

        // Payment status distribution
        $stmt = $conn->prepare("SELECT payment_status, COUNT(*) as count
                               FROM tickets 
                               WHERE event_id = ? 
                               GROUP BY payment_status");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $chartData['payment_status'] = [];
        while ($row = $result->fetch_assoc()) {
            $chartData['payment_status'][] = [
                'status' => $row['payment_status'],
                'count' => (int)$row['count']
            ];
        }

        // Department distribution
        $stmt = $conn->prepare("SELECT u.department, COUNT(*) as count
                               FROM tickets t
                               JOIN users u ON t.user_id = u.user_id
                               WHERE t.event_id = ? AND u.department IS NOT NULL
                               GROUP BY u.department
                               ORDER BY count DESC");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $chartData['department_distribution'] = [];
        while ($row = $result->fetch_assoc()) {
            $chartData['department_distribution'][] = [
                'department' => $row['department'],
                'count' => (int)$row['count']
            ];
        }

        return $chartData;
    } catch (Exception $e) {
        error_log("Get event stats chart data error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🔍 Validate event capacity
 */
function validateEventCapacity($conn, $eventId, $requestedTickets = 1)
{
    try {
        // Get event details
        $stmt = $conn->prepare("SELECT max_attendees FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if (!$event) {
            return ['valid' => false, 'message' => 'Event not found'];
        }

        // If no max attendees set, capacity is unlimited
        if (!$event['max_attendees'] || $event['max_attendees'] == 0) {
            return ['valid' => true, 'message' => 'Unlimited capacity'];
        }

        // Get current registrations
        $stmt = $conn->prepare("SELECT COUNT(*) as current_registrations 
                               FROM tickets 
                               WHERE event_id = ? AND payment_status IN ('completed', 'pending')");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentRegistrations = $result->fetch_assoc()['current_registrations'];

        $availableSpots = $event['max_attendees'] - $currentRegistrations;

        if ($availableSpots >= $requestedTickets) {
            return [
                'valid' => true,
                'available_spots' => $availableSpots,
                'message' => 'Capacity available'
            ];
        } else {
            return [
                'valid' => false,
                'available_spots' => $availableSpots,
                'message' => 'Insufficient capacity'
            ];
        }
    } catch (Exception $e) {
        error_log("Validate event capacity error: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Validation error'];
    }
}

/**
 * 🎯 Get event waitlist position
 */
function getWaitlistPosition($conn, $userId, $eventId)
{
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) + 1 as position
                               FROM event_waitlist 
                               WHERE event_id = ? 
                               AND created_at < (
                                   SELECT created_at 
                                   FROM event_waitlist 
                                   WHERE user_id = ? AND event_id = ?
                               )");
        $stmt->bind_param("iii", $eventId, $userId, $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['position'];
        }

        return 0;
    } catch (Exception $e) {
        error_log("Get waitlist position error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 🎨 Format event status message
 */
function getEventStatusMessage($event)
{
    $now = time();
    $startTime = strtotime($event['start_datetime']);
    $endTime = strtotime($event['end_datetime']);

    if ($event['status'] !== 'approved') {
        return [
            'message' => 'Event is ' . $event['status'],
            'class' => 'status-' . $event['status'],
            'icon' => 'fas fa-info-circle'
        ];
    }

    if ($now < $startTime) {
        $timeUntil = $startTime - $now;
        if ($timeUntil < 3600) { // Less than 1 hour
            return [
                'message' => 'Starting in ' . ceil($timeUntil / 60) . ' minutes',
                'class' => 'status-starting-soon',
                'icon' => 'fas fa-clock'
            ];
        } elseif ($timeUntil < 86400) { // Less than 1 day
            return [
                'message' => 'Starting in ' . ceil($timeUntil / 3600) . ' hours',
                'class' => 'status-upcoming',
                'icon' => 'fas fa-calendar-alt'
            ];
        } else {
            return [
                'message' => 'Upcoming event',
                'class' => 'status-upcoming',
                'icon' => 'fas fa-calendar-alt'
            ];
        }
    } elseif ($now >= $startTime && $now <= $endTime) {
        return [
            'message' => 'Event is live now!',
            'class' => 'status-live',
            'icon' => 'fas fa-broadcast-tower'
        ];
    } else {
        return [
            'message' => 'Event completed',
            'class' => 'status-completed',
            'icon' => 'fas fa-check-circle'
        ];
    }
}

/**
 * 🔧 System health check
 */
function performSystemHealthCheck($conn)
{
    try {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Database connection check
        try {
            $conn->query("SELECT 1");
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (Exception $e) {
            $health['checks']['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
            $health['status'] = 'unhealthy';
        }

        // File permissions check
        $uploadDir = 'uploads/';
        if (is_writable($uploadDir)) {
            $health['checks']['file_permissions'] = ['status' => 'ok', 'message' => 'Upload directory is writable'];
        } else {
            $health['checks']['file_permissions'] = ['status' => 'warning', 'message' => 'Upload directory is not writable'];
        }

        // Memory usage check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = convertToBytes($memoryLimit);
        $memoryPercentage = ($memoryUsage / $memoryLimitBytes) * 100;

        if ($memoryPercentage < 80) {
            $health['checks']['memory'] = [
                'status' => 'ok',
                'message' => 'Memory usage: ' . round($memoryPercentage, 2) . '%',
                'usage' => $memoryUsage,
                'limit' => $memoryLimitBytes
            ];
        } else {
            $health['checks']['memory'] = [
                'status' => 'warning',
                'message' => 'High memory usage: ' . round($memoryPercentage, 2) . '%',
                'usage' => $memoryUsage,
                'limit' => $memoryLimitBytes
            ];
        }

        // Disk space check
        $freeBytes = disk_free_space('.');
        $totalBytes = disk_total_space('.');
        $usedPercentage = (($totalBytes - $freeBytes) / $totalBytes) * 100;

        if ($usedPercentage < 90) {
            $health['checks']['disk_space'] = [
                'status' => 'ok',
                'message' => 'Disk usage: ' . round($usedPercentage, 2) . '%',
                'free' => $freeBytes,
                'total' => $totalBytes
            ];
        } else {
            $health['checks']['disk_space'] = [
                'status' => 'warning',
                'message' => 'High disk usage: ' . round($usedPercentage, 2) . '%',
                'free' => $freeBytes,
                'total' => $totalBytes
            ];
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }

        // Session check
        if (session_status() === PHP_SESSION_ACTIVE) {
            $health['checks']['sessions'] = ['status' => 'ok', 'message' => 'Sessions are working'];
        } else {
            $health['checks']['sessions'] = ['status' => 'error', 'message' => 'Session not active'];
            $health['status'] = 'unhealthy';
        }

        // Recent errors check
        $errorCount = 0;
        $errorLog = ini_get('error_log');
        if ($errorLog && file_exists($errorLog)) {
            $errors = file_get_contents($errorLog);
            $recentErrors = array_filter(explode("\n", $errors), function ($line) {
                return strpos($line, date('Y-m-d')) !== false;
            });
            $errorCount = count($recentErrors);
        }

        if ($errorCount < 10) {
            $health['checks']['errors'] = ['status' => 'ok', 'message' => "Recent errors: $errorCount"];
        } else {
            $health['checks']['errors'] = ['status' => 'warning', 'message' => "High error count: $errorCount"];
            if ($health['status'] === 'healthy') {
                $health['status'] = 'warning';
            }
        }

        return $health;
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Health check failed: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * 🔧 Helper function to convert memory limit to bytes
 */
function convertToBytes($value)
{
    $unit = strtolower(substr($value, -1));
    $value = (int) $value;

    switch ($unit) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }

    return $value;
}

/**
 * 🎯 Get system configuration
 */
function getSystemConfig($conn)
{
    try {
        $config = [];

        // Get from database if config table exists
        $stmt = $conn->prepare("SELECT config_key, config_value FROM system_config");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $config[$row['config_key']] = $row['config_value'];
            }
        }

        // Default configuration
        $defaults = [
            'site_name' => 'EMS - Event Management System',
            'site_description' => 'Ekwendeni Mighty Campus Event Management System',
            'admin_email' => 'admin@ems.com',
            'max_file_size' => '5MB',
            'allowed_file_types' => 'jpg,jpeg,png,gif,pdf',
            'timezone' => 'Africa/Blantyre',
            'currency' => 'MWK',
            'registration_enabled' => '1',
            'email_verification_required' => '1',
            'auto_approve_events' => '0',
            'maintenance_mode' => '0'
        ];

        return array_merge($defaults, $config);
    } catch (Exception $e) {
        error_log("Get system config error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Update system configuration
 */
function updateSystemConfig($conn, $key, $value)
{
    try {
        $sql = "INSERT INTO system_config (config_key, config_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value), 
                updated_at = NOW()";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $key, $value);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Configuration updated'];
        } else {
            return ['success' => false, 'message' => 'Failed to update configuration'];
        }
    } catch (Exception $e) {
        error_log("Update system config error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

/**
 * 🔍 Search and filter functions
 */
function buildSearchQuery($baseQuery, $searchFields, $searchTerm, &$params, &$types)
{
    if (empty($searchTerm)) {
        return $baseQuery;
    }

    $searchConditions = [];
    foreach ($searchFields as $field) {
        $searchConditions[] = "$field LIKE ?";
        $params[] = '%' . $searchTerm . '%';
        $types .= 's';
    }

    if (!empty($searchConditions)) {
        $baseQuery .= " AND (" . implode(" OR ", $searchConditions) . ")";
    }

    return $baseQuery;
}

/**
 * 🎯 Rate limiting function
 */
function checkRateLimit($action, $identifier, $maxAttempts = 5, $timeWindow = 300)
{
    $key = "rate_limit_{$action}_{$identifier}";

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }

    $rateData = $_SESSION[$key];

    // Reset if time window has passed
    if (time() - $rateData['first_attempt'] > $timeWindow) {
        $_SESSION[$key] = [
            'attempts' => 1,
            'first_attempt' => time()
        ];
        return true;
    }

    // Check if limit exceeded
    if ($rateData['attempts'] >= $maxAttempts) {
        return false;
    }

    // Increment attempts
    $_SESSION[$key]['attempts']++;
    return true;
}

/**
 * 🎨 Generate backup filename
 */
function generateBackupFilename($type = 'full')
{
    $timestamp = date('Y-m-d_H-i-s');
    $hostname = gethostname() ?: 'localhost';
    return "ems_backup_{$type}_{$hostname}_{$timestamp}.sql";
}

/**
 * 🔧 Create database backup
 */
function createDatabaseBackup($conn, $backupType = 'full')
{
    try {
        $filename = generateBackupFilename($backupType);
        $backupPath = "backups/" . $filename;

        // Create backups directory if it doesn't exist
        if (!file_exists('backups')) {
            mkdir('backups', 0755, true);
        }

        // Get database name from connection
        $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];

        // Tables to backup
        $tables = [];
        if ($backupType === 'full') {
            $result = $conn->query("SHOW TABLES");
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }
        } else {
            // Essential tables only
            $tables = ['users', 'events', 'tickets', 'notifications'];
        }

        $backup = "-- EMS Database Backup\n";
        $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Database: $dbName\n";
        $backup .= "-- Type: $backupType\n\n";

        foreach ($tables as $table) {
            // Table structure
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            $backup .= "\n-- Table structure for `$table`\n";
            $backup .= "DROP TABLE IF EXISTS `$table`;\n";
            $backup .= $row[1] . ";\n\n";

            // Table data
            $result = $conn->query("SELECT * FROM `$table`");
            if ($result->num_rows > 0) {
                $backup .= "-- Data for table `$table`\n";
                while ($row = $result->fetch_assoc()) {
                    $backup .= "INSERT INTO `$table` VALUES (";
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = $value === null ? 'NULL' : "'" . $conn->real_escape_string($value) . "'";
                    }
                    $backup .= implode(', ', $values) . ");\n";
                }
                $backup .= "\n";
            }
        }

        if (file_put_contents($backupPath, $backup)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupPath,
                'size' => filesize($backupPath)
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to write backup file'];
        }
    } catch (Exception $e) {
        error_log("Create database backup error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
    }
}

/**
 * 🎯 Get system logs
 */
function getSystemLogs($logType = 'error', $lines = 100)
{
    try {
        $logs = [];

        switch ($logType) {
            case 'error':
                $logFile = ini_get('error_log');
                break;
            case 'access':
                $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/access.log';
                break;
            case 'activity':
                $logFile = 'logs/activity.log';
                break;
            default:
                return [];
        }

        if (!$logFile || !file_exists($logFile)) {
            return [];
        }

        $file = new SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);

        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }

        return array_reverse($logs);
    } catch (Exception $e) {
        error_log("Get system logs error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event QR code for check-in
 */
function generateEventCheckInQR($eventId, $eventTitle)
{
    try {
        $qrData = json_encode([
            'type' => 'event_checkin',
            'event_id' => $eventId,
            'event_title' => $eventTitle,
            'generated_at' => time(),
            'verification' => hash('sha256', $eventId . $eventTitle . date('Y-m-d'))
        ]);

        return generateQRCode($qrData, 300);
    } catch (Exception $e) {
        error_log("Generate event check-in QR error: " . $e->getMessage());
        return null;
    }
}

/**
 * 🔍 Advanced event analytics
 */
function getAdvancedEventAnalytics($conn, $eventId)
{
    try {
        $analytics = [];

        // Registration conversion funnel
        $stmt = $conn->prepare("
            SELECT 
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_registrations,
                COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_registrations,
                COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_registrations,
                COUNT(CASE WHEN is_used = 1 THEN 1 END) as attended_users
            FROM tickets 
            WHERE event_id = ?
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $analytics['conversion_funnel'] = $result->fetch_assoc();

        // Registration by hour of day
        $stmt = $conn->prepare("
            SELECT HOUR(created_at) as hour, COUNT(*) as registrations
            FROM tickets 
            WHERE event_id = ? 
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $analytics['hourly_registrations'] = [];
        while ($row = $result->fetch_assoc()) {
            $analytics['hourly_registrations'][] = [
                'hour' => (int)$row['hour'],
                'registrations' => (int)$row['registrations'],
                'time_label' => sprintf('%02d:00', $row['hour'])
            ];
        }

        // User engagement metrics
        $stmt = $conn->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_registration_time,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(*) as total_tickets
            FROM tickets 
            WHERE event_id = ?
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $analytics['engagement'] = $result->fetch_assoc();

        // Geographic distribution (if location data available)
        $stmt = $conn->prepare("
            SELECT u.department, COUNT(*) as count
            FROM tickets t
            JOIN users u ON t.user_id = u.user_id
            WHERE t.event_id = ? AND u.department IS NOT NULL
            GROUP BY u.department
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $analytics['department_breakdown'] = [];
        while ($row = $result->fetch_assoc()) {
            $analytics['department_breakdown'][] = $row;
        }

        return $analytics;
    } catch (Exception $e) {
        error_log("Advanced event analytics error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎯 Generate event performance report
 */
function generateEventPerformanceReport($conn, $eventId)
{
    try {
        $report = [];

        // Basic event info
        $stmt = $conn->prepare("
            SELECT e.*, u.first_name, u.last_name
            FROM events e
            LEFT JOIN users u ON e.organizer_id = u.user_id
            WHERE e.event_id = ?
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $report['event'] = $result->fetch_assoc();

        if (!$report['event']) {
            return null;
        }

        // Performance metrics
        $report['metrics'] = [
            'registration_rate' => 0,
            'attendance_rate' => 0,
            'revenue_per_attendee' => 0,
            'capacity_utilization' => 0,
            'satisfaction_score' => 0
        ];

        // Get statistics
        $stats = getEventStats($conn, $eventId);
        $report['statistics'] = $stats;

        // Calculate performance metrics
        if ($stats['total_registrations'] > 0) {
            $report['metrics']['attendance_rate'] = round(($stats['attended'] / $stats['total_registrations']) * 100, 2);
            $report['metrics']['revenue_per_attendee'] = round($stats['total_revenue'] / $stats['total_registrations'], 2);
        }

        if ($report['event']['max_attendees'] > 0) {
            $report['metrics']['capacity_utilization'] = round(($stats['total_registrations'] / $report['event']['max_attendees']) * 100, 2);
        }

        // Registration timeline
        $stmt = $conn->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as registrations
            FROM tickets 
            WHERE event_id = ? 
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $report['timeline'] = [];
        while ($row = $result->fetch_assoc()) {
            $report['timeline'][] = $row;
        }

        // Feedback and ratings
        $feedback = getEventFeedback($conn, $eventId);
        $report['feedback'] = $feedback;
        $report['metrics']['satisfaction_score'] = $feedback['average_rating'];

        // Recommendations
        $report['recommendations'] = [];

        if ($report['metrics']['attendance_rate'] < 70) {
            $report['recommendations'][] = [
                'type' => 'attendance',
                'message' => 'Consider improving event promotion and reminder strategies to increase attendance rate.',
                'priority' => 'high'
            ];
        }

        if ($report['metrics']['capacity_utilization'] < 50) {
            $report['recommendations'][] = [
                'type' => 'capacity',
                'message' => 'Event capacity might be too high. Consider reducing venue size or improving marketing.',
                'priority' => 'medium'
            ];
        }

        if ($report['metrics']['satisfaction_score'] < 3.5) {
            $report['recommendations'][] = [
                'type' => 'satisfaction',
                'message' => 'Event satisfaction is below average. Review feedback and improve event quality.',
                'priority' => 'high'
            ];
        }

        $report['generated_at'] = date('Y-m-d H:i:s');
        return $report;
    } catch (Exception $e) {
        error_log("Generate event performance report error: " . $e->getMessage());
        return null;
    }
}

/**
 * 🎨 Generate event marketing insights
 */
function getEventMarketingInsights($conn, $eventId)
{
    try {
        $insights = [];

        // Registration sources
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(registration_source, 'direct') as source,
                COUNT(*) as registrations,
                COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as conversions
            FROM tickets 
            WHERE event_id = ?
            GROUP BY registration_source
            ORDER BY registrations DESC
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $insights['sources'] = [];
        while ($row = $result->fetch_assoc()) {
            $conversionRate = $row['registrations'] > 0 ? round(($row['conversions'] / $row['registrations']) * 100, 2) : 0;
            $insights['sources'][] = [
                'source' => $row['source'],
                'registrations' => (int)$row['registrations'],
                'conversions' => (int)$row['conversions'],
                'conversion_rate' => $conversionRate
            ];
        }

        // Peak registration times
        $stmt = $conn->prepare("
            SELECT 
                DAYNAME(created_at) as day_name,
                HOUR(created_at) as hour,
                COUNT(*) as registrations
            FROM tickets 
            WHERE event_id = ?
            GROUP BY DAYNAME(created_at), HOUR(created_at)
            ORDER BY registrations DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $insights['peak_times'] = [];
        while ($row = $result->fetch_assoc()) {
            $insights['peak_times'][] = [
                'day' => $row['day_name'],
                'hour' => (int)$row['hour'],
                'registrations' => (int)$row['registrations'],
                'time_label' => $row['day_name'] . ' ' . sprintf('%02d:00', $row['hour'])
            ];
        }

        // User demographics
        $stmt = $conn->prepare("
            SELECT 
                u.department,
                COUNT(*) as count,
                AVG(YEAR(CURDATE()) - YEAR(u.created_at)) as avg_account_age
            FROM tickets t
            JOIN users u ON t.user_id = u.user_id
            WHERE t.event_id = ? AND u.department IS NOT NULL
            GROUP BY u.department
            ORDER BY count DESC
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $insights['demographics'] = [];
        while ($row = $result->fetch_assoc()) {
            $insights['demographics'][] = [
                'department' => $row['department'],
                'count' => (int)$row['count'],
                'avg_account_age' => round($row['avg_account_age'], 1)
            ];
        }

        return $insights;
    } catch (Exception $e) {
        error_log("Event marketing insights error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🔔 Send event update notifications
 */
function sendEventUpdateNotifications($conn, $eventId, $updateType, $message)
{
    try {
        // Get all registered users for this event
        $stmt = $conn->prepare("
            SELECT DISTINCT t.user_id, u.first_name, u.email,
                   e.title as event_title
            FROM tickets t
            JOIN users u ON t.user_id = u.user_id
            JOIN events e ON t.event_id = e.event_id
            WHERE t.event_id = ? AND t.payment_status IN ('completed', 'pending')
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        $notificationsSent = 0;
        $emailsSent = 0;

        while ($user = $result->fetch_assoc()) {
            // Create in-app notification
            $notificationTitle = "Event Update: " . $user['event_title'];
            $notificationResult = createNotification(
                $conn,
                $user['user_id'],
                $notificationTitle,
                $message,
                'event_update',
                $eventId
            );

            if ($notificationResult['success']) {
                $notificationsSent++;
            }

            // Send email notification
            $emailSubject = "Event Update - " . $user['event_title'];
            $emailBody = "
                <h2>Hello " . htmlspecialchars($user['first_name']) . "!</h2>
                <p>There's an important update regarding your registered event:</p>
                <h3>" . htmlspecialchars($user['event_title']) . "</h3>
                <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
                <p>Please log in to your account for more details.</p>
            ";

            $emailResult = sendEmail($user['email'], $emailSubject, $emailBody);
            if ($emailResult['success']) {
                $emailsSent++;
            }
        }

        return [
            'success' => true,
            'notifications_sent' => $notificationsSent,
            'emails_sent' => $emailsSent,
            'total_users' => $result->num_rows
        ];
    } catch (Exception $e) {
        error_log("Send event update notifications error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send notifications'
        ];
    }
}

/**
 * 🎯 Process event waitlist
 */
function processEventWaitlist($conn, $eventId)
{
    try {
        // Check if event has available spots
        $capacity = validateEventCapacity($conn, $eventId);

        if (!$capacity['valid'] || $capacity['available_spots'] <= 0) {
            return ['success' => false, 'message' => 'No available spots'];
        }

        // Get waitlisted users
        $stmt = $conn->prepare("
            SELECT w.*, u.first_name, u.last_name, u.email, e.title, e.price
            FROM event_waitlist w
            JOIN users u ON w.user_id = u.user_id
            JOIN events e ON w.event_id = e.event_id
            WHERE w.event_id = ? AND w.status = 'waiting'
            ORDER BY w.created_at ASC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $eventId, $capacity['available_spots']);
        $stmt->execute();
        $result = $stmt->get_result();

        $processedCount = 0;
        while ($waitlistEntry = $result->fetch_assoc()) {
            // Create ticket for waitlisted user
            $ticketNumber = generateTicketNumber($eventId, $waitlistEntry['user_id']);

            $insertStmt = $conn->prepare("
                INSERT INTO tickets (user_id, event_id, ticket_number, price, payment_status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $insertStmt->bind_param(
                "iiss",
                $waitlistEntry['user_id'],
                $eventId,
                $ticketNumber,
                $waitlistEntry['price']
            );

            if ($insertStmt->execute()) {
                // Update waitlist status
                $updateStmt = $conn->prepare("
                    UPDATE event_waitlist 
                    SET status = 'converted', converted_at = NOW() 
                    WHERE waitlist_id = ?
                ");
                $updateStmt->bind_param("i", $waitlistEntry['waitlist_id']);
                $updateStmt->execute();

                // Send notification
                $message = "Great news! A spot has opened up for '{$waitlistEntry['title']}'. Your registration is now pending payment.";
                createNotification(
                    $conn,
                    $waitlistEntry['user_id'],
                    "Waitlist Update - Spot Available!",
                    $message,
                    'waitlist_converted',
                    $eventId
                );

                // Send email
                $emailSubject = "Spot Available - " . $waitlistEntry['title'];
                $emailBody = "
                    <h2>Hello " . htmlspecialchars($waitlistEntry['first_name']) . "!</h2>
                    <p>Exciting news! A spot has become available for:</p>
                    <h3>" . htmlspecialchars($waitlistEntry['title']) . "</h3>
                    <p>Your ticket number is: <strong>" . $ticketNumber . "</strong></p>
                    <p>Please complete your payment to secure your spot.</p>
                    <p><a href='" . APP_URL . "/tickets/view.php'>View My Tickets</a></p>
                ";
                sendEmail($waitlistEntry['email'], $emailSubject, $emailBody);

                $processedCount++;
            }
        }

        return [
            'success' => true,
            'processed' => $processedCount,
            'message' => "Processed $processedCount waitlist entries"
        ];
    } catch (Exception $e) {
        error_log("Process event waitlist error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to process waitlist'
        ];
    }
}

/**
 * 🎨 Generate event attendance certificate
 */
function generateAttendanceCertificate($conn, $ticketId)
{
    try {
        // Get ticket and event details
        $stmt = $conn->prepare("
            SELECT t.*, e.title, e.start_datetime, e.end_datetime, e.venue,
                   u.first_name, u.last_name, u.email,
                   org.first_name as org_first_name, org.last_name as org_last_name
            FROM tickets t
            JOIN events e ON t.event_id = e.event_id
            JOIN users u ON t.user_id = u.user_id
            LEFT JOIN users org ON e.organizer_id = org.user_id
            WHERE t.ticket_id = ? AND t.is_used = 1
        ");
        $stmt->bind_param("i", $ticketId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if (!$data) {
            return ['success' => false, 'message' => 'Ticket not found or event not attended'];
        }

        $certificate = [
            'certificate_id' => 'CERT-' . $data['event_id'] . '-' . $data['user_id'] . '-' . time(),
            'participant_name' => $data['first_name'] . ' ' . $data['last_name'],
            'event_title' => $data['title'],
            'event_date' => formatDate($data['start_datetime']),
            'event_duration' => formatEventDuration($data['start_datetime'], $data['end_datetime']),
            'venue' => $data['venue'],
            'organizer' => ($data['org_first_name'] ? $data['org_first_name'] . ' ' . $data['org_last_name'] : 'Event Organizer'),
            'issue_date' => date('F j, Y'),
            'verification_code' => hash('sha256', $ticketId . $data['event_id'] . date('Y-m-d')),
            'qr_code' => generateQRCode(json_encode([
                'type' => 'certificate',
                'ticket_id' => $ticketId,
                'event_id' => $data['event_id'],
                'user_id' => $data['user_id'],
                'verification' => hash('sha256', $ticketId . $data['event_id'])
            ]))
        ];

        // Store certificate record
        $insertStmt = $conn->prepare("
            INSERT INTO event_certificates (ticket_id, certificate_id, certificate_data, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            certificate_data = VALUES(certificate_data),
            updated_at = NOW()
        ");
        $certificateJson = json_encode($certificate);
        $insertStmt->bind_param("iss", $ticketId, $certificate['certificate_id'], $certificateJson);
        $insertStmt->execute();

        return [
            'success' => true,
            'certificate' => $certificate,
            'message' => 'Certificate generated successfully'
        ];
    } catch (Exception $e) {
        error_log("Generate attendance certificate error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to generate certificate'];
    }
}

/**
 * 🔍 Verify certificate authenticity
 */
function verifyCertificate($conn, $certificateId, $verificationCode)
{
    try {
        $stmt = $conn->prepare("
            SELECT ec.*, t.user_id, e.title, u.first_name, u.last_name
            FROM event_certificates ec
            JOIN tickets t ON ec.ticket_id = t.ticket_id
            JOIN events e ON t.event_id = e.event_id
            JOIN users u ON t.user_id = u.user_id
            WHERE ec.certificate_id = ?
        ");
        $stmt->bind_param("s", $certificateId);
        $stmt->execute();
        $result = $stmt->get_result();
        $certificate = $result->fetch_assoc();

        if (!$certificate) {
            return [
                'valid' => false,
                'message' => 'Certificate not found'
            ];
        }

        $certificateData = json_decode($certificate['certificate_data'], true);

        if ($certificateData['verification_code'] === $verificationCode) {
            return [
                'valid' => true,
                'certificate' => $certificateData,
                'participant' => $certificate['first_name'] . ' ' . $certificate['last_name'],
                'event_title' => $certificate['title'],
                'issued_date' => $certificate['created_at']
            ];
        } else {
            return [
                'valid' => false,
                'message' => 'Invalid verification code'
            ];
        }
    } catch (Exception $e) {
        error_log("Verify certificate error: " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'Verification failed'
        ];
    }
}

/**
 * 🎯 Get event ROI analysis
 */
function getEventROIAnalysis($conn, $eventId)
{
    try {
        $analysis = [];

        // Get event costs and revenue
        $stmt = $conn->prepare("
            SELECT 
                e.budget,
                e.price as ticket_price,
                COUNT(t.ticket_id) as total_tickets,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as paid_tickets,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
                e.max_attendees
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            WHERE e.event_id = ?
            GROUP BY e.event_id
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if (!$data) {
            return null;
        }

        $analysis['revenue'] = [
            'total_revenue' => (float)$data['total_revenue'],
            'ticket_price' => (float)$data['ticket_price'],
            'tickets_sold' => (int)$data['paid_tickets'],
            'total_registrations' => (int)$data['total_tickets']
        ];

        $analysis['costs'] = [
            'budget' => (float)$data['budget'],
            'cost_per_attendee' => $data['paid_tickets'] > 0 ? $data['budget'] / $data['paid_tickets'] : 0
        ];

        $analysis['roi'] = [
            'gross_profit' => $analysis['revenue']['total_revenue'] - $analysis['costs']['budget'],
            'roi_percentage' => $analysis['costs']['budget'] > 0 ?
                (($analysis['revenue']['total_revenue'] - $analysis['costs']['budget']) / $analysis['costs']['budget']) * 100 : 0,
            'break_even_tickets' => $data['ticket_price'] > 0 ? ceil($data['budget'] / $data['ticket_price']) : 0
        ];

        $analysis['efficiency'] = [
            'capacity_utilization' => $data['max_attendees'] > 0 ?
                ($data['paid_tickets'] / $data['max_attendees']) * 100 : 0,
            'conversion_rate' => $data['total_tickets'] > 0 ?
                ($data['paid_tickets'] / $data['total_tickets']) * 100 : 0
        ];

        return $analysis;
    } catch (Exception $e) {
        error_log("Event ROI analysis error: " . $e->getMessage());
        return null;
    }
}

/**
 * 🎨 Generate comprehensive event report
 */
function generateComprehensiveEventReport($conn, $eventId)
{
    try {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'event_id' => $eventId
        ];

        // Basic event information
        $report['event_info'] = getEventDetails($conn, $eventId);
        if (!$report['event_info']) {
            return null;
        }

        // Statistics
        $report['statistics'] = getEventStats($conn, $eventId);

        // Analytics
        $report['analytics'] = getAdvancedEventAnalytics($conn, $eventId);

        // Marketing insights
        $report['marketing'] = getEventMarketingInsights($conn, $eventId);

        // ROI analysis
        $report['roi'] = getEventROIAnalysis($conn, $eventId);

        // Feedback summary
        $report['feedback'] = getEventFeedback($conn, $eventId);

        // Attendee list
        $report['attendees'] = getEventAttendees($conn, $eventId);

        // Performance score calculation
        $report['performance_score'] = calculateEventPerformanceScore($report);

        return $report;
    } catch (Exception $e) {
        error_log("Generate comprehensive event report error: " . $e->getMessage());
        return null;
    }
}

/**
 * 🎯 Calculate event performance score
 */
function calculateEventPerformanceScore($reportData)
{
    try {
        $score = 0;
        $maxScore = 100;

        // Attendance rate (30 points)
        $attendanceRate = $reportData['statistics']['attendance_rate'] ?? 0;
        $score += min(30, ($attendanceRate / 100) * 30);

        // Revenue achievement (25 points)
        if (isset($reportData['roi']['roi_percentage'])) {
            $roiScore = max(0, min(25, ($reportData['roi']['roi_percentage'] / 100) * 25));
            $score += $roiScore;
        }

        // Satisfaction rating (25 points)
        $satisfaction = $reportData['feedback']['average_rating'] ?? 0;
        $score += ($satisfaction / 5) * 25;

        // Capacity utilization (20 points)
        $capacityUtil = $reportData['analytics']['engagement']['capacity_utilization'] ?? 0;
        $score += min(20, ($capacityUtil / 100) * 20);

        return [
            'total_score' => round($score, 1),
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100, 1),
            'grade' => getPerformanceGrade($score)
        ];
    } catch (Exception $e) {
        error_log("Calculate performance score error: " . $e->getMessage());
        return [
            'total_score' => 0,
            'max_score' => 100,
            'percentage' => 0,
            'grade' => 'F'
        ];
    }
}

/**
 * 🎨 Get performance grade
 */
function getPerformanceGrade($score)
{
    if ($score >= 90) return 'A+';
    if ($score >= 85) return 'A';
    if ($score >= 80) return 'A-';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'B-';
    if ($score >= 60) return 'C+';
    if ($score >= 55) return 'C';
    if ($score >= 50) return 'C-';
    if ($score >= 45) return 'D+';
    if ($score >= 40) return 'D';
    return 'F';
}

/**
 * 🔧 Get event details helper
 */
function getEventDetails($conn, $eventId)
{
    try {
        $stmt = $conn->prepare("
            SELECT e.*, u.first_name, u.last_name, u.email as organizer_email
            FROM events e
            LEFT JOIN users u ON e.organizer_id = u.user_id
            WHERE e.event_id = ?
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Get event details error: " . $e->getMessage());
        return null;
    }
}

/**
 * 🎯 Final utility functions
 */
function formatBytes($size, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }

    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * 🔐 Generate API key
 */
function generateAPIKey($length = 32)
{
    return 'ems_' . bin2hex(random_bytes($length));
}

/**
 * 🎨 Format duration in human readable format
 */
function formatDuration($seconds)
{
    $units = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];

    foreach ($units as $unit => $value) {
        if ($seconds >= $value) {
            $count = floor($seconds / $value);
            return $count . ' ' . $unit . ($count > 1 ? 's' : '');
        }
    }

    return '0 seconds';
}

/**
 * 🔔 Clean up expired sessions
 */
function cleanupExpiredSessions($conn)
{
    try {
        // Clean expired remember me tokens
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        $cleanedTokens = $stmt->affected_rows;

        // Clean expired password reset tokens
        $stmt = $conn->prepare("UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE reset_token_expires < NOW()");
        $stmt->execute();
        $cleanedResets = $stmt->affected_rows;

        return [
            'success' => true,
            'cleaned_tokens' => $cleanedTokens,
            'cleaned_resets' => $cleanedResets
        ];
    } catch (Exception $e) {
        error_log("Cleanup expired sessions error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Cleanup failed'];
    }
}

/**
 * 🎯 Get system status summary
 */
function getSystemStatusSummary($conn)
{
    try {
        $status = [
            'overall' => 'healthy',
            'database' => 'connected',
            'storage' => 'ok',
            'memory' => 'ok',
            'last_check' => date('Y-m-d H:i:s')
        ];

        // Quick database check
        try {
            $conn->query("SELECT 1");
        } catch (Exception $e) {
            $status['database'] = 'error';
            $status['overall'] = 'unhealthy';
        }

        // Quick storage check
        $freeSpace = disk_free_space('.');
        $totalSpace = disk_total_space('.');
        $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

        if ($usedPercentage > 90) {
            $status['storage'] = 'critical';
            $status['overall'] = 'warning';
        } elseif ($usedPercentage > 80) {
            $status['storage'] = 'warning';
        }

        // Quick memory check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = convertToBytes($memoryLimit);
        $memoryPercentage = ($memoryUsage / $memoryLimitBytes) * 100;

        if ($memoryPercentage > 90) {
            $status['memory'] = 'critical';
            $status['overall'] = 'warning';
        } elseif ($memoryPercentage > 80) {
            $status['memory'] = 'warning';
        }

        return $status;
    } catch (Exception $e) {
        return [
            'overall' => 'error',
            'message' => 'Status check failed',
            'last_check' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * 🎨 Generate system info array
 */
function getSystemInfo()
{
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'timezone' => date_default_timezone_get(),
        'current_time' => date('Y-m-d H:i:s'),
        'disk_free_space' => formatBytes(disk_free_space('.')),
        'disk_total_space' => formatBytes(disk_total_space('.')),
        'memory_usage' => formatBytes(memory_get_usage(true)),
        'peak_memory' => formatBytes(memory_get_peak_usage(true))
    ];
}



// 🎯 End of functions.php - EMS Helper Functions
// Total functions: 100+ comprehensive helper functions for the EMS system
// Last updated: <?php echo date('Y-m-d H:i:s'); 
?>

?>