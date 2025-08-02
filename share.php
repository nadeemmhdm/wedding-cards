<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for testing
error_log("Share.php accessed at " . date('Y-m-d H:i:s')); // Log access for debugging

function getCardData($cardId) {
    $cardsFile = 'cards.json';
    if (!file_exists($cardsFile)) {
        error_log("cards.json not found at $cardsFile");
        return null;
    }

    $cards = json_decode(file_get_contents($cardsFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decoding cards.json: " . json_last_error_msg());
        return null;
    }

    foreach ($cards as $card) {
        if ($card['id'] === $cardId) {
            return $card;
        }
    }
    error_log("Card ID $cardId not found in cards.json");
    return null;
}

if (isset($_GET['id'])) {
    $cardId = htmlspecialchars($_GET['id']);
    error_log("Processing card ID: $cardId");
    $card = getCardData($cardId);

    if ($card) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $shareLink = $baseUrl . '/share.php?view=' . urlencode($cardId);
        $images = is_array($card['images']) ? $card['images'] : [$card['images']];
        $firstImage = $baseUrl . '/' . $images[0];

        $response = [
            'success' => true,
            'shareLink' => $shareLink,
            'card' => [
                'name' => $card['name'],
                'description' => $card['description'],
                'backDetails' => $card['backDetails'],
                'price' => $card['price'],
                'images' => array_map(function($img) use ($baseUrl) {
                    return $baseUrl . '/' . $img;
                }, $images),
                'firstImage' => $firstImage
            ]
        ];
        error_log("Share link generated: $shareLink");
    } else {
        $response = ['success' => false, 'error' => 'Card not found'];
        error_log("Failed to find card ID: $cardId");
    }
} else {
    $response = ['success' => false, 'error' => 'No card ID provided'];
    error_log("No card ID provided in request");
}

echo json_encode($response);
?>

<?php
if (isset($_GET['view'])) {
    $cardId = htmlspecialchars($_GET['view']);
    $card = getCardData($cardId);

    if ($card) {
        $images = is_array($card['images']) ? $card['images'] : [$card['images']];
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($card['name']); ?> - Shared Card</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4 max-w-2xl">
        <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($card['name']); ?></h1>
        <div class="carousel-container mb-4">
            <?php foreach ($images as $index => $img): ?>
                <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                    <img src="<?php echo $baseUrl . '/' . $img; ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="w-full h-64 object-contain rounded-lg">
                </div>
            <?php endforeach; ?>
            <?php if (count($images) > 1): ?>
                <div class="carousel-nav">
                    <button id="prev-share-btn" class="carousel-prev"><i class="fas fa-chevron-left"></i></button>
                    <button id="next-share-btn" class="carousel-next"><i class="fas fa-chevron-right"></i></button>
                </div>
            <?php endif; ?>
        </div>
        <p class="text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($card['description'])); ?></p>
        <div class="bg-gray-50 p-4 rounded-lg mb-4">
            <h2 class="text-lg font-semibold mb-2">Details</h2>
            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($card['backDetails'])); ?></p>
            <img src="<?php echo $baseUrl . '/' . $images[0]; ?>" alt="Card Image" class="w-full h-32 object-contain mt-2 rounded-lg">
        </div>
        <p class="text-lg font-semibold text-purple-600">Price: ₹<?php echo htmlspecialchars($card['price']); ?></p>
        <p class="text-sm text-gray-500 mt-2">Shared on: <?php echo date('Y-m-d H:i:s T'); ?></p>
    </div>

    <script>
        let currentShareIndex = 0;
        const shareSlides = document.querySelectorAll('.carousel-slide');
        const prevShareBtn = document.getElementById('prev-share-btn');
        const nextShareBtn = document.getElementById('next-share-btn');

        function updateShareCarousel() {
            shareSlides.forEach((slide, index) => {
                slide.classList.toggle('active', index === currentShareIndex);
            });
            prevShareBtn.disabled = currentShareIndex === 0;
            nextShareBtn.disabled = currentShareIndex === shareSlides.length - 1;
        }

        prevShareBtn?.addEventListener('click', () => {
            if (currentShareIndex > 0) {
                currentShareIndex--;
                updateShareCarousel();
            }
        });

        nextShareBtn?.addEventListener('click', () => {
            if (currentShareIndex < shareSlides.length - 1) {
                currentShareIndex++;
                updateShareCarousel();
            }
        });

        updateShareCarousel();
    </script>
</body>
</html>
<?php
        exit;
    } else {
        echo "Card not found.";
        exit;
    }
}
?>
