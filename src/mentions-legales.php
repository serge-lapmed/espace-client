<?php
/**
 * Espace Mission — Mentions légales & Politique de confidentialité
 */
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentions légales — <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-white border-b border-gray-100 px-6 py-2 flex items-center justify-between text-xs text-gray-500">
        <a href="/" class="text-gray-400 hover:text-gray-700">&larr; Retour à l'espace mission</a>
    </div>

    <main class="max-w-3xl mx-auto px-6 py-10">
        <h1 class="text-2xl font-semibold text-gray-800 mb-8">Mentions légales & Politique de confidentialité</h1>

        <div class="space-y-8 text-sm text-gray-600 leading-relaxed">

            <!-- ÉDITEUR -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Éditeur du site</h2>
                <p>
                    <strong>La PME Digitale</strong> — Serge Fornier<br>
                    Consultant en Système d'Information<br>
                    SIRET : [à compléter]<br>
                    Email : <a href="mailto:serge@lapmedigitale.fr" class="text-blue-600">serge@lapmedigitale.fr</a><br>
                    Téléphone : +33 6 50 13 56 54<br>
                    Site : <a href="https://lapmedigitale.fr" class="text-blue-600" target="_blank">lapmedigitale.fr</a>
                </p>
            </section>

            <!-- HÉBERGEMENT -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Hébergement</h2>
                <p>
                    <strong>O2switch</strong><br>
                    222-224 Boulevard Gustave Flaubert, 63000 Clermont-Ferrand, France<br>
                    <a href="https://www.o2switch.fr" class="text-blue-600" target="_blank">o2switch.fr</a>
                </p>
            </section>

            <!-- OBJET -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Objet du site</h2>
                <p>
                    Ce site est un espace client sécurisé permettant le suivi des missions de conseil en système d'information
                    réalisées par La PME Digitale. Il donne accès aux résumés de mission, plans d'action, documents et échanges
                    liés à chaque mission. L'accès est réservé aux personnes disposant d'un compte utilisateur ou d'un lien de partage valide.
                </p>
            </section>

            <!-- DONNÉES PERSONNELLES -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Données personnelles & RGPD</h2>
                <p class="mb-3">
                    <strong>Responsable du traitement :</strong> Serge Fornier — La PME Digitale
                </p>
                <p class="mb-3">
                    <strong>Données collectées :</strong> nom, adresse email, rôle dans la mission, date de dernière connexion.
                    Ces données sont strictement nécessaires au fonctionnement du service (base légale : exécution du contrat de mission).
                </p>
                <p class="mb-3">
                    <strong>Durée de conservation :</strong> les comptes utilisateurs et les données associées sont conservés
                    pendant la durée de la mission et jusqu'à 12 mois après sa clôture, sauf demande contraire.
                </p>
                <p class="mb-3">
                    <strong>Destinataires :</strong> les données ne sont accessibles qu'au consultant (Serge Fornier)
                    et ne sont transmises à aucun tiers. Aucun outil de tracking, d'analytics ou de publicité n'est utilisé sur ce site.
                </p>
                <p class="mb-3">
                    <strong>Sécurité :</strong> les mots de passe sont stockés sous forme de hash bcrypt (irréversible).
                    Les échanges sont chiffrés via HTTPS (certificat Let's Encrypt). Les sessions utilisent des cookies sécurisés
                    (HttpOnly, Secure, SameSite=Lax).
                </p>
                <p>
                    <strong>Vos droits :</strong> conformément au RGPD, vous disposez d'un droit d'accès, de rectification,
                    de suppression et de portabilité de vos données. Pour exercer ces droits, contactez
                    <a href="mailto:serge@lapmedigitale.fr" class="text-blue-600">serge@lapmedigitale.fr</a>.
                </p>
            </section>

            <!-- COOKIES -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Cookies</h2>
                <p>
                    Ce site utilise uniquement un cookie de session technique (PHPSESSID) strictement nécessaire à l'authentification.
                    Aucun cookie tiers, de tracking ou publicitaire n'est déposé. Ce cookie ne nécessite pas de consentement
                    au titre de la directive ePrivacy (cookie strictement nécessaire au service).
                </p>
            </section>

            <!-- PROPRIÉTÉ INTELLECTUELLE -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Propriété intellectuelle</h2>
                <p>
                    L'ensemble des contenus de cet espace (résumés, analyses, livrables, documents) est la propriété
                    intellectuelle de La PME Digitale et/ou du client concerné par la mission, selon les termes du contrat de mission.
                    Toute reproduction ou diffusion non autorisée est interdite.
                </p>
            </section>

            <!-- RESPONSABILITÉ -->
            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Limitation de responsabilité</h2>
                <p>
                    La PME Digitale s'efforce d'assurer la disponibilité du service mais ne peut garantir un accès continu.
                    Les informations publiées sur cet espace sont fournies à titre indicatif dans le cadre de la mission
                    et ne constituent pas un engagement contractuel au-delà du périmètre de la mission définie.
                </p>
            </section>

        </div>

        <p class="text-xs text-gray-400 mt-10 border-t border-gray-200 pt-6">
            Dernière mise à jour : <?= date('d/m/Y') ?>
        </p>
    </main>

</body>
</html>
