#!/bin/bash
set -e

# Wait for DB
echo "Waiting for database..."
while ! php -r "new mysqli('db','ossn','ossn_pass','ossn',3306);" 2>/dev/null; do
    sleep 1
done
echo "Database ready."

# Only run installation if not already done
if [ ! -f /var/www/html/configurations/ossn.config.db.php ]; then
    echo "Running first-time installation..."

    # Import schema
    SITE_VERSION=$(php -r "\$x=simplexml_load_file('/var/www/html/opensource-socialnetwork.xml'); echo \$x->stable_version;")
    SECRET=$(php -r "echo substr(md5('ossn'.bin2hex(random_bytes(6))),3,8);")

    sed -e "s/<<owner_email>>/admin@localhost/" \
        -e "s/<<notification_email>>/noreply@localhost/" \
        -e "s/<<sitename>>/My Social Network/" \
        -e "s/<<secret>>/$SECRET/" \
        -e "s/<<siteversion>>/$SITE_VERSION/" \
        /var/www/html/installation/sql/opensource-socialnetwork.sql | \
        php -r "
            \$c=new mysqli('db','ossn','ossn_pass','ossn',3306);
            \$sql=file_get_contents('php://stdin');
            \$c->multi_query(\$sql);
            while(\$c->next_result()){;}
            echo 'Schema imported'.PHP_EOL;
        "

    # Write DB config
    cat > /var/www/html/configurations/ossn.config.db.php << 'DBEOF'
<?php
$Ossn->host = 'db';
$Ossn->port = '3306';
$Ossn->user = 'ossn';
$Ossn->password = 'ossn_pass';
$Ossn->database = 'ossn';
DBEOF

    # Write site config
    cat > /var/www/html/configurations/ossn.config.site.php << 'SITEEOF'
<?php
$Ossn->url = 'http://localhost:6050/';
$Ossn->userdata = '/var/ossn_data/';
SITEEOF

    # Create admin user directly via SQL with proper password hash
    php -r "
        \$salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        \$hash = password_hash('admin123' . \$salt, PASSWORD_BCRYPT);
        \$t = time();

        \$c = new mysqli('db','ossn','ossn_pass','ossn',3306);
        \$stmt = \$c->prepare('INSERT INTO ossn_users (first_name,last_name,email,username,type,password,salt,activation,last_login,last_activity,time_created,time_updated) VALUES (?,?,?,?,?,?,?,?,0,0,?,?)');
        \$fn='Site'; \$ln='Admin'; \$em='admin@localhost'; \$un='admin'; \$tp='admin'; \$ac='';
        \$stmt->bind_param('ssssssssii', \$fn, \$ln, \$em, \$un, \$tp, \$hash, \$salt, \$ac, \$t, \$t);
        if(\$stmt->execute()){
            \$guid = \$c->insert_id;

            // Add birthdate, gender, and password_algorithm as entity metadata
            \$c->query(\"INSERT INTO ossn_entities (owner_guid,type,subtype,time_created,time_updated,permission,active) VALUES (\$guid,'user','gender',\$t,\$t,2,1)\");
            \$eguid = \$c->insert_id;
            \$c->query(\"INSERT INTO ossn_entities_metadata (guid,value) VALUES (\$eguid,'male')\");

            \$c->query(\"INSERT INTO ossn_entities (owner_guid,type,subtype,time_created,time_updated,permission,active) VALUES (\$guid,'user','birthdate',\$t,\$t,2,1)\");
            \$eguid = \$c->insert_id;
            \$c->query(\"INSERT INTO ossn_entities_metadata (guid,value) VALUES (\$eguid,'01/01/1990')\");

            \$c->query(\"INSERT INTO ossn_entities (owner_guid,type,subtype,time_created,time_updated,permission,active) VALUES (\$guid,'user','password_algorithm',\$t,\$t,2,1)\");
            \$eguid = \$c->insert_id;
            \$c->query(\"INSERT INTO ossn_entities_metadata (guid,value) VALUES (\$eguid,'bcrypt')\");

            echo 'Admin account created (guid='.\$guid.')'.PHP_EOL;
        } else {
            echo 'Failed: '.\$stmt->error.PHP_EOL;
        }
        \$c->close();
    "

    # Mark as installed
    touch /var/www/html/installation/INSTALLED

    # Generate cache
    php -r "
        define('OSSN_ALLOW_SYSTEM_START', true);
        require_once('/var/www/html/system/start.php');
        ossn_create_cache();
        echo 'Cache created'.PHP_EOL;
    "

    chown -R www-data:www-data /var/www/html/configurations /var/ossn_data
    echo "Installation complete!"
fi

# Start Apache
exec apache2-foreground
