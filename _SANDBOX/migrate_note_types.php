<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();

echo "<h2>Migration : notes multi-types</h2><pre>";

$pdo->beginTransaction();

try {
    // 1. Créer note_types
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('note_types', $tables)) {
        $pdo->exec("
            CREATE TABLE note_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL UNIQUE,
                sur REAL NOT NULL DEFAULT 20,
                ordre INTEGER NOT NULL DEFAULT 0,
                actif INTEGER NOT NULL DEFAULT 1
            )
        ");
        echo "✅ Table 'note_types' créée\n";

        $pdo->exec("INSERT INTO note_types (nom, sur, ordre) VALUES ('CC1', 20, 1), ('CC2', 20, 2), ('Examen', 20, 3)");
        echo "✅ Types seedés : CC1, CC2, Examen\n";
    } else {
        echo "⚠️ note_types existe déjà\n";
    }

    // 2. Refondre notes : ajouter note_type_id
    $notesCols = $pdo->query("PRAGMA table_info(notes)")->fetchAll(PDO::FETCH_ASSOC);
    $notesColNames = array_column($notesCols, 'name');

    if (!in_array('note_type_id', $notesColNames)) {
        // Créer une nouvelle table notes
        $pdo->exec("
            CREATE TABLE notes_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                etudiant_id INTEGER NOT NULL,
                module_id INTEGER NOT NULL,
                note_type_id INTEGER NOT NULL,
                valeur REAL NOT NULL,
                date_saisie DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (etudiant_id) REFERENCES etudiants(id),
                FOREIGN KEY (module_id) REFERENCES modules(id),
                FOREIGN KEY (note_type_id) REFERENCES note_types(id),
                UNIQUE(etudiant_id, module_id, note_type_id)
            )
        ");

        // Copier anciennes notes (matiere_id → module_id, type_note CC1/CC2 → note_type_id)
        $cc1Id = (int)$pdo->query("SELECT id FROM note_types WHERE nom='CC1'")->fetchColumn();
        $cc2Id = (int)$pdo->query("SELECT id FROM note_types WHERE nom='CC2'")->fetchColumn();

        $oldNotes = $pdo->query("SELECT * FROM notes")->fetchAll(PDO::FETCH_ASSOC);
        $ins = $pdo->prepare("
            INSERT INTO notes_new (etudiant_id, module_id, note_type_id, valeur, date_saisie)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($oldNotes as $n) {
            $typeId = $n['type_note'] === 'CC1' ? $cc1Id : $cc2Id;
            $ins->execute([
                $n['etudiant_id'],
                $n['module_id'] ?? $n['matiere_id'] ?? 0,
                $typeId,
                $n['valeur'],
                $n['date_saisie'],
            ]);
        }
        echo "✅ " . count($oldNotes) . " note(s) migrée(s)\n";

        $pdo->exec("DROP TABLE notes");
        $pdo->exec("ALTER TABLE notes_new RENAME TO notes");
        echo "✅ Table 'notes' refondue\n";
    } else {
        echo "⚠️ notes a déjà note_type_id\n";
    }

    $pdo->commit();
    echo "\n🎉 Migration réussie !\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "Rollback effectué.\n";
}

// Récap
echo "\n--- État final ---\n";
foreach (['note_types', 'notes'] as $t) {
    echo "\n$t :\n";
    foreach ($pdo->query("PRAGMA table_info($t)") as $c) {
        echo "  - {$c['name']} ({$c['type']})\n";
    }
}
echo "</pre>";