# Release Notes for Authorize.net for Craft Commerce

## 1.0.5 - 2019-07-23

### Changed
- Authorize.net for Craft Commerce requires Craft 3.1.5 or later.
- Authorize.net for Craft Commerce now uses Omnipay v3.

## 1.0.4 - 2019-05-21

### Fixed
- Made adjustments for JS compatiblity with older browsers (Accept.js Integration).

## 1.0.3

- Added Default Payment Form (Available in the Gateway Settings)
- Card information is no longer required when Accept.js tokens are available.
- Added a parameter to `sendPaymentDataToAnet(true);` which removes the Credit Card details from the card fields after the token is created, but before the information is submitted to the server.
- Updated the documentation to reflect various updates.

## 1.0.2

- Added CraftCMS 3 requirement

## 1.0.1

- Updated migration from Commerce 1 Authorize.net AIM Gateway

## 1.0.0

- Initial release.
