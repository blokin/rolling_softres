# Rolling SoftRes

This script was created to give World of Warcraft raid leaders an easy way to maintain a rolling soft reserve loot system.  The rolling soft reserve system works by adding bonus points to your rolls for each raid you attend and place a soft-reserve on the same item.  This script currently requires a MySQL database with a separate table per raid/group.

Your first raid, you will have 0 bonus points.
Your second raid, you will have 5 bonus points.
Your third raid, you will have 10 bonus points.
So on, and so forth.

(You can change the number of points incremented for each run)

### Notes

- If you miss a raid, your bonus points will be decreased by the chosen increment value (You will lose the same amount of points you could have gained from attending the raid).  
- If you change the item you have soft-reserved, you forfeit your bonus points and start at 0.
- If you are already at 0 points, you will not have points deducted for absence.

### Technical

This project started as a Bash script and SQL database.  I have modified it to be useable via the web using PHP, but I am a beginner at PHP and was thrown together rather quickly as a functional proof of concept.  It is not perfect by any means.

What this does:

1. Creates a CSV file based using the CSV data that exported from softres.it and pasted into the softres utility.
2. Selects database table.
3. Finds and prints a list of:
     1. Returning raiders maintaining the same soft reservation
     2. Returning raiders changing their soft reservation
     3. Absent raiders
     4. New raiders
4. Adds bonus points for returning raiders that ARE maintaining the same soft reservation.
5. Updates reserved items for returning raiders ARE NOT maintaining the same soft reservation, and sets their bonus to 0.
6. Removes bonus points for raiders who were not present.
7. Adds new raiders to the database with 0 bonus points.

### Planned Enhancements

- [x] Create web UI to allow uploading CSV from softres.it instead of having to manually modify the file
- [ ] Create public standing board
- [x] Add logging functionality
- [ ] Allow table switching from web UI
- [ ] Allow table creation from web UI
- [ ] Add user management/login system
- [ ] Add Discord notifications via webhook
