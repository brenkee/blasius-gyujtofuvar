<?php
require_once __DIR__ . '/auth_lib.php';

$currentUser = auth_require_admin();
$csrfToken = auth_generate_csrf_token();
$mustChange = auth_user_must_change_password($currentUser);
$forceProfile = $mustChange || isset($_GET['force']);
?>
<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Felhasználókezelés – <?= htmlspecialchars($CFG['app']['title']) ?></title>
    <meta name="robots" content="noindex,nofollow" />
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="stylesheet" href="public/admin.css" />
    <script>
        window.ADMIN_BOOTSTRAP = {
            csrfToken: <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>,
            me: <?= json_encode($currentUser, JSON_UNESCAPED_UNICODE) ?>,
            mustChange: <?= $mustChange ? 'true' : 'false' ?>
        };
    </script>
    <script defer src="public/admin.js"></script>
</head>
<body class="admin-body" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" data-force-profile="<?= $forceProfile ? '1' : '0' ?>">
    <header class="admin-header">
        <div class="admin-brand">
            <h1>Felhasználókezelés</h1>
            <p><?= htmlspecialchars($CFG['app']['title']) ?></p>
        </div>
        <nav class="admin-nav">
            <span class="admin-user">Bejelentkezve: <strong><?= htmlspecialchars($currentUser['username']) ?></strong> (<?= htmlspecialchars($currentUser['role']) ?>)</span>
            <a class="admin-link" href="/">Vissza az alkalmazáshoz</a>
            <form method="post" action="logout.php" id="logoutForm">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                <button type="submit">Kijelentkezés</button>
            </form>
        </nav>
    </header>
    <?php if ($mustChange): ?>
        <div class="admin-banner" role="status">
            <strong>Kötelező jelszócsere!</strong>
            <span>Az alapértelmezett jelszó használata nem engedélyezett. Kérjük, azonnal változtasd meg az alábbi űrlapon.</span>
        </div>
    <?php endif; ?>
    <main class="admin-main">
        <section class="admin-section" aria-labelledby="create-user-heading">
            <div class="section-header">
                <h2 id="create-user-heading">Új felhasználó</h2>
                <p>Adj meg minden kötelező adatot. A jelszó módosítása belépéskor kötelezhető.</p>
            </div>
            <form id="createUserForm" class="admin-form">
                <div class="form-grid">
                    <label>Felhasználónév
                        <input type="text" name="username" required autocomplete="off" />
                    </label>
                    <label>E-mail cím
                        <input type="email" name="email" required autocomplete="off" />
                    </label>
                    <label>Jelszó
                        <input type="password" name="password" required autocomplete="new-password" />
                    </label>
                    <label>Szerepkör
                        <select name="role" required>
                            <option value="viewer">Viewer</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="must_change_password" checked />
                        <span>Belépéskor kötelező jelszócsere</span>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_active" checked />
                        <span>Aktív fiók</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Felhasználó létrehozása</button>
                    <button type="reset" class="btn-secondary">Mezők törlése</button>
                </div>
            </form>
        </section>

        <section class="admin-section" aria-labelledby="user-list-heading">
            <div class="section-header">
                <h2 id="user-list-heading">Felhasználók listája</h2>
                <button type="button" class="btn-secondary" id="refreshUsers">Frissítés</button>
            </div>
            <div class="table-container">
                <table class="user-table" aria-describedby="user-list-heading">
                    <thead>
                        <tr>
                            <th>Felhasználónév</th>
                            <th>E-mail</th>
                            <th>Szerepkör</th>
                            <th>Aktív</th>
                            <th>Utolsó belépés</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <tr class="empty">
                            <td colspan="6">Nincsenek felhasználók.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-section" aria-labelledby="edit-user-heading">
            <div class="section-header">
                <h2 id="edit-user-heading">Felhasználó módosítása</h2>
                <p>Válassz ki egy felhasználót a táblázatból a szerkesztéshez.</p>
            </div>
            <form id="editUserForm" class="admin-form" data-empty="true">
                <input type="hidden" name="id" />
                <div class="form-grid">
                    <label>Felhasználónév
                        <input type="text" name="username" required autocomplete="off" />
                    </label>
                    <label>E-mail cím
                        <input type="email" name="email" required autocomplete="off" />
                    </label>
                    <label>Új jelszó
                        <input type="password" name="password" autocomplete="new-password" placeholder="(választható)" />
                    </label>
                    <label>Szerepkör
                        <select name="role" required>
                            <option value="viewer">Viewer</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="must_change_password" />
                        <span>Belépéskor jelszócsere kötelező</span>
                    </label>
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_active" />
                        <span>Aktív fiók</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary" disabled>Változtatások mentése</button>
                    <button type="button" class="btn-secondary" id="cancelEdit" disabled>Mégse</button>
                </div>
            </form>
        </section>

        <section class="admin-section" aria-labelledby="profile-heading" id="profileSection">
            <div class="section-header">
                <h2 id="profile-heading">Saját adatok frissítése</h2>
                <p>Tartsd naprakészen az elérhetőségeidet és módosítsd a jelszavad.</p>
            </div>
            <form id="profileForm" class="admin-form">
                <div class="form-grid">
                    <label>Felhasználónév
                        <input type="text" name="username" required value="<?= htmlspecialchars($currentUser['username']) ?>" />
                    </label>
                    <label>E-mail cím
                        <input type="email" name="email" required value="<?= htmlspecialchars($currentUser['email']) ?>" />
                    </label>
                    <label>Új jelszó
                        <input type="password" name="password" autocomplete="new-password" placeholder="(választható)" />
                    </label>
                    <label>Új jelszó megerősítése
                        <input type="password" name="password_confirm" autocomplete="new-password" placeholder="(választható)" />
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Saját profil mentése</button>
                    <button type="reset" class="btn-secondary">Módosítások visszaállítása</button>
                </div>
            </form>
        </section>
    </main>

    <div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
