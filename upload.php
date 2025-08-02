<?php
header('Content-Type: application/json');

$jsonFile = 'cards.json';
$uploadDir = 'uploads/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
$debugLog = 'debug.log';

// Function to log debug messages
function logDebug($message) {
    global $debugLog;
    $timestamp = date('c');
    file_put_contents($debugLog, "[$timestamp] $message\n", FILE_APPEND);
}

// Create uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        logDebug("Failed to create uploads directory: $uploadDir");
        echo json_encode(['success' => false, 'error' => 'Failed to create uploads directory']);
        exit;
    }
    logDebug("Created uploads directory: $uploadDir");
}

// Check if uploads directory is writable
if (!is_writable($uploadDir)) {
    logDebug("Uploads directory is not writable: $uploadDir");
    echo json_encode(['success' => false, 'error' => 'Uploads directory is not writable']);
    exit;
}

// Check if cards.json is writable
if (file_exists($jsonFile) && !is_writable($jsonFile)) {
    logDebug("cards.json is not writable: $jsonFile");
    echo json_encode(['success' => false, 'error' => 'cards.json is not writable']);
    exit;
}

function generateUniqueId() {
    return uniqid('card-');
}

function validateImage($file) {
    global $allowedTypes;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMessage = isset($errors[$file['error']]) ? $errors[$file['error']] : 'Unknown upload error';
        logDebug("Image validation failed: $errorMessage");
        return ['success' => false, 'error' => $errorMessage];
    }
    if ($file['size'] > 5000000) { // 5MB limit
        logDebug("File size exceeds 5MB: " . $file['size']);
        return ['success' => false, 'error' => 'File size exceeds 5MB limit'];
    }
    if (!in_array($file['type'], $allowedTypes)) {
        logDebug("Invalid file type: " . $file['type']);
        return ['success' => false, 'error' => 'Only JPG, PNG, and JPEG files are allowed'];
    }
    return ['success' => true];
}

function handleImageUpload($fileInput, $urlInput, $prefix) {
    global $uploadDir;
    if (!empty($_FILES[$fileInput]['name']) && $_FILES[$fileInput]['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = validateImage($_FILES[$fileInput]);
        if (!$validation['success']) {
            logDebug("Image upload validation failed for $fileInput: " . $validation['error']);
            return $validation;
        }
        $ext = pathinfo($_FILES[$fileInput]['name'], PATHINFO_EXTENSION);
        $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $destination)) {
            logDebug("Successfully uploaded $fileInput to $destination");
            return ['success' => true, 'path' => $destination];
        } else {
            logDebug("Failed to move uploaded file for $fileInput to $destination");
            return ['success' => false, 'error' => "Failed to move uploaded file for $fileInput"];
        }
    } elseif (!empty($urlInput)) {
        // Validate URL (basic check)
        if (filter_var($urlInput, FILTER_VALIDATE_URL)) {
            logDebug("Using URL for $fileInput: $urlInput");
            return ['success' => true, 'path' => $urlInput];
        } else {
            logDebug("Invalid URL for $fileInput: $urlInput");
            return ['success' => false, 'error' => "Invalid URL for $fileInput"];
        }
    }
    logDebug("No $fileInput provided or file upload skipped");
    return ['success' => false, 'error' => "No $fileInput provided"];
}

$response = ['success' => false, 'error' => 'Invalid action'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    logDebug("Received action: $action");

    // Initialize cards.json if it doesn't exist
    if (!file_exists($jsonFile)) {
        file_put_contents($jsonFile, json_encode([]));
        logDebug("Initialized empty cards.json");
    }

    $cards = json_decode(file_get_contents($jsonFile), true);
    if ($cards === null) {
        logDebug("Failed to parse cards.json");
        echo json_encode(['success' => false, 'error' => 'Failed to parse cards.json']);
        exit;
    }

    if ($action === 'add' || $action === 'edit') {
        $cardId = $action === 'edit' ? $_POST['card_id'] : generateUniqueId();
        $cardName = isset($_POST['card_name']) ? htmlspecialchars($_POST['card_name']) : '';
        $description = isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '';
        $backDetails = isset($_POST['back_details']) ? htmlspecialchars($_POST['back_details']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $uploadTime = date('c');

        if (empty($cardName) || empty($description) || empty($backDetails) || $price <= 0) {
            logDebug("Missing or invalid form fields: name=$cardName, description=$description, backDetails=$backDetails, price=$price");
            echo json_encode(['success' => false, 'error' => 'All fields are required and price must be greater than 0']);
            exit;
        }

        // Handle front image
        $frontImageResult = handleImageUpload('image_file', $_POST['image_url'] ?? '', 'front');
        if (!$frontImageResult['success'] && empty($_POST['image_url'])) {
            echo json_encode($frontImageResult);
            exit;
        }

        // Handle back image
        $backImageResult = handleImageUpload('back_image_file', $_POST['back_image_url'] ?? '', 'back');
        if (!$backImageResult['success'] && empty($_POST['back_image_url'])) {
            echo json_encode($backImageResult);
            exit;
        }

        $card = [
            'id' => $cardId,
            'name' => $cardName,
            'image' => $frontImageResult['path'],
            'backImage' => $backImageResult['path'],
            'description' => $description,
            'backDetails' => $backDetails,
            'price' => $price,
            'uploadTime' => $uploadTime
        ];

        if ($action === 'edit') {
            $found = false;
            foreach ($cards as $index => $existingCard) {
                if ($existingCard['id'] === $cardId) {
                    $cards[$index] = $card;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                logDebug("Card not found for edit: $cardId");
                echo json_encode(['success' => false, 'error' => 'Card not found']);
                exit;
            }
        } else {
            $cards[] = $card;
        }

        if (file_put_contents($jsonFile, json_encode($cards, JSON_PRETTY_PRINT)) === false) {
            logDebug("Failed to write to cards.json");
            echo json_encode(['success' => false, 'error' => 'Failed to write to cards.json']);
            exit;
        }
        logDebug("Successfully saved card: $cardId");
        $response = ['success' => true];
    } elseif ($action === 'delete') {
        $cardId = isset($_POST['card_id']) ? $_POST['card_id'] : '';
        if (empty($cardId)) {
            logDebug("No card_id provided for delete");
            echo json_encode(['success' => false, 'error' => 'No card ID provided']);
            exit;
        }
        $cards = array_filter($cards, function($card) use ($cardId) {
            return $card['id'] !== $cardId;
        });
        if (file_put_contents($jsonFile, json_encode(array_values($cards), JSON_PRETTY_PRINT)) === false) {
            logDebug("Failed to write to cards.json during delete");
            echo json_encode(['success' => false, 'error' => 'Failed to write to cards.json']);
            exit;
        }
        logDebug("Successfully deleted card: $cardId");
        $response = ['success' => true];
    } else {
        logDebug("Invalid action received: $action");
    }
}

echo json_encode($response);
?>