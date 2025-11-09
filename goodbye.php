<?php

$accountDeleted = isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1';

$title = "Compte supprimé";
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
    
    .status-wrapper {
        min-height: calc(100vh - 400px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 4rem 1rem;
    }
    
    .status-wrapper.error {
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    }
    
    .status-card {
        background: white;
        border-radius: 2rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        padding: 3rem 2.5rem;
        max-width: 600px;
        width: 100%;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .status-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
    }
    
    .status-card.success::before {
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    }
    
    .status-card.error::before {
        background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
    }
    
    .icon-container {
        width: 120px;
        height: 120px;
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
        font-size: 3.5rem;
        color: white;
    }
    
    .status-title {
        font-size: 2.5rem;
        font-weight: bold;
        color: #111827;
        margin-bottom: 1.5rem;
        line-height: 1.2;
    }
    
    .status-message {
        font-size: 1.25rem;
        color: #6b7280;
        margin-bottom: 2.5rem;
        line-height: 1.6;
    }
    
    .info-box {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .info-box p {
        color: #4b5563;
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0;
    }
    
    .info-box i {
        color: #10b981;
        margin-right: 0.5rem;
    }
    
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        padding: 1rem 2.5rem;
        border-radius: 1rem;
        font-weight: 600;
        font-size: 1rem;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
    }
    
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: white;
        color: #6b7280;
        padding: 1rem 2.5rem;
        border-radius: 1rem;
        font-weight: 600;
        font-size: 1rem;
        text-decoration: none;
        transition: all 0.3s ease;
        border: 2px solid #e5e7eb;
        margin-left: 1rem;
    }
    
    .btn-secondary:hover {
        border-color: #10b981;
        color: #10b981;
        background: #f0fdf4;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
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
        .status-wrapper {
            padding: 2rem 1rem;
            min-height: calc(100vh - 300px);
        }
        
        .status-card {
            padding: 2rem 1.5rem;
        }
        
        .icon-container {
            width: 100px;
            height: 100px;
        }
        
        .icon-container i {
            font-size: 3rem;
        }
        
        .status-title {
            font-size: 1.75rem;
        }
        
        .status-message {
            font-size: 1rem;
        }
        
        .btn-secondary {
            margin-left: 0;
            margin-top: 0.75rem;
            width: 100%;
            justify-content: center;
        }
        
        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="status-wrapper <?= $accountDeleted ? '' : 'error' ?>">
    <div class="status-card <?= $accountDeleted ? 'success' : 'error' ?> fade-in">
        
        <?php if ($accountDeleted): ?>
            <!-- Succès -->
           
            <h1 class="status-title">
                Compte supprimé avec succès
            </h1>
            
            <p class="status-message">
                Votre compte et toutes vos données ont été définitivement supprimés de notre plateforme.
            </p>
            
            <div class="info-box">
                <p>
                    <i class="fas fa-heart"></i>
                    Merci d'avoir utilisé nos services. Nous espérons vous revoir bientôt !
                </p>
            </div>
            
            <div>
                <a href="index.php" class="btn-primary">
                    <i class="fas fa-home"></i>
                    Retour à l'accueil
                </a>
                <a href="register.php" class="btn-secondary">
                    <i class="fas fa-user-plus"></i>
                    Créer un nouveau compte
                </a>
            </div>
            
        <?php else: ?>
            <!-- Erreur -->
            <div class="icon-container error">
                <i class="fas fa-shield-alt"></i>
            </div>
            
            <h1 class="status-title">
                Accès non autorisé
            </h1>
            
            <p class="status-message">
                Vous ne pouvez pas accéder directement à cette page sans avoir effectué une suppression de compte.
            </p>
            
            <div class="info-box">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Si vous souhaitez supprimer votre compte, rendez-vous dans vos paramètres.
                </p>
            </div>
            
            <div>
                <a href="index.php" class="btn-primary">
                    <i class="fas fa-home"></i>
                    Retour à l'accueil
                </a>
            </div>
            
        <?php endif; ?>
        
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include './includes/layouts/layout_default.php';
?>
