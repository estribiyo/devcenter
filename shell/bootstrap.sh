#!/bin/bash

GITSRVR=${1}
GITPORT=${2}
GITIP=${3}

# Buscamos la IP del sevidor git
if [ -n "${GITIP}" ]; then
    grep -q "${GITSRVR}" /etc/hosts
    if [ $? -ne 0 ]
    then
        echo "${GITIP}  ${GITSRVR}" >> /etc/hosts
    else
        sed -i -r "s/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+( +${GITSRVR})/${GITIP}\1/" /etc/hosts
    fi
else
    echo "No IP given for GIT hostname (${GITSRVR})"
fi

if [ ! -d /root/.ssh ]
then
    mkdir /root/.ssh
fi

# Copiamos la clave privada y la configuración para la conexión al servidor
cp /vagrant/files/ssh-config /root/.ssh/config
cp /vagrant/files/ssh-key /root/.ssh/botkey
chmod 600 /root/.ssh/botkey

# Recogemos la clave SSH para añadirla a known_hosts
SSHKEY=`ssh-keyscan -trsa -p ${GITPORT} ${GITSRVR}`
if [ -f /root/.ssh/known_hosts ]
then
    grep -q "$SSHKEY" /root/.ssh/known_hosts || echo "$SSHKEY" >> /root/.ssh/known_hosts
else
    echo "$SSHKEY" >> /root/.ssh/known_hosts
fi

# Añadimos el repositorio y clave para poder actualizar ansible a una versión superior
# que soporte/gestione correctamente los espacios de nombre en los roles.
echo "deb http://ppa.launchpad.net/ansible/ansible/ubuntu bionic main" > /etc/apt/sources.list.d/ansible.list
TEST=$(apt-key list 2> /dev/null | grep 93C4A3FD7BB9C367)
if [[ ! $TEST ]]; then
    # echo "Missing - need to run --fetch-keys or --recv-keys"
    apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 93C4A3FD7BB9C367
fi

# Actualizamos la paquetería
DEBIAN_FRONTEND=noninteractive
apt-get -qq update
# Y nos aseguramos de que git esté instalado (y ansible actualizado)
apt-get install -qq git ansible
