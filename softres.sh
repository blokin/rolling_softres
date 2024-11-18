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

source credentials.conf

if ! [[ $? = 0 ]]; then
	echo "${bold}${red}Error!${yellow} - Unable to connect to SQL server.  Exiting!${reset}"
	exit 1
fi

echo -e "${bold}${yellow}Available Tables: ${reset}"

for TABLE in $( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "show tables;" 2> /dev/null ); do
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

echo -en "\n${bold}${yellow}You have chosen $DB_TABLE, is this correct (Y/N)? ${reset}"
read YN
while ! [[ $YN = @(Y|y|N|n) ]]; do
        echo -en "${bold}${yellow}Please enter ONLY Y or N: ${reset}"
        read YN
done


CSV=$( cat softres.csv | tail -n+2 | sed -e "s/'//g" -e 's/ //g' )

DATE=$( date +%Y-%m-%d )

echo "${bold}${red}______      _ _ _               _____        __ _  ______"
echo "| ___ \    | | (_)             /  ___|      / _| | | ___ \\"
echo "| |_/ /___ | | |_ _ __   __ _  \ \`--.  ___ | |_| |_| |_/ /___  ___"
echo "|    // _ \| | | | '_ \ / _\` |  \`--. \/ _ \|  _| __|    // _ \/ __|"
echo "| |\ \ (_) | | | | | | | (_| | /\__/ / (_) | | | |_| |\ \  __/\__ \\"
echo "|_| \_\___/|_|_|_|_| |_|\__, | \____/ \___/|_|  \__\_| \_\___||___/"
echo "                         __/ |                ${blue}By Bearijuana${red}"
echo "                        |___/${reset}"
echo -e "\n${bold}${yellow}Date: ${cyan}$DATE${reset}"

INCREMENT="$1"

if ! [[ $INCREMENT ]]; then
	echo -n "${bold}${yellow}Increment rolls by how many? ${reset}"
	read INCREMENT
fi

while ! [[ $INCREMENT =~ ^[0-9]+$ ]]; do
	echo -n "${bold}${yellow}Please enter only an integer.  Increment rolls by how many? ${reset}"
	read INCREMENT
done

echo -e "\n"

# Print current standings

CURRENT_STANDINGS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -e "SELECT Username,ItemName,ItemID,Bonus,LastAttended FROM $DB_TABLE;" 2> /dev/null )

if [[ $CURRENT_STANDINGS ]]; then
	echo -e "${bold}${yellow}CURRENT STANDINGS:\n${reset}"
	mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -e "SELECT Username,ItemName,ItemID,Bonus,LastAttended FROM $DB_TABLE;" 2> /dev/null
fi

# Print current raid group

echo -e "\n${bold}${yellow}CURRENT RAID RESERVATIONS:\n${reset}"

for RESERVATION in $( echo "$CSV" | sed -e 's/ /-/g' ); do
	CURRENT_RES_NAME=$( echo $RESERVATION | sed -e 's/,/ /g' -e 's/"//g' | awk '{print $1}' )
	CURRENT_RES_ID=$( echo $RESERVATION | sed -e 's/,/ /g' | awk '{print $2}' )
	WOW_USERNAME=$( echo $RESERVATION | sed -e 's/,/ /g' | awk '{print $4}' )
	echo -e "${bold}${cyan}\t$WOW_USERNAME ${yellow}- ${magenta}$CURRENT_RES_NAME ${yellow}[${green}$CURRENT_RES_ID${yellow}]${reset}"
done

# Check for absence
RAIDER_LIST=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT Username FROM $DB_TABLE;" 2> /dev/null )


for RAIDER in $RAIDER_LIST; do
	WOW_USERNAME=$( echo "$CSV" | sed -e 's/,/ /g' | awk '{print $4}' | grep "$RAIDER" )
	if ! [[ $WOW_USERNAME ]]; then
		WOW_USERNAME="$RAIDER"
		OLD_BONUS_QUERY="SELECT Bonus FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;"
	        OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss <<< "$OLD_BONUS_QUERY" 2> /dev/null )
		((NEW_BONUS=OLD_BONUS-$INCREMENT))
		if [[ $OLD_BONUS > 0 ]]; then
                        ABSENT_RAIDER+=("\t${bold}${cyan} $RAIDER ${yellow}- Decreasing bonus ${green} $OLD_BONUS ${yellow}->${red} $NEW_BONUS ${reset}")
		else
			ABSENT_RAIDER+=("\t${bold}${cyan} $RAIDER ${yellow} - Was absent but does not have any points to be deducted..")
		fi
	fi
done

echo -e "\n"

for X in $( echo "$CSV" | sed -e 's/ /-/g' -e 's/ //g' ); do
	CURRENT_RES_NAME=$( echo $X | sed -e 's/,/ /g' -e 's/"//g' | awk '{print $1}' )
	CURRENT_RES_ID=$( echo $X | sed -e 's/,/ /g' | awk '{print $2}' )
	BOSS_NAME=$( echo $X | sed -e 's/,/ /g' | awk '{print $3}' )
	WOW_USERNAME=$( echo $X | sed -e 's/,/ /g' | awk '{print $4}' )
	OLD_BONUS_QUERY="SELECT Bonus FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;"
	OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss <<< "$OLD_BONUS_QUERY" 2> /dev/null )
	LAST_RES_ID=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT ItemID FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )
        LAST_RES_NAME=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "SELECT ItemName FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;" 2> /dev/null )

	if [[ $CURRENT_RES_ID = $LAST_RES_ID ]]; then
		BONUSES+=("${bold}${cyan} $WOW_USERNAME ${bold}${yellow}-${magenta} $CURRENT_RES_NAME ${yellow}[${green} $CURRENT_RES_ID ${yellow}] - Points: ${red} $OLD_BONUS ${yellow}-> ${green}$((OLD_BONUS+$INCREMENT)) ${reset}")
	elif ! [[ $LAST_RES_ID ]]; then
		NEW_RAIDER+=("${bold}${cyan} $WOW_USERNAME ${yellow}- ${magenta}$CURRENT_RES_NAME ${yellow}[${green}$CURRENT_RES_ID${yellow}]${reset}")
	else
		NO_BONUS+=("${bold}${cyan} $WOW_USERNAME ${bold}${yellow}-${magenta} $LAST_RES_NAME ${yellow}[${green} $LAST_RES_ID ${yellow}] ->${yellow} $CURRENT_RES_NAME ${yellow}[${green} $CURRENT_RES_ID ${yellow} ]${reset}")
	fi
done

if [[ ${BONUSES[@]} ]]; then

	echo -e "${bold}${yellow}BONUSES TO BE APPLIED:\n${reset}"

	for BONUS in "${BONUSES[@]}"; do
		echo -e "${bold}${cyan}\t$BONUS${reset}"
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
                echo -e "$ABSENCE"
        done
fi

echo -en "${bold}${yellow}\nDo you want to write the new values to database server (Y/N)? ${reset}"

read YN
while ! [[ $YN = @(Y|y|N|n) ]]; do
	echo "${bold}${yellow}Please enter ONLY Y or N: ${reset}"
	read YN
done

if [[ $YN = @(N|n) ]]; then
	exit 0
else

	echo

	for BONUS in "${BONUSES[@]}"; do
		WOW_USERNAME=$( echo $BONUS | awk '{print $2}' )
		OLD_BONUS_QUERY="SELECT Bonus FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;"
	        OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss <<< "$OLD_BONUS_QUERY" 2> /dev/null )
		NEW_BONUS=$((OLD_BONUS+$INCREMENT))
		echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Increasing bonus ${blue}$OLD_BONUS ${yellow}-> ${magenta}$NEW_BONUS.. ${reset}"
		mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "UPDATE $DB_TABLE SET Bonus = $NEW_BONUS WHERE Username = '$WOW_USERNAME'" 2> /dev/null
		if [[ $? = 0 ]]; then
			echo "${bold}${green}OK${reset}"
		else
			echo "${bold}${red}ERROR!${reset}"
		fi
	done
	for CHANGE in "${NO_BONUS[@]}"; do
		WOW_USERNAME=$( echo $CHANGE | awk '{print $2}' )
		LAST_RES_NAME=$( echo $CHANGE | awk '{print $4}')
		LAST_RES_ID=$( echo $CHANGE | awk '{print $6}' )
		CURRENT_RES_NAME=$( echo $CHANGE | awk '{print $9}' )
		CURRENT_RES_ID=$( echo $CHANGE | awk '{print $11}' )
		echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Updating item reservation ${blue}$LAST_RES_NAME ${yellow}[${green}$LAST_RES_ID${yellow}] -> ${magenta}$CURRENT_RES_NAME ${yellow}[${green}$CURRENT_RES_ID${yellow}].. ${reset}"
		mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "UPDATE $DB_TABLE SET ItemName = '$CURRENT_RES_NAME', ItemID = '$CURRENT_RES_ID', Bonus = '0' WHERE Username = '$WOW_USERNAME'" 2> /dev/null
		if [[ $? = 0 ]]; then
                        echo "${bold}${green}OK${reset}"
                else
                        echo "${bold}${red}ERROR!${reset}"
                fi
	done

	if [[ ${ABSENT_RAIDER[*]} ]]; then
		for ABSENCE in "${ABSENT_RAIDER[@]}"; do
			WOW_USERNAME=$( echo -e "$ABSENCE" | awk '{print $2}' )
			OLD_BONUS_QUERY="SELECT Bonus FROM $DB_TABLE WHERE Username = '$WOW_USERNAME' LIMIT 1;"
	                OLD_BONUS=$( mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss <<< "$OLD_BONUS_QUERY" 2> /dev/null )
			((NEW_BONUS=OLD_BONUS-$INCREMENT))

			if [[ $OLD_BONUS > 0 ]]; then
				echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Decreasing bonus for absence ${green}$OLD_BONUS ${yellow}-> ${red}$NEW_BONUS.. ${reset}"
		                mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "UPDATE $DB_TABLE SET Bonus = '$NEW_BONUS' WHERE Username = '$WOW_USERNAME'" 2> /dev/null
			else
				echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Does not have any points and will not have points deducted.. "
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
			RAIDER=$( echo $RESERVATION | awk '{print $2}' )
			RESERVATION_CSV=$( echo "$CSV" | sed -e 's/ /-/g' -e 's/,/ /g' | grep $RAIDER )
			CURRENT_RES_NAME=$( echo $RESERVATION_CSV | sed -e 's/"//g' | awk '{print $1}' )
		        CURRENT_RES_ID=$( echo $RESERVATION_CSV | awk '{print $2}' )
			WOW_USERNAME=$( echo $RESERVATION_CSV | awk '{print $4}' )

			echo -en "\t${bold}${cyan}$WOW_USERNAME${yellow} - Adding to DB, eligible for bonus next raid.. ${reset}"
			mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -ss -e "INSERT INTO $DB_TABLE (Username, ItemName, ItemID, LastAttended) VALUES ('$WOW_USERNAME', '$CURRENT_RES_NAME', '$CURRENT_RES_ID', '$DATE');" 2> /dev/null

			if [[ $? = 0 ]]; then
				echo -e "${bold}${green}OK!${reset}"
			else
				echo -e "${bold}${red}ERROR!${yellow} Failed adding ${cyan}$WOW_USERNAME to db!${reset}"
			fi
		done
	fi
fi

# Print updated data

echo -e "\n${bold}${yellow}NEW STANDINGS:\n${reset}"

mysql -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT -D $DATABASE -e "SELECT Username,ItemName,ItemID,Bonus,LastAttended FROM $DB_TABLE;" 2> /dev/null

echo

exit 0
