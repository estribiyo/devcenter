#!/usr/bin/php
<?php
/**
 * Delete directories recursively.
 *
 * @param mixed $dir
 */
function recurseRmdir($dir)
{
    $files = array_diff(scandir($dir), array(
        '.',
        '..'
    ));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? recurseRmdir("$dir/$file") : unlink("$dir/$file");
    }
    $ret = null;
    try {
        $ret = rmdir($dir);
    } catch (Error $x) {
        echo $x->getMessage();
    }
    return $ret;
}

/**
 * [make_webdir description]
 * @param  [type] $domain                [description]
 * @param  [type] $root               [description]
 * @return [type]          [description]
 */
function make_webdir($domain, $root)
{
    $tmp = explode('/', $root);
    $bdir = implode('/', array_slice($tmp, 0, count($tmp) - 1));
    if (!file_exists($bdir)) {
        if (!mkdir($bdir, 0777, true)) {
            die("Fallo al crear las carpetas...\n");
        }
    }

    if (!file_exists($root)) {
        mkdir($root, 0775, true);
        echo "\tCreating and mounting ($root)\n";
        shell_exec("mount -t none -o bind /mnt/{$domain} $root");
    } else {
        try {
            // echo "Deleting original web folder contents ($root)\n";
            shell_exec("chattr -i $root");
            shell_exec("umount $root/log");
            recurseRmdir($root);
            echo "\tMounting ($root)\n";
            shell_exec("mount -t none -o bind /mnt/{$domain} $root");
        } catch (Error $fio) {
            echo $fio->getMessage();
        }
    }
    $must_exist = array('log' => "*.log", 'tmp' => "", 'ssl' => "*.crt\n*.key", 'error' => '');
    foreach ($must_exist as $d => $gitignore) {
        if (!file_exists("$root/$d")) {
            mkdir("$root/$d");
            file_put_contents("$root/$d/.gitignore", $gitignore);
        }
    }
}

/**
 * [create_cert description]
 *
 * @param mixed $domain
 * @param mixed $dir
 * @param mixed $country
 * @param mixed $state
 * @param mixed $locality
 * @param mixed $organization
 */
function create_cert($domain, $dir, $country = 'ES', $state = 'None', $locality = 'None', $organization = 'None')
{
    $key = "{$dir}/{$domain}.key";
    $crt = "{$dir}/{$domain}.crt";
    if (!file_exists($dir) || !file_exists($key) || !file_exists($crt)) {
        shell_exec(
            "openssl req " .
                "-x509 " .
                "-nodes " .
                "-days 365 " .
                "-newkey rsa:2048 " .
                "-subj \"/C=$country/ST=$state/L=$locality/O=$organization/CN=$domain\" " .
                "-keyout $key " .
                "-out $crt > /dev/null 2>&1"
        );
        // $subject = array(
        //     "commonName" => $domain,
        //     "countryName" => 'ES',
        //     "stateOrProvinceName" => "Provincia",
        //     "localityName" => "Localidad",
        //     "organizationName" => "Organizacion",
        //     "organizationalUnitName" => "Dpto. Web",
        //     "emailAddress" => "me@here.com"
        // );
        // $config = array('digest_alg' => 'sha1', 'private_key_bits' => 1024, 'encrypt_key' => true);
        // // Generate a new private (and public) key pair
        // $private_key = openssl_pkey_new($config);
        // // Generate a certificate signing request
        // $csr = openssl_csr_new($subject, $private_key, $config);
        // // Generate self-signed EC cert
        // $x509 = openssl_csr_sign($csr, null, $private_key, 365, $config);
        // openssl_x509_export_to_file($x509, "{$dir}/ssl/{$domain}.crt");
        // // openssl_x509_export($x509, $certificado);
        // openssl_pkey_export_to_file($private_key, "{$dir}/ssl/{$domain}.key");
        // // openssl_pkey_export($private_key, $clave_privada);
        // //
    }
}

class HostProvider
{
    public $session_id = null;
    public $country = 'ES';
    public $client = null;
    public $reseller_id = null;

    private $soap_uri = 'https://localhost:8080/remote/';
    private $soap_location = 'index.php';

    /**
     * Class construction.
     *
     * @param integer $reseller_id Reseller ID.
     */
    public function __construct($reseller_id = null)
    {
        $this->reseller_id = $reseller_id;
        $this->client = new SoapClient(null, array(
            'location' => $this->soap_uri . $this->soap_location,
            'uri' => $this->soap_uri,
            'stream_context' => stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false
                )
            ))
        ));
    }

    /**
     * Accessing the webservice.
     *
     * @param  string $username User name, it mus exist as 'external user'.
     * @param  string $password Unencrypted password.
     * @return integer Session ID to reuse on other calls.
     */
    public function login($username, $password)
    {
        $this->session_id = $this->client->login($username, $password);
        echo "Logged into remote server sucessfully as '$username'. The SessionID is $this->session_id\n";
        return $this->session_id;
    }

    /**
     * ISP Client creation. If exists, recovers their ID.
     *
     * @param string $client_username Client username (for login).
     * @param string $company_name Company full name.
     * @param string $email Company/contact e-mail.
     * @param string $contact_name Contact name (Webmaster as default).
     * @return integer Client ID.
     */
    public function isp_client($client_username, $company_name, $email, $contact_name = 'Webmaster')
    {
        $msgdisplay = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', "\n{$company_name}");
        $client_id = null;
        try {
            $client_data = $this->client->client_get_by_username($this->session_id, $client_username);
            $client_id = $client_data['client_id'];
        } catch (\SoapFault $e) {
            $msg = $e->getMessage();
            if (strpos('There is no user account for this user name.', $msg) !== false) {
                try {
                    $msgdisplay .= " -> Nuevo cliente";
                    $client_id = $this->client->client_add($this->session_id, $this->reseller_id, array(
                        'city' => '',
                        'company_name' => $company_name,
                        'contact_name' => $contact_name,
                        'country' => $this->country,
                        'created_at' => '',
                        'customer_no' => '',
                        'default_dbserver' => '',
                        'default_dnsserver' => '',
                        'default_mailserver' => '',
                        'default_webserver' => '',
                        'email' => $email,
                        'fax' => '',
                        'icq' => '',
                        'internet' => '',
                        'language' => 'es',
                        'limit_client' => '',
                        'limit_cron_frequency' => '',
                        'limit_cron_type' => 'url',
                        'limit_cron' => '',
                        'limit_database' => '',
                        'limit_dns_record' => '',
                        'limit_dns_slave_zone' => '',
                        'limit_dns_zone' => '',
                        'limit_fetchmail' => '',
                        'limit_ftp_user' => '',
                        'limit_mailalias' => '',
                        'limit_mailaliasdomain' => '',
                        'limit_mailbox' => '',
                        'limit_mailcatchall' => '',
                        'limit_maildomain' => '',
                        'limit_mailfilter' => '',
                        'limit_mailforward' => '',
                        'limit_mailquota' => '',
                        'limit_mailrouting' => '',
                        'limit_shell_user' => '',
                        'limit_spamfilter_policy' => '',
                        'limit_spamfilter_user' => '',
                        'limit_spamfilter_wblist' => '',
                        'limit_traffic_quota' => '',
                        'limit_web_aliasdomain' => '',
                        'limit_web_domain' => '',
                        'limit_web_ip' => '',
                        'limit_web_quota' => '',
                        'limit_web_subdomain' => '',
                        'limit_webdav_user' => '',
                        'mobile' => '',
                        'notes' => '',
                        'parent_client_id' => '',
                        'password' => 'Ch@ngem3!',
                        'ssh_chroot' => 'no,jailkit',
                        'state' => '',
                        'street' => '',
                        'telephone' => '',
                        'template_additional' => '',
                        'template_master' => '',
                        'username' => $client_username,
                        'usertheme' => '',
                        'vat_id' => '',
                        'web_php_options' => 'no,fast-cgi,php-fpm,mod',
                        'zip' => '',
                    ));
                } catch (\SoapFault $ee) {
                    $msgdisplay .= ": " . $ee->getMessage() . "\n";
                }
            }
        }
        $coletilla = $client_id ? "ID: $client_id\n" : "\n";
        $relleno = 78 - strlen($msgdisplay) - strlen($coletilla);
        echo $msgdisplay . " " . str_repeat("_", $relleno) . " " . $coletilla;
        return $client_id;
    }

    /**
     * Get or create ISP Domain.
     *
     * @param string $domain Domain name.
     * @param integer $client_id Client ID.
     * @param mixed $server
     * @return integer Domain ID.
     */
    public function isp_domain($client_id, $domain, $server = 1)
    {
        $domain_id = null;
        try {
            $domain_data = $this->client->sites_web_domain_get($this->session_id, array(
                'domain' => $domain
            ));
            if (!empty($domain_data)) {
                $domain_id = $domain_data[0]['domain_id'];
            }
        } catch (\SoapFault $e) {
            echo $domain . ": " . $e->getMessage() . "\n";
        }
        if (!$domain_id) {
            $domain_id = $this->client->sites_web_domain_add($this->session_id, $client_id, array(
                'active' => 'y',
                'allow_override' => 'All',
                'apache_directives' => '',
                'backup_copies' => 0,
                'backup_interval' => '',
                'cgi' => 'y',
                'custom_php_ini' => "display_errors=on\nerror_reporting=E_ALL\nmemory_limit=512M\nmax_execution_time=600\nsession.save_path=/var/lib/php/sessions\npost_max_size=100M\nupload_max_filesize=100M\n",
                'domain' => $domain,
                'errordocs' => 1,
                'hd_quota' => -1,
                'http_port' => '80',
                'https_port' => '443',
                'ip_address' => '*',
                'is_subdomainwww' => 1,
                'parent_domain_id' => '0',
                'php_open_basedir' => 'none',
                'php' => 'php-fpm',
                'pm_max_requests' => 0,
                'pm_process_idle_timeout' => 60,
                'pm' => 'ondemand',
                'redirect_path' => '',
                'redirect_type' => '',
                'ruby' => 'n',
                'server_id' => $server,
                'ssi' => 'y',
                'ssl_action' => '',
                'ssl_bundle' => '',
                'ssl_cert' => '',
                'ssl_country' => $this->country,
                'ssl_domain' => $domain,
                'ssl_key' => '',
                'ssl_locality' => '',
                'ssl_organisation_unit' => '',
                'ssl_organisation' => '',
                'ssl_request' => '',
                'ssl_state' => '',
                'ssl' => 'y',
                'stats_password' => '',
                'stats_type' => '',
                'subdomain' => '0',
                'suexec' => 'y',
                'system_group' => '',
                'system_user' => '',
                'traffic_quota_lock' => 'n',
                'traffic_quota' => -1,
                'type' => 'vhost',
                'vhost_type' => 'name',
            ));
        }
        //echo "\tDomain: $domain_id - $domain\n";
        return $domain_id;
    }

    /**
     * Get or create ISP Database.
     *
     * @param string $database Database name.
     * @param integer $domain_id Domain ID.
     * @param integer $client_id Client ID.
     * @param string $password Password for database.
     * @param string $dbuser User to use database.
     * @param mixed $sqlfile
     * @return integer Database ID.
     */
    public function isp_database($client_id, $domain_id, $database, $dbuser = 'dbuser', $password = 'Ch@ng3m3!', $sqlfile = '')
    {
        $dbuser_id = null;
        try {
            $dbuser_id = $this->client->sites_database_user_get($this->session_id, array(
                'database_user' => $dbuser
            ));
        } catch (\Error $e) {
            echo "\t" . $e->getMessage();
        }
        if (!$dbuser_id) {
            try {
                $dbuser_id = $this->client->sites_database_user_add($this->session_id, $client_id, array(
                    'server_id' => 1,
                    'database_user' => $dbuser,
                    'database_password' => $password,
                ));
            } catch (\Error $e) {
                echo "\t" . $e->getMessage();
            }
        }
        $database_id = null;
        try {
            $database_data = $this->client->sites_database_get($this->session_id, array(
                'database_name' => $database
            ));
            if (!empty($database_data)) {
                $database_id = $database_data[0]['database_id'];
            }
        } catch (\Error $e) {
            echo "\t" . $e->getMessage();
        }
        if (!$database_id) {
            // echo "    Creating database...\n";
            $database_id = $this->client->sites_database_add(
                $this->session_id,
                $client_id,
                array(
                    'active' => 'y',
                    'backup_copies' => '0',
                    'backup_interval' => '',
                    'database_charset' => 'utf8',
                    'database_name' => $database,
                    'database_quota' => -1,
                    'database_password' => $password,
                    'database_ro_user_id' => '',
                    'database_user_id' => $dbuser_id,
                    'parent_domain_id' => $domain_id,
                    'remote_access' => 'y',
                    'remote_ips' => '',
                    'server_id' => 1,
                    'type' => 'mysql',
                    'website_id' => $domain_id,
                )
            );
            if ($sqlfile != '') {
                $success = false;
                while (!$success) {
                    sleep(60); // Esperamos a que se cree toda la parafernalia.
                    try {
                        $pdo = new PDO('mysql:host=localhost;dbname=' . $database, $dbuser, $password);
                        echo "\t_________________| Recuperando copia: $sqlfile\n";
                        shell_exec("mysql $database < $sqlfile");
                        $success = true;
                    } catch (\PDOException $pdoex) {
                        $msg = $pdoex->getMessage();
                        if (strpos('Access denied for user', $msg) !== false) {
                            echo "\t_________________| Esperando acceso.: Todavía no se creó el usuario '$dbuser'.\n";
                        } else {
                            echo "\t_________________| Esperando acceso.: $msg\n";
                        }
                    } catch (\Error $err) {
                        die("\t_________________| Error inesperado: " . $err->getMessage() . "\n");
                    }
                }
            }
        }
        return $database_id;
    }

    /**
     * Disconnect WS on destruction.
     */
    public function __destruct()
    {
        if ($this->session_id) {
            // Logout
            if ($this->client->logout($this->session_id)) {
                echo "\nDisconnected.\n";
            }
        }
    }
}

try {
    $args = yaml_parse_file('/vagrant/config.yml');
    if (!isset($args['guests']['ispconfig']['extra_vars']['ispconfig_api'])) {
        die('Missing configuration!');
    } else {
        $sites = $args['guests']['ispconfig']['sites'];
        if (count($sites) > 0) {
            $ispconfig_api = $args['guests']['ispconfig']['extra_vars']['ispconfig_api'];
            $ws = new HostProvider();
            $ws->login($ispconfig_api['user'], $ispconfig_api['password']);
            foreach ($sites as $domain => $v) {
                $client_username = str_replace('@', '.', $v['email']);
                $webroot = isset($v['webroot']) ? $v['webroot'] : 'web';
                $domain_array = explode('.', $domain);
                $dbname = isset($v['dbname']) ? $v['dbname'] : implode('_', array_reverse($domain_array));
                $dbuser = isset($v['dbuser']) ? $v['dbuser'] : substr("dbu{$domain_array[0]}", 0, 16); # substr(implode('_', $domain_array), 0, 16);
                $dbpass = isset($v['dbpass']) && $v['dbpass'] != '' ? $v['dbpass'] : 'Ch@ng3m3!';
                $dbhost = isset($v['dbhost']) ? $v['dbhost'] : 'localhost';
                $intento = 0;
                $isp_client_id = null;
                while ($intento < 3 && $isp_client_id == null) {
                    $intento++;
                    try {
                        $isp_client_id = $ws->isp_client($client_username, $v['company_name'], $v['email']);
                    } catch (\Error $sf) {
                        echo $sf->getMessage();
                    }
                }
                $intento = 0;
                $isp_domain_id = null;
                while ($intento < 3 && $isp_domain_id == null) {
                    $intento++;
                    try {
                        $isp_domain_id = $ws->isp_domain($isp_client_id, $domain);
                        $root = "/var/www/clients/client{$isp_client_id}/web{$isp_domain_id}";
                        make_webdir($domain, $root);
                        create_cert($domain, "$root/ssl", $ws->country, '', '', $v['company_name']);
                        if (isset($v['type'])) {
                            switch ($v['type']) {
                                case 'Joomla':
                                    // Revisamos si existe una configuración Joomla! para DevCenter (extensión .devcenter) y si la hay, sobreescribimos la que haya en web
                                    $origen = $root . '/configuration.php.devcenter';
                                    if (file_exists($origen)) {
                                        $destino = $root . "/{$webroot}/configuration.php";
                                        chmod($origen, 0755);
                                        chmod($destino, 0755);
                                        if (file_exists($origen)) {
                                            echo "\tConfiguración____| Joomla! ($origen)\n";
                                            $cadena = file_get_contents($origen);
                                            $patron = '~(.+)(\$db = )(.+);~';
                                            $sustitucion = '$1$2\'' . $dbname . '\';';
                                            $patron = '~(.+)(\$host = )(.+);~';
                                            $sustitucion = '$1$2\'' . $dbhost . '\';';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\$user = )(.+);~';
                                            $sustitucion = '$1$2\'' . $dbuser . '\';';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\$password = )(.+);~';
                                            $sustitucion = '$1$2\'' . $dbpass . '\';';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\$log_path = )(.+);~';
                                            $sustitucion = '$1$2\'' . $root . "/{$webroot}/log\';";
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\$tmp_path = )(.+);~';
                                            $sustitucion = '$1$2\'' . $root . "/{$webroot}/tmp\';";
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            file_put_contents($origen, $cadena);
                                            @copy($origen, $destino);
                                        }
                                    }
                                    break;
                                case 'Drupal':
                                    // Revisamos si existe una configuración Drupal para DevCenter (extensión .devcenter) y si la hay, sobreescribimos la que haya en web
                                    $origen = $root . '/settings.php.devcenter';
                                    if (file_exists($origen)) {
                                        $destino = $root . "/{$webroot}/sites/default/settings.php";
                                        chmod($origen, 0755);
                                        chmod($destino, 0755);
                                        if (file_exists($origen)) {
                                            echo "\tConfiguración____| Drupal ($origen)\n";
                                            $cadena = file_get_contents($origen);
                                            $patron = '~(.+)(\'database\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbname . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\'username\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbuser . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\'password\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbpass . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\'host\' => \').+(\',)~';
                                            $sustitucion = '$1$2localhost$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            file_put_contents($origen, $cadena);
                                            @copy($origen, $destino);
                                        }
                                    }
                                    break;
                                case 'Moodle':
                                    break;
                                case 'Prestashop':
                                    // Revisamos si existe una configuración Drupal para DevCenter (extensión .devcenter) y si la hay, sobreescribimos la que haya en web
                                    $origen = $root . '/parameters.php.devcenter';
                                    if (file_exists($origen)) {
                                        $destino = $root . "/{$webroot}/app/config/parameters.php";
                                        chmod($origen, 0755);
                                        if (file_exists($origen)) {
                                            echo "\tConfiguración____| PrestaShop ($origen)\n";
                                            $cadena = file_get_contents($origen);
                                            $patron = '~(.+)(\'database_host\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbhost . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\'databse_user\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbuser . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\'database_name\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbname . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(.+)(\'database_password\' => \').+(\',)~';
                                            $sustitucion = '$1$2' . $dbpass . '$3';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            file_put_contents($origen, $cadena);
                                            copy($origen, $destino);
                                            if (file_exists($destino)) {
                                                chmod($destino, 0755);
                                            } else {
                                                echo "\tEl archivo no existe: $destino";
                                            }
                                        }
                                    }
                                    break;
                                case 'WordPress':
                                    // Revisamos si existe una configuración WordPress para DevCenter (extensión .devcenter) y si la hay, sobreescribimos la que haya en web
                                    $origen = $root . '/wp-config.php.devcenter';
                                    if (file_exists($origen)) {
                                        $destino = $root . "/{$webroot}/wp-config.php";
                                        chmod($origen, 0755);
                                        if (file_exists($origen)) {
                                            echo "\tConfiguración____| WordPress ($origen)\n";
                                            $cadena = file_get_contents($origen);
                                            $patron = '~(define\(\'DB_NAME\', \').+(\'\);)~';
                                            $sustitucion = '$1' . $dbname . '$2';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(define\(\'DB_USER\', \').+(\'\);)~';
                                            $sustitucion = '$1' . $dbuser . '$2';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(define\(\'DB_PASSWORD\', \').+(\'\);)~';
                                            $sustitucion = '$1' . $dbpass . '$2';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            $patron = '~(define\(\'DB_HOST\', \').+(\'\);)~';
                                            $sustitucion = '$1' . $dbhost . '$2';
                                            $cadena = preg_replace($patron, $sustitucion, $cadena);
                                            file_put_contents($origen, $cadena);
                                            copy($origen, $destino);
                                            if (file_exists($destino)) {
                                                chmod($destino, 0755);
                                            } else {
                                                echo "\tEl archivo no existe: $destino";
                                            }
                                        }
                                    }
                                    break;
                                default:
                                    // code...
                                    break;
                            }
                        }
                    } catch (\Error $sf) {
                        echo "\t" . $sf->getMessage() . "\n";
                    }
                }
                $intento = 0;
                $isp_database_id = null;
                $sqls = glob($root . '/sql/*sql');
                $cuantos = count($sqls);
                $sqlfile = '';
                if ($cuantos < 1) {
                    echo "\t_________________| No hay base de datos para recuperar.\n";
                } elseif ($cuantos == '1') {
                    $sqlfile = $sqls[0];
                    // echo "\tRecuperando SQL {$sqls[0]}.\n";
                    // shell_exec("mysql $dbname < {$sqls[0]}");
                    if ($sqlfile != "{$dbname}.sql") {
                    }
                } else {
                    if (file_exists("$root/sql/$dbname.sql")) {
                        // shell_exec("mysql $dbname < $root/sql/$dbname.sql");
                        $sqlfile = "$root/sql/$dbname.sql";
                    } else {
                        echo "\t_________________| Hay " . $cuantos . " ficheros SQL y no hay coincidencia con el nombre de la base de datos. No se recupera ninguno.\n";
                    }
                }
                while ($intento < 3 and $isp_database_id == null) {
                    $intento++;
                    try {
                        $isp_database_id = $ws->isp_database($isp_client_id, $isp_domain_id, $dbname, $dbuser, $dbpass, $sqlfile);
                    } catch (\Error $sf) {
                        echo "\t" . $sf->getMessage() . "\n";
                    }
                }
                echo "\tPunto de montaje_| /mnt/$domain\n";
                echo "\tDocumentRoot_____| $root\n";
                echo "\tBase de datos____| $dbname\n";
                echo "\tUsuario BD_______| $dbuser\n";
                echo "\tPassword_________| $dbpass\n";
            }
        }
    }
} catch (\SoapFault $sfe) {
    echo "\tERROR: " . $sfe->getMessage() . "\n";
    print_r($sfe->getTrace());
} catch (\Error $e) {
    die($e->getMessage() . "\n");
}
