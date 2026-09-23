# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------
# Imagen 2: Cliente Flutter (SDK 3.41.9 / Dart 3.11.5)
# Uso previsto: `flutter test`, `flutter analyze` y servir la app web
# contra la API de Laravel. Los builds nativos Android/iOS y el
# emulador se siguen haciendo con el SDK instalado en el host.
# ---------------------------------------------------------------
FROM ubuntu:24.04

ARG FLUTTER_VERSION=3.41.9
ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    FLUTTER_HOME=/opt/flutter \
    PUB_CACHE=/home/app/.pub-cache \
    PATH=/opt/flutter/bin:/opt/flutter/bin/cache/dart-sdk/bin:/home/app/.pub-cache/bin:$PATH

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip xz-utils zip ca-certificates \
        libglu1-mesa openssh-client \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# El UID 1000 ya existe en ubuntu:24.04 (usuario y grupo "ubuntu"): se renombran ambos.
RUN if id -u 1000 >/dev/null 2>&1; then \
        old_user="$(id -un 1000)"; old_group="$(id -gn 1000)"; \
        usermod -l app -d /home/app -m "$old_user" && groupmod -n app "$old_group"; \
    else groupadd -g ${GID} app && useradd -u ${UID} -g ${GID} -m -s /bin/bash app; fi

RUN git clone --depth 1 --branch ${FLUTTER_VERSION} \
        https://github.com/flutter/flutter.git ${FLUTTER_HOME} \
    && git config --global --add safe.directory ${FLUTTER_HOME} \
    && chown -R app:app ${FLUTTER_HOME}

USER app

# Precalienta la caché del SDK para que el primer `up` no tarde minutos.
RUN flutter config --no-analytics --no-cli-animations \
    && flutter precache --web --universal \
    && flutter doctor -v || true

WORKDIR /app

EXPOSE 8080

CMD ["flutter", "run", "-d", "web-server", "--web-hostname=0.0.0.0", "--web-port=8080"]
