#! /bin/bash

TERM=xterm-256color
red="$(tput setaf 1)"
green="$(tput setaf 2)"
yellow="$(tput setaf 3)"
magenta="$(tput setaf 5)"
cyan="$(tput setaf 6)"
bold="$(tput bold)"
reset="$(tput sgr0)"
blue="$(tput setaf 4)"

source credentials.ini

function getItemName {
	accessToken=$( curl -su $clientID:$clientSecret -d grant_type=client_credentials https://oauth.battle.net/token | jq '.access_token' | sed -e 's/\"//g' )
 	curl -s -H "Authorization: Bearer $accessToken" "https://us.api.blizzard.com/data/wow/item/$1?namespace=static-classic-us&locale=en_US" | jq '.name' | sed -e 's/\"//g' -e 's/,//g' -e 's/ /-/g'
}

if [[ $1 = "list-tables" ]]; then
	TABLES=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SHOW TABLES WHERE Tables_in_$DATABASE NOT LIKE 'HIDDEN%';" 2> /dev/null )
	TABLES_COUNT=$( echo "$TABLES" | wc -l )
	COUNTER="1"
	for TABLE in $TABLES; do
		if ! [[ $TABLES_COUNT = $COUNTER ]]; then
			echo -n "\"$TABLE\","
			((COUNTER++))
		else
			echo -n "\"$TABLE\""
			exit 0
		fi
	done
	exit 0
elif [[ $1 = "list-raiders" ]]; then
        DB_TABLE=$2
        for RAIDER in "$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Username FROM $DB_TABLE" 2> /dev/null | uniq -u | sort | awk '{print $1}' )"; do
		echo "$RAIDER"
	done
        exit 0
elif [[ $1 = "bonus-table" ]]; then
	DB_TABLE=$2
	mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Username,ItemName,ItemID,Bonus,ItemName_2,ItemID_2,Bonus_2 FROM $DB_TABLE" 2> /dev/null | sort | awk '{print "<tr><td>"$1"</td><td>"$2"["$3"]<td>"$4"</td><td>"$5"["$6"]</td><td>"$7"</td></tr>"}'
	exit 0
elif [[ $1 = "list-logs" ]]; then
        if ! [[ $2 ]]; then
		LOG_DIRS=$( ls logs )
		DIR_COUNT=$( echo "$LOG_DIRS" | wc -l )
		COUNTER="1"
		for LOG_DIR in $LOG_DIRS; do
			if ! [[ $DIR_COUNT = $COUNTER ]]; then
		        	echo -n "$LOG_DIR,"
				((COUNTER++))
			else
				echo -n "$LOG_DIR" | awk '{print $1}'
			fi
		done
	else
		DB_TABLE=$2
		LOGFILES=$( ls logs/$DB_TABLE | sort -r )
		LOG_COUNT=$( echo "$LOGFILES" | wc -l )
		COUNTER="1"
		for LOGFILE in $( ls logs/$DB_TABLE | sort -r ); do

                        if ! [[ $LOG_COUNT = $COUNTER ]]; then
                                echo -n "$LOGFILE,"
                                ((COUNTER++))
                        else
                                echo -n "$LOGFILE"
                        fi
		done
	fi
        exit 0
elif [[ $1 ]]; then
	DB_TABLE=$1
	INTERACTIVE=0
	DB_TABLE_TEST=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "show tables WHERE Tables_in_$DATABASE NOT LIKE 'HIDDEN%';" 2> /dev/null | grep -w $DB_TABLE );
	if ! [[ "$DB_TABLE_TEST" = "$DB_TABLE" ]]; then
		echo -e "${bold}${red}Error!${yellow} Invalid DB table selected.${reset}"
		exit 1
	fi
fi

if [[ $2 ]]; then
	INCREMENT=$2
fi

if ! [[ $? = 0 ]]; then
	echo "${bold}${red}Error!${yellow} - Unable to connect to SQL server.  Exiting!${reset}"
	exit 1
fi

if ! [[ $DB_TABLE ]]; then
	echo -e "${bold}${yellow}Available Tables: ${reset}"

	for TABLE in $( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "show tables WHERE Tables_in_$DATABASE NOT LIKE 'HIDDEN%';" 2> /dev/null ); do
		TABLES+=("$TABLE")
		TABLES_COUNT="${#TABLES[@]}"
		let "TABLES_COUNT-=1"
		echo -e "\t${bold}${magenta}$TABLES_COUNT${yellow}) ${cyan}$TABLE${reset}"
	done
	echo -en "${bold}${yellow}Which table would you like to update (0-$TABLES_COUNT)?  ${reset}"
	read DB_SELECT

	while ! [[ $DB_SELECT =~ ^[0-9]+$ ]] || [[ $DB_SELECT < 0 ]] || [[ $DB_SELECT > $TABLES_COUNT ]]; do
	        echo -n "${bold}${yellow}Invalid option selected.  Which table would you like to update (0-$TABLES_COUNT)? ${reset}"
	        read DB_SELECT
	done
	DB_TABLE="${TABLES[$DB_SELECT]}"
fi

if ! [[ $INTERACTIVE = 0 ]]; then
	echo -en "\n${bold}${yellow}You have chosen ${cyan}$DB_TABLE${yellow}, is this correct (Y/N)? ${reset}"
	read YN
	while ! [[ $YN = @(Y|y|N|n) ]]; do
	        echo -en "${bold}${yellow}Please enter ONLY Y or N: ${reset}"
	        read YN
	done
fi

SR_UUID=$3
JQ="$( curl -s https://softres.it/api/raid/$SR_UUID | jq -r '.reserved.[] | "\(.name)-\(.items)"' )"

#if [[ $( echo "$JQ" | wc -l ) -lt 2 ]]; then
#        echo "Not enough softres data was posted"
#        exit 1
#fi

DATE=$( date +%Y-%m-%d )
echo -e "Raid: $DB_TABLE"
echo -e "Softres Sheet: http://softres.it/raid/$SR_UUID"
echo -e "Date: $DATE"

if ! [[ $INCREMENT ]]; then
	echo -n "${bold}${yellow}Increment rolls by how many? ${reset}"
	read INCREMENT
fi

while ! [[ $INCREMENT =~ ^[0-9]+$ ]]; do
	echo -n "${bold}${yellow}Please enter only an integer.  Increment rolls by how many? ${reset}"
	read INCREMENT
done

## Print current standings if interactive
#	echo "CURRENT STANDINGS:"
#	mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -e "SELECT Username,ItemName,ItemID,Bonus,ItemName_2,ItemID_2,Bonus_2,LastAttended FROM $DB_TABLE;" 2> /dev/null


# Print current raid group

echo -e "\n${bold}${yellow}RAID RESERVATIONS:\n${reset}"

for RESERVATION in $JQ; do
	CURRENT_RESERVATION_IDS=$( echo $RESERVATION | sed -e 's/-/ /' -e 's/,/ /' -e 's/\[//' -e 's/\]'// | awk '{print $2" "$3}' )
	CURRENT_RES_ID=$( echo $CURRENT_RESERVATION_IDS | awk '{print $1}' )
	CURRENT_RES_ID_2=$( echo $CURRENT_RESERVATION_IDS | awk '{print $2}' )
        if ! [[ $CURRENT_RES_ID_2 ]]; then
                CURRENT_RES_ID_2="$CURRENT_RES_ID"
        fi
	CURRENT_RES_NAME="$( getItemName $CURRENT_RES_ID )"
	CURRENT_RES_NAME_2="$( getItemName $CURRENT_RES_ID_2 )"
	WOW_USERNAME=$( echo $RESERVATION | sed -e 's/-/ /' | awk '{print $1}' )
	echo -e "\t$WOW_USERNAME"
        echo -e "\t\t$CURRENT_RES_NAME [$CURRENT_RES_ID]"
        echo -e "\t\t$CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2]"
done

# Check for absence
RAIDER_LIST=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Username FROM $DB_TABLE;" 2> /dev/null )


for RAIDER in $RAIDER_LIST; do
	WOW_USERNAME=$( echo "$JQ" | sed -e 's/-/ /' | awk '{print $1}' | grep "$RAIDER" )
	if ! [[ $WOW_USERNAME ]]; then
		WOW_USERNAME="$RAIDER"
		LAST_RES_ID=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT ItemID FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
		LAST_RES_ID_2=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT ItemID_2 FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
		LAST_RES_NAME=$( getItemName $LAST_RES_ID )
		LAST_RES_NAME_2=$( getItemName $LAST_RES_ID_2 )
	        OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Bonus FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
                OLD_BONUS_2=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Bonus_2 FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
		((NEW_BONUS=OLD_BONUS-$INCREMENT))
		((NEW_BONUS_2=OLD_BONUS_2-$INCREMENT))
		if [[ $OLD_BONUS > 0 ]]; then
                        ABSENT_RAIDER+=("$RAIDER - Decreasing bonus for $LAST_RES_NAME [$LAST_RES_ID] $OLD_BONUS -> $NEW_BONUS ItemID")
		else
			ABSENT_RAIDER+=("$RAIDER - Was absent but does not have any points to be deducted for $LAST_RES_NAME [$LAST_RES_ID]..")
		fi
                if [[ $OLD_BONUS_2 > 0 ]] && [[ $LAST_RES_ID_2 > 0 ]]; then
                        ABSENT_RAIDER+=("$RAIDER - Decreasing bonus for $LAST_RES_NAME_2 [$LAST_RES_ID_2] $OLD_BONUS_2 -> $NEW_BONUS_2 ItemID_2")
                else
                        ABSENT_RAIDER+=("$RAIDER - Was absent but does not have any points to be deducted for $LAST_RES_NAME_2 [$LAST_RES_ID_2]..")
                fi
	fi
done

for RESERVATION in $JQ; do
        CURRENT_RESERVATION_IDS=$( echo $RESERVATION | sed -e 's/-/ /' -e 's/,/ /' -e 's/\[//' -e 's/\]//' | awk '{print $2" "$3}' )
        CURRENT_RES_ID=$( echo $CURRENT_RESERVATION_IDS | awk '{print $1}' )
        CURRENT_RES_ID_2=$( echo $CURRENT_RESERVATION_IDS | awk '{print $2}' )
        if ! [[ $CURRENT_RES_ID_2 ]]; then
                CURRENT_RES_ID_2="$CURRENT_RES_ID"
		CURRRENT_RESERVATION_IDS="$CURRENT_RES_ID $CURRENT_RES_ID_2"
        fi
        CURRENT_RES_NAME="$( getItemName $CURRENT_RES_ID )"
        CURRENT_RES_NAME_2="$( getItemName $CURRENT_RES_ID_2 )"
	WOW_USERNAME=$( echo $RESERVATION | sed -e 's/-/ /' | awk '{print $1}' )
        OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Bonus FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
        OLD_BONUS_2=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Bonus_2 FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
        LAST_RES_ID=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT ItemID FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
        LAST_RES_ID_2=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT ItemID_2 FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
        LAST_RES_NAME=$( getItemName $LAST_RES_ID )
        LAST_RES_NAME_2=$( getItemName $LAST_RES_ID_2 )


	if ! [[ $LAST_RES_ID ]]; then
                NEW_RAIDER+=("$WOW_USERNAME - $CURRENT_RES_NAME [$CURRENT_RES_ID] $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2]")
	elif ! [[ $CURRENT_RES_ID = $LAST_RES_ID ]] && ! [[ $CURRENT_RES_ID = $LAST_RES_ID_2 ]] && ! [[ $CURRENT_RES_ID_2 = $LAST_RES_ID ]] && ! [[ $CURRENT_RES_ID_2 = $LAST_RES_ID_2 ]]; then
		NO_BONUS+=("$WOW_USERNAME - $LAST_RES_NAME [$LAST_RES_ID] -> $CURRENT_RES_NAME [$CURRENT_RES_ID] ItemID")
		NO_BONUS+=("$WOW_USERNAME - $LAST_RES_NAME_2 [$LAST_RES_ID_2] -> $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] ItemID_2")
        elif [[ $CURRENT_RES_ID = $LAST_RES_ID ]] && [[ $CURRENT_RES_ID_2 = $LAST_RES_ID_2 ]]; then
		BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME [$CURRENT_RES_ID] - Points: $OLD_BONUS -> $((OLD_BONUS+$INCREMENT)) ItemID")
		BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] - Points: $OLD_BONUS_2 -> $((OLD_BONUS_2+$INCREMENT)) ItemID_2")
	elif [[ $CURRENT_RES_ID = $LAST_RES_ID_2 ]] && [[ $CURRENT_RES_ID_2 = $LAST_RES_ID ]]; then
                BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME [$CURRENT_RES_ID] - Points: $OLD_BONUS_2 -> $((OLD_BONUS_2+$INCREMENT)) ItemID_2")
                BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] - Points: $OLD_BONUS -> $((OLD_BONUS+$INCREMENT)) ItemID")
	elif ! [[ $CURRENT_RES_ID = $LAST_RES_ID ]] && ! [[ $CURRENT_RES_ID = $LAST_RES_ID_2 ]] && [[ $CURRENT_RES_ID_2 = $LAST_RES_ID ]]; then
		NO_BONUS+=("$WOW_USERNAME - $LAST_RES_NAME_2 [$LAST_RES_ID_2] -> $CURRENT_RES_NAME [$CURRENT_RES_ID] ItemID")
                BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] - Points: $OLD_BONUS -> $((OLD_BONUS+$INCREMENT)) ItemID")
	elif ! [[ $CURRENT_RES_ID = $LAST_RES_ID ]] && ! [[ $CURRENT_RES_ID = $LAST_RES_ID_2 ]] && [[ $CURRENT_RES_ID_2 = $LAST_RES_ID_2 ]]; then
                NO_BONUS+=("$WOW_USERNAME - $LAST_RES_NAME [$LAST_RES_ID] -> $CURRENT_RES_NAME [$CURRENT_RES_ID] ItemID")
                BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] - Points: $OLD_BONUS_2 -> $((OLD_BONUS_2+$INCREMENT)) ItemID_2")
        elif [[ $CURRENT_RES_ID = $LAST_RES_ID ]] && ! [[ $CURRENT_RES_ID_2 = $LAST_RES_ID_2 ]]; then
                NO_BONUS+=("$WOW_USERNAME - $LAST_RES_NAME_2 [$LAST_RES_ID_2] -> $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] ItemID_2")
                BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME [$CURRENT_RES_ID] - Points: $OLD_BONUS -> $((OLD_BONUS+$INCREMENT)) ItemID")
        elif [[ $CURRENT_RES_ID = $LAST_RES_ID_2 ]] && ! [[ $CURRENT_RES_ID_2 = $LAST_RES_ID ]]; then
                NO_BONUS+=("$WOW_USERNAME - $LAST_RES_NAME [$LAST_RES_ID] -> $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] ItemID_2")
                BONUSES+=("$WOW_USERNAME - $CURRENT_RES_NAME [$CURRENT_RES_ID] - Points: $OLD_BONUS -> $((OLD_BONUS+$INCREMENT)) ItemID")

	else
		echo "Error - $WOW_USERNAME C $CURRENT_RES_ID L $LAST_RES_ID C $CURRENT_RES_ID_2 L2 $LAST_RES_ID_2"
	fi

done

if [[ ${BONUSES[@]} ]]; then

	echo -e "${bold}${yellow}\nBONUSES TO BE APPLIED:\n${reset}"

	for BONUS in "${BONUSES[@]}"; do
		WOW_USERNAME=$( echo $BONUS | awk '{print $1}' )
		CURRENT_RES_NAME=$( echo $BONUS | awk '{print $3}' )
		CURRENT_RES_ID=$( echo $BONUS | awk '{print $4}' | sed -e 's/\[//' -e 's/\]//' )
		OLD_BONUS=$( echo $BONUS | awk '{print $7}' )
		NEW_BONUS=$( echo $BONUS | awk '{print $9}' )
		if [[ $OLD_BONUS -lt 25 ]]; then
			echo -e "${bold}${cyan}\t$BONUS${reset}"
		else
			echo -e "\t$WOW_USERNAME has reached the maximum of 25 bonus points and will not receive an additional bonus for $CURRENT_RES_NAME [$CURRENT_RES_ID]"
		fi
	done

fi

if [[ ${NO_BONUS[@]} ]]; then

	echo -e "${bold}${yellow}\nRESERVATIONS CHANGING:\n${reset}"

	for CHANGE in "${NO_BONUS[@]}"; do
		echo -e "\t${bold}${cyan}$CHANGE${reset}"
	done

fi

if [[ ${NEW_RAIDER[@]} ]]; then
	echo -e "${bold}${yellow}\nNEW RAIDERS BEING ADDED:\n${reset}"
	for RESERVATION in "${NEW_RAIDER[@]}"; do
		echo -e "\t$RESERVATION"
	done
fi

if [[ ${ABSENT_RAIDER[@]} ]]; then
	echo -e "${bold}${yellow}\nABSENT RAIDERS LOSING POINTS:\n${reset}"

        for ABSENCE in "${ABSENT_RAIDER[@]}"; do
                echo -e "\t$ABSENCE"
        done
fi

if ! [[ $INTERACTIVE = 0 ]]; then
	echo -en "${bold}${yellow}\nDo you want to write the new values to database server (Y/N)? ${reset}"

	read YN
	while ! [[ $YN = @(Y|y|N|n) ]]; do
		echo "${bold}${yellow}Please enter ONLY Y or N: ${reset}"
		read YN
	done
else
	YN="Y"
fi

if [[ $YN = @(N|n) ]]; then
	exit 0
else

	echo -en "${bold}${yellow}\nAPPLYING UPDATES:\n\n${reset}"

	for BONUS in "${BONUSES[@]}"; do
		WOW_USERNAME=$( echo $BONUS | awk '{print $1}' )
		CURRENT_RES_ID=$( echo $BONUS | awk '{print $4}' | sed -e 's/\[//' -e 's/\]//' )
	        CURRENT_RES_NAME="$( getItemName $CURRENT_RES_ID )"
	        OLD_BONUS=$( echo $BONUS | awk '{print $7}' )
		NEW_BONUS=$((OLD_BONUS+$INCREMENT))
		ITEM=$( echo $BONUS | awk '{print $10}' )
                if [[ $ITEM = "ItemID" ]]; then
                        BONUS_ID="Bonus"
                elif [[ $ITEM = "ItemID_2" ]]; then
                        BONUS_ID="Bonus_2"
                fi
		if [[ $OLD_BONUS -lt 25 ]]; then
			echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - $CURRENT_RES_NAME [$CURRENT_RES_ID] - Increasing $BONUS_ID ${blue}$OLD_BONUS ${yellow}-> ${magenta}$NEW_BONUS.. ${reset}"
			mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "UPDATE $DB_TABLE SET $BONUS_ID = $NEW_BONUS WHERE Username = '$WOW_USERNAME'" 2> /dev/null
		else
                        echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - $CURRENT_RES_NAME [$CURRENT_RES_ID] - Maximum bonus reached for $BONUS_ID, bonus will not be increased ${blue}$OLD_BONUS ${yellow}-> ${blue}$OLD_BONUS.. ${reset}"
		fi

		if [[ $? = 0 ]]; then
			echo "${bold}${green}OK${reset}"
		else
			echo "${bold}${red}ERROR!${reset}"
		fi
	done

	for CHANGE in "${NO_BONUS[@]}"; do
		WOW_USERNAME=$( echo $CHANGE | awk '{print $1}' )
		LAST_RES_ID=$( echo $CHANGE | awk '{print $4}' | sed -e 's/\[//' -e s'/\]//' )
	        LAST_RES_NAME="$( getItemName $LAST_RES_ID )"
		CURRENT_RES_ID=$( echo $CHANGE | awk '{print $7}' | sed -e 's/\[//' -e 's/\]//' )
	        CURRENT_RES_NAME="$( getItemName $CURRENT_RES_ID )"
		ITEM=$( echo $CHANGE | awk '{print $8}' )
		if [[ $ITEM = "ItemID" ]]; then
			BONUS_ID="Bonus"
			ITEM_NAME_COL="ItemName"
		elif [[ $ITEM = "ItemID_2" ]]; then
			BONUS_ID="Bonus_2"
			ITEM_NAME_COL="ItemName_2"
		fi
		echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Updating $ITEM reservation $LAST_RES_NAME [$LAST_RES_ID] -> $CURRENT_RES_NAME [$CURRENT_RES_ID].. "
		mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "UPDATE $DB_TABLE SET $ITEM = '$CURRENT_RES_ID', $ITEM_NAME_COL = '$CURRENT_RES_NAME', $BONUS_ID = '0' WHERE Username = '$WOW_USERNAME'" 2> /dev/null
		if [[ $? = 0 ]]; then
                        echo "${bold}${green}OK${reset}"
                else
                        echo "${bold}${red}ERROR!${reset}"
                fi
	done
	if [[ ${ABSENT_RAIDER[*]} ]]; then
		for ABSENCE in "${ABSENT_RAIDER[@]}"; do
			WOW_USERNAME=$( echo -e "$ABSENCE" | awk '{print $1}' )
			LAST_RES_NAME=$( echo -e "$ABSENCE" | awk '{print $6}' )
			LAST_RES_ID=$( echo -e "$ABSENCE" | awk '{print $7}' )
	                ITEM=$( echo $ABSENCE | awk '{print $11}')
	                if [[ $ITEM = "ItemID" ]]; then
	                        BONUS_ID="Bonus"
	                        ITEM_NAME_COL="ItemName"
	                elif [[ $ITEM = "ItemID_2" ]]; then
	                        BONUS_ID="Bonus_2"
        	                ITEM_NAME_COL="ItemName_2"
	                fi
                        OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT $BONUS_ID FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
			((NEW_BONUS=OLD_BONUS-$INCREMENT))
			if [[ $OLD_BONUS > 0 ]]; then
				echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Decreasing bonus on $LAST_RES_NAME [$LAST_RES_ID] for absence ${green}$OLD_BONUS ${yellow}-> ${red}$NEW_BONUS.. ${reset}"
		                mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "UPDATE $DB_TABLE SET $BONUS_ID = '$NEW_BONUS' WHERE Username = '$WOW_USERNAME'" 2> /dev/null
			else
				echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Was absent but has no points to be deducted.. "
			fi
	                if [[ $? = 0 ]]; then
	                        echo "${bold}${green}OK${reset}"
	                else
	                        echo "${bold}${red}ERROR!${reset}"
	                fi
		done
	fi

	if [[ ${NEW_RAIDER[*]} ]]; then
		for RESERVATION in "${NEW_RAIDER[@]}"; do
			RAIDER=$( echo $RESERVATION | sed -e 's/-/ /' | awk '{print $1}' )
		        CURRENT_RES_ID=$( echo $RESERVATION | sed -e 's/-/ /' -e 's/\[//' -e 's/\]//' | awk '{print $3}')
		        CURRENT_RES_NAME="$( getItemName $CURRENT_RES_ID )"
                        CURRENT_RES_ID_2=$( echo $RESERVATION | sed -e 's/-/ /' -e 's/\[//g' -e 's/\]//g' | awk '{print $5}')
                        CURRENT_RES_NAME_2="$( getItemName $CURRENT_RES_ID_2 )"
			WOW_USERNAME=$RAIDER
			echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - $CURRENT_RES_NAME [$CURRENT_RES_ID], $CURRENT_RES_NAME_2 [$CURRENT_RES_ID_2] - Adding to DB, eligible for bonus next raid.. ${reset}"
			mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE  -e "INSERT INTO $DB_TABLE (Username, ItemName, ItemID, ItemName_2, ItemID_2, LastAttended, Bonus, Bonus_2) VALUES ('$WOW_USERNAME', '$CURRENT_RES_NAME', '$CURRENT_RES_ID', '$CURRENT_RES_NAME_2', '$CURRENT_RES_ID_2', '$DATE', '0', '0');" # 2> /dev/null
			if [[ $? = 0 ]]; then
				echo -e "${bold}${green}OK!${reset}"
			else
				echo -e "${bold}${red}ERROR!${yellow} Failed adding ${cyan}$WOW_USERNAME to db!${reset}"
			fi
		done
	fi
fi

# Print updated data if interactive

if ! [[ $INTERACTIVE = 0 ]]; then

	echo -e "\n${bold}${yellow}NEW STANDINGS:\n${reset}"

	mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -e "SELECT Username,ItemName,ItemID,Bonus,LastAttended FROM $DB_TABLE;" 2> /dev/null

	echo

fi

exit 0
