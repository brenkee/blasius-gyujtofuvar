# Admin beállításfelület

Az új, dinamikus konfigurációs felület a `public/settings.html` oldalon érhető el, és csak admin jogosultsággal rendelkező felhasználók számára jelenik meg a fő alkalmazás hamburger menüjében. A link új böngészőablakban nyílik meg.

## Fő funkciók

- A `config/config.json` aktuális tartalmát AJAX-on keresztül tölti be, a mezők automatikusan a JSON felépítése alapján generálódnak.
- A kliens megkülönbözteti a string, number, boolean, tömb és objektum típusokat, a megfelelő szerkesztőelemekkel (toggle kapcsoló, színválasztó, tömb elem hozzáadás/törlés, összecsukható blokkok stb.).
- A módosításokat valós időben követi, a kártyák és mezők vizuálisan jelzik az eltéréseket az eredeti konfigurációhoz képest.
- Ütközés esetén (ha időközben más módosította a konfigurációt) a felület figyelmeztet, és nem engedi menteni a régi verziót.
- Mentés előtt a backend automatikus biztonsági mentést készít `config_backup.json` néven. A felületen külön „Visszaállítás” gomb tölti vissza ezt a mentést, ha elérhető.
- 30 másodpercenként meta-lekérdezés figyeli, hogy a konfiguráció változott-e, és szükség esetén figyelmeztet.

## Backend végpont

A `api/admin/update-config.php` kezeli a beállítások betöltését és mentését.

- `GET /api/admin/update-config.php` – a teljes konfigurációt, a hash értéket és a módosítás idejét adja vissza.
- `GET /api/admin/update-config.php?meta=1` – csak metaadat (hash, timestamp, backup státusz).
- `POST /api/admin/update-config.php` – `mode: "update"` esetén frissíti a config.json-t (először biztonsági mentés készül), `mode: "restore"` esetén visszaállítja a backupot.
- A végpont admin jogosultságot és érvényes CSRF tokent igényel.

A szerver oldali validáció biztosítja, hogy csak a meglévő, ismert kulcsok módosulhatnak, és a típusok is megegyezzenek az aktuális konfigurációval.

## Bővítés

- Új konfigurációs kulcsok felvételéhez elegendő a `config/config.json`-t módosítani; a felület automatikusan megjeleníti az új mezőket.
- Ha új tömb típusú struktúrát vezetsz be, ellenőrizd, hogy az első elem mintaként szolgálhat a későbbi hozzáadásokhoz.
- Szükség esetén a frontenden új mezőtípusok a `public/settings.js` fájlban, a `renderPrimitiveField` és `create*Control` segédfüggvények bővítésével adhatók hozzá.

## Hibakeresés

- A böngésző konzol jelzi a sikertelen hálózati kéréseket vagy validációs hibákat.
- A szerver logjaiban a `config_*` hibakódok (pl. `config_invalid`, `lock_failed`) mutatják, hogy hol akadt el a mentés.
