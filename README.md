# Rolling SoftRes

This script was created to give World of Warcraft raid leaders an easy way to maintain a rolling soft reserve loot system.  The rolling soft reserve system works by adding bonus points to your rolls for each raid you attend and place a soft-reserve on the same item.  This script currently requires a MySQL database with a separate table per raid/group (although I may release an update to just use a CSV file if requested).

Your first raid, you will have 0 bonus points.
Your second raid, you will have 5 bonus points.
Your third raid, you will have 10 bonus points.

(You can change the number of points incremented for each run)

## Usage

This is assuming you have already setup a SQL server.

1. Ensure MySQL is installed on the machine that will be running the script.
2. Fill out the MySQL credentials at the top of the script.
3. Ensure that the script is executable (chmod +x softres.sh).
4. Export your soft-reserve list from https://softres.it ![image](https://github.com/user-attachments/assets/7c7c38e1-8e52-4ee2-b583-d43fffeb4684)
5. Download the CSV file to the same directory as this script, and name it "softres.csv".  Please note, this file will be deleted when the script finishes running to prevent it from accidentally being used again.
6. Run the script (./softres.sh).
7. Select a database table from the list when prompted, and confirm your choice.  You should have created a separate table for each raid/group that you are tracking.
8. You will now be prompted to select the number of bonus points that should be added for returning raiders.  Enter your choice, and press enter.
9. The script will take over from here, updating the database as well as printing any changes to the terminal window.

### Notes

- If you miss a raid, your bonus points will be decreased by the chosen increment value (You will lose the same amount of points you could have gained from attending the raid).  
- If you change the item you have soft-reserved, you forfeit your bonus points and start at 0.
- If you are already at 0 points, you will not have points deducted for absence.

![image](https://github.com/user-attachments/assets/2d66f2b0-013b-4e4d-8c7c-a5387a256300)
