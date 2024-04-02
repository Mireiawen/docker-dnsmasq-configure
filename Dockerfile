# Source images
FROM "bitnami/minideb:bookworm" as minideb
FROM "composer:latest" as composer

# Build the composer application
FROM "php:8-cli" AS build-composer

# Install the installer script
COPY --from=minideb \
	"/usr/sbin/install_packages" \
	"/usr/sbin/install_packages"

# Install the composer
COPY --from=composer \
	"/usr/bin/composer" \
	"/usr/bin/composer"

# Install Git for composer
RUN install_packages "git"

# Install Zip for composer
RUN \
	install_packages "zlib1g-dev" "libzip-dev" "unzip" && \
	docker-php-ext-install "zip"

# Install the Gettext extension
RUN docker-php-ext-install \
	"gettext"

# Install YAML extension
RUN install_packages \
	"libyaml-dev"

RUN pecl "install" \
	"yaml"

RUN docker-php-ext-enable \
	"yaml"

COPY "." "/app/"

# Install the composer requirements
WORKDIR "/app"
RUN composer "install" --no-interaction --no-progress \
	--no-dev

# The main image
FROM "php:8-cli"
SHELL [ "/bin/bash", "-e", "-u", "-o", "pipefail", "-c" ]

LABEL name="dnsmasq-config"
LABEL maintainer="Mira 'Mireiawen' Manninen"

# Install the installer script
COPY --from=minideb \
	"/usr/sbin/install_packages" \
	"/usr/sbin/install_packages"

# Install the Gettext extension
RUN docker-php-ext-install \
	"gettext"

# Install YAML extension
RUN install_packages \
	"libyaml-dev"

RUN pecl "install" \
	"yaml"

RUN docker-php-ext-enable \
	"yaml"

# Copy the application
COPY --from=build-composer \
	"/app" \
	"/app"

WORKDIR "/app"
ENTRYPOINT [ "php" ]
CMD [ "configure.php" ]
