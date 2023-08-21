# Release Notes for Authorize.net for Craft Commerce

## 4.0.4 - 2023-08-21

> {warning} Changes were made to subscriptions to correct missing card form. Please run a test transaction after updating if you're using the subscription version of the plugin.

### Fixed
- After making changes for the credit card number, subscriptions may not be able to find the card field.

## 4.0.3 - 2023-06-02

> {warning} Significant changes have been made in this update, a test transaction should be processed after upgrading this plugin, specifically for subscriptions and saved payment methods.

### Fixed
- When Accept.js is enabled, the default form now adjusts the card number input field name to cc-number so that number can be used to reference the order number. Gateways without Accept.js are still required to use the "number" field since it's a reserved card input. (Thanks, @justinholtweb!)
- Updated CustomerID in Saved Payment methods due to recent Craft Commerce adjustments. (Thanks, @whoisgina!)

## 4.0.2 - 2023-03-03

### Fixed
- Corrected an error in commerce subscriptions where a server error could occur. Note that historical transactions under Commerce > Subscriptions will always be shown with a USD currency label due to limitations within the Authorize.net historical transaction records.

## 4.0.1 - 2022-10-31

> {warning} Significant changes have been made in this update, a test transaction should be processed after upgrading this plugin.

### Added
- Support for CraftCMS 4 and Craft Commerce 4

### Changed
- Craft Commerce 4 is now required.
- **Note**: Craft Commerce 4 changed the name and ID attributes on payment fields to include the payment handle (see the code examples below). Sites that use the default form will be updated automatically, but payment forms that use a custom form will need to be updated. Please run test transactions through each Authorize.net gateway after updating to this version. Run into a problem? Open an issue or drop us a note at hello@digitalpros.co.

## 1.5.1 - 2021-05-22

### Fixed
- Refunding a transaction without a card number could case the refund to fail.

## 1.5.0 - 2021-03-30

> {warning} Significant changes have been made in this update, a test transaction should be processed after upgrading this plugin.

### Added
- Subscriptions are now available through a new subscriptions gateway.

### Changed
- Order reference numbers are now passed as invoice numbers in the processing gateway.

### Fixed
- Saving a card could fail in Accept.js if the card was already present on the account.
- Credit Card Processing Form wasn't available in the control panel if the default form was disabled.
- Refunds could fail from within the Control Panel if Accept.js was used to process the transaction.

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
