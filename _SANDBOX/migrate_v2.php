<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

echo "<h2>Migration v2 : fusion promotions + promos</h2><pre>";

$pdo->beginTransaction();

try {
    // 1. Renommer 'diplomes' → 'promotions' (si pas déjà fait)
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('diplomes', $tables) && !in_array('promotions', $tables)) {
        $pdo->exec("ALTER TABLE diplomes RENAME TO promotions");
        echo "✅ Table 'diplomes' renommée en 'promotions'\n";
    }

    // 2. Ajouter colonnes manquantes à 'promotions'
    $cols = $pdo->query("PRAGMA table_info(promotions)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');

    if (!in_array('promo', $colNames)) {
        $pdo->exec("ALTER TABLE promotions ADD COLUMN promo TEXT DEFAULT NULL");
        echo "✅ Colonne 'promotions.promo' ajoutée (nullable)\n";
    }
    if (!in_array('annee', $colNames)) {
        $pdo->exec("ALTER TABLE promotions ADD COLUMN annee TEXT DEFAULT NULL");
        echo "✅ Colonne 'promotions.annee' ajoutée (nullable)\n";
    }
    if (!in_array('filiere_id', $colNames)) {
        $pdo->exec("ALTER TABLE promotions ADD COLUMN filiere_id INTEGER");
        echo "✅ Colonne 'promotions.filiere_id' ajoutée\n";
    }
    if (!in_array('actif', $colNames)) {
        $pdo->exec("ALTER TABLE promotions ADD COLUMN actif INTEGER NOT NULL DEFAULT 1");
        echo "✅ Colonne 'promotions.actif' ajoutée\n";
    }

    // 3. Remplir les nouvelles colonnes depuis la table 'promos' (si elle existe)
    if (in_array('promos', $tables)) {
        // Pour chaque promo existante, on crée une ligne dans promotions
        $oldPromos = $pdo->query("
            SELECT p.*, d.nom AS niveau_nom, d.ordre AS niveau_ordre
            FROM promos p
            JOIN diplomes d ON d.id = p.diplome_id
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Vérifier si diplome_id ou promotion_id
        if (empty($oldPromos)) {
            // Essayer avec promotion_id
            $oldPromos = $pdo->query("
                SELECT p.*, pr.nom AS niveau_nom, pr.ordre AS niveau_ordre
                FROM promos p
                JOIN promotions pr ON pr.id = p.promotion_id
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO promotions (nom, ordre, promo, annee, filiere_id, actif)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($oldPromos as $op) {
            $insertStmt->execute([
                $op['niveau_nom'],
                $op['niveau_ordre'] ?? 0,
                $op['nom'] ?? null,
                $op['annee'] ?? date('Y') . '-' . (date('Y') + 1),
                $op['filiere_id'],
                $op['actif'] ?? 1,
            ]);
            $newPromoId = (int)$pdo->lastInsertId();
            echo "➕ Promo '{$op['nom']}' ({$op['annee']}) → id $newPromoId\n";

            // Mettre à jour les étudiants liés
            $pdo->prepare("UPDATE etudiants SET promo_id = ? WHERE promo_id = ?")
                ->execute([$newPromoId, $op['id']]);
            echo "   ↳ Étudiants de l'ancienne promo {$op['id']} → nouvelle promo $newPromoId\n";
        }

        // Supprimer les anciennes lignes de promotions "pures niveaux" (L1, L2, L3, Master sans promo)
        $pdo->exec("DELETE FROM promotions WHERE promo IS NULL AND annee IS NULL");
        echo "🗑️ Anciennes lignes 'niveaux' (sans promo/année) supprimées\n";

        // Supprimer la table promos
        $pdo->exec("DROP TABLE promos");
        echo "🗑️ Table 'promos' supprimée\n";
    }

    // 4. Renommer etudiants.promo_id → etudiants.promotion_id
    $etCols = $pdo->query("PRAGMA table_info(etudiants)")->fetchAll(PDO::FETCH_ASSOC);
    $etColNames = array_column($etCols, 'name');

    if (in_array('promo_id', $etColNames) && !in_array('promotion_id', $etColNames)) {
        $pdo->exec("ALTER TABLE etudiants RENAME COLUMN promo_id TO promotion_id");
        echo "✅ 'etudiants.promo_id' → 'promotion_id'\n";
    } elseif (in_array('promotion_id', $etColNames)) {
        echo "⚠️ etudiants.promotion_id existe déjà\n";
    }

    // 5. Refondre la table matieres : remplacer promotion_id/diplome_id par niveau TEXT
    $matCols = $pdo->query("PRAGMA table_info(matieres)")->fetchAll(PDO::FETCH_ASSOC);
    $matColNames = array_column($matCols, 'name');

    if (in_array('diplome_id', $matColNames) || in_array('promotion_id', $matColNames)) {
        // Créer une nouvelle table matieres
        $pdo->exec("
            CREATE TABLE matieres_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL,
                niveau TEXT NOT NULL,
                filiere_id INTEGER NOT NULL,
                professeur_id INTEGER NOT NULL,
                actif INTEGER NOT NULL DEFAULT 1,
                FOREIGN KEY (filiere_id) REFERENCES filieres(id),
                FOREIGN KEY (professeur_id) REFERENCES users(id)
            )
        ");

        // Copier les données
        $idCol = in_array('diplome_id', $matColNames) ? 'diplome_id' : 'promotion_id';
        $oldMatieres = $pdo->query("
            SELECT m.*, d.nom AS niveau_nom
            FROM matieres m
            JOIN diplomes d ON d.id = m.$idCol
        ")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($oldMatieres)) {
            // Essayer avec promotions
            $oldMatieres = $pdo->query("
                SELECT m.*, pr.nom AS niveau_nom
                FROM matieres m
                JOIN promotions pr ON pr.id = m.$idCol
            ")->fetchAll(PDO::FETCH_ASSOC);
        }

        $ins = $pdo->prepare("
            INSERT INTO matieres_new (id, nom, niveau, filiere_id, professeur_id, actif)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($oldMatieres as $m) {
            $ins->execute([
                $m['id'],
                $m['nom'],
                $m['niveau_nom'],
                $m['filiere_id'] ?? 1,
                $m['professeur_id'],
                $m['actif'] ?? 1,
            ]);
            echo "➕ Matière '{$m['nom']}' ({$m['niveau_nom']}) → niveau TEXT\n";
        }

        $pdo->exec("DROP TABLE matieres");
        $pdo->exec("ALTER TABLE matieres_new RENAME TO matieres");
        echo "✅ Table 'matieres' refondue (niveau TEXT)\n";
    }

    $pdo->commit();
    echo "\n🎉 Migration réussie !\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "Rollback effectué, base inchangée.\n";
}

// Récap final
echo "\n--- État final ---\n";
foreach (['promotions', 'matieres', 'etudiants', 'notes'] as $t) {
    echo "\n$t :\n";
    foreach ($pdo->query("PRAGMA table_info($t)") as $c) {
        echo "  - {$c['name']} ({$c['type']})\n";
    }
}
echo "</pre>";
echo "<p><a href='/ICEANOTES/pages/accueil.php'>→ Retour accueil</a></p>";