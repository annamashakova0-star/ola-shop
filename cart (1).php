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
    header('Location: index.php');
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

if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    $size_id = (int)($_GET['size_id'] ?? 0);

    $check = $mysqli->query("SELECT * FROM cart WHERE id_user = $user_id AND id_product = $product_id AND id_size = $size_id");

    if ($check->num_rows == 0) {
        $mysqli->query("INSERT INTO cart (id_user, id_product, id_size) VALUES ($user_id, $product_id, $size_id)");
    }

    header('Location: cart.php');
    exit;
}

// Удаление из корзины
if (isset($_GET['remove'])) {
    $cart_id = $_GET['remove'];
    $mysqli->query("DELETE FROM cart WHERE id_cart=$cart_id AND id_user=$user_id");
    header('Location: cart.php');
    exit;
}

// Оформление заказа
if (isset($_POST['checkout'])) {
    $cart_items = $mysqli->query("
        SELECT c.*, s.size
        FROM cart c
        LEFT JOIN size s ON c.id_size = s.id_size
        WHERE c.id_user=$user_id
    ");

    while($item = $cart_items->fetch_assoc()) {
        $product = $mysqli->query("SELECT * FROM product WHERE id_product={$item['id_product']}")->fetch_assoc();
        $size_name = $item['size'] ?? 'M';

        $stmt = $mysqli->prepare("INSERT INTO oder (id_product, price, date, id_user, pay, size) VALUES (?, ?, NOW(), ?, 'pending', ?)");
        $stmt->bind_param("idis", $item['id_product'], $product['price'], $user_id, $size_name);
        $stmt->execute();

        $mysqli->query("DELETE FROM cart WHERE id_cart={$item['id_cart']}");
    }
    header('Location: cart.php?success=1');
    exit;
}

// Корзина
$cart = $mysqli->query("
    SELECT c.id_cart, p.*, i.image, s.size
    FROM cart c
    JOIN product p ON c.id_product = p.id_product
    LEFT JOIN image i ON p.id_product = i.id_product AND i.main = 1
    LEFT JOIN size s ON c.id_size = s.id_size
    WHERE c.id_user = $user_id
");

// Заказы
$orders = $mysqli->query("
    SELECT o.*, p.name, o.size
    FROM oder o
    JOIN product p ON o.id_product = p.id_product
    WHERE o.id_user = $user_id
    ORDER BY o.date DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Корзина</title>
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
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
        }
        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e6e6fa;
            align-items: center;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            background: #f8f6ff;
            border-radius: 8px;
        }
        .item-info {
            flex: 1;
        }
        .item-name {
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #4b0082;
        }
        .item-details {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .item-price {
            color: #8a2be2;
            font-size: 18px;
            font-weight: bold;
        }
        .item-size {
            color: #666;
            font-size: 14px;
            background: #f8f6ff;
            padding: 4px 10px;
            border-radius: 5px;
        }
        .remove-btn {
            color: #ff6b6b;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #ff6b6b;
            border-radius: 5px;
            font-size: 13px;
            background: white;
            cursor: pointer;
        }
        .remove-btn:hover {
            background: #ff6b6b;
            color: white;
        }
        .btn {
            padding: 12px 24px;
            background: #9370db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }
        .success {
            background: #e8f5e8;
            color: #2d5016;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .empty {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        h2 {
            color: #4b0082;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .total {
            text-align: right;
            margin: 20px 0;
            font-size: 20px;
            color: #4b0082;
            padding: 15px;
            background: #f8f6ff;
            border-radius: 8px;
        }
        .order-status {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .status-pending {
            color: #ffa500;
        }
        .status-paid {
            color: #00aa00;
        }
        .order-date {
            font-size: 12px;
            color: #999;
        }
        .no-photo {
            width: 80px;
            height: 80px;
            background: #f8f6ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 11px;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #9370db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
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
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">← Назад к товарам</a>

        <!-- Корзина -->
        <div class="section">
            <h2>Корзина</h2>

            <?php if (isset($_GET['success'])): ?>
                <div class="success">
                    Заказ успешно оформлен!
                </div>
            <?php endif; ?>

            <?php if ($cart->num_rows > 0): ?>
                <?php
                $total = 0;
                while($item = $cart->fetch_assoc()):
                    $total += $item['price'];
                ?>
                    <div class="cart-item">
                        <?php
                        $img = getImagePath($item['image']);
                        if (!empty($img)): ?>
                            <img src="<?= $img ?>" class="item-image" alt="<?= htmlspecialchars($item['name']) ?>">
                        <?php else: ?>
                            <div class="no-photo">
                                Нет фото
                            </div>
                        <?php endif; ?>
                        <div class="item-info">
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="item-details">
                                <div class="item-price"><?= number_format($item['price'], 0, '', ' ') ?> ₽</div>
                                <?php if (!empty($item['size'])): ?>
                                    <div class="item-size">Размер: <?= htmlspecialchars($item['size']) ?></div>
                                <?php else: ?>
                                    <div class="item-size">Размер: не указан</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="?remove=<?= $item['id_cart'] ?>" class="remove-btn" onclick="return confirm('Удалить товар из корзины?')">Удалить</a>
                    </div>
                <?php endwhile; ?>

                <div class="total">
                    Итого: <?= number_format($total, 0, '', ' ') ?> ₽
                </div>

                <form method="post" style="text-align: center;">
                    <button type="submit" name="checkout" class="btn">Оформить заказ</button>
                </form>
            <?php else: ?>
                <div class="empty">
                    <h3 style="color: #666; margin-bottom: 8px;">Корзина пуста</h3>
                    <p style="color: #999;">Добавьте товары из каталога</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Заказы -->
        <div class="section">
            <h2>Мои заказы</h2>
            <?php if ($orders->num_rows > 0): ?>
                <?php while($order = $orders->fetch_assoc()): ?>
                    <div class="cart-item">
                        <div class="item-info">
                            <div class="item-name"><?= htmlspecialchars($order['name']) ?></div>
                            <div class="item-details">
                                <div class="item-price"><?= number_format($order['price'], 0, '', ' ') ?> ₽</div>
                                <?php if (!empty($order['size'])): ?>
                                    <div class="item-size">Размер: <?= htmlspecialchars($order['size']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="order-status">
                                <span class="order-date"><?= $order['date'] ?></span>

                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    <p style="color: #999;">У вас пока нет заказов</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>