#!/bin/bash

KEYFILE=/etc/apt/trusted.gpg.d/ansible.gpg

if [[ ! -f $KEYFILE ]]; then
    DEBIAN_FRONTEND=noninteractive  
    apt-get -qq update
    apt-get install -qq git curl gpg
    # curl -fsSL "http://keyserver.ubuntu.com/pks/lookup?op=get&search=0x93C4A3FD7BB9C367" > $KEYFILE
    gpg --keyserver "hkps://keyserver.ubuntu.com:443" --recv-keys 93C4A3FD7BB9C367
    gpg --yes --output "$KEYFILE" --export "93C4A3FD7BB9C367"

    echo "deb http://ppa.launchpad.net/ansible/ansible/ubuntu bionic main" | tee -a /etc/apt/sources.list.d/ansible.list
    apt-get -qq update
    apt-get install -qq ansible
else
    echo "Ansible already installed"
fi
