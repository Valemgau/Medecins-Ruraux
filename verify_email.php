<?php
require_once './includes/config.php';

$token = $_GET['token'] ?? '';
$role = $_SESSION['role'] ?? 'candidat';

if (!$token) {
    $message = "Lien de validation invalide ou manquant.";
    $redirectUrl = "login.php?role=$role";
    $isSuccess = false;
} else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_verify_token = ? AND email_verified = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $message = "Votre adresse email a bien été validée !";
        $redirectUrl = "dashboard-$role.php?message=email_verified_success";
        $isSuccess = true;
    } else {
        $message = "Lien invalide ou votre adresse a déjà été validée.";
        $redirectUrl = "login.php?role=$role";
        $isSuccess = false;
    }
}

$title = "Validation d'Email";
ob_start();
?>

<style>
    * {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    body {
        margin: 0;
        padding: 0;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    
    .verification-wrapper {
        min-height: calc(100vh - 400px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
    }
    
    .verification-card {
        background: white;
        border-radius: 2rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        padding: 3rem 2.5rem;
        max-width: 500px;
        width: 100%;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .verification-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    }
    
    .icon-container {
        width: 100px;
        height: 100px;
        margin: 0 auto 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .icon-container.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    }
    
    .icon-container.error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
    }
    
    .icon-container i {
        font-size: 3rem;
        color: white;
    }
    
    .checkmark {
        animation: checkmark 0.6s ease-in-out;
    }
    
    @keyframes checkmark {
        0% { transform: scale(0) rotate(-45deg); opacity: 0; }
        50% { transform: scale(1.2) rotate(-45deg); opacity: 1; }
        100% { transform: scale(1) rotate(0deg); }
    }
    
    .error-icon {
        animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }
    
    .title {
        font-size: 2rem;
        font-weight: bold;
        color: #111827;
        margin-bottom: 1rem;
        line-height: 1.2;
    }
    
    .message {
        font-size: 1.125rem;
        color: #6b7280;
        margin-bottom: 2rem;
        line-height: 1.6;
    }
    
    .countdown-container {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .countdown-text {
        font-size: 1rem;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
    
    .countdown-number {
        font-size: 3rem;
        font-weight: bold;
        color: #10b981;
        font-variant-numeric: tabular-nums;
    }
    
    .progress-bar {
        width: 100%;
        height: 6px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 1rem;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        border-radius: 999px;
        transition: width 1s linear;
    }
    
    .redirect-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }
    
    .redirect-info i {
        color: #10b981;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }
    
    @media (max-width: 640px) {
        .verification-wrapper {
            padding: 2rem 1rem;
            min-height: calc(100vh - 300px);
        }
        
        .verification-card {
            padding: 2rem 1.5rem;
        }
        
        .title {
            font-size: 1.5rem;
        }
        
        .message {
            font-size: 1rem;
        }
        
        .countdown-number {
            font-size: 2.5rem;
        }
    }
</style>

<div class="verification-wrapper">
    <div class="verification-card fade-in">
        
        <!-- Icône -->
        <div class="icon-container <?= $isSuccess ? 'success' : 'error' ?>">
            <?php if ($isSuccess): ?>
                <i class="fas fa-check checkmark"></i>
            <?php else: ?>
                <i class="fas fa-times error-icon"></i>
            <?php endif; ?>
        </div>
        
        <!-- Titre -->
        <h1 class="title">
            <?= $isSuccess ? 'Email validé !' : 'Erreur de validation' ?>
        </h1>
        
        <!-- Message -->
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
        
        <!-- Compteur -->
        <div class="countdown-container">
            <p class="countdown-text">Redirection automatique dans</p>
            <div class="countdown-number" id="seconds">5</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress"></div>
            </div>
        </div>
        
        <!-- Info redirection -->
        <div class="redirect-info">
            <i class="fas fa-arrow-right"></i>
            <span>Vous allez être redirigé·e</span>
        </div>
        
    </div>
</div>

<script>
    let seconds = 5;
    const countdownElement = document.getElementById('seconds');
    const progressElement = document.getElementById('progress');
    
    progressElement.style.width = '100%';
    
    const interval = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
        
        const progressPercent = (seconds / 5) * 100;
        progressElement.style.width = progressPercent + '%';
        
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
?>
