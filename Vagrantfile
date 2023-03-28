# -*- mode: ruby -*-
# vi: set ft=ruby :

require "yaml"
require "json"
require "resolv"

unless Vagrant.has_plugin?("vagrant-vbguest")
  puts 'Installing vagrant-vbguest Plugin...'
  system('vagrant plugin install vagrant-vbguest')
end
unless Vagrant.has_plugin?("vagrant-disksize")
  puts 'Installing vagrant-disksize Plugin...'
  system('vagrant plugin install vagrant-disksize')
end
unless Vagrant.has_plugin?("vagrant-hostmanager")
  puts 'Installing vagrant-hostmanager Plugin...'
  system('vagrant plugin install vagrant-hostmanager')
end

_config = {
  "common_dirs" => {},
  "guests" => {
    "ispconfig" => {
      "disksize" => "20GB",
      "extra_vars" => {
        "ispconfig_api" => {
          "user" => "hostprovider",
          "password" => "Passw0rd"
        },
        "telegraf_agent_package_method" => "online",
        "telegraf_agent_package_state" => "latest",
        "default_php_open_basedir" => 'None'
      },
      "gui" => false,
      "hostname" => "ispconfig.devcenter.box",
      "ip" => "33.33.69.100",
      "name" => "ISPConfig3",
      "profile" => "ispconfig",
      "sites" => {},
      "vbox" => {
        "memory" => 1024,
        "vram" => 32,
        "cpus" => 2,
        "natdnshostresolver1" => "off",
        "natdnsproxy1" =>"off",
        "natdnspassdomain1" =>"off",
        "uartmode1" => "disconnected",
        "cpuexecutioncap" => 90,
        "clipboard" => "bidirectional"
      }
    },
  }, 
  "user" => {
    "login" => "vagrant",
    "password" => '$6$dSL9HjGPaP3HcQud$/1RGtiyz48/YK0xx79WCOkyOwqFOzOFN.xxrAAzmLNHy6k5y90vZU4D3eIe3PpIOCt.PYt1Wt5xthZ2ieVaY30'
  }
}
  
begin
  yaml = YAML.load(File.open(File.join(Dir.pwd, "config.yml"), File::RDONLY).read)
  CONF = YAML.load(_config.to_yaml).merge!(yaml)
rescue Errno::ENOENT
  # No config.yml found -- that's OK; just use the defaults.
  CONF = _config
  File.open("config.yml", "w") { |file| file.write(CONF.to_yaml) }
  puts 'No había archivo de configuración, se ha generado uno con la configuración por defecto... revíselo y reaprovisione.'
  exit
end

Vagrant.configure("2") do |config|

  config.vbguest.no_install  = false
  config.vbguest.auto_update = true
  config.vbguest.no_remote   = false
  config.vbguest.installer_options = { running_kernel_modules: ["vboxguest"] }

  config.hostmanager.enabled = true
  config.hostmanager.manage_host = true
  config.hostmanager.manage_guest = true
  config.hostmanager.ignore_private_ip = false
  config.hostmanager.include_offline = true
  
  CONF["guests"].each do |guest,cfg|
    config.vm.define guest do |machine|     
      gitsrv = CONF["git"]
      common_dirs = CONF['common_dirs']
      machine.vm.provider :virtualbox do |vb|
        vb.name = cfg['name']
        vb.gui = cfg['gui']
        vb.customize ["storageattach", :id, "--storagectl", "SATA Controller", "--port", "1", "--device", "0", "--type", "dvddrive", "--medium", "emptydrive"]
        cfg["vbox"].each do |parameter,value|
          if (!value.to_s.empty?)
            vb.customize ["modifyvm", :id, "--" + parameter, value]
          end
        end
      end
      machine.vm.box = "debian/buster64"
      machine.vm.hostname = cfg["hostname"]
      machine.vm.network :private_network, ip: cfg["ip"]
      machine.ssh.forward_agent = true
      machine.hostmanager.aliases = []
      machine.disksize.size = cfg['disksize']
      machine.vm.provision "bootstrap", type: "shell" do |s|
        # s.args = [gitsrv["server"], gitsrv["port"], Resolv.getaddress(git_server)]
        s.path = 'shell/bootstrap.sh'
      end
      machine.vm.provision :ansible_local do |ansible|
        ansible.become = true
        ansible.config_file = "ansible.cfg"
        ansible.galaxy_role_file = "requirements/" + cfg["profile"] + ".yml"
        ansible.galaxy_roles_path = "/vagrant/roles"
        ansible.galaxy_command = "sudo ansible-galaxy install --role-file=%{role_file} --roles-path=%{roles_path} --ignore-errors"
        ansible.playbook = "playbooks/" + cfg["profile"] + ".yml"
        ansible.limit = "all"
        ansible.install_mode = "pip3"
        ansible.extra_vars = { ansible_python_interpreter: "/usr/bin/python3" }
        cfg["extra_vars"].each do |confitem, value|
          ansible.extra_vars.store(confitem, value)
        end
      end
      common_dirs.each do |target, source|
        if source
          machine.vm.synced_folder source, target, id: source #:nfs => CONF['nfs'], :linux_nfs_options => CONF['linux_nfs_options'], :mount_options => CONF["mount_options"], :create => true
        end
      end
      if cfg.has_key?('sites')
        cfg['sites'].each do |name,site|
          machine.vm.synced_folder site['root_dir'], '/mnt/'+name, id: site['root_dir'] #:nfs => CONF['nfs'], :linux_nfs_options => CONF['linux_nfs_options'], :mount_options => CONF["mount_options"], :create => true
          machine.hostmanager.aliases.append(name)
        end
      end
      machine.hostmanager.provision :hostmanager
    end
  end
end
