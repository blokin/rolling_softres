#!/bin/bash

source credentials.ini
echo "<link rel="stylesheet" href="style.css">"
        for DB_TABLE in $( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "show tables" 2> /dev/null ); do
                echo "<ul id=\"myUL\"><li><span class=\"caret\">$DB_TABLE</span><ul class=\"nested\">"
                mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Username,Bonus FROM $DB_TABLE" 2> /dev/null | awk '{print "<li><table width=175><tr><td width=90%>"$1"</td><td>"$2"</td></tr></table></li>"}'
                echo "</ul></li></ul>" 
        done
        exit 0
