# Közúti sorrendezés be- és kikapcsolása

A kliensoldali OSRM-alapú közúti sorrendezés alapértelmezetten aktív. A viselkedés a `config/config.json` (vagy környezet-specifikus
felülírás) `routing` szekciójában állítható.

## Beállítások

```jsonc
"routing": {
  "road_sort": {
    "enabled": true,            // hamisra állítva visszakapcsolhatod a korábbi (haversine) rendezést
    "return_to_origin": false,  // true esetén a teljes útvonal visszatér a központi indulási ponthoz
    "cache_limit": 48,          // lokális gyorsítótár mérete (útvonal-kombinációk száma)
    "storage": "local"         // "local" vagy "session" – hova kerüljön a cache
  },
  "osrm": {
    "base_url": "https://router.project-osrm.org",
    "profile": "driving",
    "request_timeout_ms": 8000,
    "trip": { "enabled": true, "max_size": 90 },
    "table": { "enabled": true, "max_size": 90 },
    "route": { "enabled": true, "max_points": 90 }
  }
}
```

* **Kikapcsolás:** állítsd a `road_sort.enabled` értékét `false`-ra, majd töltsd újra a felületet. A lista és a térkép ekkor az
  eredeti (légvonal alapú) logikát használja.
* **Visszatérés a központba:** ha a kör végén is a „Központ”-hoz szeretnél visszaérni, állítsd `road_sort.return_to_origin = true`.
* **Gyorsítótár törlése:** a kliens a böngésző `localStorage`/`sessionStorage` területén tárolja az útvonalakat. A `storage`
  kulccsal válthatsz ideiglenes (session) tárolóra, vagy manuálisan törölheted a böngészőben a `road_route_cache_v1` kulcsot.

A beállítás módosítása után elég frissíteni az oldalt; szerver újraindítására nincs szükség.
