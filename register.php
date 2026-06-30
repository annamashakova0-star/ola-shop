<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (isset($_POST['register'])) {
    $name = $mysqli->real_escape_string($_POST['name']);
    $surname = $mysqli->real_escape_string($_POST['surname']);
    $phone = $mysqli->real_escape_string($_POST['phone']);
    $email = $mysqli->real_escape_string($_POST['email']);
    $password = $mysqli->real_escape_string($_POST['password']);
    $photo_name = '';

    // Загрузка фотографии
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $photo_name = basename($_FILES['photo']['name']);
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo_name);
    }

    $errors = [];

    if (!preg_match('/^[а-яА-Яa-zA-Z]{2,50}$/u', $name)) {
        $errors[] = "Имя должно содержать только буквы (2-50 символов)";
    }

    if (!preg_match('/^[а-яА-Яa-zA-Z]{2,50}$/u', $surname)) {
        $errors[] = "Фамилия должна содержать только буквы (2-50 символов)";
    }

    if (!preg_match('/^\+7\d{10}$/', $phone)) {
        $errors[] = "Телефон должен быть в формате +7XXXXXXXXXX";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Неверный формат email";
    }

    if (strlen($password) < 6) {
        $errors[] = "Пароль должен быть не менее 6 символов";
    }

    if (empty($errors)) {
        $check = $mysqli->query("SELECT id_user FROM users WHERE email = '$email'");

        if ($check->num_rows > 0) {
            $error = "Пользователь с таким email уже существует";
        } else {
            // УБИРАЕМ is_admin ИЗ ЗАПРОСА
            $result = $mysqli->query("INSERT INTO users (name, surname, phone, email, password, photo) VALUES ('$name', '$surname', '$phone', '$email', '$password', '$photo_name')");

            if ($result) {
                $_SESSION['user_id'] = $mysqli->insert_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_photo'] = $photo_name;
                header('Location: index.php');
                exit;
            } else {
                $error = "Ошибка регистрации: " . $mysqli->error;
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Регистрация</title>
    <style>
        body { font-family: Arial; margin: 0; background: #f0e6ff; }
        .container { max-width: 500px; margin: 50px auto; padding: 0 15px; }
        .auth-form { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .auth-form h2 { color: #4b0082; text-align: center; margin-top: 0; }
        .auth-form input, .auth-form select { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .auth-form button { width: 100%; padding: 12px; background: #9370db; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .auth-form button:hover { background: #8a2be2; }
        .error { color: #ff6b6b; text-align: center; margin: 10px 0; }
        .success { color: #28a745; text-align: center; margin: 10px 0; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #9370db; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h3>Регистрация</h3>

            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="text" name="name" placeholder="Имя" required>
                <input type="text" name="surname" placeholder="Фамилия" required>
                <input type="tel" name="phone" placeholder="Телефон (+7XXXXXXXXXX)" required>
                <input type="email" name="email" placeholder="Почта" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <input type="file" name="photo" required>
                <button type="submit" name="register">Зарегистрироваться</button>
            </form>

            <?php if (isset($success)) echo "<p style='color:green'>$success</p>"; ?>

            <div class="back-link">
                <a href="index.php">← Назад к главной</a>
            </div>
        </div>
    </div>
</body>
</html>