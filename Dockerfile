FROM jenkins/jenkins:lts-jdk11
USER root
RUN apt-get update && apt-get install -y rsync zip unzip apt-transport-https lsb-release ca-certificates software-properties-common && \
    curl -sL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php.gpg  && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list && \
    curl -sL https://deb.nodesource.com/setup_10.x | bash && \
    apt-get update && \
    apt-get install -y \
        php7.4-cli \
        php7.4-opcache \
        php7.4-bcmath \
        php7.4-bz2 \
        php7.4-cli \
        php7.4-common \
        php7.4-curl \
        php7.4-gd \
        php7.4-gmp \
        php7.4-intl \
        php7.4-json \
        php7.4-mbstring \
        php7.4-pgsql \
        php7.4-sqlite3 \
        php7.4-readline \
        php7.4-xml \
        php7.4-zip \
        php7.4-soap \
        php7.4-ldap \
        php7.4-mysql \
        php7.4-mysqli \
        php7.4-redis \
        php7.4-amqp \
        php7.4-ssh2 \
        php7.4-imagick \
        nodejs \
        npm \
        gettext && \
    apt-get autoremove -y && apt-get clean && \
    curl -sL https://getcomposer.org/installer | php -- --1 --filename=composer --install-dir=/usr/local/bin && chmod 0755 /usr/local/bin/composer && \
    curl -sL https://github.com/theseer/phpdox/releases/download/0.12.0/phpdox-0.12.0.phar -o /usr/local/bin/phpdox && chmod 0755 /usr/local/bin/phpdox && \npm install -g less
USER jenkins