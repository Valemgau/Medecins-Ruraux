<?php
require_once '../includes/config.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

// Gestion POST - Mise à jour du paramètre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cv_limit'])) {
    $cvLimit = (int)$_POST['cv_limit'];
    
    if ($cvLimit >= 0) {
        // Vérifier si le paramètre existe
        $stmtCheck = $pdo->prepare("SELECT id FROM admin_settings WHERE setting_key = 'cv_consultables_essentielle'");
        $stmtCheck->execute();
        
        if ($existing = $stmtCheck->fetch()) {
            // Mise à jour
            $stmt = $pdo->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'cv_consultables_essentielle'");
            $stmt->execute([$cvLimit]);
        } else {
            // Création
            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('cv_consultables_essentielle', ?)");
            $stmt->execute([$cvLimit]);
        }
        
        $_SESSION['success'] = "Paramètre mis à jour avec succès.";
    } else {
        $_SESSION['error'] = "La valeur doit être un nombre positif.";
    }
    
    header("Location: /admin/parametres.php");
    exit;
}

// Récupérer le paramètre actuel
$stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'cv_consultables_essentielle'");
$stmt->execute();
$currentValue = $stmt->fetchColumn();

if ($currentValue === false) {
    $currentValue = 0; // Valeur par défaut si le paramètre n'existe pas
}

$title = "Paramètres du site";
ob_start();
?>

<div class="space-y-6">

    <!-- Paramètre CV Consultables -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="post" class="space-y-6">
            <div>
                <label for="cv_limit" class="block text-base font-semibold text-gray-900 mb-3">
                    <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                    CV consultables pour les abonnements Essentielle
                </label>
                
                <div class="flex items-center space-x-4">
                    <input 
                        type="number" 
                        id="cv_limit" 
                        name="cv_limit" 
                        value="<?= htmlspecialchars($currentValue) ?>"
                        min="0"
                        step="1"
                        required
                        class="w-32 px-4 py-3 text-lg font-semibold border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <span class="text-gray-600">CV par mois</span>
                </div>
                
                <p class="text-sm text-gray-500 mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    Nombre de CV que les recruteurs avec l'abonnement Essentielle peuvent consulter chaque mois
                </p>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-clock mr-1"></i>
                    Valeur actuelle : <span class="font-semibold text-gray-900"><?= htmlspecialchars($currentValue) ?></span> CV/mois
                </div>
                
                <button 
                    type="submit" 
                    name="update_cv_limit"
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center font-medium">
                    <i class="fas fa-save mr-2"></i>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Informations complémentaires -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-600 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-900 mb-1">À propos de ce paramètre</h3>
                <div class="text-sm text-blue-800 space-y-1">
                    <p>• Ce paramètre définit le nombre de CV consultables par les recruteurs avec la formule <strong>Essentielle</strong></p>
                    <p>• Les formules <strong>Premium</strong> et supérieures ont un accès <strong>illimité</strong> aux CV</p>
                    <p>• La limite se réinitialise chaque mois à la date d'anniversaire de l'abonnement</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require_once '../includes/layouts/layout_admin.php';
?>