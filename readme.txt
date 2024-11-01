=== ValuePay for Fluent Forms ===
Contributors:      valuepaymy
Tags:              valuepay, fluent forms, payment
Requires at least: 4.6
Tested up to:      6.0
Stable tag:        1.0.3
Requires PHP:      7.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Accept payment on Fluent Forms using ValuePay.

== Description ==

Allows customer to made payment on Fluent Forms using ValuePay.

= Notes: =
- It is required to create field for Identity Type, Identity Value, Bank and Payment Type if recurring payment is enabled (mandate ID is filled).
- Identity Type, Bank and Payment Type field must be Select field.
- Identity Type and Bank field will be mapped automatically, no need to fill in the options.
- Identity Type field field will be mapped automatically only if it has more than two (2) options.
- To disable field mapping for Identity Type field, add more than two (2) options. Accepted values:
    1 = New IC No.
    2 = Old IC No.
    3 = Passport No.
    4 = Business Reg. No.
    5 = Others
- Payment Type field only accepts "single" and "recurring" value (without double quotes).
- Recurring payment only creates one payment record in Fluent Forms with "Pending" status.

== Installation ==

1. Log in to your WordPress admin.
2. Search plugins "ValuePay for Fluent Forms" and click "Install Now".
3. Activate the plugin.
4. Navigate to "Plugins" in the sidebar, then find "ValuePay for Fluent Forms".
5. Click "Settings" link to access the plugin settings page.
6. Follow the instructions and update the plugin settings.

== Changelog ==

= 1.0.3 - 2022-04-30 =
- Added: ValuePay payment ID and payment shortcodes in Fluent Forms

= 1.0.2 - 2022-04-17 =
- Modified: Improve instant payment notification response data sanitization

= 1.0.1 - 2022-04-10 =
- Modified: Improve instant payment notification response data sanitization

= 1.0.0 - 2022-03-24 =
- Initial release of the plugin
