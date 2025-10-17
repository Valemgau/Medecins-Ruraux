<?php
require_once './includes/config.php';


$token = $_GET['token'] ?? '';
$role = $_SESSION['user_role'] ?? 'candidat';

if (!$token) {
    // Token manquant => redirection vers login.php avec role
    $message = "Lien de validation invalide ou manquant.";
    $redirectUrl = "login.php?role=$role";
} else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_verify_token = ? AND email_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Valider l'email
        $update = $pdo->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $message = "Votre adresse email a bien été validée. Vous allez être redirigé vers votre tableau de bord.";
        // Redirect vers dashboard selon rôle, avec param email=verified_success
        $redirectUrl = "dashboard-$role.php?message=email_verified_success";
    } else {
        // Token invalide ou déjà validé => rediriger vers login.php avec role
        $message = "Lien invalide ou votre adresse a déjà été validée.";
        $redirectUrl = "login.php?role=$role";
    }
}

$title = "Validation d'Email";
ob_start();
?>

<div class="bg-white shadow p-8 max-w-md w-full text-center mx-auto my-16">
    <img src="assets/img/logo.jpg" alt="Logo" class="mx-auto mb-6 h-20 w-20 rounded" />
    <h1 class="text-2xl font-bold text-orange-500 mb-4">Validation de l'adresse e-mail</h1>
    <p class="text-gray-700 text-lg mb-6"><?= htmlspecialchars($message) ?></p>

    <p id="countdown" class="text-gray-600 mb-6">Redirection dans <span id="seconds">5</span> secondes...</p>


</div>

<script>
    let seconds = 5;
    const countdownElement = document.getElementById('seconds');

    const interval = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = '<?= $redirectUrl ?>';
        }
    }, 1000);
</script>

<?php
$pageContent = ob_get_clean();
$customJS = '';
include './includes/layouts/layout_dashboard.php';
