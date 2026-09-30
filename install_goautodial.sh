#!/bin/bash
set -e

echo "Starting GOautodial automated installation..."

echo "[1/13] Updating System..."
yum -y update

echo "[2/13] Enabling Repositories..."
yum -y install epel-release
yum -y install https://rpms.remirepo.net/enterprise/remi-release-9.rpm 
yum -y install https://download1.rpmfusion.org/free/el/rpmfusion-free-release-9.noarch.rpm 
yum -y install dnf-plugins-core

echo "[3/13] Enabling CRB and Kamailio Repo..."
yum config-manager --set-enabled crb 
yum config-manager --add-repo https://rpm.kamailio.org/centos/kamailio.repo

echo "[4/13] Setting Default PHP..."
yum -y module enable php:remi-7.4

echo "[5/13] Installing Development Tools..."
yum -y groupinstall "Development Tools"

echo "[6/13] Installing Kamailio, MariaDB, PHP and Utilities..."
yum -y --allowerasing install kamailio kamailio-mysql kamailio-websocket kamailio-utils kamailio-tls kamailio-ims kamailio-json mariadb mariadb-server php-mysqlnd php-mcrypt php-devel php-mbstring php-common php-xml php-pear php-cli php-imap php-fpm php-gd php-opcache php-pdo php-process php php-intl php-pear php-sodium php-xmlrpc lame httpd mod_ssl screen crontabs net-tools yum-utils wget nano cpan wget unzip make patch gcc gcc-c++ rsync iptables-services iptables-nft-services iptables-utils xtables-addons curl ImageMagick sox sendmail htop iftop /bin/mailx subversion libpri dkms chkconfig initscripts fail2ban

echo "[7/13] Installing Development Headers..."
yum -y install mariadb-devel gd-devel readline-devel curl-devel newt-devel libxml2-devel kernel-devel sqlite-devel libuuid-devel lame-devel libsrtp-devel dahdi-tools-devel jansson-devel opus-devel libedit-devel portaudio-devel libpri-devel gsm-devel speex-devel kernel-devel-\`uname -r\` kernel-headers-\`uname -r\`

echo "[8/13] Installing Perl Dependencies..."
yum -y install perl-CPAN perl-YAML perl-CPAN-DistnameInfo perl-libwww-perl perl-DBI perl-DBD-MySQL perl-GD perl-Env perl-Term-ReadLine-Gnu perl-SelfLoader perl-open.noarch perl-Net-Server perl-File-Touch perl-Switch perl-Time-Local perl-DBI perl-DBD-mysql perl-Crypt-Eksblowfish perl-File-Which perl-libwww-perl perl-Net-Telnet

echo "[9/13] Installing RTPengine..."
mkdir -p /usr/src/ && cd /usr/src/ 
wget -c https://downloads2.goautodial.org/almalinux/files/RPMS/noarch/ngcp-rtpengine-dkms-11.5.1.49+0~mr11.5.1.49-1.el9.noarch.rpm 
wget -c https://downloads2.goautodial.org/almalinux/files/RPMS/x86_64/ngcp-rtpengine-11.5.1.49+0~mr11.5.1.49-1.el9.x86_64.rpm 
wget -c https://downloads2.goautodial.org/almalinux/files/RPMS/x86_64/ngcp-rtpengine-kernel-11.5.1.49+0~mr11.5.1.49-1.el9.x86_64.rpm
yum -y localinstall ngcp-rtpengine-11.5.1.49+0~mr11.5.1.49-1.el9.x86_64.rpm ngcp-rtpengine-kernel-11.5.1.49+0~mr11.5.1.49-1.el9.x86_64.rpm ngcp-rtpengine-dkms-11.5.1.49+0~mr11.5.1.49-1.el9.noarch.rpm

echo "[10/13] Installing DAHDI..."
cd /usr/src 
wget -c https://downloads.asterisk.org/pub/telephony/dahdi-linux-complete/dahdi-linux-complete-3.4.0%2B3.4.0.tar.gz 
tar zxvf dahdi-linux-complete-3.4.0+3.4.0.tar.gz 
cd dahdi-linux-complete-3.4.0+3.4.0/ 
wget -c https://downloads2.goautodial.org/almalinux/files/dahdi-linux-3.4.0-almalinux9.8-fix.tar.gz 
tar zxvf dahdi-linux-3.4.0-almalinux9.8-fix.tar.gz 
make 
make install 
make install-config
cd linux 
./build_tools/dkms-helper add
cd /etc/dahdi/ 
cp system.conf.sample system.conf
systemctl daemon-reload 
systemctl enable dahdi
modprobe dahdi || true
dahdi_cfg -vv || true

echo "[11/13] Compiling Asterisk-vici..."
cd /usr/src 
wget -c https://downloads2.goautodial.org/almalinux/files/asterisk-18.21.0-vici.tar.gz 
tar zxvf asterisk-18.21.0-vici.tar.gz 
cd asterisk-18.21.0-vici 
./configure --libdir=/usr/lib64 --with-gsm=internal --enable-opus --enable-srtp --with-ssl --enable-asteriskssl --with-pjproject-bundled --with-jansson-bundled 
make menuselect/menuselect 
make menuselect-tree 
make menuselect.makeopts 
menuselect/menuselect --enable app_meetme menuselect.makeopts 
make all 
make install 
make samples

echo "[12/13] Installing GOautodial Web Application..."
cd /usr/src/ 
wget -c https://downloads2.goautodial.org/almalinux/files/RPMS/noarch/goautodial-ce-4.0-1768410003.noarch.rpm 
yum -y localinstall goautodial-ce-4.0-1768410003.noarch.rpm 
cd /usr/src/goautodial 
./install.sh

echo "[13/13] Installation Complete! Rebooting..."
reboot
