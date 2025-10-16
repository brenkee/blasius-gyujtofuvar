# Közúti sorrendezés használata

A felület alapértelmezésben a korábbi, légvonalas (haversine) logikát alkalmazza a
körök rendezésére. Közúti útvonal-tervezést csak akkor indít, ha a kör fejlécében
lévő „Rendezés” legördülőből kiválasztod az **Útvonal (OSRM)** opciót.

## UI lépések

1. Nyisd ki a kör fejlécét, majd a „Rendezés” mezőt.
2. Válaszd az **Útvonal (OSRM)** opciót – ekkor a kliens az OSRM `trip` végpontját
   hívja, és az eredményt eltárolja a `round_meta.route_order` mezőben.
3. A megkapott útvonal geometriája automatikusan felrajzolódik a térképre, a fejléc
   alatti állapotsáv pedig mutatja az össztávot/időt.
4. Ha az OSRM nem érhető el, a rendszer visszaáll a légvonalas sorrendre, miközben
   megtartja a legutóbbi sikeres OSRM sorrendet.
5. A **Alapértelmezett (légvonal)** opcióra visszakapcsolva ismét a klasszikus
   rendezés él, a mentett OSRM sorrend érintetlen marad.

## Beállítások

A `config/config.json` fájl `routing` szekciója szabályozza a működést:

```jsonc
"routing": {
  "enabled": true,             // teljes OSRM integráció ki-/bekapcsolása
  "base_url": "http://127.0.0.1:5000",
  "profile": "driving",
  "request_timeout_ms": 8000,
  "return_to_origin": false,   // true esetén az útvonal a kiinduló pontra zár
  "cache": {
    "storage": "local",
    "max_entries": 48,
    "ttl_ms": 21600000
  },
  "healthcheck": {
    "path": "/health",
    "timeout_ms": 2000,
    "cache_ms": 60000,
    "retry_ms": 120000
  }
}
```

- **Kikapcsolás:** állítsd a `routing.enabled` értékét `false`-ra, vagy hagyd üresen a
  `routing.base_url` mezőt. Ettől kezdve csak a légvonalas rendezés lesz elérhető.
- **Visszatérés a légvonalas logikához körönként:** a kör fejlécében válaszd a
  **Alapértelmezett (légvonal)** opciót.
- **Lokális OSRM induláshoz** használd a `docker-compose.osrm.yml` fájlt, részletek a
  [README_ROUTING.md](README_ROUTING.md) dokumentumban találhatók.

Az OSRM-ből származó sorrendek rövid távú gyorsítótárba kerülnek a böngészőben, így a
felesleges lekérések elkerülhetők. A mentett sorrendek az adatbázisban külön oszlopban
(`round_meta.route_order`) tárolódnak.
