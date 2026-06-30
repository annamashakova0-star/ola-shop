<?php
require_once 'config.php';

// ============================================================
// ФУНКЦИЯ ДЛЯ ПРАВИЛЬНОГО ПУТИ К ФОТО (СТАРЫЕ И НОВЫЕ)
// ============================================================
function getImagePath($path) {
    if (empty($path)) return '';

    // СТАРЫЙ ФОРМАТ: /bd\1(1).jpg
    if (strpos($path, '/bd\\') === 0) {
        $path = ltrim($path, '/');
        $path = str_replace('\\', '/', $path);
        return $path;
    }

    // НОВЫЙ ФОРМАТ: bd/31(1).jpg или 31(1).jpg
    if (strpos($path, 'bd/') === 0) {
        return $path;
    }

    if (strpos($path, '/bd/') === 0) {
        return ltrim($path, '/');
    }

    if (strpos($path, '/') === false && strpos($path, '\\') === false) {
        return 'bd/' . $path;
    }

    return $path;
}

// Проверяем наличие ID товара
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID товара не указан");
}

$product_id = (int)$_GET['id'];

// Получаем данные товара
$product_result = $mysqli->query("SELECT * FROM product WHERE id_product = $product_id");
if (!$product_result || $product_result->num_rows === 0) {
    die("Товар не найден");
}

$product = $product_result->fetch_assoc();

// Получаем доступные размеры для товара
$sizes_result = $mysqli->query("SELECT * FROM size WHERE id_product = $product_id ORDER BY
    CASE
        WHEN size = 'XS' THEN 1
        WHEN size = 'S' THEN 2
        WHEN size = 'M' THEN 3
        WHEN size = 'L' THEN 4
        WHEN size = 'XL' THEN 5
        ELSE 6
    END");
$sizes = [];
if ($sizes_result) {
    while($size = $sizes_result->fetch_assoc()) {
        $sizes[] = $size;
    }
}

// Изображения
$images = $mysqli->query("SELECT * FROM image WHERE id_product = $product_id");
$all_images = [];
if ($images) {
    while($img = $images->fetch_assoc()) {
        $all_images[] = $img;
    }
}

// Комментарии
$comments = $mysqli->query("
    SELECT c.*, u.name, u.surname FROM comments c
    JOIN users u ON c.id_user = u.id_user
    WHERE c.id_product = $product_id
    ORDER BY c.date DESC
");

// Добавление комментария
if (isset($_POST['comment']) && isset($_SESSION['user_id'])) {
    $comment = $mysqli->real_escape_string($_POST['comment']);
    $user_id = $_SESSION['user_id'];
    $mysqli->query("INSERT INTO comments (id_user, id_product, comment, date) VALUES ($user_id, $product_id, '$comment', NOW())");
    header("Location: product.php?id=$product_id");
    exit;
}

// Добавление в корзину с размером
if (isset($_POST['add_to_cart']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    if (empty($_POST['size_id'])) {
        $cart_error = "Пожалуйста, выберите размер";
    } else {
        $size_id = (int)$_POST['size_id'];
        $mysqli->query("INSERT INTO cart (id_user, id_product, id_size) VALUES ($user_id, $product_id, $size_id)");
        $cart_success = "Товар добавлен в корзину!";
    }
}

// Лайки
$like_count = 0;
$like_count_result = $mysqli->query("SELECT COUNT(*) as count FROM likes WHERE id_product = $product_id");
if ($like_count_result) {
    $like_count = $like_count_result->fetch_assoc()['count'];
}

$is_liked = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $like_check = $mysqli->query("SELECT * FROM likes WHERE id_product = $product_id AND id_user = $user_id");
    if ($like_check) {
        $is_liked = $like_check->num_rows > 0;
    }
}

// Обработка лайков
if (isset($_GET['toggle_like']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    if ($is_liked) {
        $mysqli->query("DELETE FROM likes WHERE id_product = $product_id AND id_user = $user_id");
    } else {
        $mysqli->query("INSERT INTO likes (id_user, id_product) VALUES ($user_id, $product_id)");
    }

    header("Location: product.php?id=$product_id");
    exit;
}

// Проверка прав администратора
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $admin_check = $mysqli->query("SELECT is_admin FROM users WHERE id_user = $user_id");
    if ($admin_check && $admin_check->num_rows > 0) {
        $user_data = $admin_check->fetch_assoc();
        if ($user_data['is_admin'] == 1) {
            $is_admin = true;
        }
    }
}

if (isset($_SESSION['user_id'])) {
    $product_id = $_GET['id'];
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }

    $_SESSION['recently_viewed'] = array_diff($_SESSION['recently_viewed'], [$product_id]);

    array_unshift($_SESSION['recently_viewed'], $product_id);

    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 10);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($product['name']) ?></title>
    <style>
        body {
            font-family: Arial;
            margin: 0;
            background: #f0e6ff;
        }
        .header {
            background: #8a2be2;
            color: white;
            padding: 15px 0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-photo {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }
        .nav-plus {
            font-size: 20px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            padding: 0 5px;
            transition: transform 0.2s;
        }
        .nav-plus:hover {
            transform: scale(1.2);
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #8a2be2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .product-detail {
            background: white;
            border-radius: 10px;
            padding: 30px;
            border: 2px solid #e6e6fa;
        }
        .product-header {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .product-images {
            flex: 1;
            min-width: 300px;
        }
        .product-info {
            flex: 1;
            min-width: 300px;
        }
        .main-image {
            width: 100%;
            max-width: 400px;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e6e6fa;
            margin-bottom: 10px;
        }
        .thumbnails {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #e6e6fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .thumbnail:hover {
            border-color: #9370db;
            transform: scale(1.05);
        }
        .thumbnail.active {
            border-color: #8a2be2;
            border-width: 3px;
        }
        .product-title {
            font-size: 28px;
            margin-bottom: 15px;
            color: #4b0082;
        }
        .product-price {
            font-size: 24px;
            color: #8a2be2;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .product-specs {
            margin: 20px 0;
        }
        .spec-item {
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .spec-label {
            font-weight: bold;
            color: #4b0082;
        }
        .spec-value {
            color: #666;
        }
        .btn {
            padding: 10px 20px;
            background: #9370db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-right: 10px;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #8a2be2;
        }
        .btn-success {
            background: #8a2be2;
        }
        .btn-success:hover {
            background: #7b1fa2;
        }
        .size-selector {
            margin: 20px 0;
        }
        .size-label {
            font-weight: bold;
            color: #4b0082;
            margin-bottom: 10px;
            display: block;
        }
        .size-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .size-option {
            padding: 8px 15px;
            border: 2px solid #e6e6fa;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        .size-option:hover {
            border-color: #9370db;
        }
        .size-option.selected {
            background: #8a2be2;
            color: white;
            border-color: #8a2be2;
        }
        .size-option input {
            display: none;
        }
        .cart-form {
            margin: 20px 0;
        }
        .comment-form {
            background: #f8f6ff;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #e6e6fa;
        }
        .comment-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d8bfd8;
            border-radius: 5px;
            margin-bottom: 10px;
            resize: vertical;
            background: white;
            font-family: Arial;
        }
        .comment {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e6e6fa;
        }
        .like-btn {
            color: <?= $is_liked ? '#ff69b4' : '#d8bfd8' ?>;
            text-decoration: none;
            font-size: 24px;
            margin-left: 15px;
            vertical-align: middle;
            transition: color 0.3s;
            display: inline-block;
        }
        .like-btn:hover {
            color: #ff69b4;
            transform: scale(1.1);
        }
        .like-container {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        .like-count {
            color: #666;
            font-size: 16px;
            font-weight: bold;
        }
        h3 {
            color: #4b0082;
            border-bottom: 2px solid #e6e6fa;
            padding-bottom: 10px;
        }
        .no-images {
            width: 100%;
            max-width: 400px;
            height: 400px;
            background: #f8f6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #999;
            border: 2px solid #e6e6fa;
            font-size: 18px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <!-- Шапка -->
    <div class="header">
        <div class="container">
            <div class="nav">
                <div class="logo">ОЛА</div>
                <div class="nav-links">
                    <a href="index.php">Главная</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="favorites.php">Избранное</a>
                        <a href="cart.php">Корзина</a>
                        <?php if ($is_admin): ?>
                            <a href="add_product.php" class="nav-plus" title="Добавить товар">➕</a>
                        <?php endif; ?>
                        <div class="user-info">
                            <?php if (!empty($_SESSION['user_photo'])): ?>
                                <img src="uploads/<?= $_SESSION['user_photo'] ?>" class="user-photo" alt="Фото пользователя" title="Вы вошли как: <?= $_SESSION['user_name'] ?>">
                            <?php endif; ?>
                            <a href="?logout=1">Выйти (<?= $_SESSION['user_name'] ?>)</a>
                        </div>
                    <?php else: ?>
                        <a href="login.php">Войти</a>
                        <a href="register.php">Регистрация</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">← Назад к товарам</a>

        <div class="product-detail">
            <div class="product-header">
                <div class="product-images">
                    <?php if (!empty($all_images)): ?>
                        <!-- Главное изображение -->
                        <img src="<?= getImagePath($all_images[0]['image']) ?>"
                             class="main-image im"
                             alt="<?= htmlspecialchars($product['name']) ?>">

                        <!-- Миниатюры -->
                        <?php if (count($all_images) > 1): ?>
                        <div class="thumbnails">
                            <?php foreach ($all_images as $image): ?>
                                <img src="<?= getImagePath($image['image']) ?>"
                                     class="thumbnail"
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     onclick="f(this)">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-images">
                            Нет изображения
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-info">
                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                    <div class="product-price"><?= number_format($product['price'], 0, '', ' ') ?> ₽</div>

                    <div class="product-specs">
                        <?php if (!empty($product['brand'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Бренд:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['brand']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['country'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Страна производитель:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['country']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['colour'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Цвет:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['colour']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($product['structure'])): ?>
                        <div class="spec-item">
                            <span class="spec-label">Состав:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['structure']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <p style="line-height: 1.6; margin: 20px 0; color: #666;"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (isset($cart_success)): ?>
                            <div class="success-message">
                                <?= $cart_success ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($cart_error)): ?>
                            <div class="error-message">
                                <?= $cart_error ?>
                            </div>
                        <?php endif; ?>

                        <div class="cart-form">
                            <?php if (!empty($sizes)): ?>
                                <div class="size-selector">
                                    <span class="size-label">Выберите размер:</span>
                                    <form method="post" id="cartForm">
                                        <div class="size-options" id="sizeOptions">
                                            <?php foreach ($sizes as $size): ?>
                                                <label class="size-option">
                                                    <input type="radio" name="size_id" value="<?= $size['id_size'] ?>" required>
                                                    <?= htmlspecialchars($size['size']) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="submit" name="add_to_cart" class="btn btn-success" style="margin-top: 15px;">В корзину</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p style="color: #666;">Нет доступных размеров</p>
                            <?php endif; ?>

                            <div class="like-container">
                                <a href="product.php?id=<?= $product_id ?>&toggle_like=1" class="like-btn">❤</a>
                                <span class="like-count"><?= $like_count ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: #666;">
                            <a href="login.php" style="color: #9370db;">Войдите</a>, чтобы добавить товар в корзину
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Комментарии -->
            <div>
                <h3>Отзывы покупателей</h3>

                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="comment-form">
                    <form method="post">
                        <textarea name="comment" rows="4" placeholder="Ваш отзыв..." required></textarea>
                        <button type="submit" class="btn">Отправить отзыв</button>
                    </form>
                </div>
                <?php else: ?>
                <p style="color: #666; margin-bottom: 20px;">
                    <a href="login.php" style="color: #9370db;">Войдите</a>, чтобы оставить отзыв
                </p>
                <?php endif; ?>

                <div id="commentsContainer">
                    <?php
                    // Повторно получаем комментарии для отображения
                    $comments_rewound = $mysqli->query("
                        SELECT c.*, u.name, u.surname FROM comments c
                        JOIN users u ON c.id_user = u.id_user
                        WHERE c.id_product = $product_id
                        ORDER BY c.date DESC
                    ");

                    if ($comments_rewound && $comments_rewound->num_rows > 0): ?>
                        <?php while($comment = $comments_rewound->fetch_assoc()): ?>
                        <div class="comment">
                            <strong style="color: #4b0082;">
                                <?= htmlspecialchars($comment['name']) ?> <?= htmlspecialchars($comment['surname']) ?>
                            </strong><br>
                            <?= nl2br(htmlspecialchars($comment['comment'])) ?><br>
                            <small style="color: #9370db;"><?= $comment['date'] ?></small>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #9370db; padding: 20px;">
                            Пока нет отзывов. Будьте первым!
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function f(obj) {
        document.getElementsByClassName('im')[0].src = obj.src;

        var thumbs = document.getElementsByClassName('thumbnail');
        for (var i = 0; i < thumbs.length; i++) {
            thumbs[i].classList.remove('active');
        }
        obj.classList.add('active');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var sizeOptions = document.querySelectorAll('.size-option');
        sizeOptions.forEach(function(option) {
            option.addEventListener('click', function() {
                sizeOptions.forEach(function(opt) {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
    });
    </script>
</body>
</html>