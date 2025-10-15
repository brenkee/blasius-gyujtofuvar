#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
DATA_DIR="${1:-${PROJECT_ROOT}/data/osrm}"
PBF_URL="${OSRM_PBF_URL:-https://download.geofabrik.de/europe/hungary-latest.osm.pbf}"
PBF_NAME="$(basename "${PBF_URL}")"
OSRM_BASENAME="${OSRM_MAP:-${PBF_NAME%.osm.pbf}}"

download(){
  if command -v curl >/dev/null 2>&1; then
    curl -L "$1" -o "$2"
  elif command -v wget >/dev/null 2>&1; then
    wget -O "$2" "$1"
  else
    echo "Neither curl nor wget is available. Please install one of them to download ${1}." >&2
    exit 1
  fi
}
mkdir -p "${DATA_DIR}"
if [ ! -f "${DATA_DIR}/${PBF_NAME}" ]; then
  echo "Downloading ${PBF_URL} ..."
  download "${PBF_URL}" "${DATA_DIR}/${PBF_NAME}"
fi
echo "Extracting OSM data (this can take several minutes)..."
docker run --rm -t -v "${DATA_DIR}:/data" osrm/osrm-backend:latest osrm-extract -p /opt/car.lua "/data/${PBF_NAME}"
echo "Partitioning graph ..."
docker run --rm -t -v "${DATA_DIR}:/data" osrm/osrm-backend:latest osrm-partition "/data/${OSRM_BASENAME}.osrm"
echo "Customizing graph ..."
docker run --rm -t -v "${DATA_DIR}:/data" osrm/osrm-backend:latest osrm-customize "/data/${OSRM_BASENAME}.osrm"
echo "Finished preparing OSRM data in ${DATA_DIR}."
