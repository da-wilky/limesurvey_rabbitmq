FROM php:8
RUN apt-get update && apt-get install -y \
  libzip-dev \
  zip \
  && docker-php-ext-install zip sockets \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && rm composer-setup.php

RUN mkdir /Lime_RabbitMQ
WORKDIR /Lime_RabbitMQ

CMD /bin/bash start.sh