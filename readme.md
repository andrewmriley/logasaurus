Installation
===

Composer require

Create a .logasaurus.yml in the root
format:
changelogFile: changelogfilename
finalize: true/false
filesPath: pathtofilestoread

How to run
./vendor/bin/logasaurus generate versionnumber
optional date

example
logasaurus generate 1.01.00
