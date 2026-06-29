<?php
require_once 'config.php';

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Получаем информацию о пользователе для шапки
$user_id = (int)$_SESSION['user_id'];
$user_result = $mysqli->query("SELECT * FROM users WHERE id_user = $user_id");
$user_data = $user_result->fetch_assoc();
$is_admin = ($user_data['is_admin'] == 1);

$categories = $mysqli->query("SELECT * FROM categories ORDER BY name");

if (isset($_POST['add_product'])) {
    $name = $mysqli->real_escape_string($_POST['name']);
    $description = $mysqli->real_escape_string($_POST['description']);
    $price = (float)$_POST['price'];
    $id_categories = (int)$_POST['id_categories'];
    $brand = $mysqli->real_escape_string($_POST['brand']);
    $country = $mysqli->real_escape_string($_POST['country']);
    $colour = $mysqli->real_escape_string($_POST['colour']);
    $structure = $mysqli->real_escape_string($_POST['structure']);

    $sql = "INSERT INTO product (name, description, price, id_categories, brand, country, colour, structure)
            VALUES ('$name', '$description', '$price', $id_categories, '$brand', '$country', '$colour', '$structure')";

    if ($mysqli->query($sql)) {
        $product_id = $mysqli->insert_id;

        $upload_dir = 'bd/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Обработка загрузки изображений
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] == 0) {
                    $ext = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                    $filename = $product_id . '(' . ($key + 1) . ').' . $ext;
                    $filepath = 'bd/' . $filename;

                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $is_main = ($key == 0) ? 1 : 0;
                        $db_path = '/bd/' . $filename;
                        $mysqli->query("INSERT INTO image (id_product, image, main) VALUES ($product_id, '$db_path', $is_main)");
                    }
                }
            }
        }

        if (isset($_POST['sizes']) && is_array($_POST['sizes'])) {
            foreach ($_POST['sizes'] as $size) {
                $size = $mysqli->real_escape_string(trim($size));
                if (!empty($size)) {
                    $mysqli->query("INSERT INTO size (id_product, size) VALUES ($product_id, '$size')");
                }
            }
        }

        $success = "Товар успешно добавлен! ID: " . $product_id;
    } else {
        $error = "Ошибка добавления товара: " . $mysqli->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Добавление товара</title>
    <style>
        body { font-family: Arial; margin: 0; background: #f0e6ff; }
        .header { background: #8a2be2; color: white; padding: 15px 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 0 15px; }
        .nav { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; }
        .nav-links { display: flex; align-items: center; gap: 20px; }
        .nav-links a { color: white; text-decoration: none; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-photo { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white; }
        .back-btn { display: inline-block; padding: 10px 20px; background: #8a2be2; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .form-container { background: white; padding: 30px; border-radius: 10px; border: 2px solid #e6e6fa; margin-bottom: 20px; }
        .form-container h2 { color: #4b0082; margin-top: 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; color: #4b0082; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-family: Arial; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .row { display: flex; gap: 15px; }
        .row .form-group { flex: 1; }
        .btn { padding: 12px 30px; background: #9370db; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #8a2be2; }
        .error { color: #ff6b6b; padding: 10px; background: #f8d7da; border-radius: 5px; margin-bottom: 15px; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 5px; margin-bottom: 15px; }
        .add-size-btn { padding: 6px 12px; background: #e6e6fa; border: 1px solid #9370db; border-radius: 5px; cursor: pointer; color: #4b0082; }
        .add-size-btn:hover { background: #9370db; color: white; }
        .size-item { display: flex; gap: 10px; align-items: center; margin-bottom: 5px; }
        .size-item input { flex: 1; }
        .remove-size-btn { color: #ff6b6b; cursor: pointer; border: none; background: none; font-size: 18px; padding: 0 5px; }
        .remove-size-btn:hover { color: #cc0000; }
        .file-input-wrapper { border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 5px; cursor: pointer; }
        .file-input-wrapper:hover { border-color: #9370db; }
        .file-list { margin-top: 10px; }
        .file-item { display: flex; justify-content: space-between; padding: 5px 10px; background: #f8f6ff; border-radius: 5px; margin-bottom: 5px; }
        .nav-plus { font-size: 24px; font-weight: bold; color: white; text-decoration: none; padding: 0 5px; transition: transform 0.2s; display: inline-block; }
        .nav-plus:hover { transform: scale(1.3); }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav">
                <div class="logo">ОЛА</div>
                <div class="nav-links">
                    <a href="index.php">Главная</a>
                    <a href="favorites.php">Избранное</a>
                    <a href="cart.php">Корзина</a>
                    <a href="add_product.php" class="nav-plus" title="Добавить товар">➕</a>
                    <div class="user-info">
                        <?php if (!empty($_SESSION['user_photo'])): ?>
                            <img src="uploads/<?= $_SESSION['user_photo'] ?>" class="user-photo" alt="Фото пользователя">
                        <?php endif; ?>
                        <a href="?logout=1">Выйти (<?= $_SESSION['user_name'] ?>)</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">← Назад к товарам</a>

        <div class="form-container">
            <h2>➕ Добавление нового товара</h2>

            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="success"><?= $success ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Название товара *</label>
                    <input type="text" name="name" id="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea name="description" id="description"></textarea>
                </div>

                <div class="row">
                    <div class="form-group">
                        <label for="price">Цена (₽) *</label>
                        <input type="number" name="price" id="price" step="0.01" required min="0">
                    </div>
                    <div class="form-group">
                        <label for="id_categories">Категория *</label>
                        <select name="id_categories" id="id_categories" required>
                            <option value="">Выберите категорию</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['id_categories'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group">
                        <label for="brand">Бренд</label>
                        <input type="text" name="brand" id="brand">
                    </div>
                    <div class="form-group">
                        <label for="country">Страна производитель</label>
                        <input type="text" name="country" id="country">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group">
                        <label for="colour">Цвет</label>
                        <input type="text" name="colour" id="colour">
                    </div>
                    <div class="form-group">
                        <label for="structure">Состав</label>
                        <input type="text" name="structure" id="structure">
                    </div>
                </div>

                <div class="form-group">
                    <label>Размеры</label>
                    <div id="sizeContainer">
                        <div class="size-item">
                            <input type="text" name="sizes[]" placeholder="Например: S, M, L, XL">
                            <button type="button" class="remove-size-btn" onclick="removeSize(this)" style="display:none;">×</button>
                        </div>
                    </div>
                    <button type="button" class="add-size-btn" onclick="addSizeField()">+ Добавить размер</button>
                </div>

                <div class="form-group">
                    <label>Изображения товара</label>
                    <div class="file-input-wrapper" onclick="document.getElementById('imageInput').click()">
                        <p style="margin: 0; color: #999;">Нажмите для выбора файлов</p>
                        <input type="file" name="images[]" id="imageInput" multiple accept="image/*" style="display:none;" onchange="updateFileList()">
                    </div>
                    <div class="file-list" id="fileList">
                        <p style="color: #999; text-align: center;">Файлы не выбраны</p>
                    </div>
                </div>

                <button type="submit" name="add_product" class="btn">Добавить товар</button>
            </form>
        </div>
    </div>

    <script>
        function addSizeField() {
            const container = document.getElementById('sizeContainer');
            const item = document.createElement('div');
            item.className = 'size-item';
            item.innerHTML = `
                <input type="text" name="sizes[]" placeholder="Например: S, M, L, XL">
                <button type="button" class="remove-size-btn" onclick="removeSize(this)">×</button>
            `;
            container.appendChild(item);
        }

        function removeSize(btn) {
            const item = btn.parentElement;
            if (document.querySelectorAll('.size-item').length > 1) {
                item.remove();
            } else {
                alert('Должен быть хотя бы один размер');
            }
        }

        function updateFileList() {
            const input = document.getElementById('imageInput');
            const list = document.getElementById('fileList');
            list.innerHTML = '';

            if (input.files.length === 0) {
                list.innerHTML = '<p style="color: #999; text-align: center;">Файлы не выбраны</p>';
                return;
            }

            for (let i = 0; i < input.files.length; i++) {
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `
                    <span>${input.files[i].name}</span>
                    <span style="color: #999;">${(input.files[i].size / 1024).toFixed(1)} KB</span>
                `;
                list.appendChild(div);
            }
        }
    </script>
</body>
</html>