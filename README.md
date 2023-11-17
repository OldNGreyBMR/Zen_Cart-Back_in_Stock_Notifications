# Zen Cart - Back in Stock Notifications
If a product is out of stock, customers can subscribe/request to receive a notification when that product becomes available again.

This was based on the original CEON version, not the forked ajax version. Neither are supported anymore, but the functionality is well worthwhile, and I use it, so am encouraging use and development here despite it being the usual yelling into the void...

I've been modifying it for years, multi-language and attributes handling being the most significant omissions from the original code.
I've almost finished reworking and merging the huge amount of changes, and in the process, make the code more Zen-ish to maybe get it into the core one day.

As a result, this code is hugely different from the old plugin version, so always test on a development server: DO NOT drop it into your production server without testing first.
It's compatible with the current Zen Cart 1.5.8 and php7.3+.

Note that the original documentation in the docs folder will NOT be updated for the moment, so the file list is out of date.

## Changelog
12/11/2023:
Add multi-language to email sending.
A reply to the Admin copy of BISN subscription email now replies to customer
Replace tabs with spaces with all files.
Admin
Option 1 list subscriptions by product
Option 2 list all subscriptions 
Bugfix: handle fatal error for a missing/deleted product
Added column sorts/set column sort links to table id anchor
Hide model column if not used.
Add Delete buttons for each product/subscription.
Corrected paging display text.

11/11/2023: moved admin functions file so only loaded with BISN admin page
Remove duplicated function zen_get_products_model from bis_functions.php
Renamed BACK_IN_STOCK_NOTIFICATION_ENABLED to BACK_IN_STOCK_NOTIFICATIONS_ENABLED
Minor changes to installer messages and processing.
Add delete of single subscriptions of a product

06/11/2023: relocated required/optional template files to main file structure.
Updated template files based on ZC158 responsive_classic.

23/07/23:
Use ZC158 admin header, move css to separate file.

16/02/22:
Bugfix for duplicated subscription links when no login required.

Removed: /modules/ceon_form_bis as functionality duplicated in observer class

Removed: unnecessary observer auto loaders/observers made auto-loading 

Bugfix for missing product_model in account BISN listing

Bugfix for missing image in account BISN listing, Update button

Modified: language defines

Removed: modified core file functions_general

Removed: empty language folders

Fixes for warnings in strict mode/php8 compatibility

Miscellaneous IDE recommendations, strict comparisons, short-array syntax
