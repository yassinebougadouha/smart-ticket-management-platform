<?php
$_emailPrimary   = \App\Models\Setting::get("primary_color", "#667eea");
$_emailSecondary = \App\Models\Setting::get("secondary_color", "#764ba2");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statut de votre compte - <?php echo e(\App\Models\Setting::get('app_name', 'L2T')); ?> Support</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">

                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,<?php echo e($isActive ? '#38a169' : '#e53e3e'); ?> 0%,<?php echo e($isActive ? '#276749' : '#9b2c2c'); ?> 100%);padding:40px 30px;text-align:center;">
                        <h1 style="color:#ffffff;margin:0;font-size:28px;font-weight:700;"><?php echo e(\App\Models\Setting::get('app_name', 'L2T')); ?> Support</h1>
                        <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:15px;">
                            <?php echo e($isActive ? '✅ Compte activé' : '🚫 Compte désactivé'); ?>

                        </p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:40px 30px;">
                        <h2 style="color:#2d3748;margin:0 0 16px;font-size:22px;">
                            <?php echo e($isActive ? '✅ Votre compte a été activé' : '🚫 Votre compte a été désactivé'); ?>

                        </h2>
                        <p style="color:#4a5568;font-size:15px;line-height:1.6;margin:0 0 30px;">
                            Bonjour <strong><?php echo e($name); ?></strong>,<br><br>
                            <?php if($isActive): ?>
                                Votre compte Administrateur sur <strong><?php echo e(\App\Models\Setting::get('app_name', 'L2T')); ?> Support</strong> a été <strong style="color:#38a169;">activé</strong>.
                                Vous pouvez désormais vous connecter et accéder à la plateforme.
                            <?php else: ?>
                                Votre compte Administrateur sur <strong><?php echo e(\App\Models\Setting::get('app_name', 'L2T')); ?> Support</strong> a été <strong style="color:#e53e3e;">désactivé</strong>.
                                Vous ne pouvez plus accéder à la plateforme jusqu'à nouvel ordre.
                            <?php endif; ?>
                        </p>

                        <!-- Status Box -->
                        <div style="background:<?php echo e($isActive ? '#f0fff4' : '#fff5f5'); ?>;border-left:4px solid <?php echo e($isActive ? '#38a169' : '#e53e3e'); ?>;border-radius:4px;padding:20px;margin:0 0 30px;text-align:center;">
                            <span style="display:inline-block;background:<?php echo e($isActive ? '#c6f6d5' : '#fed7d7'); ?>;color:<?php echo e($isActive ? '#276749' : '#9b2c2c'); ?>;padding:8px 24px;border-radius:20px;font-size:16px;font-weight:700;">
                                <?php echo e($isActive ? '✅ ACTIF' : '🚫 INACTIF'); ?>

                            </span>
                        </div>

                        <?php if($isActive): ?>
                        <!-- CTA Button -->
                        <div style="text-align:center;margin:30px 0;">
                            <a href="<?php echo e(url('/login')); ?>"
                               style="display:inline-block;background:linear-gradient(135deg,#38a169,#276749);color:#fff;text-decoration:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:600;">
                                Se connecter →
                            </a>
                        </div>
                        <?php else: ?>
                        <div style="background:#fff8e1;border-left:4px solid #f59e0b;border-radius:4px;padding:16px;margin:0 0 24px;">
                            <p style="color:#92400e;margin:0;font-size:14px;">
                                ⚠️ Pour toute question, veuillez contacter le Super Administrateur.
                            </p>
                        </div>
                        <?php endif; ?>

                        <p style="color:#718096;font-size:13px;line-height:1.6;margin:0;text-align:center;">
                            Cet email a été envoyé automatiquement par le système <?php echo e(\App\Models\Setting::get('app_name', 'L2T')); ?> Support.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f7fafc;padding:24px 30px;text-align:center;border-top:1px solid #e2e8f0;">
                        <p style="color:#a0aec0;font-size:13px;margin:0;">© <?php echo e(date('Y')); ?> <?php echo e(\App\Models\Setting::get('app_name', 'L2T')); ?> Support — Tous droits réservés</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html><?php /**PATH /var/www/html/resources/views/emails/admin-status-changed.blade.php ENDPATH**/ ?>