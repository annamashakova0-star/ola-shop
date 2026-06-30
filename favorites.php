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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Проверка прав администратора
$is_admin = false;
$admin_check = $mysqli->query("SELECT is_admin FROM users WHERE id_user = $user_id");
if ($admin_check && $admin_check->num_rows > 0) {
    $user_data = $admin_check->fetch_assoc();
    if ($user_data['is_admin'] == 1) {
        $is_admin = true;
    }
}

// Добавление/удаление из избранного
if (isset($_GET['like'])) {
    $product_id = $_GET['like'];
    $check = $mysqli->query("SELECT * FROM likes WHERE id_user=$user_id AND id_product=$product_id");

    if ($check->num_rows > 0) {
        $mysqli->query("DELETE FROM likes WHERE id_user=$user_id AND id_product=$product_id");
    } else {
        $mysqli->query("INSERT INTO likes (id_user, id_product) VALUES ($user_id, $product_id)");
    }
    header('Location: favorites.php');
    exit;
}

// Получение избранных товаров
$favorites = $mysqli->query("
    SELECT p.*, i.image
    FROM likes l
    JOIN product p ON l.id_product = p.id_product
    LEFT JOIN image i ON p.id_product = i.id_product AND i.main = 1
    WHERE l.id_user = $user_id
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Избранное</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
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
        .products {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        .product-card {
            background: white;
            border: 1px solid #e6e6fa;
            padding: 15px;
            border-radius: 8px;
            height: 320px;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            border-color: #9370db;
        }
        .product-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #f8f6ff;
            border-radius: 5px;
        }
        .product-name {
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #4b0082;
            height: 40px;
            overflow: hidden;
            line-height: 1.2;
        }
        .product-price {
            color: #8a2be2;
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .product-colour {
            color: #666;
            font-size: 14px;
            margin: 0 0 10px 0;
            min-height: 20px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: #9370db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            border: none;
            cursor: pointer;
            margin-top: auto;
            font-size: 14px;
            box-sizing: border-box;
        }
        .btn:hover {
            background: #8a2be2;
        }
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 10px;
            border: 2px solid #e6e6fa;
            grid-column: 1 / -1;
        }
        .empty h3 {
            color: #4b0082;
            margin: 0 0 15px 0;
        }
        .empty p {
            margin: 0 0 20px 0;
        }
        .page-title {
            color: #4b0082;
            margin: 0 0 20px 0;
            font-size: 28px;
        }
        .results-info {
            color: #666;
            margin: 0 0 20px 0;
            padding: 0;
        }
        .product-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .product-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            background: #f8f6ff;
            border-radius: 5px;
            width: 100%;
            height: 160px;
            margin-bottom: 10px;
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
                    <a href="favorites.php" style="text-decoration: underline;">Избранное</a>
                    <a href="cart.php">Корзина</a>
                    <?php if ($is_admin): ?>
                        <a href="add_product.php" class="nav-plus" title="Добавить товар">➕</a>
                    <?php endif; ?>
                    <div class="user-info">
                        <?php if ($_SESSION['user_photo']): ?>
                            <img src="uploads/<?= $_SESSION['user_photo'] ?>" class="user-photo" alt="Фото пользователя" title="Вы вошли как: <?= $_SESSION['user_name'] ?>">
                        <?php endif; ?>
                        <a href="?logout=1">Выйти (<?= $_SESSION['user_name'] ?>)</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">← На главную</a>
        <h1 class="page-title">Избранные товары</h1>

        <div class="results-info">
            Найдено товаров: <?= $favorites->num_rows ?>
        </div>

        <?php if ($favorites->num_rows > 0): ?>
            <div class="products">
                <?php while($product = $favorites->fetch_assoc()): ?>
                    <div class="product-card">
                        <a href="product.php?id=<?= $product['id_product'] ?>" class="product-link">
                            <?php
                            $img = getImagePath($product['image']);
                            if (!empty($img)): ?>
                                <img src="<?= $img ?>" class="product-image" alt="<?= htmlspecialchars($product['name']) ?>">
                            <?php else: ?>
                                <div class="no-photo">
                                    Нет фото
                                </div>
                            <?php endif; ?>
                            <div class="product-content">
                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-price"><?= number_format($product['price'], 0, '', ' ') ?> ₽</div>
                                <div class="product-colour">
                                    <?php if ($product['colour']): ?>
                                        Цвет: <?= htmlspecialchars($product['colour']) ?>
                                    <?php else: ?>
                                        &nbsp;
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <a href="?like=<?= $product['id_product'] ?>" class="btn" onclick="return confirm('Удалить из избранного?')">Удалить из избранного</a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <h3>В избранном пока ничего нет</h3>
                <p>Добавляйте товары в избранное, нажимая на ❤️ на странице товара</p>
                <a href="index.php" class="btn" style="display: inline-block; width: auto; padding: 10px 20px; margin-top: 0;">Перейти к покупкам</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>