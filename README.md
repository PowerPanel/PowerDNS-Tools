# PowerDNS-Tools
PowerDNS tools


## Manage the DNSSec + Supermasters through an API
https://github.com/PowerPanel/PowerDNS-Tools/blob/master/DNSSec-Supermasters.php

Information about how to use this script can be found here: https://support.powerpanel.io/hc/en-us/articles/360001414908-PowerDNS-DNSSec-add-on

### NOTE
This script has been tested on:
- version 3.x (Rest API PowerDNS: Ready for production)
- Version 4.0 (Rest API PowerDNS: Ready for production)
#### DO NOT USE THIS ON VERSION 4.1 (or higher).
From version 4.1 our PowerPanel PowerDNS Plugins has native support.

## Manage Supermasters
https://github.com/PowerPanel/PowerDNS-Tools/blob/master/Supermasters.php

Information about how to use this script can be found here: https://support.powerpanel.io/hc/en-us/articles/360001693748-How-to-setup-use-name-servers-in-PowerPanel

This script will communicate with your MySQL server(s) used by PowerDNS. It will add/delete Supermasters used for creating "whitelabled" nameservers for your Resellers.

## Installation steps
- Edit fields/variables in the top of the Supermasters.php file
- Install php + nginx (Needs to listen on port 8082)
- Script need to install on all slaves (master is not important in this case)
