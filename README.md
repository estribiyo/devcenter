# DevCenter

Multisite development machine, with [ISPConfig](https://www.ispconfig.org/), [Docker](https://www.docker.com/) and multiple interesting tools.

## Installation

### Intall Vagrant and VirtualBox

- [Download & install VirtualBox](https://www.virtualbox.org)
- [Download & install Vagrant](https://www.vagrantup.com)

#### Intall required plugins

If they aren't installed, Vagrant will install itself (with root privileges).

- `vagrant plugin install vagrant-disksize`
- `vagrant plugin install vagrant-hostmanager`
- `vagrant plugin install vagrant-vbguest`

## Provision

The default configuration file will be generated if it does not exist. The first time we run `vagrant up`, it will write a `config.yml` and exit. We can modify said file to adapt it to our needs and launch `vagrant up` again.

**IMPORTANT**: On Linux machines we need to create a file in `/etc/vbox/networs.conf` (if not exists) and put a valid range in it or disable it:

```
* 0.0.0.0/0 ::/0
```

## Usage

### User login

When you bring up your machine, default user and password are:

- User: `vagrant` (As default, you can configure your own on config.yml)
- Password: `vagrant` (Same as above)

Anyway you can acces as `vagrant` user typing (from provisioning dir): `vagrant ssh`.

### Sites administration

You can acces your admin panel on https://ispconfig.devcenter.box:8080 with user `admin`, and the password stablished on provisioning (displayed as MOTD when login).

**VERY IMPORTANT: It's not intended to be a production machine as it's hacked in some ways that could be insecure for that kind of use.**

### Docker containers

After login you can use Docker to provide some services. By default there are a directory shared between real machine (`docker`) and mounted inside the virtual host (`/mnt/docker`). It's used to store persistent data.

**IMPORTANT**: Note that if the guest machine is under Windows, Linux permissions and links will not work as espected.

#### Portainer

As GUI for Docker, you can install [Portainer](https://www.portainer.io/) as a docker container:

```
docker run -d \
--name=Portainer \
-p 9000:9000 \
--restart always \
-v /var/run/docker.sock:/var/run/docker.sock \
-v /mnt/docker/portainer:/data \
portainer/portainer  
```

And access it in http://ispconfig.devcenter.box:9000

#### Troubleshooting

In some cases, some web have troubles wit `session_start` and need an `php.ini` setting (`session.save_path`) to be modified -you can set it per site-:

```
session.save_path=/tmp
```

## What includes?

- _common_: All base packages (including XFCE as desktop GUI).
- _ispconfig_: ISPConfig3 suite.
- _phpdev_: PHP development libraries and executables (less, sass, mysqltools, webmirror, composer, ...).

[^1]: Must be specified in the `config.yml` file.
