<UPDATED_CODE><?php
/**
 * 🛠️ Essential Helper Functions - EMS
 * Ekwendeni Mighty Campus Event Management System
 */

/**
 * 🧹 Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * 📧 Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 📱 Validate phone number
 */
function isValidPhone($phone) {
    return preg_match('/^[+]?[0-9\s\-\(\)]{10,}$/', $phone);
}

/**
 * 🔐 Generate secure token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * 📅 Format date for display
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * 📅 Format datetime for display
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    return date($format, strtotime($datetime));
}

/**
 * ⏰ Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

/**
 * 💰 Format currency
 */
function formatCurrency($amount, $currency = 'MWK') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * 📊 Generate random color
 */
function generateRandomColor() {
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
    ];
    return $colors[array_rand($colors)];
}

/**
 * 🖼️ Handle file upload
 */
function handleFileUpload($file, $uploadDir = 'uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
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
function sendEmail($to, $subject, $message, $isHTML = true) {
    // This is a placeholder for email functionality
    // You can integrate with PHPMailer, SendGrid, or other email services
    
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
function createNotification($conn, $userId, $title, $message, $type = 'system', $relatedId = null) {
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
function generateQRCode($data, $size = 200) {
    // This is a placeholder for QR code generation
    // You can integrate with libraries like phpqrcode or use online services
    
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
    return $qrUrl;
}

/**
 * 🎫 Generate ticket number
 */
function generateTicketNumber($eventId, $userId) {
    return 'EMT-' . str_pad($eventId, 4, '0', STR_PAD_LEFT) . '-' . str_pad($userId, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -4));
}

/**
 * 🔍 Search and highlight text
 */
function highlightSearchTerm($text, $searchTerm) {
    if (empty($searchTerm)) return $text;
    
    return preg_replace('/(' . preg_quote($searchTerm, '/') . ')/i', '<mark>$1</mark>', $text);
}

/**
 * 📊 Calculate event statistics
 */
function getEventStats($conn, $eventId) {
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
function getStatusBadge($status, $type = 'event') {
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
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 🛡️ Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 📝 Log activity
 */
function logActivity($conn, $userId, $action, $details = null, $ipAddress = null) {
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
function getUserAvatar($user, $size = 50) {
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
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

/**
 * 🎯 Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * 🔄 Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * 💬 Get flash message
 */
function getFlashMessage() {
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
function displayFlashMessage() {
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
function paginate($conn, $sql, $params = [], $types = '', $page = 1, $perPage = 10) {
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
function generatePaginationHTML($pagination, $baseUrl = '') {
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
function encryptData($data, $key = null) {
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
function decryptData($data, $key = null) {
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
function getClientIP() {
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
function getEventChartData($conn, $userId = null, $days = 30) {
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
        return $data;
        
    } catch (Exception $e) {
        error_log("Chart data error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate event category colors
 */
function getCategoryColor($category) {
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
function getEventTypeIcon($type) {
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
function sendSMS($phone, $message) {
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
function searchEvents($conn, $query, $filters = []) {
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
        error_log("Search error: " . $e->getMessage());
        return [];
    }
}

/**
 * 📊 Get dashboard statistics
 */
function getDashboardStats($conn, $userId = null, $role = 'user') {
    try {
        $stats = [];
        
        if ($role === 'admin') {
            // Admin stats
            $stats['total_users'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM users");
            $stats['total_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events");
            $stats['pending_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE status = 'pending'");
            $stats['total_tickets'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets");
            $stats['total_revenue'] = getSingleStat($conn, "SELECT SUM(price) as count FROM tickets WHERE payment_status = 'completed'") ?? 0;
            
        } elseif ($role === 'organizer') {
            // Organizer stats
            $stats['my_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE organizer_id = ?", [$userId], 'i');
            $stats['approved_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE organizer_id = ? AND status = 'approved'", [$userId], 'i');
            $stats['pending_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE organizer_id = ? AND status = 'pending'", [$userId], 'i');
            $stats['total_attendees'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets t JOIN events e ON t.event_id = e.event_id WHERE e.organizer_id = ?", [$userId], 'i');
            $stats['revenue'] = getSingleStat($conn, "SELECT SUM(t.price) as count FROM tickets t JOIN events e ON t.event_id = e.event_id WHERE e.organizer_id = ? AND t.payment_status = 'completed'", [$userId], 'i') ?? 0;
            
        } else {
            // User stats
            $stats['registered_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE user_id = ?", [$userId], 'i');
            $stats['attended_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets WHERE user_id = ? AND is_used = 1", [$userId], 'i');
            $stats['upcoming_events'] = getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets t JOIN events e ON t.event_id = e.event_id WHERE t.user_id = ? AND e.start_datetime > NOW()", [$userId], 'i');
            $stats['total_spent'] = getSingleStat($conn, "SELECT SUM(price) as count FROM tickets WHERE user_id = ? AND payment_status = 'completed'", [$userId], 'i') ?? 0;
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🔢 Helper function to get single statistic
 */
function getSingleStat($conn, $sql, $params = [], $types = '') {
    try {
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Single stat error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 🎨 Generate breadcrumb navigation
 */
function generateBreadcrumb($items) {
    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';
    
    $count = count($items);
    foreach ($items as $index => $item) {
        if ($index === $count - 1) {
            // Last item (current page)
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            $html .= '<i class="' . ($item['icon'] ?? 'fas fa-circle') . '"></i> ';
            $html .= htmlspecialchars($item['title']);
            $html .= '</li>';
        } else {
            // Clickable items
            $html .= '<li class="breadcrumb-item">';
            if (!empty($item['url'])) {
                $html .= '<a href="' . htmlspecialchars($item['url']) . '">';
                $html .= '<i class="' . ($item['icon'] ?? 'fas fa-circle') . '"></i> ';
                $html .= htmlspecialchars($item['title']);
                $html .= '</a>';
            } else {
                $html .= '<i class="' . ($item['icon'] ?? 'fas fa-circle') . '"></i> ';
                $html .= htmlspecialchars($item['title']);
            }
            $html .= '</li>';
        }
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * 🔔 Get user notifications
 */
function getUserNotifications($conn, $userId, $limit = 10, $unreadOnly = false) {
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        $types = 'i';
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
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
        error_log("Notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * ✅ Mark notification as read
 */
function markNotificationRead($conn, $notificationId, $userId) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notificationId, $userId);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

/**
 * 🎯 Get upcoming events
 */
function getUpcomingEvents($conn, $limit = 5, $userId = null) {
    try {
        $sql = "SELECT e.*, u.first_name, u.last_name 
                FROM events e 
                LEFT JOIN users u ON e.organizer_id = u.user_id 
                WHERE e.status = 'approved' AND e.start_datetime > NOW()";
        
        $params = [];
        $types = '';
        
        if ($userId) {
            // Get events user is registered for
            $sql = "SELECT e.*, u.first_name, u.last_name, t.ticket_id 
                    FROM events e 
                    LEFT JOIN users u ON e.organizer_id = u.user_id 
                    JOIN tickets t ON e.event_id = t.event_id 
                    WHERE e.status = 'approved' AND e.start_datetime > NOW() AND t.user_id = ?";
            $params[] = $userId;
            $types = 'i';
        }
        
        $sql .= " ORDER BY e.start_datetime ASC LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Upcoming events error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Format event card HTML
 */
function formatEventCard($event, $showActions = true) {
    $startDate = formatDateTime($event['start_datetime'], 'M j, Y');
    $startTime = formatDateTime($event['start_datetime'], 'g:i A');
    $categoryColor = getCategoryColor($event['category']);
    $typeIcon = getEventTypeIcon($event['event_type']);
    
    $html = '<div class="event-card" data-event-id="' . $event['event_id'] . '">';
    $html .= '<div class="event-card-header">';
    
    // Event image or placeholder
    if (!empty($event['image'])) {
        $html .= '<img src="uploads/' . htmlspecialchars($event['image']) . '" alt="Event Image" class="event-image">';
    } else {
        $html .= '<div class="event-placeholder" style="background: linear-gradient(135deg, ' . $categoryColor . ', ' . adjustBrightness($categoryColor, -20) . ');">';
        $html .= '<i class="' . $typeIcon . '"></i>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '<div class="event-card-body">';
    $html .= '<h5 class="event-title">' . htmlspecialchars($event['title']) . '</h5>';
    $html .= '<p class="event-description">' . truncateText($event['description'], 100) . '</p>';
    
    $html .= '<div class="event-meta">';
    $html .= '<div class="event-date"><i class="fas fa-calendar"></i> ' . $startDate . '</div>';
    $html .= '<div class="event-time"><i class="fas fa-clock"></i> ' . $startTime . '</div>';
    $html .= '<div class="event-venue"><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($event['venue']) . '</div>';
    $html .= '</div>';
    
    if ($showActions) {
        $html .= '<div class="event-actions">';
        $html .= '<a href="events/view.php?id=' . $event['event_id'] . '" class="btn btn-primary btn-sm">';
        $html .= '<i class="fas fa-eye"></i> View Details';
        $html .= '</a>';
        
        if ($event['is_paid']) {
            $html .= '<span class="event-price">' . formatCurrency($event['price'] ?? 0) . '</span>';
        } else {
            $html .= '<span class="event-free">FREE</span>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div>'; // Close event-card-body
    $html .= '</div>'; // Close event-card
    
    return $html;
}

/**
 * 🎨 Adjust color brightness
 */
function adjustBrightness($hex, $steps) {
    // Steps should be between -255 and 255. Negative = darker, positive = lighter
    $steps = max(-255, min(255, $steps));

    // Normalize into a six character long hex string
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
    }

    // Split into three parts: R, G and B
    $color_parts = str_split($hex, 2);
    $return = '#';

    foreach ($color_parts as $color) {
        $color   = hexdec($color); // Convert to decimal
        $color   = max(0,min(255,$color + $steps)); // Adjust color
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
    }

    return $return;
}

/**
 * 📊 Get event capacity info
 */
function getEventCapacity($conn, $eventId) {
    try {
        // Get event max attendees
        $stmt = $conn->prepare("SELECT max_attendees FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        
        if (!$event || !$event['max_attendees']) {
            return ['unlimited' => true];
        }
        
        // Get current registrations
        $stmt = $conn->prepare("SELECT COUNT(*) as registered FROM tickets WHERE event_id = ? AND payment_status IN ('completed', 'pending')");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $registered = $result->fetch_assoc()['registered'];
        
        $maxAttendees = $event['max_attendees'];
        $available = $maxAttendees - $registered;
        $percentFull = round(($registered / $maxAttendees) * 100, 2);
        
        return [
            'unlimited' => false,
            'max_attendees' => $maxAttendees,
            'registered' => $registered,
            'available' => max(0, $available),
            'percent_full' => $percentFull,
            'is_full' => $available <= 0
        ];
        
    } catch (Exception $e) {
        error_log("Event capacity error: " . $e->getMessage());
        return ['unlimited' => true];
    }
}

/**
 * 🎯 Check if user is registered for event
 */
function isUserRegistered($conn, $userId, $eventId) {
    try {
        $stmt = $conn->prepare("SELECT ticket_id FROM tickets WHERE user_id = ? AND event_id = ?");
        $stmt->bind_param("ii", $userId, $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
        
    } catch (Exception $e) {
        error_log("Registration check error: " . $e->getMessage());
        return false;
    }
}

/**
 * 🎨 Generate event status color
 */
function getEventStatusColor($status) {
    $colors = [
        'draft' => '#6c757d',
        'pending' => '#ffc107',
        'approved' => '#28a745',
        'rejected' => '#dc3545',
        'cancelled' => '#343a40',
        'completed' => '#17a2b8'
    ];
    
    return $colors[$status] ?? '#6c757d';
}

/**
 * 📱 Format phone number for display
 */
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format for Malawi numbers
    if (strlen($phone) == 9 && substr($phone, 0, 1) == '0') {
        return '+265 ' . substr($phone, 1, 2) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6);
    } elseif (strlen($phone) == 8) {
        return '+265 ' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5);
    }
    
    return $phone;
}

/**
 * 🔍 Validate event data
 */
function validateEventData($data) {
    $errors = [];
    
    // Required fields
    $required = ['title', 'description', 'start_datetime', 'end_datetime', 'venue', 'category', 'event_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Date validation
    if (!empty($data['start_datetime']) && !empty($data['end_datetime'])) {
        $start = strtotime($data['start_datetime']);
        $end = strtotime($data['end_datetime']);
        
        if ($start >= $end) {
            $errors[] = 'End date must be after start date';
        }
        
        if ($start < time()) {
            $errors[] = 'Start date cannot be in the past';
        }
    }
    
    // Max attendees validation
    if (isset($data['max_attendees']) && !empty($data['max_attendees'])) {
        if (!is_numeric($data['max_attendees']) || $data['max_attendees'] < 1) {
            $errors[] = 'Max attendees must be a positive number';
        }
    }
    
    return $errors;
}

/**
 * 🎨 Generate QR code for ticket
 */
function generateTicketQR($ticketData) {
    $qrData = json_encode([
        'ticket_id' => $ticketData['ticket_id'],
        'event_id' => $ticketData['event_id'],
        'user_id' => $ticketData['user_id'],
        'verification_code' => hash('sha256', $ticketData['ticket_id'] . $ticketData['created_at'])
    ]);
    
    return generateQRCode($qrData, 150);
}

/**
 * 📊 Get popular events
 */
function getPopularEvents($conn, $limit = 5) {
    try {
        $sql = "SELECT e.*, u.first_name, u.last_name, COUNT(t.ticket_id) as registration_count
                FROM events e 
                LEFT JOIN users u ON e.organizer_id = u.user_id 
                LEFT JOIN tickets t ON e.event_id = t.event_id
                WHERE e.status = 'approved' AND e.start_datetime > NOW()
                GROUP BY e.event_id
                ORDER BY registration_count DESC, e.created_at DESC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Popular events error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎯 Get featured events
 */
function getFeaturedEvents($conn, $limit = 3) {
    try {
        $sql = "SELECT e.*, u.first_name, u.last_name
                FROM events e 
                LEFT JOIN users u ON e.organizer_id = u.user_id 
                WHERE e.status = 'approved' AND e.featured = 1 AND e.start_datetime > NOW()
                ORDER BY e.start_datetime ASC
                LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Featured events error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🔔 Send event reminder notifications
 */
function sendEventReminders($conn) {
    try {
        // Get events starting in 24 hours
        $sql = "SELECT e.*, t.user_id, u.email, u.first_name
                FROM events e
                JOIN tickets t ON e.event_id = t.event_id
                JOIN users u ON t.user_id = u.user_id
                WHERE e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                AND t.payment_status = 'completed'
                AND e.status = 'approved'";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $remindersSent = 0;
        while ($row = $result->fetch_assoc()) {
            // Create notification
            $title = "Event Reminder: " . $row['title'];
            $message = "Don't forget! Your event '" . $row['title'] . "' starts tomorrow at " . formatDateTime($row['start_datetime']);
            
            $notificationResult = createNotification($conn, $row['user_id'], $title, $message, 'event_reminder', $row['event_id']);
            
            if ($notificationResult['success']) {
                $remindersSent++;
            }
        }
        
        return $remindersSent;
        
    } catch (Exception $e) {
        error_log("Event reminders error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 🎨 Generate event calendar data
 */
function getEventCalendarData($conn, $month = null, $year = null) {
    try {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $sql = "SELECT event_id, title, start_datetime, end_datetime, category
                FROM events 
                WHERE status = 'approved' 
                AND MONTH(start_datetime) = ? 
                AND YEAR(start_datetime) = ?
                ORDER BY start_datetime";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => $row['event_id'],
                'title' => $row['title'],
                'start' => date('Y-m-d\TH:i:s', strtotime($row['start_datetime'])),
                'end' => date('Y-m-d\TH:i:s', strtotime($row['end_datetime'])),
                'backgroundColor' => getCategoryColor($row['category']),
                'borderColor' => getCategoryColor($row['category'])
            ];
        }
        
        return $events;
        
    } catch (Exception $e) {
        error_log("Calendar data error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎯 Clean old data
 */
function cleanOldData($conn) {
    try {
        $cleaned = 0;
        
        // Clean old notifications (older than 30 days)
        $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($stmt->execute()) {
            $cleaned += $stmt->affected_rows;
        }
        
        // Clean old verification tokens (older than 24 hours)
        $stmt = $conn->prepare("UPDATE users SET verification_token = NULL WHERE verification_token IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        if ($stmt->execute()) {
            $cleaned += $stmt->affected_rows;
        }
        
        return $cleaned;
        
    } catch (Exception $e) {
        error_log("Clean old data error: " . $e->getMessage());
        return 0;
    }
}

/**
 * 🔍 Search users
 */
function searchUsers($conn, $query, $role = null, $limit = 20) {
    try {
        $sql = "SELECT user_id, username, email, first_name, last_name, role, department, created_at
                FROM users 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)";
        
        $searchTerm = '%' . $query . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        $types = 'ssss';
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
            $types .= 's';
        }
        
        $sql .= " ORDER BY first_name, last_name LIMIT ?";
        $params[] = $limit;
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Search users error: " . $e->getMessage());
        return [];
    }
}

/**
 * 🎨 Generate user role badge
 */
function getUserRoleBadge($role) {
    $badges = [
        'admin' => '<span class="badge badge-danger"><i class="fas fa-crown"></i> Admin</span>',
        'organizer' => '<span class="badge badge-primary"><i class="fas fa-calendar-plus"></i> Organizer</span>',
        'user' => '<span class="badge badge-success"><i class="fas fa-user"></i> Student</span>',
        'guest' => '<span class="badge badge-secondary"><i class="fas fa-user-friends"></i> Guest</span>'
    ];
    
    return $badges[$role] ?? '<span class="badge badge-light">' . ucfirst($role) . '</span>';
}

/**
 * 🎯 Get event registration statistics
 */
function getEventRegistrationStats($conn, $eventId) {
    try {
        $stats = [];
        
        // Total registrations
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tickets WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total'] = $result->fetch_assoc()['total'];
        
        // By payment status
        $stmt = $conn->prepare("SELECT payment_status, COUNT(*) as count FROM tickets WHERE event_id =