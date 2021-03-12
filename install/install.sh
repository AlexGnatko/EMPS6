#!/bin/bash

### General APT installation

apt update
apt -y install software-properties-common

### Install MC (optional)

apt -y install mc

### Install Webmin

apt -y install perl libnet-ssleay-perl openssl libauthen-pam-perl libpam-runtime libio-pty-perl apt-show-versions python
echo "deb http://download.webmin.com/download/repository sarge contrib" >> /etc/apt/sources.list
cd /root
wget http://www.webmin.com/jcameron-key.asc
apt-key add jcameron-key.asc
apt update
apt -y install apt-transport-https
apt -y install webmin
apt -y install libio-socket-inet6-perl

### Install smartmontools

apt -y install smartmontools

### Install mysql

apt -y install mysql.server

### Install nginx

apt -y install nginx

### PHP

apt -y install php
apt -y install php-fpm
apt -y install php-curl php-gd php-mbstring php-mysqli php-mysqlnd php-zip php-http

### Install Git

apt -y install git

### Install At without which the worker heartbeat services will not work

apt -y install at

### Install AWStats

apt -y install awstats

### Let's Encrypt certbot

add-apt-repository universe
add-apt-repository ppa:certbot/certbot
apt update
apt -y install certbot python-certbot-nginx

### Run the PHP installer script

/usr/bin/php ./install.php
