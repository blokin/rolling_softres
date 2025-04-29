# Rolling SoftRes

This project was created to give World of Warcraft raid leaders an easy way to maintain a rolling soft reserve loot system.    

### What is "Rolling Soft Reserve"?

SoftRes is short for "soft reservation".  This loot system allows raiders to select an item (or sometimes two) from the raid to reserve.  If that item drops and only one raider has the it reserved, it goes to them.  If more than one raider has it reserved, they use /roll within the game and whoever rolls higher gets the item.

Soft reserve sheets can be created at http://www.softres.it

To incentivize raiders to continue raiding with our group, we are implementing a "rolling soft reserve" system.  What this means is that for each consecutive week a raider reserves the same item, they will receive bonus points which will be added on to their /roll.  For example, Raider A is on week 5 of reserving the same item, and the rolling soft res system is configured to increment by 5 points per week.  Raider B is new and is on his first week reserving that item.  The item drops.  Raider A rolls a 56, and Raider B rolls a 74.  Typically Raider B would take the item.  With the rolling soft res bonus points however, Raider A will get +25 to his roll for a total of 81, and Raider A will take the item.

Likewise, if a raider does not attend a raid, they will lose an equal number of bonus points.  

If a raider changes their reservation, their points will be forfeit and reset to 0, which is a permanent change.

### Technical

This project started as a Bash script writing to a MySQL database.  I have modified it to be usable via the web using PHP, but there's still work that needs to be done.  It is now capable of handling multiple MySQL tables for tracking separate raid groups.  Tables can be created/destroyed using the admin page.

When a table is "destroyed", it renames to be in a "recycle bin" and is no longer displayed on the site.  This is to prevent accidents from causing data loss as well as to safeguard against anyone with malicious intent destroying data.  I will eventually add a recycle bin cleanup function once I make the admin panel more secure.

### Planned Enhancements

- [x] ~Create web UI to allow uploading CSV from http://www.softres.it instead of having to manually modify the file~\
          - This is now using the http://www.softres.it API to directly pull the information from the site instead of requiring you to export as CSV first.  Simply log in to the admin panel, select which table you would like to update, paste the URL of your soft reserve sheet in to the form, and click submit.  
- [x] Create public standing board
- [x] Add logging functionality
- [x] Allow table switching from web UI
- [x] Allow table creation from web UI
- [ ] Add user management/login system
- [ ] Add Discord notifications via webhook
- [ ] Re-write bash script in php for better portability
- [ ] Make the UI not ugly
- [ ] Add recycle bin cleanup
- [ ] Add support for 1x or 2x soft res (currently only supporting 2x)
