# Release Notes for Authorize.net for Craft Commerce

## 1.0.12 - 2020-05-13

### Fixed
- Manually captured transactions can now be refunded after they have settled.

## 1.0.11 - 2020-03-01

### Fixed
- Accept.js can now capture payments after they have been authorized (Manual Capture).

## 1.0.10 - 2020-02-24

### Fixed
- Saved Payment Sources can now be named using a description field while saving the payment source.

## 1.0.9 - 2020-01-30

### Added
- Support for Commerce 3

## 1.0.8 - 2019-10-09

### Added
- Support for Environment Variables

## 1.0.7 - 2019-08-02

### Fixed
- Fixed an error that can occur when viewing transactions in the Craft Commerce order area.

## 1.0.6 - 2019-08-01

> {note} Significant changes have been made in this update, and a test transaction should be processed after upgrading this plugin. Saved payment sources are disabled by default, but can be enabled in the gateway settings.

### Added
- Support for Saved Payment Sources using the Authorize.net Customer Information Manager.

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
