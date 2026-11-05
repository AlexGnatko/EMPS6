#!/bin/bash

export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a

### General APT installation

CurrentDir=$(pwd)

apt update
apt -y install software-properties-common

### Install MC (optional)

apt -y install mc

apt -y install perl libnet-ssleay-perl openssl libauthen-pam-perl libpam-runtime libio-pty-perl apt-show-versions

cd $CurrentDir
pwd

### Install smartmontools

apt -y install smartmontools

### Install mysql

apt -y install mysql-server

### Install nginx

apt -y install nginx

### PHP

apt -y install php php-fpm curl php-curl php-gd php-mbstring php-mysqli php-mysqlnd php-zip php-http php-dom php-xml php-exif
apt -y remove apache2

apt -y install ca-certificates curl gnupg lsb-release
curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg
echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list
apt update

for v in 8.2; do
    if apt-cache policy php$v-fpm | grep -q 'Candidate: [0-9]'; then
        apt -y install php$v-cli php$v-fpm php$v-curl php$v-gd php$v-mbstring \
                       php$v-mysql php$v-zip php$v-xml
        apt-cache policy php$v-http | grep -q 'Candidate: [0-9]' && apt -y install php$v-http
        php$v ./configphp.php
    else
        echo "WARNING: php$v not available, skipped"
    fi
done

#apt -y install php7.4-fpm php7.4-curl php7.4-gd php7.4-mbstring php7.4-mysqli php7.4-mysqlnd php7.4-zip php7.4-http php7.4-dom php7.4-xml php7.4-exif

### Install Git

apt -y install git

### Install At without which the worker heartbeat services will not work

apt -y install at

### Install AWStats

apt -y install cron
apt -y install awstats

### Let's Encrypt certbot

apt -y install certbot
apt -y install python3-certbot-nginx

### NPM

apt -y install npm

### Run the PHP installer script

php ./install.php

chmod +x ~/sysinfo.sh && ~/sysinfo.sh
