# bhl-oclc-creator-viafs
A script used to search and attempt to match BHL Creators to OCLC and VIAF

## Overview
The script creates a CSV File comparing Creators in the Biodiversity Heritage Library to the Creators provided by OCLC using the OCLC numbers stores in BHL.
## Requirements

PHP 5.7+ (I think). The script was built and tested in PHP 7.0.32

php.ini **memory_limit** set to something high. At least **512M**. This script is not kind to RAM.

## Usage

Simply run: `php compare.php`

While the script can be run from scratch (it will download all that it needs and it will be slow), the majority of the work is in processing the data and attempting to match up creator names from BHL and OCLC. 

*If you were to run the script as-is, it will download 103,000 RDF files from OCLC. Let's be nice to them. If you want to run this script, first download this file (142 MB) and extract it to an RDF folder in the same location as the compare.php script. Then it will only download what is new. https://www.dropbox.com/s/0ets41f9koy5fm7/RDF.zip?dl=0*


## The CSV file

The headings of the file are pretty self-explanatory. The **BHL (something)** and **OCLC ID** fields are all from BHL's database. The **OCLC Title**, **OCLC VIAF**, **OCLC Name**, etc are all pull from OCLC's linked data using the BHL-provided OCLC Number.

The data is laid out in such a fashion as to have the Title-level information repeated while the Creator-level information is not repeated. 

When BHL Creator names without birth/death dates were similar enough to the OCLC Full Name (see also: Levenshtein distance) you will find the BHL Creator and OCLC Full Name on the same row. If they were not similar enough, and the OCLC Full Name *contained* the BHL Creator name, then they are also placed on the same row as a match. Otherwise, they were placed on different rows. 

A good example is BHL ID 29284 (Line 70802 in Excel): 

* **H. C. Dannevi**g matched just fine.
* **Australia. Department of Trade and Customs.** did not match **Australia. Dept. of Trade and Customs.** because they were deemed too different.
* Similarly **Endeavour (Trawler)** didn't match **Endeavour (Ship)** for the same reason.

Therefore you will see five rows of text for these three authors.
