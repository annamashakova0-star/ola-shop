<?php
require_once 'config.php';

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Вход
if (isset($_POST['login'])) {
    $email = $mysqli->real_escape_string($_POST['email']);
    $password = $mysqli->real_escape_string($_POST['password']);
    $result = $mysqli->query("SELECT * FROM users WHERE email='$email' AND password='$password'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_photo'] = $user['photo'] ?? '';
    } else {
        $error = "Неверный email или пароль";
    }
}

// Проверка прав администратора
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $admin_check = $mysqli->query("SELECT is_admin FROM users WHERE id_user = $user_id");
    if ($admin_check && $admin_check->num_rows > 0) {
        $user_data = $admin_check->fetch_assoc();
        if ($user_data['is_admin'] == 1) {
            $is_admin = true;
        }
    }
}



// Простая фильтрация
$search = isset($_GET['search']) ? $mysqli->real_escape_string($_GET['search']) : '';
$category = (int)($_GET['category'] ?? 0);
$price_min = (int)($_GET['price_min'] ?? 0);
$price_max = (int)($_GET['price_max'] ?? 0);
$colour = isset($_GET['colour']) ? $mysqli->real_escape_string($_GET['colour']) : '';
$country = isset($_GET['country']) ? $mysqli->real_escape_string($_GET['country']) : '';
$structure = isset($_GET['structure']) ? $mysqli->real_escape_string($_GET['structure']) : '';

// Поиск товаров
$where_conditions = "WHERE 1=1";

if (isset($_GET['filter'])) {
    if ($search) {
        $where_conditions .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
    }

    if ($category > 0) {
        $where_conditions .= " AND p.id_categories = $category";
    }

    if ($price_min > 0) {
        $where_conditions .= " AND p.price >= $price_min";
    }

    if ($price_max > 0) {
        $where_conditions .= " AND p.price <= $price_max";
    }

    if ($colour) {
        $where_conditions .= " AND p.colour = '$colour'";
    }

    if ($country) {
        $where_conditions .= " AND p.country = '$country'";
    }

    if ($structure) {
        $where_conditions .= " AND p.structure LIKE '%$structure%'";
    }
}

// Пагинация
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$limit = 8;
$offset = ($page - 1) * $limit;

// Подсчет товаров
$count_result = $mysqli->query("SELECT COUNT(*) as total FROM product p $where_conditions");
$total_products = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);

// Получение товаров
$sql = "SELECT p.*, i.image FROM product p
        LEFT JOIN image i ON p.id_product = i.id_product AND i.main = 1
        $where_conditions
        ORDER BY p.id_product DESC
        LIMIT $limit OFFSET $offset";

$products_result = $mysqli->query($sql);

// Категории и цвета
$categories = $mysqli->query("SELECT * FROM categories");
$colours_result = $mysqli->query("SELECT DISTINCT colour FROM product WHERE colour IS NOT NULL AND colour != '' ORDER BY colour");
$countries_result = $mysqli->query("SELECT DISTINCT country FROM product WHERE country IS NOT NULL AND country != '' ORDER BY country");
$structures_result = $mysqli->query("SELECT DISTINCT structure FROM product WHERE structure IS NOT NULL AND structure != '' ORDER BY structure");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Магазин</title>
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
        .search {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
        }
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-form input, .search-form select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            flex: 1;
            min-width: 120px;
        }
        .search-form button {
            background: #9370db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            width: 100%;
        }
        .price-range {
            display: flex;
            gap: 10px;
            flex: 1;
        }
        .price-range input {
            flex: 1;
        }

        /* Сетка товаров */
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
            text-decoration: none;
            color: black;
            border-radius: 8px;
            cursor: pointer;
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
            margin: 10px 0 5px 0;
            color: #4b0082;
            height: 40px;
            overflow: hidden;
        }

        .product-price {
            color: #8a2be2;
            font-size: 18px;
            font-weight: bold;
            margin: 5px 0;
        }

        .product-colour {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
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
        }

        .auth {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .auth input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .auth button {
            width: 100%;
            padding: 12px;
            background: #9370db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a, .pagination strong {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover {
            background: #9370db;
            color: white;
        }
        .pagination strong {
            background: #9370db;
            color: white;
        }
        .results-info {
            color: #666;
            margin-bottom: 15px;
        }
        .reset-btn {
            padding: 10px 20px;
            background: #ff6b6b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .error {
            color: #ff6b6b;
            text-align: center;
            margin: 10px 0;
        }

    </style>
</head>
<body>
    <!-- Шапка -->
    <div class="header">
        <div class="container">
            <div class="nav">
                <div class="logo">OLNI</div>
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
        <!-- Форма входа -->
        <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="auth">
            <h3 style="color: #4b0082; margin-top: 0; text-align: center;">Вход в аккаунт</h3>
            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit" name="login">Войти</button>
            </form>
            <p style="text-align: center; margin-top: 15px;">
                Нет аккаунта? <a href="register.php" style="color: #9370db;">Зарегистрироваться</a>
            </p>
        </div>
        <?php endif; ?>


        <!-- Форма поиска и фильтрации -->
        <div class="search">
            <form method="get" class="search-form">
                <input type="text" name="search" placeholder="Поиск товаров..." value="<?= htmlspecialchars($search) ?>">

                <select name="category">
                    <option value="0">Все категории</option>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id_categories'] ?>" <?= $category == $cat['id_categories'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <div class="filter-row">
                    <div class="price-range">
                        <input type="number" name="price_min" placeholder="Цена от" value="<?= $price_min ?>" min="0">
                        <input type="number" name="price_max" placeholder="Цена до" value="<?= $price_max ?>" min="0">
                    </div>

                    <select name="colour">
                        <option value="">Все цвета</option>
                        <?php while($colour_item = $colours_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($colour_item['colour']) ?>" <?= $colour == $colour_item['colour'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($colour_item['colour']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-row">
                    <select name="country">
                        <option value="">Все страны</option>
                        <?php while($country_item = $countries_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($country_item['country']) ?>" <?= $country == $country_item['country'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($country_item['country']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="structure">
                        <option value="">Любой состав</option>
                        <?php while($structure_item = $structures_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($structure_item['structure']) ?>" <?= $structure == $structure_item['structure'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($structure_item['structure']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <button type="submit" name="filter">Применить</button>

                <?php if (isset($_GET['filter'])): ?>
                    <a href="index.php" class="reset-btn">Сбросить</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Информация о результатах -->
        <div class="results-info">
            Найдено товаров: <?= $total_products ?>
            <?php if ($search): ?>
                по запросу "<?= htmlspecialchars($search) ?>"
            <?php endif; ?>
        </div>

        <!-- Товары -->
        <div class="products">
            <?php if ($products_result->num_rows > 0): ?>
                <?php while($product = $products_result->fetch_assoc()): ?>
                    <a href="product.php?id=<?= $product['id_product'] ?>" class="product-card">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?= $product['image'] ?>" class="product-image" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                Нет фото
                            </div>
                        <?php endif; ?>
                        <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="product-price"><?= number_format($product['price'], 0, '', ' ') ?> ₽</div>
                        <div class="product-colour">
                            <?php if (!empty($product['colour'])): ?>
                                Цвет: <?= htmlspecialchars($product['colour']) ?>
                            <?php else: ?>
                                &nbsp;
                            <?php endif; ?>
                        </div>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="btn" onclick="window.location.href='cart.php?add=<?= $product['id_product'] ?>'">В корзину</button>
                        <?php endif; ?>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                    Товары не найдены
                </div>
            <?php endif; ?>
        </div>

        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <strong><?= $i ?></strong>
                <?php else: ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>&price_min=<?= $price_min ?>&price_max=<?= $price_max ?>&colour=<?= urlencode($colour) ?>&country=<?= urlencode($country) ?>&structure=<?= urlencode($structure) ?>&filter=1"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>