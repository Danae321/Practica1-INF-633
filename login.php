<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "secure_login";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$ip_address = $_SERVER['REMOTE_ADDR']; 

$sql = "SELECT * FROM login_attempts WHERE ip_address='$ip_address'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $attempts = $row['attempts'];
    $last_attempt = strtotime($row['last_attempt']);
    
    if ($attempts >= 3 && (time() - $last_attempt) < 300) {
        die("Has superado el límite de intentos. Por favor espera 5 minutos.");
    }
} else {
    $sql = "INSERT INTO login_attempts (ip_address) VALUES ('$ip_address')";
    $conn->query($sql);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido.");
    }

    $email = $conn->real_escape_string($_POST['email']);
    $pass = $conn->real_escape_string($_POST['password']);

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            echo "Inicio de sesión exitoso";
            
            $sql = "UPDATE login_attempts SET attempts = 0 WHERE ip_address='$ip_address'";
            $conn->query($sql);
            $_SESSION['id'] = $row['id'];
        } else {
            echo "Contraseña incorrecta";
            $sql = "UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address='$ip_address'";
            $conn->query($sql);
        }
    } else {
        echo "El usuario no existe";
    }
}
?>
<form method="POST" action="login.php">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="text" name="email" placeholder="E-Mail" required><br>
    <input type="password" name="password" placeholder="Contraseña" required><br>
    <input type="submit" value="Iniciar sesión"><br>
</form>
