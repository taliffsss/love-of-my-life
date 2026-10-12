#!/bin/sh
# Run on the server, in the folder that holds docker-compose.yml and .env.
#   sh deploy.sh [version] [stack]        e.g.  sh deploy.sh 2.0.0 jinelin
set -e

VERSION="${1:-latest}"
STACK="${2:-jinelin}"
IMAGE="taliffsss/love-of-my-life"

[ -f .env ] || { echo "ERROR: .env is missing. Copy .env.docker.example to .env and fill it in." >&2; exit 1; }
[ -f docker-compose.yml ] || { echo "ERROR: docker-compose.yml is missing." >&2; exit 1; }

export LOM_VERSION="$VERSION"
docker pull "$IMAGE:$VERSION"
docker stack deploy -c docker-compose.yml "$STACK" --with-registry-auth
docker service ls --filter "name=${STACK}_"
