# This project adds tags to the osX finder for google drive.

The purpose of the project is to make ownership of google drive files visible on
the mac OSX, allowing for search to work, as well as general viewing of files.

- This script supports multiple google drive accounts.
- Easiest to make it work is with Hazel (https://www.noodlesoft.com)
- The hazel rules are included, you will need to change the script it runs to match the location of this script.


#To install

- Download gdrive CLI from https://github.com/prasmussen/gdrive (Easiest is to cp it as "gdrive" under /usr/local/bin)
- Add execute permissions to the file (sudo chmod +x <file>)
- Copy the config.ini.template to config.ini
- Change the gdrive location (unless you named it gdrive under /usr/local/bin)
- Add the folder locations in the config file for your google drive files
- make tagGoogleFiles.php executable (if you want to be able to run it without /usr/bin/php infront)
