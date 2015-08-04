if [ -f /var/www/html/commands/flag.txt ]
then
    echo the file exists
    echo reload apache2
    /etc/init.d/apache2 force-reload
    echo delete the file
    rm /var/www/html/commands/flag.txt
else
    echo the file does not exist
    echo do nothing
fi
